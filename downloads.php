<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/github.php';

require_login();

$result   = fetch_releases();
$releases = $result['releases'];

// Latest stable version on top, everything else under "Weitere Versionen"
$latest = null;
foreach ($releases as $release) {
    if (!$release['prerelease']) {
        $latest = $release;
        break;
    }
}
$latest ??= $releases[0] ?? null;
$older = array_values(array_filter($releases, fn($r) => $r !== $latest));

// Presentation per app, detected from the APK file name.
// Classes are written out in full so Tailwind finds them at build time.
const VARIANTS = [
    'server' => [
        'kicker'   => 'Wand-Tablet · Server',
        'title'    => 'HEMS Server',
        'subtitle' => 'Läuft dauerhaft auf dem Tablet im Heimnetz',
        'logo'     => '/assets/logo-server.svg',
        'icon'     => 'tablet_android',
        'hint'     => 'Für das Tablet an der Wand. Es gibt nur ein Server-Gerät.',
        'features' => [
            ['Dauerbetrieb', 'Läuft als Vordergrund-Dienst rund um die Uhr.'],
            ['Wechselrichter', 'Sungrow SH5.0RT lokal per Modbus TCP, ohne Cloud.'],
            ['Wallbox', 'go-eCharger per REST, PV-Überschussladen mit Phasenumschaltung.'],
            ['Preise & Prognose', 'Tibber-Strompreise und Solarprognose von Open-Meteo.'],
        ],
        'dot'      => 'bg-primary',
        'text'     => 'text-primary',
        'border'   => 'border-primary/30 hover:border-primary/60',
        'glow'     => 'bg-primary/10 group-hover:bg-primary/20',
        'button'   => 'bg-primary text-on-primary hover:bg-primary-fixed shadow-primary/20',
        'chip'     => 'bg-primary/10 text-primary border-primary/30 hover:bg-primary/20',
    ],
    'client' => [
        'kicker'   => 'Dashboard · Client',
        'title'    => 'HEMS Client',
        'subtitle' => 'Dashboard für Smartphone oder Tablet im WLAN',
        'logo'     => '/assets/logo-client.svg',
        'icon'     => 'smartphone',
        'hint'     => 'Verbindet sich über das WLAN mit dem Server-Tablet.',
        'features' => [
            ['Energiefluss live', 'PV, Batterie, Netz und Wallbox in Echtzeit.'],
            ['Batterie & Laden', 'Ladestand des Speichers und Status der Wallbox.'],
            ['Preise & Prognose', 'Strompreise und Solarprognose der nächsten Stunden.'],
            ['Lokal', 'Spricht nur mit dem Server im Heimnetz.'],
        ],
        'dot'      => 'bg-tertiary',
        'text'     => 'text-tertiary',
        'border'   => 'border-tertiary/30 hover:border-tertiary/60',
        'glow'     => 'bg-tertiary/10 group-hover:bg-tertiary/20',
        'button'   => 'bg-tertiary-container text-on-tertiary hover:bg-tertiary shadow-tertiary/20',
        'chip'     => 'bg-tertiary/10 text-tertiary border-tertiary/30 hover:bg-tertiary/20',
    ],
    'other' => [
        'kicker'   => 'Android-App',
        'title'    => 'APK',
        'subtitle' => '',
        'logo'     => '/assets/logo.svg',
        'icon'     => 'android',
        'hint'     => '',
        'features' => [],
        'dot'      => 'bg-secondary',
        'text'     => 'text-secondary',
        'border'   => 'border-secondary/30 hover:border-secondary/60',
        'glow'     => 'bg-secondary/10 group-hover:bg-secondary/20',
        'button'   => 'bg-secondary text-on-secondary hover:bg-secondary/90 shadow-secondary/20',
        'chip'     => 'bg-secondary/10 text-secondary border-secondary/30 hover:bg-secondary/20',
    ],
];

function asset_variant(array $asset): array {
    $name = strtolower($asset['name']);
    $key  = str_contains($name, 'server') ? 'server' : (str_contains($name, 'client') ? 'client' : 'other');
    $v    = VARIANTS[$key];
    if ($key === 'other') {
        $v['title'] = $asset['name'];
    }
    return $v;
}

// Server first, then client, then the rest
function sort_assets(array $assets): array {
    $rank = fn($a) => match (true) {
        str_contains(strtolower($a['name']), 'server') => 0,
        str_contains(strtolower($a['name']), 'client') => 1,
        default => 2,
    };
    usort($assets, fn($a, $b) => $rank($a) <=> $rank($b));
    return $assets;
}

