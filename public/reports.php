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
$period = (string) ($_GET['period'] ?? 'week');
$period = in_array($period, ['day', 'week', 'month'], true) ? $period : 'week';
$selectedDate = valid_date_or_today($_GET['date'] ?? null);
$selectedWeek = valid_week_or_current($_GET['week'] ?? null);
$selectedMonth = valid_month_or_current($_GET['month'] ?? null);
$requestedDriverId = (int) ($_GET['driver_id'] ?? 0);
$requestedCarId = (int) ($_GET['car_id'] ?? 0);
$scope = scope_filters($requestedDriverId, $requestedCarId);
$driverId = $scope['driver_id'];
$carId = $scope['car_id'];

if ($period === 'day') {
    $range = ['start' => $selectedDate, 'end' => $selectedDate, 'label' => date_ar($selectedDate)];
} elseif ($period === 'week') {
    $bounds = iso_week_bounds($selectedWeek);
    $range = ['start' => $bounds['start'], 'end' => $bounds['end'], 'label' => date_ar($bounds['start']) . ' - ' . date_ar($bounds['end'])];
} else {
    $bounds = month_bounds($selectedMonth);
    $range = ['start' => $bounds['start'], 'end' => $bounds['end'], 'label' => month_name_ar($selectedMonth)];
}

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
$params = ['start' => $range['start'], 'end' => $range['end']];
append_entry_scope($where, $params, 'e', $driverId, $carId);

$dailyStatement = $pdo->prepare(
    'SELECT e.entry_date,
        SUM(e.gross_income) AS income,
        SUM(e.total_expenses) AS expenses,
        SUM(e.net_total) AS net,
        SUM(e.rental_amount) AS rental
    FROM daily_entries e
    WHERE ' . implode(' AND ', $where) . '
    GROUP BY e.entry_date
    ORDER BY e.entry_date ASC'
);
$dailyStatement->execute($params);
$days = $dailyStatement->fetchAll();

$totalIncome = 0.0;
$totalExpenses = 0.0;
$totalNet = 0.0;
$totalRental = 0.0;
foreach ($days as $day) {
    $totalIncome += (float) $day['income'];
    $totalExpenses += (float) $day['expenses'];
    $totalNet += (float) $day['net'];
    $totalRental += (float) $day['rental'];
}

$showFleetReport = is_admin() && $driverId === 0 && $carId === 0;
$fleetExpensesTotal = 0.0;
if ($showFleetReport) {
    $statement = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
        FROM general_expenses
        WHERE owner_user_id = :owner_user_id AND expense_date BETWEEN :start AND :end'
    );
    $statement->execute(['owner_user_id' => $ownerUserId, 'start' => $range['start'], 'end' => $range['end']]);
    $fleetExpensesTotal = (float) $statement->fetchColumn();
}
$fleetNetTotal = $totalRental - $fleetExpensesTotal;

$activeDays = count($days);
$averageDaily = $activeDays > 0 ? $totalNet / $activeDays : 0;
$bestDays = $days;
usort($bestDays, static fn (array $a, array $b): int => (float) $b['net'] <=> (float) $a['net']);
$bestDays = array_slice($bestDays, 0, 3);
$worstDays = $days;
usort($worstDays, static fn (array $a, array $b): int => (float) $a['net'] <=> (float) $b['net']);
$worstDays = array_slice($worstDays, 0, 3);

$comparisonWhere = ['1 = 1'];
$comparisonParams = [];
append_entry_scope($comparisonWhere, $comparisonParams, 'e', $driverId, $carId);

$weekStatement = $pdo->prepare(
    'SELECT e.year_number, e.week_number, MIN(e.entry_date) AS start_date, MAX(e.entry_date) AS end_date,
        SUM(e.gross_income) AS income, SUM(e.total_expenses) AS expenses, SUM(e.net_total) AS net
    FROM daily_entries e
    WHERE ' . implode(' AND ', $comparisonWhere) . '
    GROUP BY e.year_number, e.week_number
    ORDER BY start_date DESC
    LIMIT 8'
);
$weekStatement->execute($comparisonParams);
$weeklyComparison = $weekStatement->fetchAll();

$monthStatement = $pdo->prepare(
    'SELECT e.year_number, e.month_number,
        SUM(e.gross_income) AS income, SUM(e.total_expenses) AS expenses, SUM(e.net_total) AS net
    FROM daily_entries e
    WHERE ' . implode(' AND ', $comparisonWhere) . '
    GROUP BY e.year_number, e.month_number
    ORDER BY e.year_number DESC, e.month_number DESC
    LIMIT 8'
);
$monthStatement->execute($comparisonParams);
$monthlyComparison = $monthStatement->fetchAll();

