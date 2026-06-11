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
        flash('error', 'Tu usuario tiene permisos de solo lectura.');
        redirect('dashboard.php');
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

    return (float) $value;
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

function active_nav(string $file): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $file ? 'is-active' : '';
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

function settlement_math(array $row): array
{
    $gross = (float) ($row['gross_earnings'] ?? 0);
    $cash = (float) ($row['cash_earnings'] ?? 0);
    $fuel = (float) ($row['fuel_cost'] ?? 0);
    $rental = (float) ($row['rental_amount'] ?? 0);
    $rentalPaid = (float) ($row['rental_paid'] ?? 0);
    $rentalUnpaid = (float) ($row['rental_unpaid'] ?? 0);

    return [
        'virtual_earnings' => $gross - $cash,
        'driver_profit' => $gross - $fuel - $rental,
        'transfer_due' => $gross - $cash - $rentalPaid,
        'owner_income' => $rentalPaid,
        'owner_receivable' => $rentalUnpaid,
    ];
}

function render_database_error(Throwable $exception): never
{
    $pageTitle = 'Configurar base de datos';
    require __DIR__ . '/views/header.php';
    ?>
    <main class="shell section">
        <section class="notice-panel">
            <p class="eyebrow">Configuracion pendiente</p>
            <h1>Conecta Ruta Clara a MySQL</h1>
            <p><?= e($exception->getMessage()) ?></p>
            <ol class="setup-list">
                <li>Crea una base MySQL en Hostinger.</li>
                <li>Importa <strong>database/schema.sql</strong> desde phpMyAdmin.</li>
                <li>Edita <strong>config/database.php</strong> con los datos reales.</li>
            </ol>
        </section>
    </main>
    <?php
    require __DIR__ . '/views/footer.php';
    exit;
}
