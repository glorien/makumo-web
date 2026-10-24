<?php
declare(strict_types=1);

function load_env(): void {
    $path = __DIR__ . '/../config/env.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

function fetch_releases(): array {
    load_env();

    $owner = defined('GITHUB_OWNER') ? GITHUB_OWNER : '';
    $repo  = defined('GITHUB_REPO')  ? GITHUB_REPO  : '';
    $token = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';

    if (!$owner || !$repo) {
        return [];
    }

    $cache_dir  = __DIR__ . '/../cache';
    $cache_file = $cache_dir . '/releases.json';
    $cache_ttl  = 300; // 5 minutes

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
        $cached = file_get_contents($cache_file);
        if ($cached !== false) {
            return json_decode($cached, true) ?? [];
        }
    }

    $url = "https://api.github.com/repos/$owner/$repo/releases";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => array_filter([
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: makumo-web/1.0',
            $token ? "Authorization: Bearer $token" : null,
        ]),
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        return [];
    }

    $raw      = json_decode($response, true) ?? [];
    $releases = [];

    foreach ($raw as $release) {
        if ($release['draft'] ?? false) {
            continue;
        }

        $apk_assets = [];
        foreach ($release['assets'] ?? [] as $asset) {
            if (str_ends_with(strtolower($asset['name']), '.apk')) {
                $apk_assets[] = [
                    'name'         => $asset['name'],
                    'size'         => $asset['size'],
                    'download_url' => $asset['browser_download_url'],
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
    file_put_contents($cache_file, json_encode($releases));

    return $releases;
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
