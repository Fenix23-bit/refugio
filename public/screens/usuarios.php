<?php
/**
 * usuarios.php
 * Usuarios registrados: búsqueda, filtro por rol, paginación y cambio de rol (solo administradores).
 */
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../config/database.php'; // define $pdo
$usuario = requiere_admin();

const POR_PAGINA = 10;
const ROLES = ['usuario' => 'Usuario', 'admin' => 'Administrador'];

/* ---------- Filtros (GET) ---------- */
$q      = trim((string) ($_GET['q'] ?? ''));
$fRol   = (string) ($_GET['rol'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
if (!isset(ROLES[$fRol])) {
    $fRol = '';
}

$qs = function (array $extra = []) use (&$q, &$fRol, &$pagina): string {
    $p = array_merge(['q' => $q, 'rol' => $fRol, 'p' => $pagina > 1 ? $pagina : ''], $extra);
    $p = array_filter($p, fn($x) => $x !== '' && $x !== null);
    return $p ? '?' . http_build_query($p) : '';
};

/* ---------- Cambio de rol (POST) ---------- */
$errorGeneral = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPost  = (int) ($_POST['id'] ?? 0);
    $rolPost = (string) ($_POST['rol_nuevo'] ?? '');

    if (!csrf_valido()) {
        $errorGeneral = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($idPost < 1 || !isset(ROLES[$rolPost])) {
        $errorGeneral = 'No se pudo actualizar el rol.';
    } elseif ($idPost === (int) $usuario['id']) {
        // Evita quedarse sin administradores
        $errorGeneral = 'No puedes cambiar tu propio rol.';
    } else {
        $st = $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?');
        $st->execute([$rolPost, $idPost]);
        header('Location: usuarios.php' . $qs(['ok' => 'rol']));
        exit;
    }
}

/* ---------- Consulta ---------- */
$where = [];
$par   = [];
if ($q !== '') {
    $where[]   = '(nombre LIKE :q OR correo LIKE :q OR telefono LIKE :q)';
    $par[':q'] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($fRol !== '') {
    $where[]     = 'rol = :rol';
    $par[':rol'] = $fRol;
}
$cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM usuarios $cond");
$st->execute($par);
$total = (int) $st->fetchColumn();

$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare(
    "SELECT id, nombre, telefono, correo, rol
       FROM usuarios $cond
      ORDER BY id DESC
      LIMIT :lim OFFSET :off"
);
foreach ($par as $k => $val) {
    $st->bindValue($k, $val);
}
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

$desde = $total ? $offset + 1 : 0;
$hasta = $offset + count($usuarios);

$nums = [];
for ($i = 1; $i <= $paginas; $i++) {
    if ($i === 1 || $i === $paginas || abs($i - $pagina) <= 1) {
        $nums[] = $i;
    } elseif (end($nums) !== '…') {
        $nums[] = '…';
    }
}

$titulo      = 'Usuarios';
$adminActivo = 'Usuarios';
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
        <h1>Usuarios <span class="cuenta">· <?= $total ?></span></h1>
    </div>

    <?php if (($_GET['ok'] ?? '') === 'rol'): ?>
        <p class="alerta ok" role="status">Rol actualizado.</p>
    <?php endif; ?>
    <?php if ($errorGeneral): ?>
        <p class="alerta error" role="alert"><?= e($errorGeneral) ?></p>
    <?php endif; ?>

    <form class="filtros" method="get" action="usuarios.php" role="search">
        <div class="buscar">
            <label class="solo-lector" for="q">Buscar por nombre, correo o teléfono</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, correo o teléfono">
        </div>

        <label class="solo-lector" for="rol">Rol</label>
        <select id="rol" name="rol">
            <option value="">Rol</option>
            <?php foreach (ROLES as $valor => $texto): ?>
                <option value="<?= e($valor) ?>" <?= $fRol === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-sec" type="submit">Buscar</button>
    </form>

    <?php if (!$usuarios): ?>
        <p class="vacio">
            <?= ($q !== '' || $fRol !== '') ? 'No hay usuarios que coincidan con la búsqueda.' : 'Todavía no hay usuarios registrados.' ?>
        </p>
    <?php else: ?>
        <div class="tabla-cont">
            <table class="lista">
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Rol</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php $esYo = (int) $u['id'] === (int) $usuario['id']; ?>
                    <tr>
                        <td>
                            <strong><?= e($u['nombre']) ?></strong>
                            <?php if ($esYo): ?><span class="yo">(tú)</span><?php endif; ?>
                            <span class="cod">ID <?= (int) $u['id'] ?></span>
                        </td>
                        <td><?= e($u['correo']) ?></td>
                        <td><?= e($u['telefono'] ?: '—') ?></td>
                        <td>
                            <form class="rol-form" method="post" action="usuarios.php<?= e($qs()) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <select class="sel-rol rol-<?= e($u['rol']) ?>" name="rol_nuevo"
                                        aria-label="Rol de <?= e($u['nombre']) ?>"
                                        <?= $esYo ? 'disabled title="No puedes cambiar tu propio rol"' : 'onchange="this.form.submit()"' ?>>
                                    <?php foreach (ROLES as $valor => $texto): ?>
                                        <option value="<?= e($valor) ?>" <?= $u['rol'] === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$esYo): ?><button class="btn btn-sec btn-ok" type="submit">OK</button><?php endif; ?>
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
                        <a href="<?= e('usuarios.php' . $qs(['p' => $pagina - 1 > 1 ? $pagina - 1 : ''])) ?>" aria-label="Página anterior">‹</a>
                    <?php else: ?>
                        <span class="off" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <?php foreach ($nums as $n): ?>
                        <?php if ($n === '…'): ?>
                            <span aria-hidden="true">…</span>
                        <?php elseif ($n === $pagina): ?>
                            <span aria-current="page"><?= $n ?></span>
                        <?php else: ?>
                            <a href="<?= e('usuarios.php' . $qs(['p' => $n > 1 ? $n : ''])) ?>"><?= $n ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($pagina < $paginas): ?>
                        <a href="<?= e('usuarios.php' . $qs(['p' => $pagina + 1])) ?>" aria-label="Página siguiente">›</a>
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
