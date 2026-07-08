<?php $user = current_user(); ?>

<?php if ($user): ?>
            <footer class="app-footer">
                <span>Ruta Clara</span>
                <span>Desarrollado por The Panther Soft</span>
            </footer>
        </div>

        <nav class="bottom-nav" aria-label="Navegacion inferior">
            <?php foreach ($navItems as $item): ?>
                <a class="<?= active_nav($item['files']) ?>" href="<?= e($item['href']) ?>">
                    <span class="nav-icon"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
<?php else: ?>
    <footer class="auth-footer">
        <span>Ruta Clara</span>
        <span>Desarrollado por The Panther Soft</span>
    </footer>
<?php endif; ?>

<script src="assets/js/app.js"></script>
</body>
</html>
