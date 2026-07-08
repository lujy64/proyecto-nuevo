<?php

declare(strict_types=1);

session_name('ruta_clara_session');
session_start();

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

set_exception_handler(static function (Throwable $exception): void {
    render_runtime_error($exception);
});

attempt_remember_login();
