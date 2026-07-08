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
$postReturnTo = (string) ($_POST['return_to'] ?? 'daily_entries.php');
if (!preg_match('/^daily_entries\.php(\?[A-Za-z0-9%_.=&+-]*)?$/', $postReturnTo)) {
    $postReturnTo = 'daily_entries.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete_entry' && $id > 0) {
        $statement = $pdo->prepare('SELECT driver_id, owner_user_id FROM daily_entries WHERE id = :id');
        $statement->execute(['id' => $id]);
        $entry = $statement->fetch();

        if (!$entry) {
            flash('error', 'No se encontro la carga.');
        } elseif (is_admin() && (int) ($entry['owner_user_id'] ?? 0) !== $ownerUserId) {
            flash('error', 'No podes eliminar cargas de otra cuenta.');
        } elseif (is_driver_user() && (int) $entry['driver_id'] !== (int) current_driver_id()) {
            flash('error', 'No podes eliminar cargas de otro chofer.');
        } else {
            $delete = $pdo->prepare('DELETE FROM daily_entries WHERE id = :id AND owner_user_id = :owner_user_id');
            $delete->execute(['id' => $id, 'owner_user_id' => $ownerUserId]);
            flash('success', 'Carga diaria eliminada.');
        }
    }

    if ($action === 'toggle_rental_status' && $id > 0) {
        require_admin();

        $status = (string) ($_POST['rental_billing_status'] ?? 'pending');
        $status = in_array($status, ['pending', 'invoiced'], true) ? $status : 'pending';

        $statement = $pdo->prepare('SELECT driver_id, owner_user_id FROM daily_entries WHERE id = :id');
        $statement->execute(['id' => $id]);
        $entry = $statement->fetch();

        if (!$entry) {
            flash('error', 'No se encontro la carga.');
        } elseif ((int) ($entry['owner_user_id'] ?? 0) !== $ownerUserId) {
            flash('error', 'No podes cambiar el estado de una carga de otra cuenta.');
        } elseif (is_driver_user() && (int) $entry['driver_id'] !== (int) current_driver_id()) {
            flash('error', 'No podes cambiar el estado de otro chofer.');
        } else {
            $statement = $pdo->prepare('UPDATE daily_entries SET rental_billing_status = :status, updated_by = :updated_by WHERE id = :id AND owner_user_id = :owner_user_id');
            $statement->execute([
                'status' => $status,
                'updated_by' => current_user()['id'],
                'id' => $id,
                'owner_user_id' => $ownerUserId,
            ]);
            flash('success', $status === 'invoiced' ? 'Alquiler marcado como facturado.' : 'Alquiler marcado como falta facturar.');
        }
    }

    if ($action === 'save_general_expense') {
        require_admin();

        $expenseDate = valid_date_or_today($_POST['expense_date'] ?? null);
        $amount = parse_decimal($_POST['amount'] ?? 0);
        $observations = trim((string) ($_POST['observations'] ?? ''));

        if ($amount <= 0) {
            flash('error', 'El importe del gasto debe ser mayor a cero.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO general_expenses (owner_user_id, expense_date, amount, observations, created_by, updated_by)
                VALUES (:owner_user_id, :expense_date, :amount, :observations, :created_by, :updated_by)'
            );
            $statement->execute([
                'owner_user_id' => $ownerUserId,
                'expense_date' => $expenseDate,
                'amount' => $amount,
                'observations' => $observations,
                'created_by' => current_user()['id'],
                'updated_by' => current_user()['id'],
            ]);
            flash('success', 'Gasto de flota cargado.');
        }
    }

    if ($action === 'delete_general_expense' && $id > 0) {
        require_admin();

        $statement = $pdo->prepare('DELETE FROM general_expenses WHERE id = :id AND owner_user_id = :owner_user_id');
        $statement->execute(['id' => $id, 'owner_user_id' => $ownerUserId]);
        flash('success', 'Gasto de flota eliminado.');
    }

    if ($action === 'save_weekly_money_note') {
        require_admin();

        $week = valid_week_or_current($_POST['week'] ?? null);
        $weekBounds = iso_week_bounds($week);
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($notes === '') {
            flash('error', 'La observacion semanal es obligatoria.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO weekly_money_notes (owner_user_id, week_start, week_end, notes, created_by, updated_by)
                VALUES (:owner_user_id, :week_start, :week_end, :notes, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    week_end = VALUES(week_end),
                    notes = VALUES(notes),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $statement->execute([
                'owner_user_id' => $ownerUserId,
                'week_start' => $weekBounds['start'],
                'week_end' => $weekBounds['end'],
                'notes' => $notes,
                'created_by' => current_user()['id'],
                'updated_by' => current_user()['id'],
            ]);
            flash('success', 'Observacion semanal guardada.');
        }
    }

    if ($action === 'delete_weekly_money_note') {
        require_admin();

        $week = valid_week_or_current($_POST['week'] ?? null);
        $weekBounds = iso_week_bounds($week);
        $statement = $pdo->prepare('DELETE FROM weekly_money_notes WHERE owner_user_id = :owner_user_id AND week_start = :week_start');
        $statement->execute(['owner_user_id' => $ownerUserId, 'week_start' => $weekBounds['start']]);
        flash('success', 'Observacion semanal eliminada.');
    }

    redirect($postReturnTo);
}

$selectedPeriod = (string) ($_GET['period'] ?? 'week');
$selectedPeriod = in_array($selectedPeriod, ['day', 'week', 'month'], true) ? $selectedPeriod : 'week';
$selectedDate = valid_date_or_today($_GET['date'] ?? null);
$selectedMonth = valid_month_or_current($_GET['month'] ?? null);
$selectedWeek = valid_week_or_current($_GET['week'] ?? null);
$rentalStatus = (string) ($_GET['rental_status'] ?? 'all');
$rentalStatus = in_array($rentalStatus, ['all', 'pending', 'invoiced'], true) ? $rentalStatus : 'all';
$requestedDriverId = (int) ($_GET['driver_id'] ?? 0);
$requestedCarId = (int) ($_GET['car_id'] ?? 0);
$scope = scope_filters($requestedDriverId, $requestedCarId);
$driverId = $scope['driver_id'];
$carId = $scope['car_id'];
$periodBounds = match ($selectedPeriod) {
    'day' => ['start' => $selectedDate, 'end' => $selectedDate],
    'week' => iso_week_bounds($selectedWeek),
    default => month_bounds($selectedMonth),
};
$periodLabel = match ($selectedPeriod) {
    'day' => date_ar($selectedDate),
    'week' => 'Semana ' . substr($selectedWeek, 6) . ', ' . substr($selectedWeek, 0, 4),
    default => month_name_ar($selectedMonth),
};
$filterQuery = [
    'period' => $selectedPeriod,
    'date' => $selectedDate,
    'week' => $selectedWeek,
    'month' => $selectedMonth,
    'driver_id' => $driverId,
    'car_id' => $carId,
    'rental_status' => $rentalStatus,
];
$filteredEntriesUrl = 'daily_entries.php?' . http_build_query($filterQuery);
$expenseDrawerUrl = 'daily_entries.php?' . http_build_query(array_merge($filterQuery, ['new_expense' => 1]));
$weeklyNoteDrawerUrl = 'daily_entries.php?' . http_build_query(array_merge($filterQuery, ['weekly_note' => 1]));
$expenseDrawerOpen = is_admin() && isset($_GET['new_expense']) && !isset($_GET['weekly_note']);
$weeklyNoteDrawerOpen = is_admin() && isset($_GET['weekly_note']) && $selectedPeriod === 'week';

if (is_admin()) {
    $statement = $pdo->prepare('SELECT id, full_name FROM drivers WHERE owner_user_id = :owner_user_id ORDER BY full_name ASC');
    $statement->execute(['owner_user_id' => $ownerUserId]);
    $drivers = $statement->fetchAll();

    $statement = $pdo->prepare("SELECT id, CONCAT(brand, ' ', model, ' - ', plate) AS label FROM cars WHERE owner_user_id = :owner_user_id ORDER BY brand ASC, model ASC");
    $statement->execute(['owner_user_id' => $ownerUserId]);
    $cars = $statement->fetchAll();
} elseif (current_driver_id()) {
    $statement = $pdo->prepare('SELECT id, full_name FROM drivers WHERE id = :id');
    $statement->execute(['id' => current_driver_id()]);
    $drivers = $statement->fetchAll();

    $statement = $pdo->prepare(
        "SELECT DISTINCT c.id, CONCAT(c.brand, ' ', c.model, ' - ', c.plate) AS label
        FROM cars c
        LEFT JOIN daily_entries e ON e.car_id = c.id
        WHERE c.current_driver_id = :driver_id OR e.driver_id = :entry_driver_id
        ORDER BY label ASC"
    );
    $statement->execute(['driver_id' => current_driver_id(), 'entry_driver_id' => current_driver_id()]);
    $cars = $statement->fetchAll();
} else {
    $drivers = [];
    $cars = [];
}

$where = ['e.entry_date BETWEEN :start AND :end'];
$params = ['start' => $periodBounds['start'], 'end' => $periodBounds['end']];
append_entry_scope($where, $params, 'e', $driverId, $carId);

if ($rentalStatus !== 'all') {
    $where[] = 'e.rental_billing_status = :rental_status';
    $params['rental_status'] = $rentalStatus;
}

$statement = $pdo->prepare(
    'SELECT e.*, d.full_name AS driver_name, CONCAT(c.brand, \' \', c.model, \' - \', c.plate) AS car_label
    FROM daily_entries e
    INNER JOIN drivers d ON d.id = e.driver_id
    INNER JOIN cars c ON c.id = e.car_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY e.entry_date DESC, d.full_name ASC, c.plate ASC'
);
$statement->execute($params);
$entries = $statement->fetchAll();

$totals = ['rental' => 0.0, 'rental_pending' => 0.0, 'rental_invoiced' => 0.0];
foreach ($entries as $entry) {
    $totals['rental'] += (float) $entry['rental_amount'];

    if (($entry['rental_billing_status'] ?? 'pending') === 'invoiced') {
        $totals['rental_invoiced'] += (float) $entry['rental_amount'];
    } else {
        $totals['rental_pending'] += (float) $entry['rental_amount'];
    }
}

$generalExpenses = [];
$generalExpensesTotal = 0.0;
$fleetNetTotal = 0.0;
$weeklyMoneyNote = null;
$showFleetSummary = is_admin() && $driverId === 0 && $carId === 0;
if (is_admin()) {
    $statement = $pdo->prepare(
        'SELECT ge.*, u.full_name AS user_name
        FROM general_expenses ge
        LEFT JOIN users u ON u.id = ge.created_by
        WHERE ge.owner_user_id = :owner_user_id AND ge.expense_date BETWEEN :start AND :end
        ORDER BY ge.expense_date DESC, ge.id DESC'
    );
    $statement->execute(['owner_user_id' => $ownerUserId, 'start' => $periodBounds['start'], 'end' => $periodBounds['end']]);
    $generalExpenses = $statement->fetchAll();

    foreach ($generalExpenses as $expense) {
        $generalExpensesTotal += (float) $expense['amount'];
    }

    if ($showFleetSummary) {
        $fleetNetTotal = $totals['rental'] - $generalExpensesTotal;
    }

    if ($selectedPeriod === 'week') {
        $statement = $pdo->prepare(
            'SELECT wmn.*, u.full_name AS updated_by_name
            FROM weekly_money_notes wmn
            LEFT JOIN users u ON u.id = wmn.updated_by
            WHERE wmn.owner_user_id = :owner_user_id AND wmn.week_start = :week_start
            LIMIT 1'
        );
        $statement->execute(['owner_user_id' => $ownerUserId, 'week_start' => $periodBounds['start']]);
        $weeklyMoneyNote = $statement->fetch() ?: null;
    }
}

$pageTitle = 'Panel';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Panel operativo</p>
            <h1>Panel de control diario</h1>
            <p>Vista rapida de cargas, alquileres, gastos de flota y control semanal.</p>
        </div>
        <div class="page-actions">
            <?php if (is_admin()): ?>
                <a class="button secondary" href="<?= e($expenseDrawerUrl) ?>">Nuevo gasto de flota</a>
                <?php if ($selectedPeriod === 'week'): ?>
                    <a class="button ghost" href="<?= e($weeklyNoteDrawerUrl) ?>"><?= $weeklyMoneyNote ? 'Editar observacion' : 'Observacion semanal' ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <a class="button primary" href="daily_entry_form.php">Nueva carga</a>
        </div>
    </div>

    <section class="filter-bar">
        <form method="get" class="filters compact">
            <label>
                Periodo
                <select name="period" data-filter-period>
                    <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : '' ?>>Semana</option>
                    <option value="day" <?= $selectedPeriod === 'day' ? 'selected' : '' ?>>Dia</option>
                    <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : '' ?>>Mes</option>
                </select>
            </label>
            <label <?= $selectedPeriod === 'day' ? '' : 'hidden' ?>>
                Dia
                <input type="date" name="date" value="<?= e($selectedDate) ?>" data-filter-day>
            </label>
            <label <?= $selectedPeriod === 'week' ? '' : 'hidden' ?>>
                Semana
                <input type="week" name="week" value="<?= e($selectedWeek) ?>" data-filter-week>
            </label>
            <label <?= $selectedPeriod === 'month' ? '' : 'hidden' ?>>
                Mes
                <input type="month" name="month" value="<?= e($selectedMonth) ?>" data-filter-month>
            </label>
            <?php if (is_admin()): ?>
                <label>
                    Chofer
                    <select name="driver_id">
                        <option value="0">Todos</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= (int) $driver['id'] ?>" <?= $driverId === (int) $driver['id'] ? 'selected' : '' ?>>
                                <?= e($driver['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>
                Auto
                <select name="car_id">
                    <option value="0">Todos</option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= (int) $car['id'] ?>" <?= $carId === (int) $car['id'] ? 'selected' : '' ?>>
                            <?= e($car['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Estado alquiler
                <select name="rental_status">
                    <option value="all" <?= $rentalStatus === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="pending" <?= $rentalStatus === 'pending' ? 'selected' : '' ?>>Falta facturar</option>
                    <option value="invoiced" <?= $rentalStatus === 'invoiced' ? 'selected' : '' ?>>Facturado</option>
                </select>
            </label>
            <button class="button secondary" type="submit">Filtrar</button>
            <a class="button ghost" href="daily_entries.php">Limpiar</a>
        </form>
    </section>

    <section class="metric-grid">
        <article class="metric-card"><span>Alquiler a facturar</span><strong><?= money($totals['rental']) ?></strong></article>
        <article class="metric-card expense"><span>Falta facturar</span><strong><?= money($totals['rental_pending']) ?></strong></article>
        <article class="metric-card gain"><span>Facturado</span><strong><?= money($totals['rental_invoiced']) ?></strong></article>
        <?php if ($showFleetSummary): ?>
            <article class="metric-card expense"><span>Gastos flota/empresa</span><strong><?= money($generalExpensesTotal) ?></strong></article>
            <article class="metric-card net"><span>Neto flota/empresa</span><strong class="<?= $fleetNetTotal < 0 ? 'text-danger' : '' ?>"><?= money($fleetNetTotal) ?></strong></article>
        <?php endif; ?>
    </section>

    <?php if (is_admin() && $selectedPeriod === 'week'): ?>
        <section class="panel weekly-note-panel">
            <div class="panel-heading">
                <div>
                    <h2>Control semanal del dinero</h2>
                    <p class="muted">Semana del <?= e(date_ar($periodBounds['start'])) ?> al <?= e(date_ar($periodBounds['end'])) ?></p>
                </div>
                <div class="panel-actions">
                    <a class="button ghost small" href="<?= e($weeklyNoteDrawerUrl) ?>"><?= $weeklyMoneyNote ? 'Editar' : 'Agregar observacion' ?></a>
                    <?php if ($weeklyMoneyNote): ?>
                        <span>Actualizado por <?= e($weeklyMoneyNote['updated_by_name'] ?: 'Sistema') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($weeklyMoneyNote): ?>
                <p class="weekly-note-text"><?= nl2br(e($weeklyMoneyNote['notes'])) ?></p>
            <?php else: ?>
                <p class="empty-cell">Todavia no hay observacion sobre que se hizo con la plata de esta semana.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-heading">
            <h2><?= e($periodLabel) ?></h2>
            <span><?= count($entries) ?> registros</span>
        </div>
        <div class="entry-list">
            <?php foreach ($entries as $entry): ?>
                <?php
                $rentalBillingStatus = $entry['rental_billing_status'] ?? 'pending';
                $rentalStatusLabel = $rentalBillingStatus === 'invoiced' ? 'Facturado' : 'Falta facturar';
                $nextRentalStatus = $rentalBillingStatus === 'invoiced' ? 'pending' : 'invoiced';
                ?>
                <article class="entry-card">
                    <div class="entry-main">
                        <div>
                            <strong><?= date_ar($entry['entry_date']) ?></strong>
                            <span><?= ($entry['period_type'] ?? 'day') === 'week' ? 'Carga semanal' : 'Carga diaria' ?> - Semana <?= (int) $entry['week_number'] ?></span>
                        </div>
                        <div>
                            <strong><?= e($entry['driver_name']) ?></strong>
                            <span><?= e($entry['car_label']) ?></span>
                        </div>
                    </div>

                    <div class="entry-money">
                        <div>
                            <span>Ganancias</span>
                            <strong class="text-gain"><?= money($entry['gross_income']) ?></strong>
                        </div>
                        <div>
                            <span>Descuentos</span>
                            <strong class="text-expense"><?= money($entry['total_expenses']) ?></strong>
                        </div>
                        <div>
                            <span>Neto chofer</span>
                            <strong class="<?= (float) $entry['net_total'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($entry['net_total']) ?></strong>
                        </div>
                    </div>

                    <div class="entry-expenses">
                        <span>App <strong><?= money($entry['app_expenses'] ?? 0) ?></strong></span>
                        <span>Efectivo <strong><?= money($entry['cash_collected'] ?? 0) ?></strong></span>
                        <span>A facturar <strong><?= money($entry['rental_amount'] ?? 0) ?></strong></span>
                        <span>Estado <strong class="<?= $rentalBillingStatus === 'invoiced' ? 'text-gain' : 'text-expense' ?>"><?= e($rentalStatusLabel) ?></strong></span>
                        <span>Combustible <strong><?= money($entry['fuel_cost']) ?></strong></span>
                        <span>Km <strong><?= number_format((float) ($entry['distance_km'] ?? 0), 2, ',', '.') ?></strong></span>
                    </div>

                    <div class="entry-actions">
                        <a class="button ghost small" href="daily_entry_form.php?id=<?= (int) $entry['id'] ?>">Editar</a>
                        <?php if (is_admin() && (float) ($entry['rental_amount'] ?? 0) > 0): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                                <input type="hidden" name="action" value="toggle_rental_status">
                                <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                                <input type="hidden" name="rental_billing_status" value="<?= e($nextRentalStatus) ?>">
                                <button class="button ghost small" type="submit"><?= $nextRentalStatus === 'invoiced' ? 'Marcar facturado' : 'Falta facturar' ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Eliminar esta carga diaria?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                            <input type="hidden" name="action" value="delete_entry">
                            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                            <button class="button ghost small danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$entries): ?>
                <p class="empty-cell">No hay cargas diarias con esos filtros.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (is_admin()): ?>
        <section class="panel-offset">
            <div class="panel wide">
                <div class="panel-heading">
                    <h2>Gastos de flota/empresa</h2>
                    <div class="panel-actions">
                        <a class="button ghost small" href="<?= e($expenseDrawerUrl) ?>">Nuevo gasto</a>
                        <span><?= count($generalExpenses) ?> registros</span>
                    </div>
                </div>
                <div class="entry-list">
                    <?php foreach ($generalExpenses as $expense): ?>
                        <article class="entry-card general-expense-card">
                            <div class="entry-main">
                                <div>
                                    <strong><?= date_ar($expense['expense_date']) ?></strong>
                                    <span><?= e($expense['user_name'] ?: 'Sistema') ?></span>
                                </div>
                                <div>
                                    <strong><?= money($expense['amount']) ?></strong>
                                    <span><?= e($expense['observations'] ?: 'Sin observacion') ?></span>
                                </div>
                            </div>
                            <div class="entry-actions">
                                <form method="post" data-confirm="Eliminar este gasto de flota?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                                    <input type="hidden" name="action" value="delete_general_expense">
                                    <input type="hidden" name="id" value="<?= (int) $expense['id'] ?>">
                                    <button class="button ghost small danger" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$generalExpenses): ?>
                        <p class="empty-cell">No hay gastos de flota/empresa en este periodo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($expenseDrawerOpen): ?>
            <div class="drawer-shell" role="dialog" aria-modal="true" aria-labelledby="expense-drawer-title">
                <a class="drawer-backdrop" href="<?= e($filteredEntriesUrl) ?>" aria-label="Cerrar formulario"></a>
                <aside class="drawer-panel">
                    <div class="drawer-heading">
                        <div>
                            <p class="eyebrow">Gasto de flota</p>
                            <h2 id="expense-drawer-title">Nuevo gasto de flota</h2>
                        </div>
                        <a class="drawer-close" href="<?= e($filteredEntriesUrl) ?>" aria-label="Cerrar">Cerrar</a>
                    </div>
                    <form method="post" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                        <input type="hidden" name="action" value="save_general_expense">
                        <label>
                            Fecha
                            <input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required>
                        </label>
                        <label>
                            Importe
                            <input type="number" step="0.01" name="amount" inputmode="decimal" required>
                        </label>
                        <label>
                            Observacion
                            <textarea name="observations" rows="4" placeholder="Ej: seguro, mantenimiento general, estacionamiento, compra menor"></textarea>
                        </label>
                        <button class="button primary full" type="submit">Guardar gasto</button>
                    </form>
                </aside>
            </div>
        <?php endif; ?>

        <?php if ($weeklyNoteDrawerOpen): ?>
            <div class="drawer-shell" role="dialog" aria-modal="true" aria-labelledby="weekly-note-drawer-title">
                <a class="drawer-backdrop" href="<?= e($filteredEntriesUrl) ?>" aria-label="Cerrar formulario"></a>
                <aside class="drawer-panel">
                    <div class="drawer-heading">
                        <div>
                            <p class="eyebrow">Control semanal</p>
                            <h2 id="weekly-note-drawer-title"><?= $weeklyMoneyNote ? 'Editar observacion' : 'Observacion semanal' ?></h2>
                        </div>
                        <a class="drawer-close" href="<?= e($filteredEntriesUrl) ?>" aria-label="Cerrar">Cerrar</a>
                    </div>
                    <form method="post" class="form-stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                        <input type="hidden" name="action" value="save_weekly_money_note">
                        <input type="hidden" name="week" value="<?= e($selectedWeek) ?>">
                        <label>
                            Semana
                            <input type="week" value="<?= e($selectedWeek) ?>" disabled>
                        </label>
                        <label>
                            Que se hizo con la plata
                            <textarea name="notes" rows="8" placeholder="Ej: se pago seguro, se separo efectivo para mantenimiento, quedo pendiente depositar alquileres..." required><?= e($weeklyMoneyNote['notes'] ?? '') ?></textarea>
                        </label>
                        <button class="button primary full" type="submit">Guardar observacion</button>
                    </form>
                    <?php if ($weeklyMoneyNote): ?>
                        <form method="post" data-confirm="Eliminar esta observacion semanal?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= e($filteredEntriesUrl) ?>">
                            <input type="hidden" name="action" value="delete_weekly_money_note">
                            <input type="hidden" name="week" value="<?= e($selectedWeek) ?>">
                            <button class="button ghost danger full" type="submit">Eliminar observacion</button>
                        </form>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
