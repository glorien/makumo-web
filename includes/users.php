<?php
declare(strict_types=1);

// Zwei Nutzerquellen:
// - config/users.php: feste Nutzer, nur per FTP änderbar. Nur hier kann jemand Admin sein ('admin' => true).
// - config/users.json: über die Admin-Seite eingeladene Nutzer, vom Webserver beschreibbar.

const INVITE_LIFETIME    = 172800; // 48 hours
const PASSWORD_MIN_BYTES = 8;
const PASSWORD_MAX_BYTES = 72;     // bcrypt ignoriert alles danach

function users_json_file(): string {
    return __DIR__ . '/../config/users.json';
}

function static_users(): array {
    $config_path = __DIR__ . '/../config/users.php';
    if (!file_exists($config_path)) {
        return [];
    }
    $users = require $config_path;
    return is_array($users) ? $users : [];
}

function managed_users(): array {
    $file = users_json_file();
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Alle Nutzer, jeweils mit 'source' => 'static' oder 'managed'
function all_users(): array {
    $users = [];
    foreach (static_users() as $user) {
        $users[] = $user + ['source' => 'static', 'admin' => false];
    }
    foreach (managed_users() as $user) {
        // Admin-Rechte gibt es nur aus users.php, nie aus der beschreibbaren Datei
        $users[] = ['source' => 'managed', 'admin' => false] + $user;
    }
    return $users;
}

function find_user_by_email(string $email): ?array {
    $email = strtolower(trim($email));
    foreach (all_users() as $user) {
        if (strtolower($user['email']) === $email) {
            return $user;
        }
    }
    return null;
}

function find_user_by_id(string $id): ?array {
    foreach (all_users() as $user) {
        if ((string) $user['id'] === $id) {
            return $user;
        }
    }
    return null;
}

function users_store_writable(): bool {
    $file = users_json_file();
    return file_exists($file) ? is_writable($file) : is_writable(dirname($file));
}

// Liest users.json unter exklusiver Sperre, übergibt die Liste an $fn und schreibt das Ergebnis zurück.
// $fn bekommt die Liste per Referenz und darf einen beliebigen Rückgabewert liefern.
function update_managed_users(callable $fn): mixed {
    $handle = fopen(users_json_file(), 'c+');
    if ($handle === false) {
        throw new RuntimeException('config/users.json kann nicht geöffnet werden.');
    }
    try {
        flock($handle, LOCK_EX);
        $data  = json_decode((string) stream_get_contents($handle), true);
        $users = is_array($data) ? $data : [];

        $result = $fn($users);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $result;
    } finally {
        fclose($handle);
    }
}

function new_invite(): array {
    $token = bin2hex(random_bytes(32));
    return [$token, ['hash' => hash('sha256', $token), 'expires' => time() + INVITE_LIFETIME]];
}

// Legt einen Nutzer ohne Passwort an und gibt den Einladungs-Token zurück
function create_invited_user(string $email, string $name): string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.');
    }
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Bitte einen Namen eingeben.');
    }
    if (find_user_by_email($email) !== null) {
        throw new InvalidArgumentException('Diese E-Mail-Adresse ist schon vergeben.');
    }

    [$token, $invite] = new_invite();
    update_managed_users(function (array &$users) use ($email, $name, $invite) {
        $users[] = [
            'id'            => 'u' . bin2hex(random_bytes(6)),
            'email'         => $email,
            'name'          => $name,
            'password_hash' => null,
            'created'       => time(),
            'invite'        => $invite,
        ];
    });
    return $token;
}

// Neuer Link für einen eingeladenen Nutzer (abgelaufene Einladung oder Passwort vergessen)
function renew_invite(string $id): string {
    [$token, $invite] = new_invite();
    $found = update_managed_users(function (array &$users) use ($id, $invite) {
        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user['invite'] = $invite;
                return true;
            }
        }
        return false;
    });
    if (!$found) {
        throw new InvalidArgumentException('Nutzer nicht gefunden.');
    }
    return $token;
}

function delete_managed_user(string $id): void {
    update_managed_users(function (array &$users) use ($id) {
        $users = array_filter($users, fn($user) => $user['id'] !== $id);
    });
}

// Nutzer zu einem gültigen, nicht abgelaufenen Token, sonst null
function find_user_by_invite(string $token): ?array {
    if ($token === '') {
        return null;
    }
    $hash = hash('sha256', $token);
    foreach (managed_users() as $user) {
        $invite = $user['invite'] ?? null;
        if (is_array($invite) && hash_equals($invite['hash'], $hash) && $invite['expires'] > time()) {
            return $user;
        }
    }
    return null;
}

function validate_new_password(string $password, string $confirm): ?string {
    if (strlen($password) < PASSWORD_MIN_BYTES) {
        return 'Das Passwort muss mindestens ' . PASSWORD_MIN_BYTES . ' Zeichen lang sein.';
    }
    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return 'Das Passwort darf höchstens ' . PASSWORD_MAX_BYTES . ' Bytes lang sein.';
    }
    if (!hash_equals($password, $confirm)) {
        return 'Die Passwörter stimmen nicht überein.';
    }
    return null;
}

// Setzt das Passwort und verbraucht den Token. Gibt den Nutzer zurück oder null, wenn der Token nicht (mehr) gilt.
function accept_invite(string $token, string $password): ?array {
    $hash          = hash('sha256', $token);
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    return update_managed_users(function (array &$users) use ($hash, $password_hash) {
        foreach ($users as &$user) {
            $invite = $user['invite'] ?? null;
            if (is_array($invite) && hash_equals($invite['hash'], $hash) && $invite['expires'] > time()) {
                $user['password_hash'] = $password_hash;
                $user['invite']        = null;
                return $user;
            }
        }
        return null;
    });
}

// Basis-URL für Einladungslinks (Schema und Host der aktuellen Anfrage)
function invite_url(string $token): string {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/invite.php?t=" . urlencode($token);
}
