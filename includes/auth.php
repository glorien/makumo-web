<?php
declare(strict_types=1);
require_once __DIR__ . '/users.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function require_login(): void {
    start_session();
    // Deleted users also lose any session that is still open
    if (empty($_SESSION['user_id']) || find_user_by_id((string) $_SESSION['user_id']) === null) {
        $_SESSION = [];
        header('Location: /login.php');
        exit;
    }
}

function is_admin(): bool {
    start_session();
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $user = find_user_by_id((string) $_SESSION['user_id']);
    return $user !== null && $user['source'] === 'static' && !empty($user['admin']);
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        header('Location: /downloads.php');
        exit;
    }
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ];
}

function attempt_login(string $email, string $password): bool {
    $user = find_user_by_email($email);
    // Invited users who have not set a password yet cannot sign in
    if ($user === null || !is_string($user['password_hash'] ?? null)) {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    log_in_user($user);
    return true;
}

function log_in_user(array $user): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']    = (string) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}

function logout(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): bool {
    start_session();
    $token = $_POST['csrf'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

// Login lock: at most LOGIN_MAX_ATTEMPTS failed attempts per IP within LOGIN_WINDOW seconds
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW       = 900; // 15 minutes

function login_attempts_file(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return __DIR__ . '/../cache/login_attempts/' . hash('sha256', $ip) . '.json';
}

function read_login_attempts(): array {
    $file = login_attempts_file();
    if (!file_exists($file)) {
        return ['count' => 0, 'first' => time()];
    }

    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || time() - ($data['first'] ?? 0) >= LOGIN_WINDOW) {
        return ['count' => 0, 'first' => time()];
    }
    return $data;
}

// Remaining lock time in seconds, 0 if not locked
function login_lockout_remaining(): int {
    $data = read_login_attempts();
    if ($data['count'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    return max(0, $data['first'] + LOGIN_WINDOW - time());
}

function register_failed_login(): void {
    $data = read_login_attempts();
    $data['count']++;

    $file = login_attempts_file();
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0750, true);
    }
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clear_failed_logins(): void {
    $file = login_attempts_file();
    if (file_exists($file)) {
        unlink($file);
    }
}
