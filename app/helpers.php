<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');

    if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        exit('La sesion expiro. Vuelve atras e intenta nuevamente.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function current_owner_user_id(): int
{
    $user = current_user();

    if (!$user) {
        return 0;
    }

    return (int) ($user['owner_user_id'] ?? $user['id'] ?? 0);
}

function append_owner_scope(array &$where, array &$params, string $alias = ''): void
{
    $ownerId = current_owner_user_id();

    if ($ownerId <= 0) {
        $where[] = '1 = 0';
        return;
    }

    $column = ($alias !== '' ? "{$alias}." : '') . 'owner_user_id';
    $where[] = "{$column} = :owner_user_id";
    $params['owner_user_id'] = $ownerId;
}

function can_manage_users(): bool
{
    $user = current_user();

    return ($user['role'] ?? null) === 'admin'
        && ($user['username'] ?? '') === 'personal';
}

function is_driver_user(): bool
{
    return (current_user()['role'] ?? null) === 'driver';
}

function current_driver_id(): ?int
{
    $driverId = current_user()['driver_id'] ?? null;
    return $driverId ? (int) $driverId : null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'driver_id' => $user['driver_id'] !== null ? (int) $user['driver_id'] : null,
        'owner_user_id' => isset($user['owner_user_id']) && $user['owner_user_id'] !== null ? (int) $user['owner_user_id'] : (int) $user['id'],
    ];
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        flash('error', 'Tu usuario no tiene permisos para administrar esta seccion.');
        redirect('daily_entries.php');
    }
}

function require_user_manager(): void
{
    require_login();

    if (!can_manage_users()) {
        flash('error', 'Solo Usuario Personal puede administrar usuarios y vinculaciones.');
        redirect('daily_entries.php');
    }
}

function parse_decimal(mixed $value): float
{
    $value = trim((string) $value);

    if ($value === '') {
        return 0.0;
    }

    if (str_contains($value, ',')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return round((float) $value, 2);
}

function money(mixed $value, int $decimals = 0): string
{
    return '$ ' . number_format((float) $value, $decimals, ',', '.');
}

function number_ar(mixed $value, int $decimals = 2): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

function date_ar(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('d/m/Y', strtotime($date));
}

function month_name_ar(string $month): string
{
    $names = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];
    $timestamp = strtotime($month . '-01');

    return ($names[(int) date('n', $timestamp)] ?? $month) . ' ' . date('Y', $timestamp);
}

function active_nav(string|array $files): string
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $files = is_array($files) ? $files : [$files];

    return in_array($current, $files, true) ? 'is-active' : '';
}

function verify_stored_password(string $plain, string $stored): bool
{
    if (password_verify($plain, $stored)) {
        return true;
    }

    if (preg_match('/^[a-f0-9]{64}$/i', $stored) === 1) {
        return hash_equals(strtolower($stored), hash('sha256', $plain));
    }

    return false;
}

function password_should_be_rehashed(string $stored): bool
{
    return preg_match('/^[a-f0-9]{64}$/i', $stored) === 1 || password_needs_rehash($stored, PASSWORD_DEFAULT);
}

function remember_cookie_name(): string
{
    return 'ruta_clara_remember';
}

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function app_cookie_path(): string
{
    $path = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function set_app_cookie(string $name, string $value, int $expires): void
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => app_cookie_path(),
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_app_cookie(string $name): void
{
    set_app_cookie($name, '', time() - 3600);
    unset($_COOKIE[$name]);
}

function create_remember_login(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    $statement = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent, ip_address)
        VALUES (:user_id, :selector, :token_hash, :expires_at, :user_agent, :ip_address)'
    );
    $statement->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => hash('sha256', $validator),
        'expires_at' => $expiresAt,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    set_app_cookie(remember_cookie_name(), $selector . ':' . $validator, strtotime($expiresAt));
}

function clear_remember_login(?PDO $pdo = null): void
{
    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');

    if ($pdo && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        try {
            $statement = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
            $statement->execute(['selector' => $selector]);
        } catch (Throwable $exception) {
            // The auth migration may not be installed yet; clearing the cookie is enough here.
        }
    }

    clear_app_cookie(remember_cookie_name());
}

function attempt_remember_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $cookie = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{24}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $validator)) {
        clear_app_cookie(remember_cookie_name());
        return;
    }

    try {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT rt.*, u.id AS user_id, u.full_name, u.username, u.role, u.driver_id, u.owner_user_id
            FROM remember_tokens rt
            INNER JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = :selector AND rt.expires_at > NOW() AND u.is_active = 1
            LIMIT 1'
        );
        $statement->execute(['selector' => $selector]);
        $token = $statement->fetch();

        if (!$token || !hash_equals((string) $token['token_hash'], hash('sha256', $validator))) {
            clear_remember_login($pdo);
            return;
        }

        login_user([
            'id' => $token['user_id'],
            'full_name' => $token['full_name'],
            'username' => $token['username'],
            'role' => $token['role'],
            'driver_id' => $token['driver_id'],
            'owner_user_id' => $token['owner_user_id'],
        ]);

        $pdo->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $token['id']]);
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $token['user_id']]);
    } catch (Throwable $exception) {
        return;
    }
}

function reset_password_url(string $selector, string $token): string
{
    $scheme = is_https_request() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $path = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    $path = rtrim($path, '/');
    $relative = ($path === '' ? '' : $path) . '/reset_password.php?selector=' . rawurlencode($selector) . '&token=' . rawurlencode($token);

    return $host !== '' ? "{$scheme}://{$host}{$relative}" : $relative;
}

