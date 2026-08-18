<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

$cookiePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
if ($cookiePath === '\\' || $cookiePath === '.' || $cookiePath === '') {
    $cookiePath = '/';
}
if ($cookiePath !== '/') {
    $cookiePath = rtrim($cookiePath, '/') . '/';
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
send_security_headers();

try {
    $config = Config::load(__DIR__ . '/config.php');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
}

$auth = new Auth($config);
