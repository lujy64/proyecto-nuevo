<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    render_database_error($exception);
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$settlement = null;

if ($id > 0) {
    $statement = $pdo->prepare('SELECT * FROM settlements WHERE id = :id');
    $statement->execute(['id' => $id]);
    $settlement = $statement->fetch();

    if (!$settlement) {
        flash('error', 'No se encontro la liquidacion.');
        redirect('settlements.php');
    }
}

$driverSql = "SELECT id, name FROM drivers WHERE status = 'active'";
$driverParams = [];

if ($settlement) {
    $driverSql .= ' OR id = :current_driver_id';
    $driverParams['current_driver_id'] = $settlement['driver_id'];
}

$driverSql .= ' ORDER BY name ASC';
$driverStatement = $pdo->prepare($driverSql);
$driverStatement->execute($driverParams);
$drivers = $driverStatement->fetchAll();
$errors = [];
$values = $settlement ?: [
    'driver_id' => '',
    'period_start' => date('Y-m-d'),
    'period_end' => date('Y-m-d'),
    'kilometers' => '',
    'gross_earnings' => '',
    'cash_earnings' => '',
    'fuel_cost' => '',
    'rental_amount' => '',
    'rental_unpaid' => '',
    'rental_paid' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values = [
        'driver_id' => (int) ($_POST['driver_id'] ?? 0),
        'period_start' => (string) ($_POST['period_start'] ?? ''),
        'period_end' => (string) ($_POST['period_end'] ?? ''),
        'kilometers' => parse_decimal($_POST['kilometers'] ?? 0),
        'gross_earnings' => parse_decimal($_POST['gross_earnings'] ?? 0),
        'cash_earnings' => parse_decimal($_POST['cash_earnings'] ?? 0),
        'fuel_cost' => parse_decimal($_POST['fuel_cost'] ?? 0),
        'rental_amount' => parse_decimal($_POST['rental_amount'] ?? 0),
        'rental_unpaid' => parse_decimal($_POST['rental_unpaid'] ?? 0),
        'rental_paid' => ($_POST['rental_paid'] ?? '') === '' ? parse_decimal($_POST['rental_amount'] ?? 0) : parse_decimal($_POST['rental_paid'] ?? 0),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($values['driver_id'] <= 0) {
        $errors[] = 'Selecciona un chofer.';
    }

    if ($values['period_start'] === '' || $values['period_end'] === '') {
        $errors[] = 'Completa las fechas del periodo.';
    }

    if ($values['period_start'] && $values['period_end'] && strtotime($values['period_end']) < strtotime($values['period_start'])) {
        $errors[] = 'La fecha de fin no puede ser anterior al inicio.';
    }

    if (!$errors) {
        if ($id > 0) {
            $statement = $pdo->prepare(
                'UPDATE settlements
                SET driver_id = :driver_id, period_start = :period_start, period_end = :period_end,
                    kilometers = :kilometers, gross_earnings = :gross_earnings, cash_earnings = :cash_earnings,
                    fuel_cost = :fuel_cost, rental_amount = :rental_amount, rental_unpaid = :rental_unpaid,
                    rental_paid = :rental_paid, notes = :notes, updated_by = :updated_by
                WHERE id = :id'
            );
            $values['updated_by'] = current_user()['id'];
            $values['id'] = $id;
            $statement->execute($values);
            flash('success', 'Liquidacion actualizada.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO settlements
                    (driver_id, period_start, period_end, kilometers, gross_earnings, cash_earnings, fuel_cost,
                    rental_amount, rental_unpaid, rental_paid, notes, created_by, updated_by)
                VALUES
                    (:driver_id, :period_start, :period_end, :kilometers, :gross_earnings, :cash_earnings, :fuel_cost,
                    :rental_amount, :rental_unpaid, :rental_paid, :notes, :created_by, :updated_by)'
            );
            $values['created_by'] = current_user()['id'];
            $values['updated_by'] = current_user()['id'];
            $statement->execute($values);
            flash('success', 'Liquidacion creada.');
        }

        redirect('settlements.php');
    }
}

$pageTitle = $id > 0 ? 'Editar liquidacion' : 'Nueva liquidacion';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section narrow">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Carga de datos</p>
            <h1><?= $id > 0 ? 'Editar liquidacion' : 'Nueva liquidacion' ?></h1>
        </div>
        <a class="button ghost" href="settlements.php">Volver</a>
    </div>

    <?php if (!$drivers): ?>
        <div class="flash error inline">Primero carga al menos un chofer activo.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="flash error inline"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <section class="form-layout">
        <form method="post" class="panel form-grid" data-settlement-calculator>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label>
                Chofer
                <select name="driver_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= (int) $driver['id'] ?>" <?= (int) $values['driver_id'] === (int) $driver['id'] ? 'selected' : '' ?>>
                            <?= e($driver['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Inicio
                <input type="date" name="period_start" value="<?= e($values['period_start']) ?>" required>
            </label>
            <label>
                Fin
                <input type="date" name="period_end" value="<?= e($values['period_end']) ?>" required>
            </label>
            <label>
                Kilometros
                <input type="number" step="0.01" name="kilometers" value="<?= e($values['kilometers']) ?>" data-calc="kilometers">
            </label>
            <label>
                Total de ganancias
                <input type="number" step="0.01" name="gross_earnings" value="<?= e($values['gross_earnings']) ?>" data-calc="gross">
            </label>
            <label>
                Ganancias en efectivo
                <input type="number" step="0.01" name="cash_earnings" value="<?= e($values['cash_earnings']) ?>" data-calc="cash">
            </label>
            <label>
                Combustible
                <input type="number" step="0.01" name="fuel_cost" value="<?= e($values['fuel_cost']) ?>" data-calc="fuel">
            </label>
            <label>
                Alquiler del auto
                <input type="number" step="0.01" name="rental_amount" value="<?= e($values['rental_amount']) ?>" data-calc="rental">
            </label>
            <label>
                Alquiler no pagado
                <input type="number" step="0.01" name="rental_unpaid" value="<?= e($values['rental_unpaid']) ?>" data-calc="unpaid">
            </label>
            <label>
                Alquiler pagado
                <input type="number" step="0.01" name="rental_paid" value="<?= e($values['rental_paid']) ?>" data-calc="paid">
            </label>
            <label class="full-field">
                Observaciones
                <textarea name="notes" rows="4"><?= e($values['notes']) ?></textarea>
            </label>

            <div class="form-actions full-field">
                <button class="button primary" type="submit" <?= !$drivers ? 'disabled' : '' ?>>Guardar liquidacion</button>
                <a class="button ghost" href="settlements.php">Cancelar</a>
            </div>
        </form>

        <aside class="panel calc-panel">
            <h2>Vista rapida</h2>
            <dl>
                <div><dt>Neto virtual</dt><dd data-output="virtual">$ 0</dd></div>
                <div><dt>Ganancia del chofer</dt><dd data-output="driver">$ 0</dd></div>
                <div><dt>A transferir</dt><dd data-output="transfer">$ 0</dd></div>
                <div><dt>Ingreso propietario</dt><dd data-output="owner">$ 0</dd></div>
            </dl>
        </aside>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
