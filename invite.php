<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

// The token is in the URL: do not pass it on to other sites as referrer
header('Referrer-Policy: no-referrer');

start_session();

$token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
$user  = find_user_by_invite($token);
$error = '';

if ($user !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!verify_csrf()) {
        $error = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
    } elseif (($error = validate_new_password($password, $confirm) ?? '') === '') {
        $accepted = accept_invite($token, $password);
        if ($accepted !== null) {
            log_in_user($accepted);
            header('Location: /downloads.php');
            exit;
        }
        $user = null;
    }
}

render_head('Passwort festlegen');
?>
<main class="flex-1 flex flex-col items-center justify-center px-4 py-6 sm:py-16">

    <div class="w-full max-w-sm">

        <div class="flex flex-col items-center mb-6 sm:mb-8 text-center">
            <img src="/assets/logo.svg" alt="" class="w-20 sm:w-24 h-auto drop-shadow-lg mb-4 sm:mb-5">
            <h1 class="text-4xl"><?= wordmark() ?></h1>
            <p class="font-label text-[11px] tracking-widest uppercase text-on-surface-variant mt-2">Passwort festlegen</p>
        </div>

        <?php if ($user === null): ?>
            <?php render_notice('error', 'Dieser Link ist ungültig, abgelaufen oder wurde schon verwendet. Bitte um einen neuen Link.', 'error'); ?>
            <p class="text-center mt-6">
                <a href="/login.php" class="font-label text-xs text-on-surface-variant hover:text-on-surface transition-colors">Zur Anmeldung</a>
            </p>
        <?php else: ?>
            <div class="rounded-2xl bg-surface-container-lowest/90 border border-outline-variant/40 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">

                <p class="text-on-surface-variant text-sm mb-6">
                    Hallo <?= htmlspecialchars($user['name']) ?>, leg hier dein Passwort für
                    <span class="text-on-surface"><?= htmlspecialchars($user['email']) ?></span> fest.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="mb-6 px-4 py-3 rounded-xl bg-error-container/30 border border-error/30 text-error text-sm flex items-start gap-2">
                        <span class="material-symbols-outlined text-base mt-0.5">error</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/invite.php" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
                    <!-- For password managers: the username that belongs to the new password -->
                    <input type="email" name="username" value="<?= htmlspecialchars($user['email']) ?>" autocomplete="username" class="hidden" readonly tabindex="-1">

                    <div class="mb-5">
                        <label for="password" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">Neues Passwort</label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
                            <input type="password" id="password" name="password" autocomplete="new-password" required minlength="<?= PASSWORD_MIN_BYTES ?>"
                                   class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                          focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60 transition-colors font-label text-sm"
                                   placeholder="Mindestens <?= PASSWORD_MIN_BYTES ?> Zeichen">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="password_confirm" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">Wiederholen</label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
                            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required
                                   class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                          focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60 transition-colors font-label text-sm"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full min-h-[48px] py-3 px-4 rounded-xl bg-primary hover:bg-primary-fixed text-on-primary
                                   font-headline font-semibold text-sm tracking-wide shadow-lg shadow-primary/20 transition-colors">
                        Passwort speichern
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php render_footer(false); ?>
