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

    if ($action === 'delete' && $id > 0) {
        $statement = $pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $statement->execute(['id' => $id]);
        flash('success', 'Gasto eliminado.');
        redirect('expenses.php');
    }

    $data = [
        'expense_date' => (string) ($_POST['expense_date'] ?? date('Y-m-d')),
        'driver_id' => ($_POST['driver_id'] ?? '') === '' ? null : (int) $_POST['driver_id'],
        'concept' => trim((string) ($_POST['concept'] ?? '')),
        'amount' => parse_decimal($_POST['amount'] ?? 0),
        'paid_by' => trim((string) ($_POST['paid_by'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($data['concept'] === '') {
        flash('error', 'El concepto del gasto es obligatorio.');
        redirect('expenses.php');
    }

    if ($id > 0) {
        $statement = $pdo->prepare(
            'UPDATE expenses
            SET expense_date = :expense_date, driver_id = :driver_id, concept = :concept,
                amount = :amount, paid_by = :paid_by, notes = :notes
            WHERE id = :id'
        );
        $data['id'] = $id;
        $statement->execute($data);
        flash('success', 'Gasto actualizado.');
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO expenses (expense_date, driver_id, concept, amount, paid_by, notes, created_by)
            VALUES (:expense_date, :driver_id, :concept, :amount, :paid_by, :notes, :created_by)'
        );
        $data['created_by'] = current_user()['id'];
        $statement->execute($data);
        flash('success', 'Gasto creado.');
    }

    redirect('expenses.php');
}

$drivers = $pdo->query('SELECT id, name FROM drivers ORDER BY name ASC')->fetchAll();
$editExpense = null;

if (is_admin() && isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM expenses WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['edit']]);
    $editExpense = $statement->fetch() ?: null;
}

$month = (string) ($_GET['month'] ?? date('Y-m'));
$where = [];
$params = [];

if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
    $where[] = 'e.expense_date BETWEEN :start AND :end';
    $params['start'] = "{$month}-01";
    $params['end'] = date('Y-m-t', strtotime("{$month}-01"));
}

$sql = 'SELECT e.*, d.name AS driver_name
    FROM expenses e
    LEFT JOIN drivers d ON d.id = e.driver_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY e.expense_date DESC, e.id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$expenses = $statement->fetchAll();
$totalExpenses = array_reduce($expenses, static fn (float $carry, array $expense): float => $carry + (float) $expense['amount'], 0.0);

$pageTitle = 'Gastos';
require __DIR__ . '/../app/views/header.php';
?>

<main class="shell section">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Egresos y ajustes</p>
            <h1>Gastos</h1>
        </div>
        <strong class="summary-pill"><?= money($totalExpenses) ?></strong>
    </div>

    <section class="filter-bar">
        <form method="get" class="filters">
            <label>
                Mes
                <input type="month" name="month" value="<?= e($month) ?>">
            </label>
            <button class="button secondary" type="submit">Filtrar</button>
            <a class="button ghost" href="expenses.php">Limpiar</a>
        </form>
    </section>

    <section class="content-grid">
        <div class="panel wide">
            <div class="panel-heading">
                <h2>Registros</h2>
                <span><?= count($expenses) ?> gastos</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Chofer</th>
                        <th>Pago por</th>
                        <th>Valor</th>
                        <?php if (is_admin()): ?><th></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= date_ar($expense['expense_date']) ?></td>
                            <td>
                                <strong><?= e($expense['concept']) ?></strong>
                                <?php if ($expense['notes']): ?><small><?= e($expense['notes']) ?></small><?php endif; ?>
                            </td>
                            <td><?= e($expense['driver_name'] ?: '-') ?></td>
                            <td><?= e($expense['paid_by'] ?: '-') ?></td>
                            <td><?= money($expense['amount']) ?></td>
                            <?php if (is_admin()): ?>
                                <td class="row-actions">
                                    <a class="button ghost small" href="expenses.php?edit=<?= (int) $expense['id'] ?>&month=<?= e($month) ?>">Editar</a>
                                    <form method="post" data-confirm="Eliminar este gasto?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $expense['id'] ?>">
                                        <button class="button ghost small danger" type="submit">Eliminar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$expenses): ?>
                        <tr><td colspan="<?= is_admin() ? 6 : 5 ?>" class="empty-cell">No hay gastos en este periodo.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (is_admin()): ?>
            <aside class="panel">
                <div class="panel-heading">
                    <h2><?= $editExpense ? 'Editar gasto' : 'Nuevo gasto' ?></h2>
                    <?php if ($editExpense): ?><a href="expenses.php">Nuevo</a><?php endif; ?>
                </div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editExpense['id'] ?? 0) ?>">
                    <label>
                        Fecha
                        <input type="date" name="expense_date" value="<?= e($editExpense['expense_date'] ?? date('Y-m-d')) ?>" required>
                    </label>
                    <label>
                        Concepto
                        <input type="text" name="concept" value="<?= e($editExpense['concept'] ?? '') ?>" required>
                    </label>
                    <label>
                        Valor
                        <input type="number" step="0.01" name="amount" value="<?= e($editExpense['amount'] ?? '') ?>" required>
                    </label>
                    <label>
                        Chofer relacionado
                        <select name="driver_id">
                            <option value="">Sin chofer</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= (int) $driver['id'] ?>" <?= (int) ($editExpense['driver_id'] ?? 0) === (int) $driver['id'] ? 'selected' : '' ?>>
                                    <?= e($driver['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Pago por
                        <input type="text" name="paid_by" value="<?= e($editExpense['paid_by'] ?? '') ?>">
                    </label>
                    <label>
                        Notas
                        <textarea name="notes" rows="4"><?= e($editExpense['notes'] ?? '') ?></textarea>
                    </label>
                    <button class="button primary full" type="submit">Guardar gasto</button>
                </form>
            </aside>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
