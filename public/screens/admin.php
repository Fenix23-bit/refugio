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
            AND COALESCE(fecha_resolucion, fecha) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            AND COALESCE(fecha_resolucion, fecha) <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH"
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

// Gráfica: adopciones aceptadas por mes, últimos 6 meses (los meses sin datos valen 0)
const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$serie = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i month");
    $serie[date('Y-m', $t)] = ['mes' => MESES_CORTOS[(int) date('n', $t) - 1], 'total' => 0];
}
try {
    $filas = $pdo->query(
        "SELECT DATE_FORMAT(COALESCE(fecha_resolucion, fecha), '%Y-%m') AS ym, COUNT(*) AS total
           FROM solicitudes_adopcion
          WHERE estado = 'aceptada'
            AND COALESCE(fecha_resolucion, fecha) >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
          GROUP BY ym"
    )->fetchAll();
    foreach ($filas as $f) {
        if (isset($serie[$f['ym']])) {
            $serie[$f['ym']]['total'] = (int) $f['total'];
        }
    }
} catch (PDOException $ex) {
    // se deja la serie en ceros
}
$maxSerie = max(1, ...array_column($serie, 'total'));
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
            <div class="barras" role="img"
                 aria-label="Adopciones por mes: <?= e(implode(', ', array_map(fn($m) => $m['mes'] . ' ' . $m['total'], $serie))) ?>">
                <?php foreach ($serie as $m): ?>
                    <div class="barra-col">
                        <span class="barra-num"><?= $m['total'] ?></span>
                        <div class="barra-pista">
                            <div class="barra<?= $m['total'] === 0 ? ' barra-cero' : '' ?>" style="--alto: <?= round($m['total'] / $maxSerie * 100) ?>%"></div>
                        </div>
                        <span class="barra-mes"><?= e($m['mes']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
