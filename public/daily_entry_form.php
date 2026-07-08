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
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$entry = null;

if ($id > 0) {
    $statement = $pdo->prepare('SELECT * FROM daily_entries WHERE id = :id');
    $statement->execute(['id' => $id]);
    $entry = $statement->fetch();

    if (!$entry) {
        flash('error', 'No se encontro la carga diaria.');
        redirect('daily_entries.php');
    }

    if (is_admin() && (int) ($entry['owner_user_id'] ?? 0) !== $ownerUserId) {
        flash('error', 'No podes editar cargas de otra cuenta.');
        redirect('daily_entries.php');
    }

    if (is_driver_user() && (int) $entry['driver_id'] !== (int) current_driver_id()) {
        flash('error', 'No podes editar cargas de otro chofer.');
        redirect('daily_entries.php');
    }
}

if (is_admin()) {
    $statement = $pdo->prepare("SELECT id, full_name FROM drivers WHERE status = 'active' AND owner_user_id = :owner_user_id ORDER BY full_name ASC");
    $statement->execute(['owner_user_id' => $ownerUserId]);
    $drivers = $statement->fetchAll();
} elseif (current_driver_id()) {
    $statement = $pdo->prepare("SELECT id, full_name FROM drivers WHERE id = :id AND status = 'active'");
    $statement->execute(['id' => current_driver_id()]);
    $drivers = $statement->fetchAll();
} else {
    $drivers = [];
}

if (is_admin()) {
    $statement = $pdo->prepare("SELECT id, CONCAT(brand, ' ', model, ' - ', plate) AS label FROM cars WHERE status = 'active' AND owner_user_id = :owner_user_id ORDER BY brand ASC, model ASC");
    $statement->execute(['owner_user_id' => $ownerUserId]);
    $cars = $statement->fetchAll();
} elseif (current_driver_id()) {
    $statement = $pdo->prepare(
        "SELECT id, CONCAT(brand, ' ', model, ' - ', plate) AS label
        FROM cars
        WHERE status = 'active' AND current_driver_id = :driver_id
        ORDER BY brand ASC, model ASC"
    );
    $statement->execute(['driver_id' => current_driver_id()]);
    $cars = $statement->fetchAll();
} else {
    $cars = [];
}

