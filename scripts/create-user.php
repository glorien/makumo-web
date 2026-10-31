#!/usr/bin/env php
<?php
// Usage: php scripts/create-user.php "MyPassword"
// Prints the bcrypt hash to put into config/users.php.

if (PHP_SAPI !== 'cli') {
    exit('Nur via CLI ausführen.' . PHP_EOL);
}

$password = $argv[1] ?? null;

if ($password === null || $password === '') {
    fwrite(STDERR, 'Verwendung: php scripts/create-user.php "MeinPasswort"' . PHP_EOL);
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, 'Fehler: Passwort muss mindestens 8 Zeichen lang sein.' . PHP_EOL);
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo $hash . PHP_EOL;
