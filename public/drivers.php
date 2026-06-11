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

    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'archive' && $id > 0) {
        $statement = $pdo->prepare("UPDATE drivers SET status = 'inactive' WHERE id = :id");
        $statement->execute(['id' => $id]);
        flash('success', 'Chofer archivado.');
        redirect('drivers.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $vehicleLabel = trim((string) ($_POST['vehicle_label'] ?? ''));
    $requestedStatus = (string) ($_POST['status'] ?? 'active');
    $status = in_array($requestedStatus, ['active', 'inactive'], true) ? $requestedStatus : 'active';
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($name === '') {
        flash('error', 'El nombre del chofer es obligatorio.');
        redirect('drivers.php');
    }

    if ($id > 0) {
        $statement = $pdo->prepare(
            'UPDATE drivers
            SET name = :name, phone = :phone, vehicle_label = :vehicle_label, status = :status, notes = :notes
            WHERE id = :id'
        );
        $statement->execute([
            'name' => $name,
            'phone' => $phone,
            'vehicle_label' => $vehicleLabel,
            'status' => $status,
            'notes' => $notes,
            'id' => $id,
        ]);
        flash('success', 'Chofer actualizado.');
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO drivers (name, phone, vehicle_label, status, notes)
            VALUES (:name, :phone, :vehicle_label, :status, :notes)'
        );
        $statement->execute([
            'name' => $name,
            'phone' => $phone,
            'vehicle_label' => $vehicleLabel,
            'status' => $status,
            'notes' => $notes,
        ]);
        flash('success', 'Chofer creado.');
    }

    redirect('drivers.php');
}

$editDriver = null;
if (is_admin() && isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM drivers WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editDriver = $statement->fetch() ?: null;
}

$drivers = $pdo->query(
    'SELECT d.*,
        COALESCE(stats.settlements_count, 0) AS settlements_count,
        COALESCE(stats.rental_paid_total, 0) AS rental_paid_total,
        COALESCE(stats.rental_unpaid_total, 0) AS rental_unpaid_total
    FROM drivers d
    LEFT JOIN (
        SELECT driver_id,
            COUNT(id) AS settlements_count,
            SUM(rental_paid) AS rental_paid_total,
            SUM(rental_unpaid) AS rental_unpaid_total
        FROM settlements
        GROUP BY driver_id
    ) stats ON stats.driver_id = d.id
    ORDER BY d.status ASC, d.name ASC'
)->fetchAll();

$pageTitle = 'Choferes';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Personas y autos</p>
            <h1>Choferes</h1>
        </div>
        <?php if (!is_admin()): ?>
            <span class="role-badge">Solo lectura</span>
        <?php endif; ?>
    </div>

    <section class="content-grid">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Listado</h2>
                <span><?= count($drivers) ?> choferes</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th>Auto</th>
                        <th>Estado</th>
                        <th>Liquidaciones</th>
                        <th>Alquiler cobrado</th>
                        <th>Pendiente</th>
                        <?php if (is_admin()): ?><th></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($drivers as $driver): ?>
                        <tr>
                            <td>
                                <strong><?= e($driver['name']) ?></strong>
                                <?php if ($driver['notes']): ?><small><?= e($driver['notes']) ?></small><?php endif; ?>
                            </td>
                            <td><?= e($driver['phone'] ?: '-') ?></td>
                            <td><?= e($driver['vehicle_label'] ?: '-') ?></td>
                            <td><span class="status <?= e($driver['status']) ?>"><?= $driver['status'] === 'active' ? 'Activo' : 'Archivado' ?></span></td>
                            <td><?= (int) $driver['settlements_count'] ?></td>
                            <td><?= money($driver['rental_paid_total']) ?></td>
                            <td><?= money($driver['rental_unpaid_total']) ?></td>
                            <?php if (is_admin()): ?>
                                <td class="row-actions">
                                    <a class="button ghost small" href="drivers.php?edit=<?= (int) $driver['id'] ?>">Editar</a>
                                    <?php if ($driver['status'] === 'active'): ?>
                                        <form method="post" data-confirm="Archivar este chofer?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= (int) $driver['id'] ?>">
                                            <button class="button ghost small danger" type="submit">Archivar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$drivers): ?>
                        <tr><td colspan="<?= is_admin() ? 8 : 7 ?>" class="empty-cell">Carga tu primer chofer para empezar.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (is_admin()): ?>
            <aside class="panel">
                <div class="panel-heading">
                    <h2><?= $editDriver ? 'Editar chofer' : 'Nuevo chofer' ?></h2>
                    <?php if ($editDriver): ?><a href="drivers.php">Nuevo</a><?php endif; ?>
                </div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editDriver['id'] ?? 0) ?>">
                    <label>
                        Nombre
                        <input type="text" name="name" value="<?= e($editDriver['name'] ?? '') ?>" required>
                    </label>
                    <label>
                        Telefono
                        <input type="text" name="phone" value="<?= e($editDriver['phone'] ?? '') ?>">
                    </label>
                    <label>
                        Auto o patente
                        <input type="text" name="vehicle_label" value="<?= e($editDriver['vehicle_label'] ?? '') ?>">
                    </label>
                    <label>
                        Estado
                        <select name="status">
                            <option value="active" <?= ($editDriver['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= ($editDriver['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Archivado</option>
                        </select>
                    </label>
                    <label>
                        Notas
                        <textarea name="notes" rows="4"><?= e($editDriver['notes'] ?? '') ?></textarea>
                    </label>
                    <button class="button primary full" type="submit">Guardar chofer</button>
                </form>
            </aside>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
