    </main>

    <footer class="pie-sitio">
        <div class="contenedor_footer pie-in">
            <div>
                <a class="logo" href="inicio.php">
                    <svg class="logo-pata" aria-hidden="true"><use href="#pata"/></svg>
                    Huellitas
                </a>
            </div>

            <nav aria-label="Pie de página">
                <a href="mascotas.php">Mascotas</a>
                <a href="comoAdoptar.php">Cómo adoptar</a>
                <?php if (usuario_actual()): ?>
                    <?php if ((usuario_actual()['rol'] ?? '') === 'admin'): ?>
                        <a href="admin.php">Panel admin</a>
                    <?php endif; ?>
                    <a href="panel.php">Mi cuenta</a>
                <?php else: ?>
                    <a href="login.php">Iniciar sesión</a>
                    <a href="registro.php">Crear cuenta</a>
                <?php endif; ?>
            </nav>
        </div>

        <p class="copyright">&copy; <?= date('Y') ?> Huellitas - Todos los derechos reservados.</p>
    </footer>
</body>
</html>
