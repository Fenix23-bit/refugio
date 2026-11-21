<?php
/**
 * lista_mascotas.php
 * Listado de mascotas con búsqueda, filtros, paginación y edición (solo administradores).
 * Ubicación: public/screens/
 */
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../config/database.php'; // debe definir $pdo

/* ---------- Acceso: solo admin ---------- */
$usuario = usuario_actual();
if (!$usuario || ($usuario['rol'] ?? '') !== 'admin') {
    acceso_denegado();
}

/* ---------- Constantes ---------- */
const POR_PAGINA = 10;
const ESTADOS = [
    'borrador'       => 'Borrador',
    'disponible'     => 'Disponible',
    'reservado'      => 'Reservado',
    'adoptado'       => 'Adoptado',
    'en_tratamiento' => 'En tratamiento',
];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/* ---------- Filtros (GET) ---------- */
$q      = trim((string) ($_GET['q'] ?? ''));
$fEsp   = (int) ($_GET['especie'] ?? 0);
$fEst   = (string) ($_GET['estado'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
if (!isset(ESTADOS[$fEst])) {
    $fEst = '';
}

// Construye "?q=...&estado=..." conservando los filtros
$qs = function (array $extra = []) use (&$q, &$fEsp, &$fEst, &$pagina): string {
    $p = array_merge([
        'q'       => $q,
        'especie' => $fEsp ?: '',
        'estado'  => $fEst,
        'p'       => $pagina > 1 ? $pagina : '',
    ], $extra);
    $p = array_filter($p, fn($x) => $x !== '' && $x !== null);
    return $p ? '?' . http_build_query($p) : '';
};

/* ---------- Cambio rápido de estado (POST) ---------- */
$errorGeneral = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPost     = (int) ($_POST['id'] ?? 0);
    $estadoPost = (string) ($_POST['estado_nuevo'] ?? '');

    if (!hash_equals((string) csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($idPost < 1 || !isset(ESTADOS[$estadoPost])) {
        $errorGeneral = 'No se pudo actualizar el estado.';
    } else {
        $pdo->prepare('UPDATE mascotas SET estado = ?, disponible = ? WHERE id = ?')
            ->execute([$estadoPost, (int) ($estadoPost === 'disponible'), $idPost]);
        header('Location: lista_mascotas.php' . $qs(['ok' => 'estado']));
        exit;
    }
}

/* ---------- Consulta ---------- */
$especies = $pdo->query('SELECT id, nombre FROM especies ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$par   = [];
if ($q !== '') {
    $where[]      = '(m.nombre LIKE :q OR m.id = :cod)';
    $par[':q']    = '%' . addcslashes($q, '%_\\') . '%';
    $par[':cod']  = ctype_digit($q) ? (int) $q : 0;
}
if ($fEsp > 0) {
    $where[]       = 'm.especie_id = :esp';
    $par[':esp']   = $fEsp;
}
if ($fEst !== '') {
    $where[]       = 'm.estado = :est';
    $par[':est']   = $fEst;
}
$cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$bindTodo = function (PDOStatement $st) use ($par): void {
    foreach ($par as $k => $val) {
        $st->bindValue($k, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
};

$st = $pdo->prepare("SELECT COUNT(*) FROM mascotas m $cond");
$bindTodo($st);
$st->execute();
$total = (int) $st->fetchColumn();

$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare(
    "SELECT m.id, m.nombre, m.imagen, m.estado, m.fecha_ingreso, e.nombre AS especie
       FROM mascotas m
       JOIN especies e ON e.id = m.especie_id
       $cond
      ORDER BY m.id DESC
      LIMIT :lim OFFSET :off"
);
$bindTodo($st);
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$mascotas = $st->fetchAll(PDO::FETCH_ASSOC);

$desde = $total ? $offset + 1 : 0;
$hasta = $offset + count($mascotas);

// Números de página: 1 … 4 5 6 … 8
$nums = [];
for ($i = 1; $i <= $paginas; $i++) {
    if ($i === 1 || $i === $paginas || abs($i - $pagina) <= 1) {
        $nums[] = $i;
    } elseif (end($nums) !== '…') {
        $nums[] = '…';
    }
}

$fecha = function (?string $f): string {
    if (!$f) {
        return '—';
    }
    $t = strtotime($f);
    return $t ? date('j', $t) . ' ' . MESES[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '—';
};

$titulo      = 'Mascotas';
$adminActivo = 'Mascotas';
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
        <h1>Mascotas <span class="cuenta">· <?= $total ?></span></h1>
        <a class="btn" href="registro_mascotas.php">+ Registrar</a>
    </div>

    <?php if (($_GET['ok'] ?? '') === '1'): ?>
        <p class="alerta ok" role="status">Los cambios se guardaron correctamente.</p>
    <?php elseif (($_GET['ok'] ?? '') === 'estado'): ?>
        <p class="alerta ok" role="status">Estado actualizado.</p>
    <?php endif; ?>
    <?php if ($errorGeneral): ?>
        <p class="alerta error" role="alert"><?= e($errorGeneral) ?></p>
    <?php endif; ?>

    <form class="filtros" method="get" action="lista_mascotas.php" role="search">
        <div class="buscar">
            <label class="solo-lector" for="q">Buscar por nombre o código</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o código">
        </div>

        <label class="solo-lector" for="especie">Especie</label>
        <select id="especie" name="especie">
            <option value="">Especie</option>
            <?php foreach ($especies as $esp): ?>
                <option value="<?= (int) $esp['id'] ?>" <?= $fEsp === (int) $esp['id'] ? 'selected' : '' ?>>
                    <?= e($esp['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="solo-lector" for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Estado</option>
            <?php foreach (ESTADOS as $valor => $texto): ?>
                <option value="<?= e($valor) ?>" <?= $fEst === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-sec" type="submit">Buscar</button>
    </form>

    <?php if (!$mascotas): ?>
        <p class="vacio">
            <?= ($q !== '' || $fEsp || $fEst !== '')
                ? 'No hay mascotas que coincidan con la búsqueda.'
                : 'Todavía no hay mascotas registradas.' ?>
        </p>
    <?php else: ?>
        <div class="tabla-cont">
            <table class="lista">
                <thead>
                    <tr>
                        <th scope="col">Mascota</th>
                        <th scope="col">Especie</th>
                        <th scope="col">Ingreso</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="solo-lector">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mascotas as $m): ?>
                    <tr>
                        <td>
                            <div class="mascota-celda">
                                <?php if ($m['imagen']): ?>
                                    <img class="mini-foto" src="../<?= e($m['imagen']) ?>" alt="">
                                <?php else: ?>
                                    <span class="mini-vacia" aria-hidden="true">
                                        <svg><use href="#pata"/></svg>
                                    </span>
                                <?php endif; ?>
                                <div>
                                    <a href="registro_mascotas.php?id=<?= (int) $m['id'] ?>"><?= e($m['nombre']) ?></a>
                                    <span class="cod">Código <?= (int) $m['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= e($m['especie']) ?></td>
                        <td><?= e($fecha($m['fecha_ingreso'])) ?></td>
                        <td>
                            <form class="est-form" method="post" action="lista_mascotas.php<?= e($qs()) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <select class="sel-est est-<?= e($m['estado']) ?>" name="estado_nuevo"
                                        aria-label="Estado de <?= e($m['nombre']) ?>"
                                        onchange="this.form.submit()">
                                    <?php foreach (ESTADOS as $valor => $texto): ?>
                                        <option value="<?= e($valor) ?>" <?= $m['estado'] === $valor ? 'selected' : '' ?>>
                                            <?= e($texto) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sec btn-ok" type="submit">OK</button>
                            </form>
                        </td>
                        <td>
                            <a class="btn btn-sec btn-mini" href="registro_mascotas.php?id=<?= (int) $m['id'] ?>"
                               aria-label="Editar a <?= e($m['nombre']) ?>">Editar</a>
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
                        <a href="<?= e('lista_mascotas.php' . $qs(['p' => $pagina - 1 > 1 ? $pagina - 1 : ''])) ?>"
                           aria-label="Página anterior">‹</a>
                    <?php else: ?>
                        <span class="off" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <?php foreach ($nums as $n): ?>
                        <?php if ($n === '…'): ?>
                            <span aria-hidden="true">…</span>
                        <?php elseif ($n === $pagina): ?>
                            <span aria-current="page"><?= $n ?></span>
                        <?php else: ?>
                            <a href="<?= e('lista_mascotas.php' . $qs(['p' => $n > 1 ? $n : ''])) ?>"><?= $n ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($pagina < $paginas): ?>
                        <a href="<?= e('lista_mascotas.php' . $qs(['p' => $pagina + 1])) ?>"
                           aria-label="Página siguiente">›</a>
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
