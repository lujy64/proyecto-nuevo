<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_login();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf();

    if (($_POST['action'] ?? '') === 'delete') {
        $statement = $pdo->prepare('DELETE FROM settlements WHERE id = :id');
        $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        flash('success', 'Liquidacion eliminada.');
    }

    redirect('settlements.php');
}

$drivers = $pdo->query("SELECT id, name FROM drivers ORDER BY name ASC")->fetchAll();
$month = (string) ($_GET['month'] ?? date('Y-m'));
$driverId = (int) ($_GET['driver_id'] ?? 0);
$where = [];
$params = [];

if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
    $where[] = 's.period_start BETWEEN :start AND :end';
    $params['start'] = "{$month}-01";
    $params['end'] = date('Y-m-t', strtotime("{$month}-01"));
}

if ($driverId > 0) {
    $where[] = 's.driver_id = :driver_id';
    $params['driver_id'] = $driverId;
}

$sql = 'SELECT s.*, d.name AS driver_name
    FROM settlements s
    INNER JOIN drivers d ON d.id = s.driver_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY s.period_start DESC, d.name ASC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$settlements = $statement->fetchAll();

$totals = [
    'gross' => 0,
    'cash' => 0,
    'virtual' => 0,
    'fuel' => 0,
    'rental_paid' => 0,
    'rental_unpaid' => 0,
    'transfer_due' => 0,
    'driver_profit' => 0,
];

foreach ($settlements as $settlement) {
    $math = settlement_math($settlement);
    $totals['gross'] += (float) $settlement['gross_earnings'];
    $totals['cash'] += (float) $settlement['cash_earnings'];
    $totals['virtual'] += $math['virtual_earnings'];
    $totals['fuel'] += (float) $settlement['fuel_cost'];
    $totals['rental_paid'] += (float) $settlement['rental_paid'];
    $totals['rental_unpaid'] += (float) $settlement['rental_unpaid'];
    $totals['transfer_due'] += $math['transfer_due'];
    $totals['driver_profit'] += $math['driver_profit'];
}

$pageTitle = 'Liquidaciones';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Semanas de trabajo</p>
            <h1>Liquidaciones</h1>
        </div>
        <?php if (is_admin()): ?>
            <a class="button primary" href="settlement_form.php">Nueva liquidacion</a>
        <?php endif; ?>
    </div>

    <section class="filter-bar">
        <form method="get" class="filters">
            <label>
                Mes
                <input type="month" name="month" value="<?= e($month) ?>">
            </label>
            <label>
                Chofer
                <select name="driver_id">
                    <option value="0">Todos</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= (int) $driver['id'] ?>" <?= $driverId === (int) $driver['id'] ? 'selected' : '' ?>>
                            <?= e($driver['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button secondary" type="submit">Filtrar</button>
            <a class="button ghost" href="settlements.php">Limpiar</a>
        </form>
    </section>

    <section class="metric-grid compact">
        <article class="metric-card"><span>Total</span><strong><?= money($totals['gross']) ?></strong></article>
        <article class="metric-card"><span>Virtual</span><strong><?= money($totals['virtual']) ?></strong></article>
        <article class="metric-card"><span>Combustible</span><strong><?= money($totals['fuel']) ?></strong></article>
        <article class="metric-card"><span>Alquiler cobrado</span><strong><?= money($totals['rental_paid']) ?></strong></article>
        <article class="metric-card"><span>A transferir</span><strong><?= money($totals['transfer_due']) ?></strong></article>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Registros</h2>
            <span><?= count($settlements) ?> resultados</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Chofer</th>
                    <th>Periodo</th>
                    <th>Kms</th>
                    <th>Total</th>
                    <th>Efectivo</th>
                    <th>Virtual</th>
                    <th>Combustible</th>
                    <th>Alquiler</th>
                    <th>Pendiente</th>
                    <th>Chofer</th>
                    <th>A transferir</th>
                    <?php if (is_admin()): ?><th></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($settlements as $settlement): ?>
                    <?php $math = settlement_math($settlement); ?>
                    <tr>
                        <td>
                            <strong><?= e($settlement['driver_name']) ?></strong>
                            <?php if ($settlement['notes']): ?><small><?= e($settlement['notes']) ?></small><?php endif; ?>
                        </td>
                        <td><?= date_ar($settlement['period_start']) ?> - <?= date_ar($settlement['period_end']) ?></td>
                        <td><?= number_ar($settlement['kilometers']) ?></td>
                        <td><?= money($settlement['gross_earnings']) ?></td>
                        <td><?= money($settlement['cash_earnings']) ?></td>
                        <td><?= money($math['virtual_earnings']) ?></td>
                        <td><?= money($settlement['fuel_cost']) ?></td>
                        <td><?= money($settlement['rental_paid']) ?></td>
                        <td><?= money($settlement['rental_unpaid']) ?></td>
                        <td><?= money($math['driver_profit']) ?></td>
                        <td class="<?= $math['transfer_due'] < 0 ? 'text-danger' : '' ?>"><?= money($math['transfer_due']) ?></td>
                        <?php if (is_admin()): ?>
                            <td class="row-actions">
                                <a class="button ghost small" href="settlement_form.php?id=<?= (int) $settlement['id'] ?>">Editar</a>
                                <form method="post" data-confirm="Eliminar esta liquidacion?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $settlement['id'] ?>">
                                    <button class="button ghost small danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$settlements): ?>
                    <tr><td colspan="<?= is_admin() ? 12 : 11 ?>" class="empty-cell">No hay liquidaciones con esos filtros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
