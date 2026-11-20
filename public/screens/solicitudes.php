<?php
/**
 * solicitudes.php
 * Solicitudes de adopción: búsqueda, filtro por estado, paginación y cambio de estado (solo administradores).
 */
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../config/database.php'; // define $pdo
$usuario = requiere_admin();

const POR_PAGINA = 10;
const ESTADOS = [
    'pendiente' => 'Pendiente',
    'aceptada'  => 'Aceptada',
    'cancelada' => 'Cancelada',
];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/* ---------- Filtros (GET) ---------- */
$q      = trim((string) ($_GET['q'] ?? ''));
$fEst   = (string) ($_GET['estado'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
if (!isset(ESTADOS[$fEst])) {
    $fEst = '';
}

$qs = function (array $extra = []) use (&$q, &$fEst, &$pagina): string {
    $p = array_merge(['q' => $q, 'estado' => $fEst, 'p' => $pagina > 1 ? $pagina : ''], $extra);
    $p = array_filter($p, fn($x) => $x !== '' && $x !== null);
    return $p ? '?' . http_build_query($p) : '';
};

/* ---------- Cambio de estado (POST) ---------- */
$errorGeneral = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPost     = (int) ($_POST['id'] ?? 0);
    $estadoPost = (string) ($_POST['estado_nuevo'] ?? '');

    if (!csrf_valido()) {
        $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($idPost < 1 || !isset(ESTADOS[$estadoPost])) {
        $errorGeneral = 'No se pudo actualizar el estado.';
    } else {
        $pdo->prepare('UPDATE solicitudes_adopcion SET estado = ? WHERE id = ?')->execute([$estadoPost, $idPost]);
        header('Location: solicitudes.php' . $qs(['ok' => 'estado']));
        exit;
    }
}

/* ---------- Consulta ---------- */
$where = [];
$par   = [];
if ($q !== '') {
    $where[]   = '(u.nombre LIKE :q OR u.correo LIKE :q OR m.nombre LIKE :q)';
    $par[':q'] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($fEst !== '') {
    $where[]     = 'sa.estado = :est';
    $par[':est'] = $fEst;
}
$cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM solicitudes_adopcion sa
         JOIN usuarios u ON u.id = sa.usuario_id
         JOIN mascotas m ON m.id = sa.mascota_id';

$st = $pdo->prepare("SELECT COUNT(*) $from $cond");
$st->execute($par);
$total = (int) $st->fetchColumn();

$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare(
    "SELECT sa.id, sa.fecha, sa.motivo, sa.estado,
            u.nombre AS adoptante, u.correo, u.telefono,
            m.id AS mascota_id, m.nombre AS mascota
       $from $cond
      ORDER BY (sa.estado = 'pendiente') DESC, sa.fecha DESC
      LIMIT :lim OFFSET :off"
);
foreach ($par as $k => $val) {
    $st->bindValue($k, $val);
}
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$solicitudes = $st->fetchAll(PDO::FETCH_ASSOC);

$desde = $total ? $offset + 1 : 0;
$hasta = $offset + count($solicitudes);

$nums = [];
for ($i = 1; $i <= $paginas; $i++) {
    if ($i === 1 || $i === $paginas || abs($i - $pagina) <= 1) {
        $nums[] = $i;
    } elseif (end($nums) !== '…') {
        $nums[] = '…';
    }
}

$fecha = function (?string $f): string {
    $t = $f ? strtotime($f) : false;
    return $t ? date('j', $t) . ' ' . MESES[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '—';
};

$titulo      = 'Solicitudes';
$adminActivo = 'Solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | Huellitas</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&family=Fraunces:opsz,wght@9..144,900&display=swap">
    <link rel="stylesheet" href="../css/huellitas.css">
    <link rel="stylesheet" href="../css/admin.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="admin-body">
<div class="admin">
<?php require __DIR__ . '/../partials/admin_lado.php'; ?>
<main class="admin-main">
<div class="lm">

    <div class="lm-cab">
        <h1>Solicitudes <span class="cuenta">· <?= $total ?></span></h1>
    </div>

    <?php if (($_GET['ok'] ?? '') === 'estado'): ?>
        <p class="alerta ok" role="status">Estado actualizado.</p>
    <?php endif; ?>
    <?php if ($errorGeneral): ?>
        <p class="alerta error" role="alert"><?= e($errorGeneral) ?></p>
    <?php endif; ?>

    <form class="filtros" method="get" action="solicitudes.php" role="search">
        <div class="buscar">
            <label class="solo-lector" for="q">Buscar por adoptante, correo o mascota</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por adoptante, correo o mascota">
        </div>

        <label class="solo-lector" for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Estado</option>
            <?php foreach (ESTADOS as $valor => $texto): ?>
                <option value="<?= e($valor) ?>" <?= $fEst === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-sec" type="submit">Buscar</button>
    </form>

    <?php if (!$solicitudes): ?>
        <p class="vacio">
            <?= ($q !== '' || $fEst !== '') ? 'No hay solicitudes que coincidan con la búsqueda.' : 'Todavía no hay solicitudes de adopción.' ?>
        </p>
    <?php else: ?>
        <div class="tabla-cont">
            <table class="lista lista-top">
                <thead>
                    <tr>
                        <th scope="col">Adoptante</th>
                        <th scope="col">Mascota</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['adoptante']) ?></strong>
                            <span class="cod"><?= e($s['correo']) ?></span>
                            <?php if ($s['telefono']): ?><span class="cod"><?= e($s['telefono']) ?></span><?php endif; ?>
                        </td>
                        <td>
                            <a href="registro_mascotas.php?id=<?= (int) $s['mascota_id'] ?>"><?= e($s['mascota']) ?></a>
                            <span class="cod">Código <?= (int) $s['mascota_id'] ?></span>
                        </td>
                        <td><?= e($fecha($s['fecha'])) ?></td>
                        <td>
                            <?php if (trim((string) $s['motivo']) !== ''): ?>
                                <details class="motivo">
                                    <summary>Ver motivo</summary>
                                    <p><?= e($s['motivo']) ?></p>
                                </details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <form class="est-form" method="post" action="solicitudes.php<?= e($qs()) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <select class="sel-est est-<?= e($s['estado']) ?>" name="estado_nuevo"
                                        aria-label="Estado de la solicitud de <?= e($s['adoptante']) ?>"
                                        onchange="this.form.submit()">
                                    <?php foreach (ESTADOS as $valor => $texto): ?>
                                        <option value="<?= e($valor) ?>" <?= $s['estado'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sec btn-ok" type="submit">OK</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="lm-pie">
            <p class="sin-margen">Mostrando <?= $desde ?>–<?= $hasta ?> de <?= $total ?></p>

            <?php if ($paginas > 1): ?>
                <nav class="pag" aria-label="Paginación">
                    <?php if ($pagina > 1): ?>
                        <a href="<?= e('solicitudes.php' . $qs(['p' => $pagina - 1 > 1 ? $pagina - 1 : ''])) ?>" aria-label="Página anterior">‹</a>
                    <?php else: ?>
                        <span class="off" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <?php foreach ($nums as $n): ?>
                        <?php if ($n === '…'): ?>
                            <span aria-hidden="true">…</span>
                        <?php elseif ($n === $pagina): ?>
                            <span aria-current="page"><?= $n ?></span>
                        <?php else: ?>
                            <a href="<?= e('solicitudes.php' . $qs(['p' => $n > 1 ? $n : ''])) ?>"><?= $n ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($pagina < $paginas): ?>
                        <a href="<?= e('solicitudes.php' . $qs(['p' => $pagina + 1])) ?>" aria-label="Página siguiente">›</a>
                    <?php else: ?>
                        <span class="off" aria-hidden="true">›</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</main>
</div>
</body>
</html>
