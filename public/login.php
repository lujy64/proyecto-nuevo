<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
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
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        redirect('dashboard.php');
    }

    $error = 'Usuario o password incorrectos.';
}

$pageTitle = 'Ingresar';
$bodyClass = 'auth-page';
require __DIR__ . '/../app/views/header.php';
?>

<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Acceso privado</p>
        <h1>Ingresar a Ruta Clara</h1>
        <p class="muted">Usa el usuario administrador creado al importar la base de datos.</p>

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
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button primary full" type="submit">Ingresar</button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
