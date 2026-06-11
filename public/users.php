<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $requestedRole = (string) ($_POST['role'] ?? 'viewer');
    $role = in_array($requestedRole, ['admin', 'viewer'], true) ? $requestedRole : 'viewer';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $currentUserId = (int) current_user()['id'];

    if ($fullName === '' || $username === '') {
        flash('error', 'Nombre y usuario son obligatorios.');
        redirect('users.php');
    }

    if ($id === 0 && $password === '') {
        flash('error', 'El password es obligatorio para crear usuarios.');
        redirect('users.php');
    }

    if ($id === $currentUserId && ($role !== 'admin' || $isActive !== 1)) {
        flash('error', 'No podes quitarte permisos de administrador ni desactivar tu propio usuario.');
        redirect('users.php');
    }

    try {
        if ($id > 0) {
            $params = [
                'full_name' => $fullName,
                'username' => $username,
                'role' => $role,
                'is_active' => $isActive,
                'id' => $id,
            ];
            $passwordSql = '';

            if ($password !== '') {
                $passwordSql = ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $statement = $pdo->prepare(
                "UPDATE users
                SET full_name = :full_name, username = :username, role = :role, is_active = :is_active {$passwordSql}
                WHERE id = :id"
            );
            $statement->execute($params);
            flash('success', 'Usuario actualizado.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO users (full_name, username, password_hash, role, is_active)
                VALUES (:full_name, :username, :password_hash, :role, :is_active)'
            );
            $statement->execute([
                'full_name' => $fullName,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'is_active' => $isActive,
            ]);
            flash('success', 'Usuario creado.');
        }
    } catch (PDOException $exception) {
        flash('error', 'No se pudo guardar. Revisa que el usuario no este repetido.');
    }

    redirect('users.php');
}

$editUser = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editUser = $statement->fetch() ?: null;
}

$users = $pdo->query('SELECT * FROM users ORDER BY is_active DESC, role ASC, full_name ASC')->fetchAll();

$pageTitle = 'Usuarios';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Accesos</p>
            <h1>Usuarios</h1>
        </div>
    </div>

    <section class="content-grid">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Listado</h2>
                <span><?= count($users) ?> usuarios</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Ultimo acceso</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $userRow): ?>
                        <tr>
                            <td><strong><?= e($userRow['full_name']) ?></strong></td>
                            <td><?= e($userRow['username']) ?></td>
                            <td><?= $userRow['role'] === 'admin' ? 'Administrador' : 'Lector' ?></td>
                            <td><span class="status <?= (int) $userRow['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $userRow['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td><?= $userRow['last_login_at'] ? date('d/m/Y H:i', strtotime($userRow['last_login_at'])) : '-' ?></td>
                            <td class="row-actions">
                                <a class="button ghost small" href="users.php?edit=<?= (int) $userRow['id'] ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="panel">
            <div class="panel-heading">
                <h2><?= $editUser ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
                <?php if ($editUser): ?><a href="users.php">Nuevo</a><?php endif; ?>
            </div>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
                <label>
                    Nombre
                    <input type="text" name="full_name" value="<?= e($editUser['full_name'] ?? '') ?>" required>
                </label>
                <label>
                    Usuario
                    <input type="text" name="username" value="<?= e($editUser['username'] ?? '') ?>" required>
                </label>
                <label>
                    Password <?= $editUser ? '<small>dejar vacio para no cambiar</small>' : '' ?>
                    <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                </label>
                <label>
                    Rol
                    <select name="role">
                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="viewer" <?= ($editUser['role'] ?? 'viewer') === 'viewer' ? 'selected' : '' ?>>Solo lectura</option>
                    </select>
                </label>
                <label class="check-row">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($editUser['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Usuario activo
                </label>
                <button class="button primary full" type="submit">Guardar usuario</button>
            </form>
        </aside>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