$pageTitle = 'Reportes';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Reportes</p>
            <h1>Analisis de rendimiento</h1>
            <p>Totales, promedios y comparaciones para tomar decisiones sin planillas.</p>
        </div>
        <span class="summary-pill"><?= e($range['label']) ?></span>
    </div>

    <section class="filter-bar">
        <form method="get" class="filters report-filters">
            <label>
                Tipo
                <select name="period" data-filter-period>
                    <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Semana</option>
                    <option value="day" <?= $period === 'day' ? 'selected' : '' ?>>Dia</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Mes</option>
                </select>
            </label>
            <label <?= $period === 'day' ? '' : 'hidden' ?>>
                Dia
                <input type="date" name="date" value="<?= e($selectedDate) ?>" data-filter-day>
            </label>
            <label <?= $period === 'week' ? '' : 'hidden' ?>>
                Semana
                <input type="week" name="week" value="<?= e($selectedWeek) ?>" data-filter-week>
            </label>
            <label <?= $period === 'month' ? '' : 'hidden' ?>>
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
            <button class="button secondary" type="submit">Generar</button>
            <a class="button ghost" href="reports.php">Limpiar</a>
        </form>
    </section>

    <section class="metric-grid">
        <article class="metric-card gain"><span>Total ganancias</span><strong><?= money($totalIncome) ?></strong></article>
        <article class="metric-card expense"><span>Total descuentos</span><strong><?= money($totalExpenses) ?></strong></article>
        <article class="metric-card net"><span>Neto final</span><strong><?= money($totalNet) ?></strong></article>
        <article class="metric-card"><span>Promedio diario</span><strong><?= money($averageDaily) ?></strong></article>
        <?php if ($showFleetReport): ?>
            <article class="metric-card"><span>Alquileres flota</span><strong><?= money($totalRental) ?></strong></article>
            <article class="metric-card expense"><span>Gastos flota/empresa</span><strong><?= money($fleetExpensesTotal) ?></strong></article>
            <article class="metric-card net"><span>Neto flota/empresa</span><strong class="<?= $fleetNetTotal < 0 ? 'text-danger' : '' ?>"><?= money($fleetNetTotal) ?></strong></article>
        <?php endif; ?>
    </section>

    <section class="content-grid">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Detalle por dia</h2>
                <span><?= $activeDays ?> dias con carga</span>
            </div>
            <div class="table-wrap report-table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ganancias</th>
                        <th>Descuentos</th>
                        <th>Neto</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $day): ?>
                        <tr>
                            <td><?= date_ar($day['entry_date']) ?></td>
                            <td class="text-gain"><?= money($day['income']) ?></td>
                            <td class="text-expense"><?= money($day['expenses']) ?></td>
                            <td class="<?= (float) $day['net'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($day['net']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$days): ?>
                        <tr><td colspan="4" class="empty-cell">No hay datos para este reporte.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="panel">
            <div class="panel-heading">
                <h2>Mejores dias</h2>
            </div>
            <div class="timeline-list">
                <?php foreach ($bestDays as $day): ?>
                    <div class="timeline-row"><span><?= date_ar($day['entry_date']) ?></span><strong class="text-gain"><?= money($day['net']) ?></strong></div>
                <?php endforeach; ?>
                <?php if (!$bestDays): ?><p class="muted">Sin datos todavia.</p><?php endif; ?>
            </div>

            <div class="panel-heading panel-offset">
                <h2>Peores dias</h2>
            </div>
            <div class="timeline-list">
                <?php foreach ($worstDays as $day): ?>
                    <div class="timeline-row"><span><?= date_ar($day['entry_date']) ?></span><strong class="<?= (float) $day['net'] < 0 ? 'text-danger' : 'text-expense' ?>"><?= money($day['net']) ?></strong></div>
                <?php endforeach; ?>
                <?php if (!$worstDays): ?><p class="muted">Sin datos todavia.</p><?php endif; ?>
            </div>
        </aside>
    </section>

    <section class="content-grid content-offset">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Comparacion entre semanas</h2>
                <span>Ultimas 8 semanas</span>
            </div>
            <div class="table-wrap report-table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Periodo</th>
                        <th>Ganancias</th>
                        <th>Descuentos</th>
                        <th>Neto</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($weeklyComparison as $week): ?>
                        <tr>
                            <td><?= (int) $week['week_number'] ?>/<?= (int) $week['year_number'] ?></td>
                            <td><?= date_ar($week['start_date']) ?> - <?= date_ar($week['end_date']) ?></td>
                            <td class="text-gain"><?= money($week['income']) ?></td>
                            <td class="text-expense"><?= money($week['expenses']) ?></td>
                            <td class="<?= (float) $week['net'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($week['net']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$weeklyComparison): ?>
                        <tr><td colspan="5" class="empty-cell">Sin semanas para comparar.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <h2>Comparacion entre meses</h2>
                <span>Ultimos 8 meses</span>
            </div>
            <div class="summary-list">
                <?php foreach ($monthlyComparison as $month): ?>
                    <?php $monthValue = sprintf('%04d-%02d', (int) $month['year_number'], (int) $month['month_number']); ?>
                    <div class="summary-row">
                        <span><?= e(month_name_ar($monthValue)) ?></span>
                        <strong class="<?= (float) $month['net'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($month['net']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$monthlyComparison): ?><p class="muted">Sin meses para comparar.</p><?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
