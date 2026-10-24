<?php
declare(strict_types=1);

function render_head(string $title = 'Makumo HEMS'): void {
    $full_title = $title === 'Makumo HEMS' ? $title : "$title — Makumo HEMS";
    ?>
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($full_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                        display: ['Space Grotesk', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        emerald: { 500: '#10B981' },
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-[#0a0a0a] text-gray-100 font-sans min-h-screen">
    <?php
}

function render_nav(): void {
    $user = current_user();
    if (!$user) return;
    ?>
    <nav class="border-b border-white/10 bg-white/5 backdrop-blur-sm">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="font-display font-semibold text-white tracking-tight">Makumo HEMS</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-400 font-mono"><?= htmlspecialchars($user['email']) ?></span>
                <a href="/logout.php"
                   class="text-sm text-gray-400 hover:text-white transition-colors px-3 py-1.5 rounded-md hover:bg-white/10">
                    Abmelden
                </a>
            </div>
        </div>
    </nav>
    <?php
}

function render_footer(): void {
    ?>
    <footer class="border-t border-white/5 mt-16 py-8 text-center text-xs text-gray-600 font-mono">
        Makumo HEMS &mdash; GridFlow OS
    </footer>
</body>
</html>
    <?php
}