function create_password_reset(PDO $pdo, array $user): array
{
    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60);

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id AND used_at IS NULL')->execute(['user_id' => $user['id']]);
    $statement = $pdo->prepare(
        'INSERT INTO password_resets (user_id, selector, token_hash, expires_at, user_agent, ip_address)
        VALUES (:user_id, :selector, :token_hash, :expires_at, :user_agent, :ip_address)'
    );
    $statement->execute([
        'user_id' => $user['id'],
        'selector' => $selector,
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expiresAt,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    return [
        'selector' => $selector,
        'token' => $token,
        'expires_at' => $expiresAt,
        'url' => reset_password_url($selector, $token),
    ];
}

function find_valid_password_reset(PDO $pdo, string $selector, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT pr.*, u.full_name, u.username, u.is_active
        FROM password_resets pr
        INNER JOIN users u ON u.id = pr.user_id
        WHERE pr.selector = :selector AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_active = 1
        LIMIT 1'
    );
    $statement->execute(['selector' => $selector]);
    $reset = $statement->fetch();

    if (!$reset || !hash_equals((string) $reset['token_hash'], hash('sha256', $token))) {
        return null;
    }

    return $reset;
}

function valid_date_or_today(?string $date): string
{
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
        return $date;
    }

    return date('Y-m-d');
}

function valid_month_or_current(?string $month): string
{
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
        return $month;
    }

    return date('Y-m');
}

function valid_week_or_current(?string $week): string
{
    if ($week && preg_match('/^\d{4}-W\d{2}$/', $week) === 1) {
        return $week;
    }

    return date('o-\WW');
}

function iso_week_bounds(string $week): array
{
    if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches) !== 1) {
        $week = date('o-\WW');
        preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches);
    }

    $date = new DateTimeImmutable();
    $date = $date->setISODate((int) $matches[1], (int) $matches[2]);

    return [
        'start' => $date->format('Y-m-d'),
        'end' => $date->modify('+6 days')->format('Y-m-d'),
    ];
}

function month_bounds(string $month): array
{
    $start = $month . '-01';

    return [
        'start' => $start,
        'end' => date('Y-m-t', strtotime($start)),
    ];
}

function daily_entry_periods(string $date): array
{
    $timestamp = strtotime($date);

    return [
        'week_number' => (int) date('W', $timestamp),
        'month_number' => (int) date('n', $timestamp),
        'year_number' => (int) date('Y', $timestamp),
    ];
}

function daily_entry_math(array $row): array
{
    $gross = (float) ($row['gross_income'] ?? 0);
    $appExpenses = (float) ($row['app_expenses'] ?? 0);
    $cash = (float) ($row['cash_collected'] ?? 0);
    $rental = (float) ($row['rental_amount'] ?? 0);
    $deductions = $appExpenses + $cash + $rental;

    return [
        'total_expenses' => round($deductions, 2),
        'net_total' => round($gross - $deductions, 2),
    ];
}

function scope_filters(?int $driverId = null, int $carId = 0): array
{
    if (is_driver_user()) {
        $driverId = current_driver_id() ?? -1;
    }

    return [
        'driver_id' => $driverId ? (int) $driverId : 0,
        'car_id' => $carId > 0 ? $carId : 0,
    ];
}

function append_entry_scope(array &$where, array &$params, string $alias, int $driverId, int $carId): void
{
    if (is_admin()) {
        append_owner_scope($where, $params, $alias);
    }

    if ($driverId < 0) {
        $where[] = '1 = 0';
        return;
    }

    if ($driverId > 0) {
        $where[] = "{$alias}.driver_id = :driver_id";
        $params['driver_id'] = $driverId;
    }

    if ($carId > 0) {
        $where[] = "{$alias}.car_id = :car_id";
        $params['car_id'] = $carId;
    }
}

function render_database_error(Throwable $exception): never
{
    $pageTitle = 'Configurar base de datos';
    require __DIR__ . '/views/header.php';
    ?>
    <main class="setup-shell">
        <section class="notice-panel">
            <p class="eyebrow">Configuracion pendiente</p>
            <h1>Conecta Ruta Clara a MySQL</h1>
            <p><?= e($exception->getMessage()) ?></p>
            <ol class="setup-list">
                <li>Crea una base MySQL.</li>
                <li>Importa <strong>database/schema.sql</strong> desde phpMyAdmin.</li>
                <li>Edita <strong>config/database.php</strong> con los datos reales.</li>
            </ol>
        </section>
    </main>
    <?php
    require __DIR__ . '/views/footer.php';
    exit;
}

function render_runtime_error(Throwable $exception): never
{
    http_response_code(500);
    $pageTitle = 'Error de sistema';
    require __DIR__ . '/views/header.php';
    ?>
    <main class="setup-shell">
        <section class="notice-panel">
            <p class="eyebrow">Revision necesaria</p>
            <h1>No se pudo cargar esta pantalla</h1>
            <p>La base o el codigo no estan sincronizados. Si acabas de subir cambios, importa primero <strong>database/schema.sql</strong> o ejecuta las migraciones pendientes, por ejemplo <strong>database/auth_tokens_migration.sql</strong>, <strong>database/daily_entries_income_migration.sql</strong> y <strong>database/personal_workspace_migration.sql</strong>.</p>
            <div class="error-detail"><?= e($exception->getMessage()) ?></div>
        </section>
    </main>
    <?php
    require __DIR__ . '/views/footer.php';
    exit;
}
