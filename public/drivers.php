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
        $statement = $pdo->prepare('UPDATE drivers SET status = :status WHERE id = :id AND owner_user_id = :owner_user_id');
        $statement->execute(['status' => $status, 'id' => $id, 'owner_user_id' => $ownerUserId]);
        flash('success', $status === 'active' ? 'Chofer activado.' : 'Chofer desactivado.');
        redirect('drivers.php');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'active');
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    $assignedCarId = ($_POST['assigned_car_id'] ?? '') === '' ? null : (int) $_POST['assigned_car_id'];
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($fullName === '') {
        flash('error', 'El nombre y apellido del chofer es obligatorio.');
        redirect('drivers.php');
    }

    try {
        if ($assignedCarId !== null) {
            $statement = $pdo->prepare("SELECT id FROM cars WHERE id = :id AND owner_user_id = :owner_user_id AND status = 'active'");
            $statement->execute(['id' => $assignedCarId, 'owner_user_id' => $ownerUserId]);

            if (!$statement->fetch()) {
                flash('error', 'El auto seleccionado no esta disponible.');
                redirect('drivers.php');
            }
        }

        $pdo->beginTransaction();

        if ($id > 0) {
            $statement = $pdo->prepare(
                'UPDATE drivers
                SET full_name = :full_name, phone = :phone, dni = :dni, status = :status, notes = :notes
                WHERE id = :id AND owner_user_id = :owner_user_id'
            );
            $statement->execute([
                'full_name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'dni' => $dni !== '' ? $dni : null,
                'status' => $status,
                'notes' => $notes,
                'id' => $id,
                'owner_user_id' => $ownerUserId,
            ]);
            $savedDriverId = $id;
            flash('success', 'Chofer actualizado.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO drivers (owner_user_id, full_name, phone, dni, status, notes)
                VALUES (:owner_user_id, :full_name, :phone, :dni, :status, :notes)'
            );
            $statement->execute([
                'owner_user_id' => $ownerUserId,
                'full_name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'dni' => $dni !== '' ? $dni : null,
                'status' => $status,
                'notes' => $notes,
            ]);
            $savedDriverId = (int) $pdo->lastInsertId();
            flash('success', 'Chofer creado.');
        }

        $statement = $pdo->prepare('UPDATE cars SET current_driver_id = NULL WHERE current_driver_id = :driver_id AND owner_user_id = :owner_user_id');
        $statement->execute(['driver_id' => $savedDriverId, 'owner_user_id' => $ownerUserId]);

        if ($assignedCarId !== null) {
            $statement = $pdo->prepare('UPDATE cars SET current_driver_id = :driver_id WHERE id = :car_id AND owner_user_id = :owner_user_id');
            $statement->execute([
                'driver_id' => $savedDriverId,
                'car_id' => $assignedCarId,
                'owner_user_id' => $ownerUserId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash('error', 'No se pudo guardar. Revisa si el DNI ya existe.');
    }

    redirect('drivers.php');
}

$editDriver = null;
if (is_admin() && isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM drivers WHERE id = :id AND owner_user_id = :owner_user_id');
    $statement->execute(['id' => (int) $_GET['edit'], 'owner_user_id' => $ownerUserId]);
    $editDriver = $statement->fetch() ?: null;

    if (!$editDriver) {
        flash('error', 'No se encontro el chofer solicitado.');
        redirect('drivers.php');
    }
}
$driverDrawerOpen = is_admin() && (isset($_GET['new']) || $editDriver !== null);
$assignableCars = [];
$editDriverAssignedCarId = null;

if (is_admin()) {
    $statement = $pdo->prepare(
        'SELECT c.id, c.brand, c.model, c.plate, c.current_driver_id, d.full_name AS driver_name
        FROM cars c
        LEFT JOIN drivers d ON d.id = c.current_driver_id AND d.owner_user_id = c.owner_user_id
        WHERE c.status = \'active\' AND c.owner_user_id = :owner_user_id
        ORDER BY c.brand ASC, c.model ASC, c.plate ASC'
    );
    $statement->execute(['owner_user_id' => $ownerUserId]);
    $assignableCars = $statement->fetchAll();

    if ($editDriver) {
        foreach ($assignableCars as $car) {
            if ((int) ($car['current_driver_id'] ?? 0) === (int) $editDriver['id']) {
                $editDriverAssignedCarId = (int) $car['id'];
                break;
            }
        }
    }
}

$billingPeriod = (string) ($_GET['billing_period'] ?? 'week');
$billingPeriod = in_array($billingPeriod, ['day', 'week', 'month'], true) ? $billingPeriod : 'week';
$billingDate = valid_date_or_today($_GET['billing_date'] ?? null);
$billingMonth = valid_month_or_current($_GET['billing_month'] ?? null);
$billingWeek = valid_week_or_current($_GET['billing_week'] ?? null);
$billingStatus = (string) ($_GET['billing_status'] ?? 'pending');
$billingStatus = in_array($billingStatus, ['all', 'pending', 'invoiced'], true) ? $billingStatus : 'pending';
$billingBounds = match ($billingPeriod) {
    'day' => ['start' => $billingDate, 'end' => $billingDate],
    'week' => iso_week_bounds($billingWeek),
    default => month_bounds($billingMonth),
};
$billingLabel = match ($billingPeriod) {
    'day' => date_ar($billingDate),
    'week' => 'Semana ' . substr($billingWeek, 6) . ', ' . substr($billingWeek, 0, 4),
    default => month_name_ar($billingMonth),
};
$billingStatusCondition = match ($billingStatus) {
    'pending' => "rental_billing_status = 'pending'",
    'invoiced' => "rental_billing_status = 'invoiced'",
    default => '1 = 1',
};

$where = [];
$params = [];
if (is_admin()) {
    append_owner_scope($where, $params, 'd');
} elseif (is_driver_user()) {
    $driverId = current_driver_id() ?? -1;
    if ($driverId < 0) {
        $where[] = '1 = 0';
    } else {
        $where[] = 'd.id = :driver_id';
        $params['driver_id'] = $driverId;
    }
}

$sql = 'SELECT d.*,
        COALESCE(stats.entries_count, 0) AS entries_count,
        COALESCE(stats.income_total, 0) AS income_total,
        COALESCE(stats.expenses_total, 0) AS expenses_total,
        COALESCE(stats.net_total, 0) AS net_total,
        stats.last_entry_date,
        COALESCE(cars.cars_count, 0) AS cars_count,
        cars.car_labels,
        COALESCE(billing.rental_selected_total, 0) AS rental_selected_total,
        COALESCE(billing.rental_pending_total, 0) AS rental_pending_total,
        COALESCE(billing.rental_invoiced_total, 0) AS rental_invoiced_total,
        COALESCE(billing.rental_total, 0) AS rental_total
    FROM drivers d
    LEFT JOIN (
        SELECT driver_id,
            COUNT(id) AS entries_count,
            SUM(gross_income) AS income_total,
            SUM(total_expenses) AS expenses_total,
            SUM(net_total) AS net_total,
            MAX(entry_date) AS last_entry_date
        FROM daily_entries
        GROUP BY driver_id
    ) stats ON stats.driver_id = d.id
    LEFT JOIN (
        SELECT current_driver_id,
            COUNT(id) AS cars_count,
            GROUP_CONCAT(CONCAT(brand, \' \', model, \' - \', plate) ORDER BY brand ASC, model ASC, plate ASC SEPARATOR \', \') AS car_labels
        FROM cars
        WHERE status = \'active\' AND current_driver_id IS NOT NULL
        GROUP BY current_driver_id
    ) cars ON cars.current_driver_id = d.id
    LEFT JOIN (
        SELECT driver_id,
            SUM(CASE WHEN ' . $billingStatusCondition . ' THEN rental_amount ELSE 0 END) AS rental_selected_total,
            SUM(CASE WHEN rental_billing_status = \'pending\' THEN rental_amount ELSE 0 END) AS rental_pending_total,
            SUM(CASE WHEN rental_billing_status = \'invoiced\' THEN rental_amount ELSE 0 END) AS rental_invoiced_total,
            SUM(rental_amount) AS rental_total
        FROM daily_entries
        WHERE entry_date BETWEEN :billing_start AND :billing_end
        GROUP BY driver_id
    ) billing ON billing.driver_id = d.id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY d.status ASC, d.full_name ASC';
$statement = $pdo->prepare($sql);
$params['billing_start'] = $billingBounds['start'];
$params['billing_end'] = $billingBounds['end'];
$statement->execute($params);
$drivers = $statement->fetchAll();

$billingTotals = ['selected' => 0.0, 'pending' => 0.0, 'invoiced' => 0.0, 'total' => 0.0];
foreach ($drivers as $driver) {
    $billingTotals['selected'] += (float) $driver['rental_selected_total'];
    $billingTotals['pending'] += (float) $driver['rental_pending_total'];
    $billingTotals['invoiced'] += (float) $driver['rental_invoiced_total'];
    $billingTotals['total'] += (float) $driver['rental_total'];
}

$pageTitle = 'Choferes';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Gestion de choferes</p>
            <h1>Choferes</h1>
            <p>Altas, edicion, estado, auto asignado y alquileres a facturar.</p>
        </div>
        <?php if (is_admin()): ?>
            <a class="button primary" href="drivers.php?new=1">Nuevo chofer</a>
        <?php else: ?>
            <span class="role-badge">Vista chofer</span>
        <?php endif; ?>
    </div>

    <section class="filter-bar">
        <form method="get" class="filters compact">
            <label>
                Periodo
                <select name="billing_period" data-filter-period>
                    <option value="week" <?= $billingPeriod === 'week' ? 'selected' : '' ?>>Semana</option>
                    <option value="day" <?= $billingPeriod === 'day' ? 'selected' : '' ?>>Dia</option>
                    <option value="month" <?= $billingPeriod === 'month' ? 'selected' : '' ?>>Mes</option>
                </select>
            </label>
            <label <?= $billingPeriod === 'day' ? '' : 'hidden' ?>>
                Dia
                <input type="date" name="billing_date" value="<?= e($billingDate) ?>" data-filter-day>
            </label>
            <label <?= $billingPeriod === 'week' ? '' : 'hidden' ?>>
                Semana
                <input type="week" name="billing_week" value="<?= e($billingWeek) ?>" data-filter-week>
            </label>
            <label <?= $billingPeriod === 'month' ? '' : 'hidden' ?>>
                Mes
                <input type="month" name="billing_month" value="<?= e($billingMonth) ?>" data-filter-month>
            </label>
            <label>
                Estado alquiler
                <select name="billing_status">
                    <option value="all" <?= $billingStatus === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="pending" <?= $billingStatus === 'pending' ? 'selected' : '' ?>>Falta facturar</option>
                    <option value="invoiced" <?= $billingStatus === 'invoiced' ? 'selected' : '' ?>>Facturado</option>
                </select>
            </label>
            <button class="button secondary" type="submit">Filtrar</button>
            <a class="button ghost" href="drivers.php">Limpiar</a>
        </form>
    </section>

    <section class="metric-grid">
        <article class="metric-card net"><span>Alquiler a facturar</span><strong><?= money($billingTotals['selected']) ?></strong></article>
        <article class="metric-card expense"><span>Falta facturar</span><strong><?= money($billingTotals['pending']) ?></strong></article>
        <article class="metric-card gain"><span>Facturado</span><strong><?= money($billingTotals['invoiced']) ?></strong></article>
        <article class="metric-card"><span><?= e($billingLabel) ?></span><strong><?= money($billingTotals['total']) ?></strong></article>
    </section>

    <section class="management-list">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Listado</h2>
                <span><?= count($drivers) ?> choferes</span>
            </div>
            <div class="driver-card-list">
                <?php foreach ($drivers as $driver): ?>
                    <?php
                    $driverEntriesUrl = 'daily_entries.php?' . http_build_query([
                        'period' => $billingPeriod,
                        'date' => $billingDate,
                        'month' => $billingMonth,
                        'week' => $billingWeek,
                        'driver_id' => (int) $driver['id'],
                        'rental_status' => $billingStatus,
                    ]);
                    ?>
                    <article class="driver-card">
                        <div class="driver-profile">
                            <div class="driver-title">
                                <strong><?= e($driver['full_name']) ?></strong>
                                <span class="status <?= e($driver['status']) ?>"><?= $driver['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span>
                            </div>
                            <?php if ($driver['notes']): ?>
                                <p><?= e($driver['notes']) ?></p>
                            <?php endif; ?>
                            <dl class="driver-meta">
                                <div>
                                    <dt>Telefono</dt>
                                    <dd><?= e($driver['phone'] ?: '-') ?></dd>
                                </div>
                                <div>
                                    <dt>DNI</dt>
                                    <dd><?= e($driver['dni'] ?: '-') ?></dd>
                                </div>
                                <div>
                                    <dt>Auto asignado</dt>
                                    <dd><?= e($driver['car_labels'] ?: '-') ?></dd>
                                </div>
                                <div>
                                    <dt>Ultima carga</dt>
                                    <dd><?= date_ar($driver['last_entry_date']) ?></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="driver-finance-grid">
                            <div><span>Cargas</span><strong><?= (int) $driver['entries_count'] ?></strong></div>
                            <div><span>Ganancias</span><strong class="text-gain"><?= money($driver['income_total']) ?></strong></div>
                            <div><span>Descuentos</span><strong class="text-expense"><?= money($driver['expenses_total']) ?></strong></div>
                            <div><span>Neto chofer</span><strong class="<?= (float) $driver['net_total'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($driver['net_total']) ?></strong></div>
                            <div><span>A facturar</span><strong><?= money($driver['rental_selected_total']) ?></strong></div>
                            <div><span>Falta facturar</span><strong class="text-expense"><?= money($driver['rental_pending_total']) ?></strong></div>
                            <div><span>Facturado</span><strong class="text-gain"><?= money($driver['rental_invoiced_total']) ?></strong></div>
                        </div>

                        <div class="driver-card-actions">
                            <a class="button ghost small" href="<?= e($driverEntriesUrl) ?>">Cargas</a>
                            <a class="button ghost small" href="reports.php?driver_id=<?= (int) $driver['id'] ?>">Historial</a>
                            <?php if (is_admin()): ?>
                                <a class="button ghost small" href="drivers.php?edit=<?= (int) $driver['id'] ?>">Editar</a>
                                <form method="post" data-confirm="<?= $driver['status'] === 'active' ? 'Desactivar este chofer?' : 'Activar este chofer?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $driver['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $driver['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button class="button ghost small danger" type="submit"><?= $driver['status'] === 'active' ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$drivers): ?>
                    <p class="empty-cell">Carga tu primer chofer para empezar.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($driverDrawerOpen): ?>
        <div class="drawer-shell" role="dialog" aria-modal="true" aria-labelledby="driver-drawer-title">
            <a class="drawer-backdrop" href="drivers.php" aria-label="Cerrar formulario"></a>
            <aside class="drawer-panel">
                <div class="drawer-heading">
                    <div>
                        <p class="eyebrow"><?= $editDriver ? 'Edicion' : 'Nuevo registro' ?></p>
                        <h2 id="driver-drawer-title"><?= $editDriver ? 'Editar chofer' : 'Nuevo chofer' ?></h2>
                    </div>
                    <a class="drawer-close" href="drivers.php" aria-label="Cerrar">Cerrar</a>
                </div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editDriver['id'] ?? 0) ?>">
                    <label>
                        Nombre y apellido
                        <input type="text" name="full_name" value="<?= e($editDriver['full_name'] ?? '') ?>" required>
                    </label>
                    <label>
                        Telefono
                        <input type="text" name="phone" value="<?= e($editDriver['phone'] ?? '') ?>">
                    </label>
                    <label>
                        DNI
                        <input type="text" name="dni" value="<?= e($editDriver['dni'] ?? '') ?>">
                    </label>
                    <label>
                        Auto asignado
                        <select name="assigned_car_id">
                            <option value="">Sin auto asignado</option>
                            <?php foreach ($assignableCars as $car): ?>
                                <?php
                                $isSelected = $editDriverAssignedCarId === (int) $car['id'];
                                $carLabel = trim($car['brand'] . ' ' . $car['model']) . ' - ' . $car['plate'];
                                if (!$isSelected && !empty($car['driver_name'])) {
                                    $carLabel .= ' (asignado a ' . $car['driver_name'] . ')';
                                }
                                ?>
                                <option value="<?= (int) $car['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                    <?= e($carLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Estado
                        <select name="status">
                            <option value="active" <?= ($editDriver['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= ($editDriver['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </label>
                    <label>
                        Observaciones
                        <textarea name="notes" rows="4"><?= e($editDriver['notes'] ?? '') ?></textarea>
                    </label>
                    <div class="drawer-actions">
                        <a class="button ghost" href="drivers.php">Cancelar</a>
                        <button class="button primary" type="submit">Guardar chofer</button>
                    </div>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
