<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Abmelden nur per POST mit gültigem CSRF-Token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    header('Location: /');
    exit;
}

logout();
header('Location: /login.php');
exit;