$errors = [];
$values = $entry ?: [
    'period_type' => 'day',
    'entry_date' => date('Y-m-d'),
    'driver_id' => current_driver_id() ?? '',
    'car_id' => '',
    'gross_income' => '',
    'app_expenses' => '',
    'cash_collected' => '',
    'rental_amount' => '',
    'rental_billing_status' => 'pending',
    'fuel_cost' => '',
    'distance_km' => '',
    'observations' => '',
];
$values['period_type'] = $values['period_type'] ?? 'day';
$values['entry_week'] = valid_week_or_current(date('o-\WW', strtotime((string) ($values['entry_date'] ?? date('Y-m-d')))));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $driverId = is_driver_user() ? (int) (current_driver_id() ?? 0) : (int) ($_POST['driver_id'] ?? 0);
    $periodType = (string) ($_POST['period_type'] ?? 'day');
    $periodType = in_array($periodType, ['day', 'week'], true) ? $periodType : 'day';
    $entryDate = valid_date_or_today($_POST['entry_date'] ?? null);
    $entryWeek = valid_week_or_current($_POST['entry_week'] ?? null);

    if ($periodType === 'week') {
        $entryDate = iso_week_bounds($entryWeek)['start'];
    } else {
        $entryWeek = valid_week_or_current(date('o-\WW', strtotime($entryDate)));
    }

    $values = [
        'period_type' => $periodType,
        'entry_date' => $entryDate,
        'entry_week' => $entryWeek,
        'driver_id' => $driverId,
        'car_id' => (int) ($_POST['car_id'] ?? 0),
        'gross_income' => parse_decimal($_POST['gross_income'] ?? 0),
        'app_expenses' => parse_decimal($_POST['app_expenses'] ?? 0),
        'cash_collected' => parse_decimal($_POST['cash_collected'] ?? 0),
        'rental_amount' => parse_decimal($_POST['rental_amount'] ?? 0),
        'rental_billing_status' => 'pending',
        'fuel_cost' => parse_decimal($_POST['fuel_cost'] ?? 0),
        'distance_km' => parse_decimal($_POST['distance_km'] ?? 0),
        'observations' => trim((string) ($_POST['observations'] ?? '')),
    ];
    if (is_admin()) {
        $values['rental_billing_status'] = in_array(($_POST['rental_billing_status'] ?? 'pending'), ['pending', 'invoiced'], true)
            ? (string) $_POST['rental_billing_status']
            : 'pending';
    } elseif ($entry) {
        $values['rental_billing_status'] = (string) ($entry['rental_billing_status'] ?? 'pending');
    }

    if ($values['driver_id'] <= 0) {
        $errors[] = 'Selecciona un chofer.';
    }

    if ($values['car_id'] <= 0) {
        $errors[] = 'Selecciona un auto.';
    }

    if (is_driver_user() && $values['driver_id'] !== (int) current_driver_id()) {
        $errors[] = 'No podes cargar datos para otro chofer.';
    }

    if (!$errors && is_admin()) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM drivers WHERE id = :driver_id AND owner_user_id = :owner_user_id AND status = \'active\'');
        $statement->execute(['driver_id' => $values['driver_id'], 'owner_user_id' => $ownerUserId]);
        if ((int) $statement->fetchColumn() === 0) {
            $errors[] = 'El chofer seleccionado no pertenece a tu cuenta.';
        }

        $statement = $pdo->prepare('SELECT COUNT(*) FROM cars WHERE id = :car_id AND owner_user_id = :owner_user_id AND status = \'active\'');
        $statement->execute(['car_id' => $values['car_id'], 'owner_user_id' => $ownerUserId]);
        if ((int) $statement->fetchColumn() === 0) {
            $errors[] = 'El auto seleccionado no pertenece a tu cuenta.';
        }
    }

    if (!$errors && is_driver_user()) {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM cars WHERE id = :car_id AND current_driver_id = :driver_id AND status = 'active'");
        $statement->execute(['car_id' => $values['car_id'], 'driver_id' => $values['driver_id']]);
        if ((int) $statement->fetchColumn() === 0) {
            $errors[] = 'El auto seleccionado no esta asignado a tu usuario.';
        }
    }

    $math = daily_entry_math($values);
    $periods = daily_entry_periods($values['entry_date']);
    $saveValues = array_merge($values, $math, $periods);
    $saveValues['owner_user_id'] = $ownerUserId;
    unset($saveValues['entry_week']);

    if (!$errors) {
        try {
            if ($id > 0) {
                $statement = $pdo->prepare(
                    'UPDATE daily_entries
                    SET period_type = :period_type, entry_date = :entry_date, driver_id = :driver_id, car_id = :car_id,
                        gross_income = :gross_income, app_expenses = :app_expenses,
                        cash_collected = :cash_collected, rental_amount = :rental_amount,
                        rental_billing_status = :rental_billing_status,
                        fuel_cost = :fuel_cost, distance_km = :distance_km, total_expenses = :total_expenses,
                        net_total = :net_total, week_number = :week_number,
                        month_number = :month_number, year_number = :year_number,
                        observations = :observations, updated_by = :updated_by
                    WHERE id = :id AND owner_user_id = :owner_user_id'
                );
                $saveValues['updated_by'] = current_user()['id'];
                $saveValues['id'] = $id;
                $statement->execute($saveValues);
                flash('success', 'Carga diaria actualizada.');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO daily_entries
                        (owner_user_id, period_type, entry_date, driver_id, car_id, gross_income, app_expenses,
                        cash_collected, rental_amount, rental_billing_status, fuel_cost, distance_km, total_expenses, net_total, week_number,
                        month_number, year_number, observations, created_by, updated_by)
                    VALUES
                        (:owner_user_id, :period_type, :entry_date, :driver_id, :car_id, :gross_income, :app_expenses,
                        :cash_collected, :rental_amount, :rental_billing_status, :fuel_cost, :distance_km, :total_expenses, :net_total, :week_number,
                        :month_number, :year_number, :observations, :created_by, :updated_by)'
                );
                $saveValues['created_by'] = current_user()['id'];
                $saveValues['updated_by'] = current_user()['id'];
                $statement->execute($saveValues);
                flash('success', 'Carga diaria creada.');
            }

            redirect('daily_entries.php');
        } catch (PDOException $exception) {
            $errors[] = 'Ya existe una carga para ese periodo, chofer y auto.';
        }
    }
}

$previewMath = daily_entry_math($values);
$pageTitle = $id > 0 ? 'Editar carga diaria' : 'Nueva carga diaria';
require __DIR__ . '/../app/views/header.php';
?>