function short_digest(?string $digest): ?string {
    if ($digest === null || !str_starts_with($digest, 'sha256:')) {
        return null;
    }
    return substr($digest, 7);
}

function version_label(array $release): string {
    return ltrim($release['tag'], 'vV');
}

render_head('Downloads');
render_nav($latest !== null);
?>

<main class="w-full max-w-6xl mx-auto px-4 sm:px-6 pb-16">

<?php if ($latest === null): ?>
    <section class="pt-16 sm:pt-24 pb-10 text-center">
        <h1 class="font-headline text-4xl sm:text-5xl font-light tracking-[-0.01em] text-on-surface">APK Downloads</h1>
    </section>
    <?php if ($result['error']): ?>
        <?php render_notice('error', 'Releases konnten nicht geladen werden. Bitte später erneut versuchen.', 'error'); ?>
    <?php else: ?>
        <?php render_notice('inventory_2', 'Noch keine Releases verfügbar.'); ?>
    <?php endif; ?>

<?php else: ?>
    <?php
    $latest_assets = sort_assets($latest['assets']);
    $total_downloads = array_sum(array_column($latest['assets'], 'download_count'));
    ?>

    <!-- Hero -->
    <section class="pt-12 sm:pt-20 pb-10 sm:pb-14 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-container-high/60 border border-outline-variant/40 font-label text-[11px] tracking-wider uppercase text-tertiary">
            <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
            <?= $latest['prerelease'] ? 'Pre-release' : 'Aktuelle Version' ?>
            <span class="text-on-surface-variant">|</span>
            <span class="text-on-surface">v<?= htmlspecialchars(version_label($latest)) ?></span>
        </div>

        <h1 id="page-title" class="leading-[1.05] mt-6 text-5xl sm:text-7xl"><?= wordmark() ?></h1>
        <p class="max-w-xl mx-auto mt-5 text-on-surface-variant text-base sm:text-lg leading-relaxed">
            Die Apps für das Energiemanagement zu Hause: der Server für das Wand-Tablet und das Dashboard fürs Smartphone. Alles läuft lokal im Heimnetz.
        </p>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mx-auto mt-10">
            <?php foreach ([
                ['Version', 'v' . version_label($latest), 'text-primary'],
                ['Veröffentlicht', format_date($latest['published']), 'text-on-surface'],
                ['Pakete', count($latest['assets']) . ' APK', 'text-tertiary'],
                ['Downloads', number_format($total_downloads, 0, ',', '.'), 'text-secondary'],
            ] as [$label, $value, $color]): ?>
                <div class="rounded-xl bg-surface-container-lowest/70 border border-outline-variant/30 px-4 py-3">
                    <dt class="font-label text-[10px] tracking-widest uppercase text-on-surface-variant"><?= $label ?></dt>
                    <dd class="font-headline text-2xl font-light mt-1 <?= $color ?>"><?= htmlspecialchars($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <?php if ($result['error']): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-error-container/30 border border-error/30 text-error text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            GitHub ist gerade nicht erreichbar. Angezeigt wird der zuletzt geladene Stand.
        </div>
    <?php endif; ?>

    <!-- App cards -->
    <section>
        <div class="flex items-end justify-between gap-4 mb-5">
            <div>
                <div class="font-label text-[11px] tracking-widest uppercase text-primary">Installationspakete</div>
                <h2 class="font-headline text-2xl font-light tracking-[-0.01em] text-on-surface mt-1">Wähle dein Gerät</h2>
            </div>
            <div class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-container-lowest border border-outline-variant/40 font-label text-[11px] text-on-surface-variant">
                <span class="material-symbols-outlined text-sm text-secondary">verified</span>
                Signiert mit dem makumo-Schlüssel
            </div>
        </div>

        <div class="grid grid-cols-1 <?= count($latest_assets) > 1 ? 'md:grid-cols-2' : '' ?> gap-5">
            <?php foreach ($latest_assets as $asset): $v = asset_variant($asset); $sha = short_digest($asset['digest'] ?? null); ?>
                <article class="group relative overflow-hidden rounded-2xl bg-surface-container-lowest border <?= $v['border'] ?> p-6 sm:p-8 flex flex-col shadow-2xl transition-colors duration-300">
                    <div class="absolute -top-24 -right-24 w-60 h-60 rounded-full blur-3xl pointer-events-none transition-colors duration-500 <?= $v['glow'] ?>"></div>

                    <div class="relative flex items-start justify-between gap-4 mb-5 sm:mb-6">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 rounded-xl bg-surface-container-low border border-outline-variant/40 p-1.5 sm:p-2 flex items-center justify-center">
                                <img src="<?= $v['logo'] ?>" alt="" class="w-full h-auto">
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full animate-pulse <?= $v['dot'] ?>"></span>
                                    <span class="font-label text-[11px] font-semibold uppercase tracking-wider <?= $v['text'] ?>"><?= htmlspecialchars($v['kicker']) ?></span>
                                </div>
                                <h3 class="font-headline text-2xl font-light tracking-[-0.01em] text-on-surface mt-0.5 break-words"><?= htmlspecialchars($v['title']) ?></h3>
                                <?php if ($v['subtitle'] !== ''): ?>
                                    <div class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($v['subtitle']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="hidden sm:block flex-shrink-0 px-2.5 py-1 rounded-md bg-surface-container border border-outline-variant/30 text-right">
                            <span class="font-label text-xs font-semibold block <?= $v['text'] ?>">v<?= htmlspecialchars(version_label($latest)) ?></span>
                            <span class="font-label text-[10px] text-on-surface-variant block"><?= htmlspecialchars(format_bytes($asset['size'])) ?></span>
                        </div>
                    </div>

                    <?php if ($v['hint'] !== ''): ?>
                        <div class="relative p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/30 mb-6 flex items-center gap-3">
                            <span class="material-symbols-outlined text-xl <?= $v['text'] ?>"><?= $v['icon'] ?></span>
                            <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($v['hint']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($v['features']): ?>
                        <ul class="relative space-y-2.5 mb-8">
                            <?php foreach ($v['features'] as [$feature, $text]): ?>
                                <li class="flex items-start gap-2.5 text-sm text-on-surface-variant">
                                    <span class="material-symbols-outlined text-base text-secondary mt-0.5">check_circle</span>
                                    <span><strong class="text-on-surface font-semibold"><?= htmlspecialchars($feature) ?>:</strong> <?= htmlspecialchars($text) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="relative mt-auto pt-6 border-t border-outline-variant/30">
                        <a href="/download.php?id=<?= (int) $asset['id'] ?>"
                           class="w-full min-h-[48px] py-3.5 px-4 rounded-xl font-headline text-sm font-semibold tracking-wide flex items-center justify-center gap-2 shadow-lg transition-colors <?= $v['button'] ?>">
                            <span class="material-symbols-outlined text-lg">download</span>
                            APK herunterladen
                            <span class="font-label text-xs opacity-70"><?= htmlspecialchars(format_bytes($asset['size'])) ?></span>
                        </a>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 font-label text-[11px] text-on-surface-variant">
                            <span class="truncate max-w-full" title="<?= htmlspecialchars($asset['name']) ?>"><?= htmlspecialchars($asset['name']) ?></span>
                            <span><?= number_format($asset['download_count'], 0, ',', '.') ?> Downloads</span>
                        </div>
                        <?php if ($sha !== null): ?>
                            <div class="mt-2 font-label text-[11px] text-on-surface-variant flex items-center gap-1.5 min-w-0" title="SHA-256: <?= htmlspecialchars($sha) ?>">
                                <span class="flex-shrink-0 <?= $v['text'] ?>">SHA-256</span>
                                <code class="truncate select-all"><?= htmlspecialchars($sha) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($latest['changelog'] !== ''): ?>
            <div class="mt-5 rounded-2xl bg-surface-container-lowest border border-outline-variant/40 p-6">
                <div class="font-label text-[11px] tracking-widest uppercase text-tertiary mb-3">Neu in v<?= htmlspecialchars(version_label($latest)) ?></div>
                <pre class="text-sm text-on-surface-variant font-sans whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($latest['changelog']) ?></pre>
            </div>
        <?php endif; ?>
    </section>

    <!-- Installation -->
    <section class="mt-16 sm:mt-20">
        <div class="text-center mb-8">
            <div class="font-label text-[11px] tracking-widest uppercase text-primary">Installation</div>
            <h2 class="font-headline text-2xl sm:text-3xl font-light tracking-[-0.01em] text-on-surface mt-2">In 3 Schritten startklar</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <?php foreach ([
                ['01', 'text-primary border-primary/40 bg-primary/10', 'install_mobile', 'APK laden',
                 'Diese Seite direkt auf dem Gerät öffnen, anmelden und die passende APK herunterladen: Server aufs Wand-Tablet, Client aufs Smartphone.'],
                ['02', 'text-tertiary border-tertiary/40 bg-tertiary/10', 'lock', 'Installation erlauben',
                 'Beim Öffnen der APK fragt Android nach „Unbekannte Apps installieren“. Für den Browser einmal erlauben.'],
                ['03', 'text-secondary border-secondary/40 bg-secondary/10', 'settings', 'Einrichten',
                 'Server: Demo-Modus unter „Verbindungen“ ausschalten, sobald Wechselrichter und Wallbox erreichbar sind. Client: Server-IP unter „Einstellungen“ eintragen.'],
            ] as [$num, $badge, $icon, $title, $text]): ?>
                <div class="rounded-2xl bg-surface-container-lowest border border-outline-variant/40 p-6 flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-label text-sm font-semibold px-2.5 py-1 rounded-lg border <?= $badge ?>"><?= $num ?></span>
                        <span class="material-symbols-outlined text-xl text-on-surface-variant"><?= $icon ?></span>
                    </div>
                    <h3 class="font-headline text-lg font-normal text-on-surface"><?= $title ?></h3>
                    <p class="text-sm text-on-surface-variant mt-2 leading-relaxed"><?= $text ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center font-label text-[11px] text-on-surface-variant mt-5">
            Updates einfach über die installierte App installieren – die Daten bleiben erhalten.
        </p>
    </section>

    <!-- More versions (older releases and pre-releases) -->
    <?php if ($older): ?>
        <section class="mt-16 sm:mt-20">
            <div class="flex items-center gap-2 mb-5">
                <span class="material-symbols-outlined text-xl text-on-surface-variant">history</span>
                <h2 class="font-headline text-2xl font-light tracking-[-0.01em] text-on-surface">Weitere Versionen</h2>
            </div>
            <div class="flex flex-col gap-3">
                <?php foreach ($older as $release): ?>
                    <div class="rounded-2xl bg-surface-container-lowest/80 border border-outline-variant/30 p-4 sm:p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-headline font-semibold text-on-surface"><?= htmlspecialchars($release['name']) ?></span>
                                    <?php if ($release['prerelease']): ?>
                                        <span class="px-2 py-0.5 rounded-full font-label text-[10px] font-semibold uppercase tracking-wider bg-primary/15 text-primary border border-primary/35">Pre-release</span>
                                    <?php endif; ?>
                                </div>
                                <div class="font-label text-[11px] text-on-surface-variant mt-0.5">
                                    <?= htmlspecialchars(format_date($release['published'])) ?> · <?= htmlspecialchars($release['tag']) ?>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach (sort_assets($release['assets']) as $asset): $v = asset_variant($asset); ?>
                                    <a href="/download.php?id=<?= (int) $asset['id'] ?>"
                                       title="<?= htmlspecialchars($asset['name']) ?>"
                                       class="inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-lg border font-label text-xs transition-colors <?= $v['chip'] ?>">
                                        <span class="material-symbols-outlined text-base">download</span>
                                        <?= htmlspecialchars($v['title']) ?>
                                        <span class="opacity-60"><?= htmlspecialchars(format_bytes($asset['size'])) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($release['changelog'] !== ''): ?>
                            <details class="mt-3 group/details">
                                <summary class="cursor-pointer font-label text-[11px] text-on-surface-variant hover:text-on-surface select-none">Änderungen anzeigen</summary>
                                <pre class="mt-2 text-xs text-on-surface-variant font-sans whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($release['changelog']) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

<?php endif; ?>

</main>

<script>
// Show the header name only once the page title has scrolled behind the header,
// so the name is not shown twice right below each other
(() => {
    const title = document.getElementById('page-title');
    const brand = document.querySelector('.js-reveal-brand');
    if (!title || !brand || !('IntersectionObserver' in window)) return;

    const header = brand.closest('header');
    const setHidden = (hidden) => {
        brand.classList.toggle('opacity-0', hidden);
        brand.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    };
    setHidden(true);

    new IntersectionObserver(([entry]) => {
        // Visible title, or title still below the header: keep the header name hidden
        setHidden(entry.isIntersecting || entry.boundingClientRect.top > 0);
    }, { rootMargin: `-${header.offsetHeight}px 0px 0px 0px` }).observe(title);
})();
</script>

<?php render_footer(); ?>
