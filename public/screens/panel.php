<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/auth.php';
$u = requiere_login();

if (!function_exists('edad_texto')) {
    function edad_texto($meses): string
    {
        if ($meses === null) {
            return 'Edad no indicada';
        }
        $meses = (int) $meses;
        if ($meses < 12) {
            return $meses . ($meses === 1 ? ' mes' : ' meses');
        }
        $anios = intdiv($meses, 12);
        return $anios . ($anios === 1 ? ' año' : ' años');
    }
}

function fecha_corta(string $fecha): string
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($fecha);
    return $t ? date('j', $t) . ' ' . $meses[(int) date('n', $t) - 1] : '';
}

const VISTAS  = ['resumen', 'solicitudes', 'guardados', 'datos'];
const ESTADOS = ['pendiente' => 'En revisión', 'aceptada' => 'Aceptada', 'cancelada' => 'Cancelada'];

$vista = (string) ($_GET['vista'] ?? 'resumen');
if (!in_array($vista, VISTAS, true)) {
    $vista = 'resumen';
}
$url = fn(string $v): string => $v === 'resumen' ? 'panel.php' : 'panel.php?vista=' . $v;

$errores = [];
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if (!csrf_valido()) {
        $_SESSION['flash_panel'] = ['error', 'La sesión expiró. Intenta de nuevo.'];
        header('Location: ' . $url($vista));
        exit;
    }

    if ($accion === 'quitar_guardado') {
        $vista = 'guardados';
        try {
            $pdo->prepare('DELETE FROM guardados WHERE usuario_id = ? AND mascota_id = ?')
                ->execute([$u['id'], (int) ($_POST['mascota_id'] ?? 0)]);
            $_SESSION['flash_panel'] = ['ok', 'Quitaste la mascota de tus guardados.'];
        } catch (PDOException $ex) {
            $_SESSION['flash_panel'] = ['error', 'No se pudo quitar de guardados.'];
        }
        header('Location: ' . $url($vista));
        exit;
    }

    if ($accion === 'cancelar_solicitud') {
        $vista = 'solicitudes';
        $st = $pdo->prepare(
            "UPDATE solicitudes_adopcion SET estado = 'cancelada'
              WHERE id = ? AND usuario_id = ? AND estado = 'pendiente'"
        );
        $st->execute([(int) ($_POST['id'] ?? 0), $u['id']]);
        $_SESSION['flash_panel'] = $st->rowCount()
            ? ['ok', 'Tu solicitud fue cancelada.']
            : ['error', 'No se pudo cancelar la solicitud.'];
        header('Location: ' . $url($vista));
        exit;
    }

    if ($accion === 'actualizar_datos') {
        $vista = 'datos';
        $old['nombre']   = trim((string) ($_POST['nombre'] ?? ''));
        $old['telefono'] = trim((string) ($_POST['telefono'] ?? ''));

        if ($old['nombre'] === '' || mb_strlen($old['nombre']) > 100) {
            $errores['nombre'] = 'Escribe tu nombre (máximo 100 caracteres).';
        }
        if ($old['telefono'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['telefono'])) {
            $errores['telefono'] = 'Escribe un teléfono de 7 a 20 dígitos.';
        }
        if (!$errores) {
            $pdo->prepare('UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?')
                ->execute([$old['nombre'], $old['telefono'] !== '' ? $old['telefono'] : null, $u['id']]);
            $_SESSION['usuario']['nombre'] = $old['nombre'];
            $_SESSION['flash_panel'] = ['ok', 'Tus datos se actualizaron.'];
            header('Location: ' . $url($vista));
            exit;
        }
    }

    if ($accion === 'cambiar_password') {
        $vista  = 'datos';
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva  = (string) ($_POST['nueva'] ?? '');
        $nueva2 = (string) ($_POST['nueva2'] ?? '');

        $st = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        $hash = (string) $st->fetchColumn();

        if (!password_verify($actual, $hash)) {
            $errores['actual'] = 'La contraseña actual no es correcta.';
        }
        if (strlen($nueva) < 8) {
            $errores['nueva'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($nueva !== $nueva2) {
            $errores['nueva2'] = 'Las contraseñas no coinciden.';
        }
        if (!$errores) {
            $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            $_SESSION['flash_panel'] = ['ok', 'Tu contraseña se cambió correctamente.'];
            header('Location: ' . $url($vista));
            exit;
        }
    }
}

$flash = $_SESSION['flash_panel'] ?? null;
unset($_SESSION['flash_panel']);

$st = $pdo->prepare(
    "SELECT sa.id, sa.fecha, sa.estado,
            m.id AS mascota_id, m.nombre, m.edad_meses, m.imagen, m.disponible,
            e.nombre AS especie
       FROM solicitudes_adopcion sa
       JOIN mascotas m ON m.id = sa.mascota_id
       JOIN especies e ON e.id = m.especie_id
      WHERE sa.usuario_id = ?
      ORDER BY sa.fecha DESC, sa.id DESC"
);
$st->execute([$u['id']]);
$solicitudes = $st->fetchAll();

$activa = null;
foreach ($solicitudes as $s) {
    if (in_array($s['estado'], ['pendiente', 'aceptada'], true)) {
        $activa = $s;
        break;
    }
}

$etapas     = ['Recibida', 'En revisión', 'Visita', 'Decisión', 'Adopción'];
$alcanzadas = 0;
if ($activa) {
    $alcanzadas = $activa['estado'] === 'pendiente' ? 2 : ((int) $activa['disponible'] === 0 ? 5 : 4);
}

$guardados = [];
try {
    $sg = $pdo->prepare(
        'SELECT m.id, m.nombre, m.imagen, m.edad_meses, m.disponible, e.nombre AS especie
           FROM guardados g
           JOIN mascotas m ON m.id = g.mascota_id
           JOIN especies e ON e.id = m.especie_id
          WHERE g.usuario_id = ?
          ORDER BY g.id DESC'
    );
    $sg->execute([$u['id']]);
    $guardados = $sg->fetchAll();
} catch (PDOException $ex) {
    $guardados = [];
}

$sp = $pdo->prepare('SELECT nombre, correo, telefono FROM usuarios WHERE id = ?');
$sp->execute([$u['id']]);
$perfil = $sp->fetch() ?: ['nombre' => $u['nombre'], 'correo' => $u['correo'], 'telefono' => null];

$llenos = 0;
foreach (['nombre', 'correo', 'telefono'] as $c) {
    if (trim((string) ($perfil[$c] ?? '')) !== '') {
        $llenos++;
    }
}
$completo = (int) round($llenos / 3 * 100);

$valNombre   = $old['nombre']   ?? $perfil['nombre'];
$valTelefono = $old['telefono'] ?? (string) $perfil['telefono'];

$menu = [
    'resumen'     => 'Resumen',
    'solicitudes' => 'Solicitudes',
    'guardados'   => 'Guardados',
    'datos'       => 'Mis datos',
];

$activo    = 'panel';
$bodyClass = 'panel';
require __DIR__ . '/../partials/header.php';

function foto_pn(?string $imagen, string $alt = ''): void
{
    if (!empty($imagen)) {
        echo '<img src="../img/mascotas/' . e(basename($imagen)) . '" alt="' . e($alt) . '" loading="lazy">';
    } else {
        echo '<svg aria-hidden="true"><use href="#pata"/></svg>';
    }
}
?>

<div class="pn">
    <aside class="pn-lado">
        <div class="pn-lado-cab">
            <p class="pn-rol">MI CUENTA</p>
            <button type="button" class="pn-toggle" aria-expanded="false" aria-controls="pn-menu"><img src="../img/icons/config.svg" alt="Menú" loading="lazy"></button>
        </div>
        <div class="pn-menu" id="pn-menu">
            <nav aria-label="Mi cuenta">
                <?php foreach ($menu as $clave => $texto): ?>
                    <a href="<?= e($url($clave)) ?>" <?= $clave === $vista ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <section class="pn-main">
        <?php if ($flash): ?>
            <div class="alerta <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="<?= $flash[0] === 'ok' ? 'status' : 'alert' ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

<?php ?>
<?php if ($vista === 'resumen'): ?>
        <h1>Hola, <?= e(explode(' ', trim($u['nombre']))[0]) ?></h1>

        <?php if ($activa): ?>
            <article class="pn-activa" aria-labelledby="pn-activa-t">
                <div class="pn-activa-top">
                    <div class="pn-avatar"><?php foto_pn($activa['imagen'], $activa['nombre']); ?></div>
                    <div class="pn-activa-info">
                        <p class="pn-eyebrow" id="pn-activa-t">Solicitud activa</p>
                        <p class="pn-activa-mascota"><?= e($activa['nombre']) ?> · <?= e($activa['especie']) ?> · <?= e(edad_texto($activa['edad_meses'])) ?></p>
                        <p class="pn-activa-sub">Solicitud enviada el <?= e(fecha_corta($activa['fecha'])) ?> · <?= e(ESTADOS[$activa['estado']]) ?></p>
                    </div>
                    <a class="pn-pill pn-pill-borde" href="fichaMascota.php?id=<?= (int) $activa['mascota_id'] ?>">Ver detalle</a>
                </div>
                <ol class="pn-etapas" aria-label="Progreso de la solicitud">
                    <?php foreach ($etapas as $i => $nombreEtapa): ?>
                        <li class="<?= $i < $alcanzadas ? 'hecha' : '' ?>" <?= $i === $alcanzadas - 1 ? 'aria-current="step"' : '' ?>><?= e($nombreEtapa) ?></li>
                    <?php endforeach; ?>
                </ol>
            </article>
        <?php else: ?>
            <article class="pn-activa pn-vacia" aria-labelledby="pn-activa-t">
                <p class="pn-eyebrow" id="pn-activa-t">Solicitud activa</p>
                <p class="pn-activa-mascota">Aún no tienes solicitudes en curso.</p>
                <a class="pn-pill pn-pill-borde" href="mascotas.php">Ver mascotas</a>
            </article>
        <?php endif; ?>

        <div class="pn-cab">
            <h2>Guardados</h2>
            <?php if ($guardados): ?><a href="<?= e($url('guardados')) ?>">Ver todos</a><?php endif; ?>
        </div>
        <?php if ($guardados): ?>
            <ul class="pn-guardados">
                <?php foreach (array_slice($guardados, 0, 4) as $g): ?>
                    <li>
                        <a href="fichaMascota.php?id=<?= (int) $g['id'] ?>">
                            <span class="pn-foto"><?php foto_pn($g['imagen']); ?></span>
                            <span class="pn-nombre"><?= e($g['nombre']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="pn-nota">Todavía no tienes mascotas guardadas. <a href="mascotas.php">Explora el catálogo</a>.</p>
        <?php endif; ?>

        <?php if ($completo < 100): ?>
            <div class="pn-perfil">
                <p>Completa tu perfil de adoptante para agilizar solicitudes</p>
                <a class="pn-pill pn-pill-borde" href="<?= e($url('datos')) ?>">Completar <?= $completo ?>%</a>
            </div>
        <?php endif; ?>

<?php ?>
<?php elseif ($vista === 'solicitudes'): ?>
        <h1>Solicitudes</h1>
        <?php if (!$solicitudes): ?>
            <p class="pn-nota">Aquí aparecerán tus solicitudes de adopción. <a href="mascotas.php">Ver mascotas</a>.</p>
        <?php else: ?>
            <ul class="pn-sols">
                <?php foreach ($solicitudes as $s): ?>
                    <li>
                        <div class="pn-avatar"><?php foto_pn($s['imagen'], $s['nombre']); ?></div>
                        <div class="pn-sol-info">
                            <p class="pn-activa-mascota"><a href="fichaMascota.php?id=<?= (int) $s['mascota_id'] ?>"><?= e($s['nombre']) ?></a> · <?= e($s['especie']) ?> · <?= e(edad_texto($s['edad_meses'])) ?></p>
                            <p class="pn-activa-sub">Enviada el <?= e(fecha_corta($s['fecha'])) ?></p>
                        </div>
                        <span class="pn-estado pn-<?= e($s['estado']) ?>"><?= e(ESTADOS[$s['estado']] ?? $s['estado']) ?></span>
                        <?php if ($s['estado'] === 'pendiente'): ?>
                            <form method="post" action="<?= e($url('solicitudes')) ?>" onsubmit="return confirm('¿Cancelar esta solicitud?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="cancelar_solicitud">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="pn-pill pn-pill-borde">Cancelar</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

<?php ?>
<?php elseif ($vista === 'guardados'): ?>
        <h1>Guardados</h1>
        <?php if (!$guardados): ?>
            <p class="pn-nota">Todavía no tienes mascotas guardadas. <a href="mascotas.php">Explora el catálogo</a>.</p>
        <?php else: ?>
            <ul class="pn-guardados pn-guardados-full">
                <?php foreach ($guardados as $g): ?>
                    <li>
                        <a href="fichaMascota.php?id=<?= (int) $g['id'] ?>">
                            <span class="pn-foto"><?php foto_pn($g['imagen']); ?></span>
                            <span class="pn-nombre"><?= e($g['nombre']) ?></span>
                            <span class="pn-meta"><?= e($g['especie']) ?> · <?= e(edad_texto($g['edad_meses'])) ?><?= (int) $g['disponible'] === 0 ? ' · No disponible' : '' ?></span>
                        </a>
                        <form method="post" action="<?= e($url('guardados')) ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="accion" value="quitar_guardado">
                            <input type="hidden" name="mascota_id" value="<?= (int) $g['id'] ?>">
                            <button type="submit" class="pn-quitar">Quitar ♡</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

<?php ?>

<?php else: ?>
        <h1>Mis datos</h1>

        <form method="post" action="<?= e($url('datos')) ?>" class="pn-form" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="actualizar_datos">
            <h2>Información personal</h2>

            <div class="campo">
                <label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" value="<?= e($valNombre) ?>" maxlength="100" required autocomplete="name"<?= isset($errores['nombre']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['nombre'])): ?><p class="campo-error"><?= e($errores['nombre']) ?></p><?php endif; ?>
            </div>
            <div class="campo">
                <label for="correo">Correo</label>
                <input id="correo" type="email" value="<?= e($perfil['correo']) ?>" readonly>
            </div>
            <div class="campo">
                <label for="telefono">Teléfono <span class="opc">(opcional)</span></label>
                <input id="telefono" type="tel" name="telefono" value="<?= e($valTelefono) ?>" maxlength="20" autocomplete="tel"<?= isset($errores['telefono']) ? ' aria-invalid="true"' : '' ?>>
                <?php if (isset($errores['telefono'])): ?><p class="campo-error"><?= e($errores['telefono']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="btn">Guardar cambios</button>
        </form>
<?php endif; ?>
    </section>
</div>

<script>
(function () {
    var btn = document.querySelector('.pn-toggle');
    var lado = document.querySelector('.pn-lado');
    if (!btn || !lado) return;
    btn.addEventListener('click', function () {
        var abierto = lado.classList.toggle('abierto');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