<main class="page-shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Carga diaria</p>
            <h1><?= $id > 0 ? 'Editar carga diaria' : 'Nueva carga diaria' ?></h1>
            <p>Carga por dia o semana con calculo de neto del chofer.</p>
        </div>
        <a class="button ghost" href="daily_entries.php">Volver</a>
    </div>

    <?php if (!$drivers || !$cars): ?>
        <div class="flash error inline">Antes de cargar datos debe existir al menos un chofer activo y un auto activo asignado.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="flash error inline"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <section class="form-layout">
        <form method="post" class="panel form-grid" data-entry-calculator>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label>
                Tipo de carga
                <select name="period_type" data-period-type>
                    <option value="day" <?= ($values['period_type'] ?? 'day') === 'day' ? 'selected' : '' ?>>Por dia</option>
                    <option value="week" <?= ($values['period_type'] ?? '') === 'week' ? 'selected' : '' ?>>Por semana</option>
                </select>
            </label>

            <label data-period-day>
                Dia
                <input type="date" name="entry_date" value="<?= e($values['entry_date']) ?>" required>
            </label>

            <label data-period-week>
                Semana
                <input type="week" name="entry_week" value="<?= e($values['entry_week']) ?>">
            </label>

            <?php if (is_admin()): ?>
                <label>
                    Chofer
                    <select name="driver_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= (int) $driver['id'] ?>" <?= (int) $values['driver_id'] === (int) $driver['id'] ? 'selected' : '' ?>>
                                <?= e($driver['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="driver_id" value="<?= (int) ($values['driver_id'] ?? 0) ?>">
                <label>
                    Chofer
                    <input type="text" value="<?= e($drivers[0]['full_name'] ?? 'Sin chofer vinculado') ?>" disabled>
                </label>
            <?php endif; ?>

            <label>
                Auto
                <select name="car_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= (int) $car['id'] ?>" <?= (int) $values['car_id'] === (int) $car['id'] ? 'selected' : '' ?>>
                            <?= e($car['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Ganancias totales
                <input type="number" step="0.01" name="gross_income" value="<?= e($values['gross_income']) ?>" data-calc="gross" inputmode="decimal" required>
            </label>
            <label>
                Devoluciones/gastos app
                <input type="number" step="0.01" name="app_expenses" value="<?= e($values['app_expenses'] ?? '') ?>" data-calc="app_expenses" inputmode="decimal">
            </label>
            <label>
                Efectivo
                <input type="number" step="0.01" name="cash_collected" value="<?= e($values['cash_collected'] ?? '') ?>" data-calc="cash" inputmode="decimal">
            </label>
            <label>
                Alquiler a facturar
                <input type="number" step="0.01" name="rental_amount" value="<?= e($values['rental_amount'] ?? '') ?>" data-calc="rental" inputmode="decimal">
            </label>
            <?php if (is_admin()): ?>
                <label>
                    Estado del alquiler
                    <select name="rental_billing_status">
                        <option value="pending" <?= ($values['rental_billing_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Falta facturar</option>
                        <option value="invoiced" <?= ($values['rental_billing_status'] ?? '') === 'invoiced' ? 'selected' : '' ?>>Facturado</option>
                    </select>
                </label>
            <?php endif; ?>
            <label>
                Combustible
                <input type="number" step="0.01" name="fuel_cost" value="<?= e($values['fuel_cost']) ?>" data-calc="fuel" inputmode="decimal">
            </label>
            <label>
                Kilometros
                <input type="number" step="0.01" name="distance_km" value="<?= e($values['distance_km'] ?? '') ?>" inputmode="decimal">
            </label>
            <label class="full-field">
                Observaciones
                <textarea name="observations" rows="4"><?= e($values['observations']) ?></textarea>
            </label>

            <div class="form-actions full-field">
                <button class="button primary" type="submit" <?= (!$drivers || !$cars) ? 'disabled' : '' ?>>Guardar carga</button>
                <a class="button ghost" href="daily_entries.php">Cancelar</a>
            </div>
        </form>

        <aside class="panel calc-panel">
            <h2>Calculo automatico</h2>
            <dl>
                <div><dt>Descuentos que afectan neto</dt><dd data-output="deductions"><?= money($previewMath['total_expenses']) ?></dd></div>
                <div><dt>Combustible registrado</dt><dd data-output="fuel"><?= money($values['fuel_cost'] ?? 0) ?></dd></div>
                <div><dt>Neto chofer</dt><dd data-output="net" class="<?= $previewMath['net_total'] < 0 ? 'text-danger' : 'text-gain' ?>"><?= money($previewMath['net_total']) ?></dd></div>
                <div><dt>Semana</dt><dd><?= (int) daily_entry_periods($values['entry_date'])['week_number'] ?></dd></div>
                <div><dt>Mes</dt><dd><?= date('m/Y', strtotime($values['entry_date'])) ?></dd></div>
            </dl>
            <p class="muted calc-note">El combustible no descuenta el neto porque queda a cargo del chofer.</p>
        </aside>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
