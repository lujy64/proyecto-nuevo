<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

try {
    clear_remember_login(Database::connection());
} catch (Throwable $exception) {
    clear_remember_login();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
redirect('index.php');
