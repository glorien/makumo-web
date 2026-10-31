<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

require_admin();

$error     = '';
$success   = '';
$link      = '';
$link_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (string) ($_POST['id'] ?? '');
    $target = $id !== '' ? find_user_by_id($id) : null;

    try {
        if (!verify_csrf()) {
            $error = 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.';
        } elseif (!users_store_writable()) {
            $error = 'config/users.json ist nicht beschreibbar. Bitte die Schreibrechte für config/ prüfen.';
        } elseif ($action === 'invite') {
            $name      = trim((string) ($_POST['name'] ?? ''));
            $link      = invite_url(create_invited_user((string) ($_POST['email'] ?? ''), $name));
            $link_name = $name;
        } elseif ($target === null || $target['source'] !== 'managed') {
            // Fixed users from users.php cannot be changed here
            $error = 'Nutzer nicht gefunden.';
        } elseif ($action === 'renew') {
            $link      = invite_url(renew_invite($id));
            $link_name = $target['name'];
        } elseif ($action === 'delete') {
            delete_managed_user($id);
            $success = $target['name'] . ' wurde gelöscht.';
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$users = all_users();

// A user's status for the list: [text, icon, text colour]
function user_status(array $user): array {
    $invite = $user['invite'] ?? null;
    if (is_array($invite)) {
        if ($invite['expires'] <= time()) {
            return ['Link abgelaufen', 'schedule', 'text-error'];
        }
        $label = is_string($user['password_hash'] ?? null) ? 'Neuer Link offen' : 'Einladung offen';
        return [$label . ' bis ' . date('d.m. H:i', $invite['expires']), 'schedule', 'text-primary'];
    }
    if (!is_string($user['password_hash'] ?? null)) {
        return ['Kein Passwort', 'error', 'text-error'];
    }
    return ['Aktiv', 'check_circle', 'text-secondary'];
}

render_head('Nutzer');
render_nav();
?>
<main class="w-full max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-16">

    <div class="mb-8">
        <a href="/downloads.php" class="inline-flex items-center gap-1 font-label text-xs text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Downloads
        </a>
        <h1 class="font-headline text-4xl sm:text-5xl font-light tracking-[-0.01em] text-on-surface mt-3">Nutzer</h1>
        <p class="text-on-surface-variant text-sm mt-2">Neue Nutzer bekommen einen Einladungslink, über den sie ihr Passwort selbst setzen. Er gilt 48 Stunden und nur einmal.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-error-container/30 border border-error/30 text-error text-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-base mt-0.5">error</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="mb-6 px-4 py-3 rounded-xl bg-secondary/10 border border-secondary/30 text-secondary text-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-base mt-0.5">check_circle</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($link !== ''): ?>
        <div class="mb-8 rounded-2xl bg-surface-container-lowest/90 border border-primary/40 p-5 sm:p-6 shadow-2xl">
            <div class="flex items-center gap-2 text-primary font-label text-[11px] tracking-widest uppercase mb-3">
                <span class="material-symbols-outlined text-base">link</span>
                Link für <?= htmlspecialchars($link_name) ?>
            </div>
            <p class="text-on-surface-variant text-sm mb-4">Schick diesen Link selbst weiter, zum Beispiel per Messenger. Er wird nur jetzt angezeigt.</p>
            <div class="flex flex-col sm:flex-row gap-2">
                <input id="invite-link" type="text" readonly value="<?= htmlspecialchars($link) ?>"
                       class="flex-1 min-w-0 min-h-[48px] px-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface font-label text-xs
                              focus:outline-none focus:ring-2 focus:ring-primary/40">
                <button type="button" id="copy-link"
                        class="inline-flex items-center justify-center gap-2 min-h-[48px] px-4 rounded-xl bg-primary hover:bg-primary-fixed text-on-primary font-headline font-semibold text-sm transition-colors">
                    <span class="material-symbols-outlined text-base">content_copy</span>
                    <span>Kopieren</span>
                </button>
            </div>
        </div>
        <script>
            document.getElementById('copy-link').addEventListener('click', function () {
                var input = document.getElementById('invite-link');
                var label = this.querySelector('span:last-child');
                input.select();
                (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject())
                    .catch(function () { document.execCommand('copy'); })
                    .finally(function () { label.textContent = 'Kopiert'; });
            });
        </script>
    <?php endif; ?>

    <section class="mb-10 rounded-2xl bg-surface-container-lowest/90 border border-outline-variant/40 p-5 sm:p-6">
        <h2 class="font-headline text-2xl font-light tracking-[-0.01em] text-on-surface mb-5">Nutzer einladen</h2>
        <form method="POST" action="/admin.php" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="invite">
            <div>
                <label for="name" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">Name</label>
                <div class="relative">
                    <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">person</span>
                    <input type="text" id="name" name="name" required autocomplete="off"
                           value="<?= $link === '' ? htmlspecialchars($_POST['name'] ?? '') : '' ?>"
                           class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                  focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60 transition-colors font-label text-sm"
                           placeholder="Vorname Name">
                </div>
            </div>
            <div>
                <label for="email" class="block font-label text-[11px] tracking-widest uppercase text-on-surface-variant mb-2">E-Mail</label>
                <div class="relative">
                    <span class="material-symbols-outlined text-lg text-on-surface-variant absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">mail</span>
                    <input type="email" id="email" name="email" required autocomplete="off"
                           value="<?= $link === '' ? htmlspecialchars($_POST['email'] ?? '') : '' ?>"
                           class="w-full min-h-[48px] pl-11 pr-4 py-3 rounded-xl bg-surface-container-low border border-outline-variant/50 text-on-surface placeholder-on-surface-variant/40
                                  focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/60 transition-colors font-label text-sm"
                           placeholder="name@example.com">
                </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 min-h-[48px] px-5 rounded-xl bg-primary hover:bg-primary-fixed text-on-primary
                           font-headline font-semibold text-sm tracking-wide shadow-lg shadow-primary/20 transition-colors">
                <span class="material-symbols-outlined text-base">person_add</span>
                Einladen
            </button>
        </form>
    </section>

    <section>
        <h2 class="font-headline text-2xl font-light tracking-[-0.01em] text-on-surface mb-4">Alle Nutzer</h2>
        <ul class="flex flex-col gap-3">
            <?php foreach ($users as $user): ?>
                <?php [$status, $status_icon, $status_class] = user_status($user); ?>
                <li class="rounded-2xl bg-surface-container-lowest/90 border border-outline-variant/40 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="text-on-surface text-sm font-medium truncate">
                            <?= htmlspecialchars($user['name']) ?>
                            <?php if (!empty($user['admin'])): ?>
                                <span class="ml-1 px-2 py-0.5 rounded-full bg-primary/10 border border-primary/30 text-primary font-label text-[10px] tracking-widest uppercase">Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="font-label text-xs text-on-surface-variant truncate mt-0.5"><?= htmlspecialchars($user['email']) ?></div>
                        <div class="inline-flex items-center gap-1 font-label text-[11px] mt-1.5 <?= $status_class ?>">
                            <span class="material-symbols-outlined text-sm"><?= $status_icon ?></span>
                            <?= htmlspecialchars($status) ?>
                        </div>
                    </div>
                    <?php if ($user['source'] === 'managed'): ?>
                        <div class="flex gap-2">
                            <form method="POST" action="/admin.php">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="renew">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                                <button type="submit" title="Neuer Link, auch bei vergessenem Passwort"
                                        class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] rounded-lg bg-surface-container-high/60 border border-outline-variant/40
                                               text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest font-label text-xs transition-colors">
                                    <span class="material-symbols-outlined text-base">link</span>
                                    Neuer Link
                                </button>
                            </form>
                            <form method="POST" action="/admin.php" onsubmit="return confirm(<?= htmlspecialchars(json_encode($user['name'] . ' wirklich löschen?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>);">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-2 px-3 py-2 min-h-[44px] rounded-lg bg-error-container/20 border border-error/30
                                               text-error hover:bg-error-container/40 font-label text-xs transition-colors">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                    Löschen
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="font-label text-[11px] text-on-surface-variant">Fest in users.php</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
<?php render_footer(); ?>
