<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pageTitle = 'Inicio';
$bodyClass = 'landing-page';
require __DIR__ . '/../app/views/header.php';
?>

<main>
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">Sistema privado de administracion</p>
                <h1>Ruta Clara</h1>
                <p class="hero-text">
                    Centraliza choferes, liquidaciones semanales, alquileres del auto, combustible,
                    transferencias y gastos extra en una vista clara para administrar remises usados en plataformas.
                </p>
                <div class="hero-actions">
                    <a class="button primary" href="<?= is_logged_in() ? 'dashboard.php' : 'login.php' ?>">
                        <?= is_logged_in() ? 'Abrir panel' : 'Ingresar al sistema' ?>
                    </a>
                    <a class="button secondary" href="#funciones">Ver funciones</a>
                </div>
            </div>

            <div class="product-preview" aria-label="Vista previa del panel">
                <div class="preview-bar">
                    <span></span>
                    <strong>Junio</strong>
                    <small>semana activa</small>
                </div>
                <div class="preview-metrics">
                    <div>
                        <small>Alquiler cobrado</small>
                        <strong>$ 300.000</strong>
                    </div>
                    <div>
                        <small>A transferir</small>
                        <strong>$ 119.000</strong>
                    </div>
                    <div>
                        <small>Gastos</small>
                        <strong>$ 221.000</strong>
                    </div>
                </div>
                <div class="preview-table">
                    <div><span>Franco</span><span>$ 60.000</span><span>pagado</span></div>
                    <div><span>Esteban</span><span>$ 200.000</span><span>pagado</span></div>
                    <div><span>Seguro</span><span>$ 121.000</span><span>gasto</span></div>
                </div>
            </div>
        </div>
    </section>

    <section id="funciones" class="shell section">
        <div class="section-heading">
            <p class="eyebrow">De la planilla al panel</p>
            <h2>Todo lo importante sin pelearte con filas y formulas</h2>
        </div>
        <div class="feature-grid">
            <article class="feature-card">
                <h3>Liquidaciones semanales</h3>
                <p>Registra inicio, fin, kilometros, ganancias, efectivo, combustible y alquiler del auto.</p>
            </article>
            <article class="feature-card">
                <h3>Calculos automaticos</h3>
                <p>El sistema calcula ganancia virtual, ganancia del chofer, alquiler cobrado y transferencia estimada.</p>
            </article>
            <article class="feature-card">
                <h3>Gastos separados</h3>
                <p>Carga seguros, arreglos, services, luz u otros gastos que afectan la ganancia real.</p>
            </article>
            <article class="feature-card">
                <h3>Usuarios con permisos</h3>
                <p>Administra vos; otros usuarios pueden entrar en modo lectura para ver reportes sin tocar datos.</p>
            </article>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
