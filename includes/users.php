<?php
declare(strict_types=1);

// Two user sources:
// - config/users.php: fixed users, changed via FTP only. Only these can be admins ('admin' => true).
// - config/users.json: users invited on the admin page, writable by the web server.

const INVITE_LIFETIME    = 172800; // 48 hours
const PASSWORD_MIN_BYTES = 8;
const PASSWORD_MAX_BYTES = 72;     // bcrypt ignores anything beyond

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

// All users, each with 'source' => 'static' or 'managed'
function all_users(): array {
    $users = [];
    foreach (static_users() as $user) {
        $users[] = $user + ['source' => 'static', 'admin' => false];
    }
    foreach (managed_users() as $user) {
        // Admin rights only come from users.php, never from the writable file
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

// Reads users.json under an exclusive lock, passes the list to $fn and writes the result back.
// $fn receives the list by reference and may return any value.
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

// Creates a user without a password and returns the invitation token
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

// New link for an invited user (expired invitation or forgotten password)
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

// User for a valid, unexpired token, otherwise null
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

// Sets the password and consumes the token. Returns the user, or null if the token is not (or no longer) valid.
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

// Invitation link URL (scheme and host of the current request)
function invite_url(string $token): string {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/invite.php?t=" . urlencode($token);
}
