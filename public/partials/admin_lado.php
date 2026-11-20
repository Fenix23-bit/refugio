<?php
/*
 * Barra lateral del área de administración.
 */
$adminActivo = $adminActivo ?? '';
$menuAdmin = [
    'Panel'       => 'admin.php',
    'Mascotas'    => 'lista_mascotas.php',
    'Solicitudes' => 'solicitudes.php',
    'Usuarios'    => 'usuarios.php',
];
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="pata" viewBox="0 0 64 64">
        <ellipse cx="32" cy="43" rx="15" ry="12"/>
        <ellipse cx="13" cy="27" rx="6" ry="8.5" transform="rotate(-22 13 27)"/>
        <ellipse cx="25" cy="14" rx="6" ry="9"/>
        <ellipse cx="39" cy="14" rx="6" ry="9"/>
        <ellipse cx="51" cy="27" rx="6" ry="8.5" transform="rotate(22 51 27)"/>
    </symbol>
</svg>

<aside class="admin-lado">
    <a class="admin-logo" href="inicio.php">
        <svg class="logo-pata" aria-hidden="true"><use href="#pata"/></svg>
        Huellitas
    </a>
    <p class="admin-rol">ADMIN</p>
    <nav aria-label="Administración">
        <?php foreach ($menuAdmin as $texto => $href): ?>
            <a href="<?= e($href) ?>" <?= $texto === $adminActivo ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="logout.php" class="admin-salir">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Cerrar sesión</button>
    </form>
</aside>
