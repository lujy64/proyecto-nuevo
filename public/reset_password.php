<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('daily_entries.php');
}

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

$selector = (string) ($_GET['selector'] ?? $_POST['selector'] ?? '');
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = find_valid_password_reset($pdo, $selector, $token);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$reset) {
        $errors[] = 'El enlace de recuperacion no es valido o ya vencio.';
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (strlen($password) < 8) {
        $errors[] = 'La nueva contrasena debe tener al menos 8 caracteres.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Las contrasenas no coinciden.';
    }

    if (!$errors && $reset) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $update->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $reset['user_id'],
            ]);

            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')->execute(['id' => $reset['id']]);
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')->execute(['user_id' => $reset['user_id']]);
            $pdo->commit();
            clear_remember_login();
            flash('success', 'Contrasena actualizada. Ya podes ingresar.');
            redirect('login.php');
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'No se pudo actualizar la contrasena. Intenta nuevamente.';
        }
    }
}

$pageTitle = 'Nueva contrasena';
require __DIR__ . '/../app/views/header.php';
?>

<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Recuperar acceso</p>
        <h1>Nueva contrase&ntilde;a</h1>

        <?php if (!$reset): ?>
            <div class="flash error inline">El enlace de recuperacion no es valido o ya vencio.</div>
            <a class="button primary full" href="forgot_password.php">Generar otro enlace</a>
        <?php else: ?>
            <p>Estas cambiando la contrase&ntilde;a de <strong><?= e($reset['full_name']) ?></strong>.</p>

            <?php if ($errors): ?>
                <div class="flash error inline"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="selector" value="<?= e($selector) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>
                    Nueva contrase&ntilde;a
                    <input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
                </label>
                <label>
                    Repetir contrase&ntilde;a
                    <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                </label>
                <button class="button primary full" type="submit">Guardar contrase&ntilde;a</button>
                <a class="button ghost full" href="login.php">Volver al login</a>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
