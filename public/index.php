<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

redirect(is_logged_in() ? 'daily_entries.php' : 'login.php');
