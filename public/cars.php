<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_login();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

$ownerUserId = current_owner_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf();

    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id > 0) {
        $status = (string) ($_POST['status'] ?? 'inactive');
        $status = $status === 'active' ? 'active' : 'inactive';
        $statement = $pdo->prepare('UPDATE cars SET status = :status WHERE id = :id AND owner_user_id = :owner_user_id');
        $statement->execute(['status' => $status, 'id' => $id, 'owner_user_id' => $ownerUserId]);
        flash('success', $status === 'active' ? 'Auto activado.' : 'Auto desactivado.');
        redirect('cars.php');
    }

    $brand = trim((string) ($_POST['brand'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? ''));
    $plate = strtoupper(trim((string) ($_POST['plate'] ?? '')));
    $year = trim((string) ($_POST['year'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'active');
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($brand === '' || $model === '' || $plate === '') {
        flash('error', 'Marca, modelo y patente son obligatorios.');
        redirect('cars.php');
    }

    try {
        if ($id > 0) {
            $statement = $pdo->prepare(
                'UPDATE cars
                SET brand = :brand, model = :model, plate = :plate, year = :year,
                    status = :status, notes = :notes
                WHERE id = :id AND owner_user_id = :owner_user_id'
            );
            $statement->execute([
                'brand' => $brand,
                'model' => $model,
                'plate' => $plate,
                'year' => $year !== '' ? (int) $year : null,
                'status' => $status,
                'notes' => $notes,
                'id' => $id,
                'owner_user_id' => $ownerUserId,
            ]);
            flash('success', 'Auto actualizado.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO cars (owner_user_id, brand, model, plate, year, status, notes)
                VALUES (:owner_user_id, :brand, :model, :plate, :year, :status, :notes)'
            );
            $statement->execute([
                'owner_user_id' => $ownerUserId,
                'brand' => $brand,
                'model' => $model,
                'plate' => $plate,
                'year' => $year !== '' ? (int) $year : null,
                'status' => $status,
                'notes' => $notes,
            ]);
            flash('success', 'Auto creado.');
        }
    } catch (PDOException $exception) {
        flash('error', 'No se pudo guardar. Revisa si la patente ya existe.');
    }

    redirect('cars.php');
}

$editCar = null;
if (is_admin() && isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM cars WHERE id = :id AND owner_user_id = :owner_user_id');
    $statement->execute(['id' => (int) $_GET['edit'], 'owner_user_id' => $ownerUserId]);
    $editCar = $statement->fetch() ?: null;

    if (!$editCar) {
        flash('error', 'No se encontro el auto solicitado.');
        redirect('cars.php');
    }
}
$carDrawerOpen = is_admin() && (isset($_GET['new']) || $editCar !== null);

$where = [];
$params = [];
if (is_admin()) {
    append_owner_scope($where, $params, 'c');
} elseif (is_driver_user()) {
    $driverId = current_driver_id() ?? -1;
    if ($driverId < 0) {
        $where[] = '1 = 0';
    } else {
        $where[] = 'c.current_driver_id = :driver_id';
        $params['driver_id'] = $driverId;
    }
}

$sql = 'SELECT c.*, d.full_name AS driver_name,
        COALESCE(stats.entries_count, 0) AS entries_count,
        COALESCE(stats.income_total, 0) AS income_total,
        COALESCE(stats.expenses_total, 0) AS expenses_total,
        COALESCE(stats.net_total, 0) AS net_total
    FROM cars c
    LEFT JOIN drivers d ON d.id = c.current_driver_id AND d.owner_user_id = c.owner_user_id
    LEFT JOIN (
        SELECT car_id,
            COUNT(id) AS entries_count,
            SUM(gross_income) AS income_total,
            SUM(total_expenses) AS expenses_total,
            SUM(net_total) AS net_total
        FROM daily_entries
        GROUP BY car_id
    ) stats ON stats.car_id = c.id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY c.status ASC, c.brand ASC, c.model ASC, c.plate ASC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$cars = $statement->fetchAll();

$pageTitle = 'Autos';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Gestion de autos</p>
            <h1>Autos</h1>
            <p>Vehiculos activos y rendimiento acumulado. La asignacion se gestiona desde Choferes.</p>
        </div>
        <?php if (is_admin()): ?>
            <a class="button primary" href="cars.php?new=1">Nuevo auto</a>
        <?php else: ?>
            <span class="role-badge">Autos asignados</span>
        <?php endif; ?>
    </div>

    <section class="management-list">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Listado</h2>
                <span><?= count($cars) ?> autos</span>
            </div>
            <div class="driver-card-list vehicle-card-list">
                <?php foreach ($cars as $car): ?>
                    <article class="driver-card vehicle-card">
                        <div class="driver-profile vehicle-profile">
                            <div class="driver-title vehicle-title">
                                <strong><?= e($car['brand'] . ' ' . $car['model']) ?></strong>
                                <span class="status <?= e($car['status']) ?>"><?= $car['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span>
                            </div>
                            <?php if ($car['notes']): ?>
                                <p><?= e($car['notes']) ?></p>
                            <?php endif; ?>
                            <dl class="driver-meta vehicle-meta">
                                <div>
                                    <dt>Patente</dt>
                                    <dd><?= e($car['plate']) ?></dd>
                                </div>
                                <div>
                                    <dt>A&ntilde;o</dt>
                                    <dd><?= e($car['year'] ?: '-') ?></dd>
                                </div>
                                <div>
                                    <dt>Chofer asignado</dt>
                                    <dd><?= e($car['driver_name'] ?: 'Sin asignar') ?></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="driver-finance-grid vehicle-finance-grid">
                            <div><span>Cargas</span><strong><?= (int) $car['entries_count'] ?></strong></div>
                            <div><span>Ganancias</span><strong class="text-gain"><?= money($car['income_total']) ?></strong></div>
                            <div><span>Descuentos</span><strong class="text-expense"><?= money($car['expenses_total']) ?></strong></div>
                            <div><span>Neto</span><strong class="<?= (float) $car['net_total'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($car['net_total']) ?></strong></div>
                        </div>

                        <div class="driver-card-actions vehicle-card-actions">
                            <a class="button ghost small" href="reports.php?car_id=<?= (int) $car['id'] ?>">Reporte</a>
                            <?php if (is_admin()): ?>
                                <a class="button ghost small" href="cars.php?edit=<?= (int) $car['id'] ?>">Editar</a>
                                <form method="post" data-confirm="<?= $car['status'] === 'active' ? 'Desactivar este auto?' : 'Activar este auto?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $car['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $car['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button class="button ghost small danger" type="submit"><?= $car['status'] === 'active' ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$cars): ?>
                    <p class="empty-cell">Carga tu primer auto para empezar.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($carDrawerOpen): ?>
        <div class="drawer-shell" role="dialog" aria-modal="true" aria-labelledby="car-drawer-title">
            <a class="drawer-backdrop" href="cars.php" aria-label="Cerrar formulario"></a>
            <aside class="drawer-panel">
                <div class="drawer-heading">
                    <div>
                        <p class="eyebrow"><?= $editCar ? 'Edicion' : 'Nuevo registro' ?></p>
                        <h2 id="car-drawer-title"><?= $editCar ? 'Editar auto' : 'Nuevo auto' ?></h2>
                    </div>
                    <a class="drawer-close" href="cars.php" aria-label="Cerrar">Cerrar</a>
                </div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editCar['id'] ?? 0) ?>">
                    <label>
                        Marca
                        <input type="text" name="brand" value="<?= e($editCar['brand'] ?? '') ?>" required>
                    </label>
                    <label>
                        Modelo
                        <input type="text" name="model" value="<?= e($editCar['model'] ?? '') ?>" required>
                    </label>
                    <label>
                        Patente
                        <input type="text" name="plate" value="<?= e($editCar['plate'] ?? '') ?>" required>
                    </label>
                    <label>
                        A&ntilde;o
                        <input type="number" name="year" min="1980" max="2100" value="<?= e($editCar['year'] ?? '') ?>">
                    </label>
                    <label>
                        Estado
                        <select name="status">
                            <option value="active" <?= ($editCar['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= ($editCar['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </label>
                    <label>
                        Observaciones
                        <textarea name="notes" rows="4"><?= e($editCar['notes'] ?? '') ?></textarea>
                    </label>
                    <div class="drawer-actions">
                        <a class="button ghost" href="cars.php">Cancelar</a>
                        <button class="button primary" type="submit">Guardar auto</button>
                    </div>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
