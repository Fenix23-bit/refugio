<?php
/*
 * Barra lateral del área de administración.
 * Espera (opcional): $adminActivo = 'Panel' | 'Mascotas' | ...
 */
$adminActivo = $adminActivo ?? '';
$menuAdmin = [
    'Panel'       => 'admin.php',
    'Mascotas'    => 'lista_mascotas.php',
    'Solicitudes' => '#',
    'Adoptantes'  => '#',
    'Historias'   => '#',
    'Ajustes'     => '#',
];
?>
<aside class="admin-lado">
    <a class="admin-logo" href="inicio.php">Huellitas</a>
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
