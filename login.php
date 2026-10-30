<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: /downloads.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $lockout  = login_lockout_remaining();

    if (!verify_csrf()) {
        $error = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
    } elseif ($lockout > 0) {
        $error = 'Zu viele Anmeldeversuche. Bitte in ' . (int) ceil($lockout / 60) . ' Minuten erneut versuchen.';
    } elseif ($email === '' || $password === '') {
        $error = 'Bitte E-Mail und Passwort eingeben.';
    } elseif (!attempt_login($email, $password)) {
        register_failed_login();
        $error = 'Ungültige Anmeldedaten.';
    } else {
        clear_failed_logins();
        header('Location: /downloads.php');
        exit;
    }
}

render_head('Anmelden');
?>
<div class="min-h-screen flex flex-col items-center justify-center px-4">

    <div class="w-full max-w-sm">

        <div class="flex flex-col items-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="font-display text-2xl font-semibold text-white">Makumo HEMS</h1>
            <p class="text-sm text-gray-500 mt-1">APK Downloads</p>
        </div>

        <div class="glass-card rounded-2xl p-8">

            <?php if ($error !== ''): ?>
                <div class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">E-Mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="email"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600
                               focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50
                               transition-colors font-mono text-sm"
                        placeholder="name@example.com"
                    >
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Passwort</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white placeholder-gray-600
                               focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50
                               transition-colors font-mono text-sm"
                        placeholder="••••••••"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full py-3 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 active:bg-emerald-600
                           text-black font-display font-semibold text-sm transition-colors"
                >
                    Anmelden
                </button>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
