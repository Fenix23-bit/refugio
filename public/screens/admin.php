<?php
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../config/database.php';
$u = requiere_admin();

$stats       = [];
$solicitudes = [];
try {
    $enRefugio = (int) $pdo->query(
        "SELECT COUNT(*) FROM mascotas WHERE estado IN ('disponible','reservado','en_tratamiento')"
    )->fetchColumn();

    $solPend = (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_adopcion WHERE estado = 'pendiente'"
    )->fetchColumn();

    $adopMes = (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_adopcion
          WHERE estado = 'aceptada'
            AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            AND fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH"
    )->fetchColumn();

    $stats = [
        ['EN REFUGIO', $enRefugio],
        ['SOLICITUDES', $solPend],
        ['ADOPCIONES MES', $adopMes],
    ];

    $solicitudes = $pdo->query(
        "SELECT u.nombre AS adoptante, m.nombre AS mascota, sa.estado
           FROM solicitudes_adopcion sa
           JOIN usuarios u ON u.id = sa.usuario_id
           JOIN mascotas m ON m.id = sa.mascota_id
          WHERE sa.estado = 'pendiente'
          ORDER BY sa.fecha DESC
          LIMIT 5"
    )->fetchAll();
} catch (PDOException $ex) {
    $stats = [['EN REFUGIO', 0], ['SOLICITUDES', 0], ['ADOPCIONES MES', 0]];
}
$adminActivo = 'Panel';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel admin | Huellitas</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&family=Fraunces:opsz,wght@9..144,900&display=swap">
    <link rel="stylesheet" href="../css/huellitas.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">
<div class="admin">
    <?php require __DIR__ . '/../partials/admin_lado.php'; ?>

    <main class="admin-main">
        <div class="admin-cab">
            <h1>Panel</h1>
            <a class="btn admin-nueva" href="registro_mascotas.php">+ Registrar mascota</a>
        </div>

        <section class="admin-stats" aria-label="Resumen">
            <?php foreach ($stats as [$etq, $num]): ?>
                <div class="stat">
                    <span class="stat-etq"><?= e($etq) ?></span>
                    <strong><?= (int) $num ?></strong>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="admin-card">
            <h2>Solicitudes por revisar</h2>
            <?php if (!$solicitudes): ?>
                <p class="ayuda sin-margen">No hay solicitudes pendientes.</p>
            <?php else: ?>
                <ul class="lista-sol">
                    <?php foreach ($solicitudes as $sol): ?>
                        <li>
                            <span><?= e($sol['adoptante']) ?> → <?= e($sol['mascota']) ?></span>
                            <span class="pill">Pendiente</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="admin-card">
            <h2>Adopciones por mes</h2>
            <div class="grafica">gráfica de barras</div>
        </section>
    </main>
</div>
</body>
</html>
