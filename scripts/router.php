<?php
declare(strict_types=1);

// Router for the PHP built-in server, which ignores .htaccess. Mirrors the
// clean-URL rules there: /login serves login.php, /login.php redirects to /login.
// Usage (from the project root): php -S localhost:8000 scripts/router.php

if (PHP_SAPI !== 'cli-server') {
    exit;
}

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$query = $_SERVER['QUERY_STRING'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Same as the RedirectMatch 404 in .htaccess
if (preg_match('#^/(config|cache|includes|scripts|logs)(/|$)#', $path)) {
    http_response_code(404);
    exit;
}

// Old root-level URLs with extension: 301 to the clean form (GET/HEAD only)
if (preg_match('#^/([^/]+)\.php$#', $path, $m) && in_array($method, ['GET', 'HEAD'], true)) {
    $target = $m[1] === 'index' ? '/' : '/' . $m[1];
    header('Location: ' . $target . ($query !== '' ? "?$query" : ''), true, 301);
    exit;
}

// Extensionless URL -> matching PHP file
if (preg_match('#^/([^/.]+)$#', $path, $m) && is_file("$root/{$m[1]}.php")) {
    $_SERVER['SCRIPT_NAME'] = "/{$m[1]}.php";
    require "$root/{$m[1]}.php";
    exit;
}

// Everything else (/, assets, POST to old URLs) as the built-in server would
return false;
