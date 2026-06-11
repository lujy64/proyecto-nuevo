<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_login();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$activeDrivers = (int) $pdo->query("SELECT COUNT(*) FROM drivers WHERE status = 'active'")->fetchColumn();

$stats = $pdo->prepare(
    'SELECT
        COALESCE(SUM(gross_earnings), 0) AS gross,
        COALESCE(SUM(gross_earnings - cash_earnings), 0) AS virtual_earnings,
        COALESCE(SUM(fuel_cost), 0) AS fuel,
        COALESCE(SUM(rental_paid), 0) AS rental_paid,
        COALESCE(SUM(rental_unpaid), 0) AS rental_unpaid,
        COALESCE(SUM(gross_earnings - cash_earnings - rental_paid), 0) AS transfer_due,
        COALESCE(SUM(gross_earnings - fuel_cost - rental_amount), 0) AS driver_profit
    FROM settlements
    WHERE period_start BETWEEN :start AND :end'
);
$stats->execute(['start' => $monthStart, 'end' => $monthEnd]);
$monthStats = $stats->fetch() ?: [];

$expensesStatement = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN :start AND :end');
$expensesStatement->execute(['start' => $monthStart, 'end' => $monthEnd]);
$monthExpenses = (float) $expensesStatement->fetchColumn();
$ownerNet = (float) ($monthStats['rental_paid'] ?? 0) - $monthExpenses;

$recentStatement = $pdo->query(
    'SELECT s.*, d.name AS driver_name
    FROM settlements s
    INNER JOIN drivers d ON d.id = s.driver_id
    ORDER BY s.period_start DESC, s.id DESC
    LIMIT 8'
);
$recentSettlements = $recentStatement->fetchAll();

$driversStatement = $pdo->prepare(
    'SELECT d.name,
        COUNT(s.id) AS settlements_count,
        COALESCE(SUM(s.gross_earnings), 0) AS gross,
        COALESCE(SUM(s.rental_paid), 0) AS rental_paid,
        COALESCE(SUM(s.gross_earnings - s.fuel_cost - s.rental_amount), 0) AS driver_profit
    FROM drivers d
    LEFT JOIN settlements s ON s.driver_id = d.id AND s.period_start BETWEEN :start AND :end
    WHERE d.status = "active"
    GROUP BY d.id, d.name
    ORDER BY gross DESC, d.name ASC
    LIMIT 6'
);
$driversStatement->execute(['start' => $monthStart, 'end' => $monthEnd]);
$driverSummary = $driversStatement->fetchAll();

$pageTitle = 'Panel';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Resumen mensual</p>
            <h1>Panel de control</h1>
        </div>
        <?php if (is_admin()): ?>
            <a class="button primary" href="settlement_form.php">Nueva liquidacion</a>
        <?php endif; ?>
    </div>

    <section class="metric-grid">
        <article class="metric-card">
            <span>Choferes activos</span>
            <strong><?= $activeDrivers ?></strong>
        </article>
        <article class="metric-card">
            <span>Ganancia total</span>
            <strong><?= money($monthStats['gross'] ?? 0) ?></strong>
        </article>
        <article class="metric-card">
            <span>Alquiler cobrado</span>
            <strong><?= money($monthStats['rental_paid'] ?? 0) ?></strong>
        </article>
        <article class="metric-card">
            <span>Ganancia real propietario</span>
            <strong><?= money($ownerNet) ?></strong>
        </article>
        <article class="metric-card">
            <span>A transferir</span>
            <strong><?= money($monthStats['transfer_due'] ?? 0) ?></strong>
        </article>
        <article class="metric-card">
            <span>Alquiler pendiente</span>
            <strong><?= money($monthStats['rental_unpaid'] ?? 0) ?></strong>
        </article>
    </section>

    <section class="content-grid">
        <div class="panel">
            <div class="panel-heading">
                <h2>Ultimas liquidaciones</h2>
                <a href="settlements.php">Ver todas</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Chofer</th>
                        <th>Periodo</th>
                        <th>Total</th>
                        <th>Alquiler</th>
                        <th>A transferir</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentSettlements as $settlement): ?>
                        <?php $math = settlement_math($settlement); ?>
                        <tr>
                            <td><?= e($settlement['driver_name']) ?></td>
                            <td><?= date_ar($settlement['period_start']) ?> - <?= date_ar($settlement['period_end']) ?></td>
                            <td><?= money($settlement['gross_earnings']) ?></td>
                            <td><?= money($settlement['rental_paid']) ?></td>
                            <td class="<?= $math['transfer_due'] < 0 ? 'text-danger' : '' ?>"><?= money($math['transfer_due']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentSettlements): ?>
                        <tr><td colspan="5" class="empty-cell">Todavia no hay liquidaciones cargadas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <h2>Choferes del mes</h2>
                <a href="drivers.php">Administrar</a>
            </div>
            <div class="driver-list">
                <?php foreach ($driverSummary as $driver): ?>
                    <div class="driver-row">
                        <div>
                            <strong><?= e($driver['name']) ?></strong>
                            <span><?= (int) $driver['settlements_count'] ?> liquidaciones</span>
                        </div>
                        <div>
                            <strong><?= money($driver['rental_paid']) ?></strong>
                            <span><?= money($driver['driver_profit']) ?> chofer</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$driverSummary): ?>
                    <p class="muted">Carga choferes para ver el resumen mensual.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
