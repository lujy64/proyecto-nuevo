<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('daily_entries.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $pdo = Database::connection();
    } catch (Throwable $exception) {
        render_database_error($exception);
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);

    $statement = $pdo->prepare('SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    if ($user && verify_stored_password($password, (string) $user['password_hash'])) {
        if (password_should_be_rehashed((string) $user['password_hash'])) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute(['password_hash' => $newHash, 'id' => $user['id']]);
        }

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        login_user($user);

        if ($rememberMe) {
            try {
                create_remember_login($pdo, (int) $user['id']);
            } catch (Throwable $exception) {
                flash('error', 'Ingresaste correctamente, pero no se pudo activar Recordarme. Ejecuta database/auth_tokens_migration.sql.');
            }
        } else {
            clear_remember_login($pdo);
        }

        redirect('daily_entries.php');
    }

    $error = 'Usuario o password incorrectos.';
}

$pageTitle = 'Ingresar';
require __DIR__ . '/../app/views/header.php';
?>

<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Acceso privado</p>
        <h1>Ingresar a Ruta Clara</h1>
        <p>Gestion privada para administrar choferes, autos, ingresos diarios, gastos y reportes de rendimiento desde cualquier dispositivo.</p>

        <?php if ($error): ?>
            <div class="flash error inline"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>
                Usuario
                <input type="text" name="username" autocomplete="username" required autofocus>
            </label>
            <label>
                Password
                <span class="password-field">
                    <input type="password" name="password" autocomplete="current-password" required data-password-input>
                    <button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar password" aria-pressed="false">Ver</button>
                </span>
            </label>
            <div class="auth-options">
                <label class="check-row">
                    <input type="checkbox" name="remember_me" value="1">
                    Recordarme
                </label>
                <a class="button ghost small" href="forgot_password.php">Olvide mi contrase&ntilde;a</a>
            </div>
            <button class="button primary full" type="submit">Ingresar</button>
        </form>

        <div class="auth-info">
            <strong>Ruta Clara</strong>
            <span>Sistema de control operativo desarrollado por The Panther Soft.</span>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
