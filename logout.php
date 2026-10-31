<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Sign out only via POST with a valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    header('Location: /');
    exit;
}

logout();
header('Location: /login.php');
exit;
