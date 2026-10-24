<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: /downloads.php');
} else {
    header('Location: /login.php');
}
exit;
