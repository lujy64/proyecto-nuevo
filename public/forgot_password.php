<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('daily_entries.php');
}

$reset = null;
$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $pdo = Database::connection();
    } catch (Throwable $exception) {
        render_database_error($exception);
    }

    $username = trim((string) ($_POST['username'] ?? ''));

    if ($username === '') {
        $error = 'Ingresa tu usuario para continuar.';
    } else {
        $statement = $pdo->prepare('SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        try {
            if ($user) {
                $reset = create_password_reset($pdo, $user);
            }

            $sent = true;
        } catch (Throwable $exception) {
            $error = 'No se pudo generar el enlace. Ejecuta database/auth_tokens_migration.sql.';
        }
    }
}

$pageTitle = 'Recuperar contrasena';
require __DIR__ . '/../app/views/header.php';
?>

<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Recuperar acceso</p>
        <h1>Olvide mi contrase&ntilde;a</h1>
        <p>Ingresa tu usuario y generamos un enlace temporal para cambiar la clave.</p>

        <?php if ($error): ?>
            <div class="flash error inline"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="flash success inline">Si el usuario existe y esta activo, se genero una solicitud de recuperacion.</div>
            <?php if ($reset): ?>
                <div class="reset-link-box">
                    <strong>Enlace temporal</strong>
                    <p>Modo MVP: cuando configures email o WhatsApp, este enlace se envia automaticamente. Por ahora podes abrirlo desde aca.</p>
                    <a class="button secondary full" href="<?= e($reset['url']) ?>">Cambiar contrase&ntilde;a</a>
                    <input type="text" value="<?= e($reset['url']) ?>" readonly>
                    <small>Vence a las <?= e(date('H:i', strtotime($reset['expires_at']))) ?>.</small>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>
                Usuario
                <input type="text" name="username" autocomplete="username" required autofocus>
            </label>
            <button class="button primary full" type="submit">Generar enlace</button>
            <a class="button ghost full" href="login.php">Volver al login</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
