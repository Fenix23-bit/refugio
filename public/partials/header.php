<?php
require_once __DIR__ . '/../../include/auth.php';

$usuario   = usuario_actual();
$activo    = $activo ?? '';
$tituloDoc = isset($titulo) ? $titulo . ' | Huellitas' : 'Huellitas';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloDoc) ?></title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&family=Fraunces:opsz,wght@9..144,900&display=swap">
    <link rel="stylesheet" href="../css/huellitas.css">
    <script>document.documentElement.classList.add('js');</script>
    <script src="../js/huellitas.js" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="pata" viewBox="0 0 64 64">
            <ellipse cx="32" cy="43" rx="15" ry="12"/>
            <ellipse cx="13" cy="27" rx="6" ry="8.5" transform="rotate(-22 13 27)"/>
            <ellipse cx="25" cy="14" rx="6" ry="9"/>
            <ellipse cx="39" cy="14" rx="6" ry="9"/>
            <ellipse cx="51" cy="27" rx="6" ry="8.5" transform="rotate(22 51 27)"/>
        </symbol>
    </svg>

    <a class="saltar" href="#contenido">Saltar al contenido</a>

    <header class="top">
        <div class="contenedor_header top-in">
            <?php if ($usuario && ($usuario['rol'] ?? '') === 'admin'): ?>
                <a href="registro_mascotas.php" <?= $activo === 'registro_mascotas' ? 'aria-current="page"' : '' ?>>Registrar mascota</a>
                <a href="lista_mascotas.php" <?= $activo === 'lista_mascotas' ? 'aria-current="page"' : '' ?>>Buscar mascota</a>
            <?php endif; ?>

            <a class="logo" href="inicio.php">
                <svg class="logo-pata" aria-hidden="true"><use href="#pata"/></svg>
                Huellitas
            </a>

            <button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu">Menú</button>

            <nav id="menu" class="menu" aria-label="Principal">
                <a href="mascotas.php">Mascotas</a>
                <a href="comoAdoptar.php">Cómo adoptar</a>
                <?php if ($usuario): ?>
                    <?php if (($usuario['rol'] ?? '') === 'admin'): ?>
                        <a href="admin.php">Panel admin</a>
                    <?php endif; ?>
                    <a href="panel.php" <?= $activo === 'panel' ? 'aria-current="page"' : '' ?>>Mi cuenta</a>
                    <form method="post" action="logout.php">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="enlace">Cerrar sesión</button>
                    </form>
                <?php else: ?>
                    <a href="login.php" <?= $activo === 'login' ? 'aria-current="page"' : '' ?>>Iniciar sesión</a>
                    <a class="btn-nav" href="registro.php" <?= $activo === 'registro' ? 'aria-current="page"' : '' ?>>Crear cuenta</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main id="contenido">
