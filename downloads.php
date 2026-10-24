<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/github.php';

require_login();

$releases = fetch_releases();

render_head('Downloads');
render_nav();
?>

<main class="max-w-5xl mx-auto px-6 py-12">

    <div class="mb-10">
        <h1 class="font-display text-3xl font-semibold text-white">APK Downloads</h1>
        <p class="text-gray-400 mt-2 text-sm">Aktuelle Releases der Makumo HEMS Android-App</p>
    </div>

    <?php if (empty($releases)): ?>
        <div class="glass-card rounded-2xl p-12 text-center">
            <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
            </div>
            <p class="text-gray-400 text-sm">Noch keine Releases verfügbar.</p>
        </div>

    <?php else: ?>
        <div class="flex flex-col gap-4">
            <?php foreach ($releases as $i => $release): ?>
                <div class="glass-card rounded-2xl p-6 <?= $i === 0 ? 'ring-1 ring-emerald-500/30' : '' ?>">
                    <div class="flex items-start justify-between gap-4 flex-wrap">

                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2">
                                    <span class="font-display font-semibold text-white text-lg">
                                        <?= htmlspecialchars($release['name']) ?>
                                    </span>
                                    <?php if ($i === 0 && !$release['prerelease']): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                            Aktuell
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($release['prerelease']): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                            Pre-release
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="font-mono text-xs text-gray-500 mt-0.5">
                                    <?= htmlspecialchars(format_date($release['published'])) ?>
                                    &nbsp;·&nbsp;
                                    <?= htmlspecialchars($release['tag']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($release['assets'] as $asset): ?>
                                <a
                                    href="<?= htmlspecialchars($asset['download_url']) ?>"
                                    download
                                    class="flex items-center gap-2 px-4 py-2 rounded-xl
                                           <?= $i === 0 ? 'bg-emerald-500 hover:bg-emerald-400 text-black' : 'bg-white/10 hover:bg-white/15 text-white' ?>
                                           font-display font-medium text-sm transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <?= htmlspecialchars($asset['name']) ?>
                                    <span class="font-mono text-xs opacity-60"><?= htmlspecialchars(format_bytes($asset['size'])) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($release['changelog'] !== ''): ?>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <pre class="text-xs text-gray-400 font-mono whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($release['changelog']) ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php render_footer(); ?>
