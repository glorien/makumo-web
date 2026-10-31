<?php
declare(strict_types=1);

// Material Symbols icons used by the pages (alphabetical, otherwise Google Fonts rejects the request)
const ICON_NAMES = 'android,arrow_back,check_circle,content_copy,delete,download,error,group,history,install_mobile,inventory_2,link,lock,logout,mail,person,person_add,schedule,settings,smartphone,tablet_android,verified,wifi';

// Appends the modification time so browsers do not use cached CSS after a deploy
function asset_url(string $path): string {
    $mtime = @filemtime(__DIR__ . '/..' . $path);
    return $mtime ? "$path?v=$mtime" : $path;
}

// App name as the app writes it (KioskHeader.kt): lowercase "makumo" in primary amber, Light.
// "HEMS" additionally gets the amber → green → cyan gradient of the portal.
const APP_NAME = 'makumo HEMS';

function wordmark(string $classes = ''): string {
    return '<span class="font-headline font-light tracking-[-0.01em] text-primary whitespace-nowrap ' . $classes . '">'
        . 'makumo <span class="bg-gradient-to-r from-primary via-secondary to-tertiary bg-clip-text text-transparent">HEMS</span>'
        . '</span>';
}

function render_head(string $title = APP_NAME): void {
    $full_title = $title === APP_NAME ? $title : "$title — " . APP_NAME;
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#13003a">
    <title><?= htmlspecialchars($full_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
    <link rel="preload" href="/assets/fonts/outfit-light.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/assets/fonts/outfit-regular.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=<?= ICON_NAMES ?>&display=block" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('/assets/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('/assets/style.css') ?>">
</head>
<body class="bg-background text-on-surface font-sans flex flex-col overflow-x-hidden selection:bg-primary selection:text-on-primary">
    <div class="ambient-glow top-0 left-1/4 w-[600px] h-[600px] bg-primary/10 blur-[140px]"></div>
    <div class="ambient-glow top-1/3 right-10 w-[500px] h-[500px] bg-tertiary/10 blur-[160px]"></div>
    <div class="ambient-glow bottom-10 left-1/3 w-[700px] h-[700px] bg-surface-container-high/40 blur-[180px]"></div>
    <?php
}

// $reveal_brand: the page shows the name as its own title (element #page-title), so the header
// name only fades in once that title has scrolled behind the header
function render_nav(bool $reveal_brand = false): void {
    $user = current_user();
    if (!$user) return;
    ?>
    <header class="sticky top-0 z-50 bg-surface-container-lowest/80 backdrop-blur-xl border-b border-outline-variant/30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <a href="/downloads" class="flex items-center gap-3 min-w-0">
                <img src="/assets/logo.svg" alt="" class="w-10 h-auto drop-shadow-md">
                <?= wordmark('text-xl sm:text-2xl transition-opacity duration-300' . ($reveal_brand ? ' js-reveal-brand' : '')) ?>
            </a>
            <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                <span class="hidden sm:block font-label text-xs text-on-surface-variant truncate"><?= htmlspecialchars($user['email']) ?></span>
                <?php if (is_admin()): ?>
                    <a href="/admin"
                       class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] rounded-lg bg-surface-container-high/60 border border-outline-variant/40
                              text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest font-label text-xs transition-colors">
                        <span class="material-symbols-outlined text-base">group</span>
                        <span class="hidden sm:inline">Nutzer</span>
                    </a>
                <?php endif; ?>
                <form method="POST" action="/logout">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] rounded-lg bg-surface-container-high/60 border border-outline-variant/40
                                   text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest font-label text-xs transition-colors">
                        <span class="material-symbols-outlined text-base">logout</span>
                        <span class="hidden sm:inline">Abmelden</span>
                    </button>
                </form>
            </div>
        </div>
    </header>
    <?php
}

// $show_footer = false: closing tags only (login page, so it fits on a phone screen)
function render_footer(bool $show_footer = true): void {
    if ($show_footer):
    ?>
    <footer class="mt-auto border-t border-outline-variant/30 bg-surface-container-lowest/60">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <div>
                <div class="text-lg"><?= wordmark() ?></div>
                <div class="font-label text-[11px] text-on-surface-variant mt-1">Private Installation · läuft lokal im Heimnetz</div>
            </div>
            <div class="inline-flex items-center gap-2 font-label text-[11px] text-secondary">
                <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
                Nur für angemeldete Nutzer
            </div>
        </div>
    </footer>
    <?php endif; ?>
</body>
</html>
    <?php
}

/**
 * Shared notice card (empty, error, info).
 */
function render_notice(string $icon, string $message, string $tone = 'muted'): void {
    $icon_class = $tone === 'error' ? 'text-error' : 'text-on-surface-variant';
    ?>
    <div class="rounded-2xl bg-surface-container-lowest border border-outline-variant/40 p-10 text-center">
        <div class="w-12 h-12 rounded-xl bg-surface-container-low border border-outline-variant/40 flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-2xl <?= $icon_class ?>"><?= htmlspecialchars($icon) ?></span>
        </div>
        <p class="text-on-surface-variant text-sm"><?= htmlspecialchars($message) ?></p>
    </div>
    <?php
}
