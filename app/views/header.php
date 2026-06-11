<?php

$title = isset($pageTitle) ? $pageTitle . ' - Ruta Clara' : 'Ruta Clara';
$user = current_user();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="topbar">
    <div class="shell topbar-inner">
        <a class="brand" href="index.php" aria-label="Ruta Clara">
            <span class="brand-mark">RC</span>
            <span>
                <strong>Ruta Clara</strong>
                <small>remis y liquidaciones</small>
            </span>
        </a>

        <?php if ($user): ?>
            <nav class="nav" aria-label="Navegacion principal">
                <a class="<?= active_nav('dashboard.php') ?>" href="dashboard.php">Panel</a>
                <a class="<?= active_nav('settlements.php') ?>" href="settlements.php">Liquidaciones</a>
                <a class="<?= active_nav('drivers.php') ?>" href="drivers.php">Choferes</a>
                <a class="<?= active_nav('expenses.php') ?>" href="expenses.php">Gastos</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a class="<?= active_nav('users.php') ?>" href="users.php">Usuarios</a>
                <?php endif; ?>
            </nav>
            <div class="user-menu">
                <span><?= e($user['full_name'] ?? $user['username']) ?></span>
                <a class="button ghost small" href="logout.php">Salir</a>
            </div>
        <?php else: ?>
            <nav class="nav nav-public" aria-label="Navegacion publica">
                <a href="index.php#funciones">Funciones</a>
                <a href="login.php">Ingresar</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<?php if ($message = flash()): ?>
    <div class="shell">
        <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    </div>
<?php endif; ?>
