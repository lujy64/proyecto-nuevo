<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_user_manager();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

$ownerUserId = current_owner_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $requestedRole = (string) ($_POST['role'] ?? 'driver');
    $role = in_array($requestedRole, ['admin', 'driver'], true) ? $requestedRole : 'driver';
    $driverId = ($_POST['driver_id'] ?? '') === '' ? null : (int) $_POST['driver_id'];
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

    if ($role === 'admin') {
        $driverId = null;
    }

    if ($driverId !== null) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM drivers WHERE id = :id AND owner_user_id = :owner_user_id');
        $statement->execute(['id' => $driverId, 'owner_user_id' => $ownerUserId]);

        if ((int) $statement->fetchColumn() === 0) {
            flash('error', 'El chofer seleccionado no pertenece a tu cuenta.');
            redirect('users.php');
        }
    }

    if ($id === $currentUserId && ($role !== 'admin' || $isActive !== 1)) {
        flash('error', 'No podes quitarte permisos de administrador ni desactivar tu propio usuario.');
        redirect('users.php');
    }

    if ($id > 0) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id AND owner_user_id = :owner_user_id');
        $statement->execute(['id' => $id, 'owner_user_id' => $ownerUserId]);

        if ((int) $statement->fetchColumn() === 0) {
            flash('error', 'No podes editar usuarios de otra cuenta.');
            redirect('users.php');
        }
    }

    try {
        if ($id > 0) {
            $params = [
                'full_name' => $fullName,
                'username' => $username,
                'role' => $role,
                'driver_id' => $driverId,
                'is_active' => $isActive,
                'owner_user_id' => $ownerUserId,
                'id' => $id,
            ];
            $passwordSql = '';

            if ($password !== '') {
                $passwordSql = ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $statement = $pdo->prepare(
                "UPDATE users
                SET full_name = :full_name, username = :username, role = :role,
                    driver_id = :driver_id, is_active = :is_active {$passwordSql}
                WHERE id = :id AND owner_user_id = :owner_user_id"
            );
            $statement->execute($params);
            flash('success', 'Usuario actualizado.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO users (full_name, username, password_hash, role, driver_id, owner_user_id, is_active)
                VALUES (:full_name, :username, :password_hash, :role, :driver_id, :owner_user_id, :is_active)'
            );
            $statement->execute([
                'full_name' => $fullName,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'driver_id' => $driverId,
                'owner_user_id' => $ownerUserId,
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
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id AND owner_user_id = :owner_user_id');
    $statement->execute(['id' => (int) $_GET['edit'], 'owner_user_id' => $ownerUserId]);
    $editUser = $statement->fetch() ?: null;

    if (!$editUser) {
        flash('error', 'No se encontro el usuario solicitado.');
        redirect('users.php');
    }
}
$userDrawerOpen = isset($_GET['new']) || $editUser !== null;

$statement = $pdo->prepare(
    'SELECT u.*, d.full_name AS driver_name
    FROM users u
    LEFT JOIN drivers d ON d.id = u.driver_id AND d.owner_user_id = u.owner_user_id
    WHERE u.owner_user_id = :owner_user_id
    ORDER BY u.is_active DESC, u.role ASC, u.full_name ASC'
);
$statement->execute(['owner_user_id' => $ownerUserId]);
$users = $statement->fetchAll();

$statement = $pdo->prepare("SELECT id, full_name FROM drivers WHERE status = 'active' AND owner_user_id = :owner_user_id ORDER BY full_name ASC");
$statement->execute(['owner_user_id' => $ownerUserId]);
$drivers = $statement->fetchAll();

$pageTitle = 'Usuarios';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Roles de usuario</p>
            <h1>Usuarios</h1>
            <p>Solo Usuario Personal puede administrar accesos y vinculaciones. Los demas administradores y choferes no ven este modulo.</p>
        </div>
        <a class="button primary" href="users.php?new=1">Nuevo usuario</a>
    </div>

    <section class="management-list">
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
                        <th>Chofer vinculado</th>
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
                            <td><?= $userRow['role'] === 'admin' ? 'Administrador' : 'Chofer' ?></td>
                            <td><?= e($userRow['driver_name'] ?: '-') ?></td>
                            <td><span class="status <?= (int) $userRow['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $userRow['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td><?= $userRow['last_login_at'] ? date('d/m/Y H:i', strtotime($userRow['last_login_at'])) : '-' ?></td>
                            <td class="row-actions">
                                <a class="button ghost small" href="users.php?edit=<?= (int) $userRow['id'] ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="7" class="empty-cell">No hay usuarios cargados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php if ($userDrawerOpen): ?>
        <div class="drawer-shell" role="dialog" aria-modal="true" aria-labelledby="user-drawer-title">
            <a class="drawer-backdrop" href="users.php" aria-label="Cerrar formulario"></a>
            <aside class="drawer-panel">
                <div class="drawer-heading">
                    <div>
                        <p class="eyebrow"><?= $editUser ? 'Edicion' : 'Nuevo acceso' ?></p>
                        <h2 id="user-drawer-title"><?= $editUser ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
                    </div>
                    <a class="drawer-close" href="users.php" aria-label="Cerrar">Cerrar</a>
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
                            <option value="driver" <?= ($editUser['role'] ?? 'driver') === 'driver' ? 'selected' : '' ?>>Chofer</option>
                        </select>
                    </label>
                    <label>
                        Chofer vinculado
                        <select name="driver_id">
                            <option value="">Sin vincular</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= (int) $driver['id'] ?>" <?= (int) ($editUser['driver_id'] ?? 0) === (int) $driver['id'] ? 'selected' : '' ?>>
                                    <?= e($driver['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" name="is_active" value="1" <?= (int) ($editUser['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        Usuario activo
                    </label>
                    <div class="drawer-actions">
                        <a class="button ghost" href="users.php">Cancelar</a>
                        <button class="button primary" type="submit">Guardar usuario</button>
                    </div>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
