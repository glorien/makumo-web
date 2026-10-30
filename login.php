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
<main class="flex-1 flex flex-col items-center justify-center px-4 py-16">

    <div class="w-full max-w-sm">

        <div class="flex flex-col items-center mb-8 text-center">
            <img src="/assets/logo.svg" alt="" class="w-24 h-auto drop-shadow-lg mb-5">
            <h1 class="font-headline text-4xl font-light tracking-[-0.01em] text-on-surface">
                Makumo <span class="bg-gradient-to-r from-primary via-secondary to-tertiary bg-clip-text text-transparent">HEMS</span>
            </h1>
            <p class="font-label text-[11px] tracking-widest uppercase text-on-surface-variant mt-2">Private APK-Downloads</p>
        </div>

        <div class="rounded-2xl bg-surface-container-lowest/90 border border-outline-variant/40 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">

            <?php if ($error !== ''): ?>
                <div class="mb-6 px-4 py-3 rounded-xl bg-error-container/30 border border-error/30 text-error text-sm flex items-start gap-2">
                    <span class="material-symbols-outlined text-base mt-0.5">error</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-5">
                    <label for="email" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">E-Mail</label>
                    <div class="relative">
                        <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">mail</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="email"
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                   focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60
                                   transition-colors font-label text-sm"
                            placeholder="name@example.com"
                        >
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">Passwort</label>
                    <div class="relative">
                        <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                   focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60
                                   transition-colors font-label text-sm"
                            placeholder="••••••••"
                        >
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full min-h-[48px] py-3 px-4 rounded-xl bg-primary hover:bg-primary-fixed text-on-primary
                           font-headline font-semibold text-sm tracking-wide shadow-lg shadow-primary/20 transition-colors"
                >
                    Anmelden
                </button>
            </form>
        </div>
    </div>
</main>
<?php render_footer(); ?>
