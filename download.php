<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/github.php';

require_login();

function render_download_error(int $status, string $message): void {
    http_response_code($status);
    render_head('Download');
    render_nav();
    ?>
<main class="max-w-5xl mx-auto px-6 py-12">
    <div class="glass-card rounded-2xl p-12 text-center">
        <p class="text-gray-400 text-sm"><?= htmlspecialchars($message) ?></p>
        <a href="/downloads.php" class="inline-block mt-6 text-sm text-emerald-400 hover:text-emerald-300">
            Zurück zur Übersicht
        </a>
    </div>
</main>
    <?php
    render_footer();
    exit;
}

function log_download(array $asset): void {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $user = current_user();
    $line = implode("\t", [
        date('c'),
        $user['email'] ?? '-',
        $asset['tag'],
        $asset['name'],
        $_SERVER['REMOTE_ADDR'] ?? '-',
    ]) . PHP_EOL;

    file_put_contents($log_dir . '/downloads.log', $line, FILE_APPEND | LOCK_EX);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!is_int($id) || $id <= 0) {
    render_download_error(404, 'Datei nicht gefunden.');
}

$asset = find_asset($id);
if ($asset === null) {
    render_download_error(404, 'Datei nicht gefunden.');
}

$url = asset_download_url($id);
if ($url === null) {
    render_download_error(502, 'Der Download konnte nicht gestartet werden. Bitte später erneut versuchen.');
}

log_download($asset);

header('Cache-Control: no-store');
header('Location: ' . $url, true, 302);
exit;
