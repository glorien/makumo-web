<?php
declare(strict_types=1);

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
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
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
    $config_path = __DIR__ . '/../config/users.php';
    if (!file_exists($config_path)) {
        return false;
    }

    $users = require $config_path;
    $email = strtolower(trim($email));

    foreach ($users as $user) {
        if (strtolower($user['email']) === $email) {
            if (password_verify($password, $user['password_hash'])) {
                start_session();
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                return true;
            }
            return false;
        }
    }

    return false;
}

function logout(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
}
