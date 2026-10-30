<?php
declare(strict_types=1);

function load_env(): void {
    $path = __DIR__ . '/../config/env.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

function github_repo_path(): ?string {
    load_env();

    $owner = defined('GITHUB_OWNER') ? GITHUB_OWNER : '';
    $repo  = defined('GITHUB_REPO')  ? GITHUB_REPO  : '';

    if (!$owner || !$repo) {
        return null;
    }
    return "/repos/$owner/$repo";
}

/**
 * Führt einen GET-Request gegen die GitHub-API aus.
 * Gibt Status, Body und (bei Redirects) die Location zurück.
 */
function github_request(string $path, string $accept = 'application/vnd.github+json', bool $follow = true): array {
    load_env();
    $token = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';

    $ch = curl_init('https://api.github.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => array_values(array_filter([
            "Accept: $accept",
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: makumo-web/1.0',
            $token ? "Authorization: Bearer $token" : null,
        ])),
    ]);

    $body     = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;

    return [
        'status'   => $status,
        'body'     => $body === false ? null : $body,
        'location' => $location,
    ];
}

/**
 * Lädt die Releases (5 Minuten gecacht).
 * Bei API-Fehlern wird ein abgelaufener Cache weiterverwendet.
 * Rückgabe: ['releases' => array, 'error' => bool]
 */
function fetch_releases(): array {
    $repo_path = github_repo_path();
    if ($repo_path === null) {
        return ['releases' => [], 'error' => true];
    }

    $cache_dir  = __DIR__ . '/../cache';
    $cache_file = $cache_dir . '/releases-v2.json';
    $cache_ttl  = 300; // 5 minutes

    $cached = null;
    if (file_exists($cache_file)) {
        $content = file_get_contents($cache_file);
        $cached  = $content !== false ? json_decode($content, true) : null;
        if (is_array($cached) && (time() - filemtime($cache_file)) < $cache_ttl) {
            return ['releases' => $cached, 'error' => false];
        }
    }

    $response = github_request("$repo_path/releases");

    if ($response['body'] === null || $response['status'] !== 200) {
        return is_array($cached)
            ? ['releases' => $cached, 'error' => false]
            : ['releases' => [], 'error' => true];
    }

    $raw      = json_decode($response['body'], true) ?? [];
    $releases = [];

    foreach ($raw as $release) {
        if ($release['draft'] ?? false) {
            continue;
        }

        $apk_assets = [];
        foreach ($release['assets'] ?? [] as $asset) {
            if (str_ends_with(strtolower($asset['name']), '.apk')) {
                $apk_assets[] = [
                    'id'             => $asset['id'],
                    'name'           => $asset['name'],
                    'size'           => $asset['size'],
                    'download_count' => $asset['download_count'],
                ];
            }
        }

        if (empty($apk_assets)) {
            continue;
        }

        $body = $release['body'] ?? '';
        $changelog_lines = array_slice(
            array_filter(explode("\n", trim($body)), fn($l) => trim($l) !== ''),
            0,
            5
        );

        $releases[] = [
            'tag'        => $release['tag_name'],
            'name'       => $release['name'] ?: $release['tag_name'],
            'prerelease' => $release['prerelease'] ?? false,
            'published'  => $release['published_at'],
            'changelog'  => implode("\n", $changelog_lines),
            'assets'     => $apk_assets,
        ];
    }

    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0750, true);
    }
    file_put_contents($cache_file, json_encode($releases), LOCK_EX);

    return ['releases' => $releases, 'error' => false];
}

/**
 * Sucht ein APK-Asset in den angezeigten Releases.
 * Nur Assets aus dieser Liste dürfen heruntergeladen werden.
 */
function find_asset(int $id): ?array {
    foreach (fetch_releases()['releases'] as $release) {
        foreach ($release['assets'] as $asset) {
            if ($asset['id'] === $id) {
                return $asset + ['tag' => $release['tag']];
            }
        }
    }
    return null;
}

/**
 * Liefert die kurzlebige, signierte Download-URL eines Release-Assets.
 */
function asset_download_url(int $id): ?string {
    $repo_path = github_repo_path();
    if ($repo_path === null) {
        return null;
    }

    $response = github_request(
        "$repo_path/releases/assets/$id",
        'application/octet-stream',
        false
    );

    if ($response['status'] !== 302 || $response['location'] === null) {
        return null;
    }
    return $response['location'];
}

function format_bytes(int $bytes): string {
    if ($bytes >= 1_048_576) {
        return round($bytes / 1_048_576, 1) . ' MB';
    }
    return round($bytes / 1_024, 1) . ' KB';
}

function format_date(string $iso): string {
    $dt = new DateTimeImmutable($iso);
    return $dt->format('d.m.Y');
}
