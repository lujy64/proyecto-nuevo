<?php

$title = isset($pageTitle) ? $pageTitle . ' - Ruta Clara' : 'Ruta Clara';
$user = current_user();
$bodyClasses = trim(($bodyClass ?? '') . ' ' . ($user ? 'app-page' : 'auth-page'));
$cssPath = __DIR__ . '/../../public/assets/css/styles.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$navItems = [
    ['label' => 'Panel', 'href' => 'daily_entries.php', 'files' => ['daily_entries.php'], 'icon' => 'P'],
    ['label' => 'Carga', 'href' => 'daily_entry_form.php', 'files' => ['daily_entry_form.php'], 'icon' => '+'],
    ['label' => 'Choferes', 'href' => 'drivers.php', 'files' => ['drivers.php'], 'icon' => 'C'],
    ['label' => 'Autos', 'href' => 'cars.php', 'files' => ['cars.php'], 'icon' => 'A'],
    ['label' => 'Reportes', 'href' => 'reports.php', 'files' => ['reports.php'], 'icon' => 'R'],
];

if ($user && can_manage_users()) {
    $navItems[] = ['label' => 'Usuarios', 'href' => 'users.php', 'files' => ['users.php'], 'icon' => 'U'];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= e($cssVersion) ?>">
</head>
<body class="<?= e($bodyClasses) ?>">
<?php if ($user): ?>
    <div class="app-layout">
        <aside class="sidebar" aria-label="Navegacion principal">
            <a class="brand" href="daily_entries.php" aria-label="Ruta Clara">
                <span class="brand-mark">RC</span>
                <span>
                    <strong>Ruta Clara</strong>
                    <small>control diario</small>
                </span>
            </a>
            <span class="developer-badge">Desarrollado por The Panther Soft</span>

            <nav class="sidebar-nav">
                <?php foreach ($navItems as $item): ?>
                    <a class="<?= active_nav($item['files']) ?>" href="<?= e($item['href']) ?>">
                        <span class="nav-icon"><?= e($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div>
                    <strong><?= e($user['full_name'] ?? $user['username']) ?></strong>
                    <span><?= ($user['role'] ?? '') === 'admin' ? 'Administrador' : 'Chofer' ?></span>
                </div>
                <a class="button ghost small" href="logout.php">Salir</a>
            </div>
        </aside>

        <div class="app-main">
            <header class="mobile-topbar">
                <a class="brand compact" href="daily_entries.php" aria-label="Ruta Clara">
                    <span class="brand-mark">RC</span>
                    <span>
                        <strong>Ruta Clara</strong>
                        <small>The Panther Soft</small>
                    </span>
                </a>
                <a class="button ghost small" href="logout.php">Salir</a>
            </header>

            <?php if ($message = flash()): ?>
                <div class="page-shell">
                    <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
                </div>
            <?php endif; ?>
<?php else: ?>
    <header class="auth-topbar">
        <a class="brand" href="index.php" aria-label="Ruta Clara">
            <span class="brand-mark">RC</span>
            <span>
                <strong>Ruta Clara</strong>
                <small>The Panther Soft</small>
            </span>
        </a>
        <a class="button ghost small" href="login.php">Ingresar</a>
    </header>

    <?php if ($message = flash()): ?>
        <div class="auth-flash">
            <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        </div>
    <?php endif; ?>
<?php endif; ?>
