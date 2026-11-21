<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/auth.php';

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

function tamano_sigla(string $tamano): string
{
    return match (strtolower($tamano)) {
        'chico'   => 'S',
        'mediano' => 'M',
        'grande'  => 'L',
        default   => strtoupper($tamano),
    };
}

$leer = function (string $campo, array $permitidos): string {
    $v = $_GET[$campo] ?? '';
    return is_string($v) && in_array($v, $permitidos, true) ? $v : '';
};

$leerArray = function (string $campo, array $permitidos): array {
    $v = $_GET[$campo] ?? [];
    if (!is_array($v)) {
        $v = is_string($v) && $v !== '' ? [$v] : [];
    }
    return array_values(array_intersect($v, $permitidos));
};

// Obtener razas disponibles desde BD
$razasDisponibles = [];
try {
    $stRazas = $pdo->query("
        SELECT DISTINCT m.raza
          FROM mascotas m
         WHERE m.disponible = 1
           AND m.estado = 'disponible'
           AND NOT EXISTS (
               SELECT 1 FROM solicitudes_adopcion sa
                WHERE sa.mascota_id = m.id
                  AND sa.estado = 'aceptada'
           )
           AND m.raza IS NOT NULL
           AND m.raza <> ''
         ORDER BY m.raza ASC
    ");
    $razasDisponibles = $stRazas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $ex) {
    $razasDisponibles = [];
}

$filtros = [
    'especie'       => $leer('especie', ['perro', 'gato']),
    'edad'          => $leerArray('edad', ['cachorro', 'adulto', 'senior']),
    'tamano'        => $leer('tamano', ['chico', 'mediano', 'grande']),
    'sexo'          => $leer('sexo', ['macho', 'hembra']),
    'raza'          => $leer('raza', $razasDisponibles),
    'apto_ninos'    => isset($_GET['apto_ninos']) && $_GET['apto_ninos'] === '1',
    'apto_mascotas' => isset($_GET['apto_mascotas']) && $_GET['apto_mascotas'] === '1',
];

$orden = $leer('orden', ['recientes', 'antiguos', 'edad_asc', 'edad_desc']);
if ($orden === '') {
    $orden = 'recientes';
}

$totalFiltrosActivos = 0;
if ($filtros['especie'] !== '') $totalFiltrosActivos++;
$totalFiltrosActivos += count($filtros['edad']);
if ($filtros['tamano'] !== '') $totalFiltrosActivos++;
if ($filtros['sexo'] !== '') $totalFiltrosActivos++;
if ($filtros['raza'] !== '') $totalFiltrosActivos++;
if ($filtros['apto_ninos']) $totalFiltrosActivos++;
if ($filtros['apto_mascotas']) $totalFiltrosActivos++;

$hayFiltros = $totalFiltrosActivos > 0;

// Consultas
$sql = "SELECT m.id, m.nombre, m.sexo, m.raza, m.edad_meses, m.tamano, m.imagen,
               m.apto_ninos, m.apto_mascotas, s.nombre AS especie
          FROM mascotas m
          JOIN especies s ON s.id = m.especie_id
         WHERE m.disponible = 1
           AND m.estado = 'disponible'
           AND NOT EXISTS (
               SELECT 1 FROM solicitudes_adopcion sa
                WHERE sa.mascota_id = m.id
                  AND sa.estado = 'aceptada'
           )";
$params = [];

if ($filtros['especie'] !== '') {
    $sql .= ' AND LOWER(s.nombre) = ?';
    $params[] = $filtros['especie'];
}

if ($filtros['tamano'] !== '') {
    $sql .= ' AND m.tamano = ?';
    $params[] = $filtros['tamano'];
}

if ($filtros['sexo'] !== '') {
    $sql .= ' AND m.sexo = ?';
    $params[] = $filtros['sexo'];
}

if ($filtros['raza'] !== '') {
    $sql .= ' AND m.raza = ?';
    $params[] = $filtros['raza'];
}

if (!empty($filtros['edad'])) {
    $condicionesEdad = [];
    if (in_array('cachorro', $filtros['edad'], true)) {
        $condicionesEdad[] = 'm.edad_meses < 12';
    }
    if (in_array('adulto', $filtros['edad'], true)) {
        $condicionesEdad[] = 'm.edad_meses BETWEEN 12 AND 83';
    }
    if (in_array('senior', $filtros['edad'], true)) {
        $condicionesEdad[] = 'm.edad_meses >= 84';
    }
    if ($condicionesEdad) {
        $sql .= ' AND (' . implode(' OR ', $condicionesEdad) . ')';
    }
}

if ($filtros['apto_ninos']) {
    $sql .= ' AND m.apto_ninos = 1';
}

if ($filtros['apto_mascotas']) {
    $sql .= ' AND m.apto_mascotas = 1';
}

$orderBy = match ($orden) {
    'antiguos'  => 'm.id ASC',
    'edad_asc'  => 'm.edad_meses ASC',
    'edad_desc' => 'm.edad_meses DESC',
    default     => 'm.id DESC',
};
$sql .= " ORDER BY {$orderBy} LIMIT 60";

$mascotas = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $mascotas = $st->fetchAll();
} catch (PDOException $ex) {
    $mascotas = [];
}

$usuario = usuario_actual();

// 1. Endpoint rápido para guardar/quitar favorito sin recargar (POST AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_favorito') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$usuario) {
        $_SESSION['flash'] = 'Inicia sesión para guardar tus mascotas favoritas.';
        echo json_encode(['ok' => false, 'redirect' => 'login.php']);
        exit;
    }

    $mascotaId = filter_input(INPUT_POST, 'mascota_id', FILTER_VALIDATE_INT);
    if (!$mascotaId) {
        echo json_encode(['ok' => false]);
        exit;
    }

    try {
        $stExiste = $pdo->prepare('SELECT id FROM guardados WHERE usuario_id = ? AND mascota_id = ? LIMIT 1');
        $stExiste->execute([$usuario['id'], $mascotaId]);
        $fila = $stExiste->fetch();

        if ($fila) {
            $pdo->prepare('DELETE FROM guardados WHERE id = ?')->execute([$fila['id']]);
            echo json_encode(['ok' => true, 'guardado' => false]);
        } else {
            $pdo->prepare('INSERT INTO guardados (usuario_id, mascota_id) VALUES (?, ?)')->execute([$usuario['id'], $mascotaId]);
            echo json_encode(['ok' => true, 'guardado' => true]);
        }
    } catch (PDOException $ex) {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// Obtener IDs de mascotas guardadas
$guardadosIds = [];
if ($usuario) {
    try {
        $stG = $pdo->prepare('SELECT mascota_id FROM guardados WHERE usuario_id = ?');
        $stG->execute([$usuario['id']]);
        $guardadosIds = array_flip($stG->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $ex) {
        $guardadosIds = [];
    }
}

$bodyClass = 'catalogo';
require __DIR__ . '/../partials/header.php';
?>

<section id="mascotas" class="seccion">
    <div class="contenedor catalogo-layout">

        <div class="catalogo-backdrop" id="catalogoBackdrop" hidden></div>

        <!-- PANEL LATERAL DE FILTROS -->
        <aside class="catalogo-sidebar" id="panelFiltros" aria-label="Filtros de búsqueda">
            <div class="catalogo-sidebar-top">
                <h2>Filtros</h2>
                <button type="button" class="btn-sec cerrar-filtros" id="cerrarFiltros" aria-label="Cerrar filtros">✕</button>
            </div>

            <form method="get" action="" id="formFiltros">
                <input type="hidden" name="orden" value="<?= e($orden) ?>">

                <fieldset class="filtro-grupo">
                    <legend>ESPECIE</legend>
                    <div class="chips">
                        <label class="chip">
                            <input type="radio" name="especie" value="perro" <?= $filtros['especie'] === 'perro' ? 'checked' : '' ?>>
                            <span>Perro</span>
                        </label>
                        <label class="chip">
                            <input type="radio" name="especie" value="gato" <?= $filtros['especie'] === 'gato' ? 'checked' : '' ?>>
                            <span>Gato</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="filtro-grupo">
                    <legend>EDAD</legend>
                    <div class="check-lista">
                        <label>
                            <input type="checkbox" name="edad[]" value="cachorro" <?= in_array('cachorro', $filtros['edad'], true) ? 'checked' : '' ?>>
                            Cachorro
                        </label>
                        <label>
                            <input type="checkbox" name="edad[]" value="adulto" <?= in_array('adulto', $filtros['edad'], true) ? 'checked' : '' ?>>
                            Adulto
                        </label>
                        <label>
                            <input type="checkbox" name="edad[]" value="senior" <?= in_array('senior', $filtros['edad'], true) ? 'checked' : '' ?>>
                            Senior
                        </label>
                    </div>
                </fieldset>

                <fieldset class="filtro-grupo">
                    <legend>TAMAÑO</legend>
                    <div class="chips">
                        <label class="chip chip-circulo" title="Chico">
                            <input type="radio" name="tamano" value="chico" <?= $filtros['tamano'] === 'chico' ? 'checked' : '' ?>>
                            <span>S</span>
                        </label>
                        <label class="chip chip-circulo" title="Mediano">
                            <input type="radio" name="tamano" value="mediano" <?= $filtros['tamano'] === 'mediano' ? 'checked' : '' ?>>
                            <span>M</span>
                        </label>
                        <label class="chip chip-circulo" title="Grande">
                            <input type="radio" name="tamano" value="grande" <?= $filtros['tamano'] === 'grande' ? 'checked' : '' ?>>
                            <span>L</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="filtro-grupo">
                    <legend>SEXO</legend>
                    <div class="chips">
                        <label class="chip">
                            <input type="radio" name="sexo" value="macho" <?= $filtros['sexo'] === 'macho' ? 'checked' : '' ?>>
                            <span>Macho</span>
                        </label>
                        <label class="chip">
                            <input type="radio" name="sexo" value="hembra" <?= $filtros['sexo'] === 'hembra' ? 'checked' : '' ?>>
                            <span>Hembra</span>
                        </label>
                    </div>
                </fieldset>

                

                <fieldset class="filtro-grupo">
                    <legend>CONVIVENCIA</legend>
                    <div class="check-lista">
                        <label>
                            <input type="checkbox" name="apto_ninos" value="1" <?= $filtros['apto_ninos'] ? 'checked' : '' ?>>
                            Apto con niños
                        </label>
                        <label>
                            <input type="checkbox" name="apto_mascotas" value="1" <?= $filtros['apto_mascotas'] ? 'checked' : '' ?>>
                            Con otras mascotas
                        </label>
                    </div>
                </fieldset>

                <?php if ($hayFiltros): ?>
                    <a href="" class="limpiar-filtros">Limpiar filtros</a>
                <?php endif; ?>

                <button type="submit" class="btn btn-block aplicar-movil">Aplicar filtros</button>
            </form>
        </aside>

        <!-- ZONA DERECHA: CONTEO + ORDEN + CUADRÍCULA -->
        <div>
            <div class="catalogo-top">
                <h2 role="status"><?= count($mascotas) ?> <?= count($mascotas) === 1 ? 'mascota' : 'mascotas' ?></h2>

                <form method="get" action="" class="orden-form">
                    <?php foreach ($_GET as $k => $v): if ($k === 'orden') continue; ?>
                        <?php if (is_array($v)): ?>
                            <?php foreach ($v as $item): ?>
                                <input type="hidden" name="<?= e($k) ?>[]" value="<?= e((string)$item) ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <select name="orden" aria-label="Ordenar por" onchange="this.form.submit()">
                        <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Recientes</option>
                        <option value="antiguos" <?= $orden === 'antiguos' ? 'selected' : '' ?>>Más antiguos</option>
                        <option value="edad_asc" <?= $orden === 'edad_asc' ? 'selected' : '' ?>>Menor edad</option>
                        <option value="edad_desc" <?= $orden === 'edad_desc' ? 'selected' : '' ?>>Mayor edad</option>
                    </select>
                </form>

                <button type="button" class="btn-sec btn-movil-filtros" id="abrirFiltros">
                    Filtros<?= $totalFiltrosActivos > 0 ? " ({$totalFiltrosActivos})" : '' ?>
                </button>
            </div>

            <?php if (!$mascotas && $hayFiltros): ?>
                <p class="vacio">No encontramos mascotas con esos filtros. Prueba limpiando o cambiando alguna opción.</p>
            <?php elseif (!$mascotas): ?>
                <p class="vacio">Aún no hay mascotas publicadas. Cuando el refugio agregue nuevas, aparecerán aquí.</p>
            <?php else: ?>
                <ul class="tarjetas catalogo-grid">
                    <?php foreach ($mascotas as $m): ?>
                        <?php $esFavorito = isset($guardadosIds[$m['id']]); ?>
                        <li class="tarjeta-mascota">
                            <button
                                type="button"
                                class="btn-fav <?= $esFavorito ? 'activo' : '' ?>"
                                data-mascota-id="<?= (int) $m['id'] ?>"
                                aria-pressed="<?= $esFavorito ? 'true' : 'false' ?>"
                                aria-label="<?= $esFavorito ? 'Quitar de guardados' : 'Guardar en favoritos' ?>"
                                title="<?= $esFavorito ? 'Quitar de favoritos' : 'Guardar en favoritos' ?>"
                            >
                                <?= $esFavorito ? '♥' : '♡' ?>
                            </button>

                            <a class="tarjeta-link" href="fichaMascota.php?id=<?= (int) $m['id'] ?>">
                                <?php if (!empty($m['imagen'])): ?>
                                    <img class="foto" src="../img/mascotas/<?= e(basename($m['imagen'])) ?>" alt="<?= e($m['nombre']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="foto foto-vacia" aria-hidden="true"><svg><use href="#pata"/></svg></div>
                                <?php endif; ?>
                                
                                <div class="tarjeta-cuerpo">
                                    <h3><?= e($m['nombre']) ?></h3>
                                    <p class="meta">
                                        <?= e($m['especie']) ?> · <?= e(edad_texto($m['edad_meses'])) ?> · <?= e(tamano_sigla($m['tamano'])) ?>
                                    </p>
                                    <?php if ($m['apto_ninos'] || $m['apto_mascotas']): ?>
                                        <p class="etiquetas">
                                            <?php if ($m['apto_ninos']): ?><span>Niños</span><?php endif; ?>
                                            <?php if ($m['apto_mascotas']): ?><span>Mascotas</span><?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formFiltros');
    const panel = document.getElementById('panelFiltros');
    const backdrop = document.getElementById('catalogoBackdrop');
    const abrir = document.getElementById('abrirFiltros');
    const cerrar = document.getElementById('cerrarFiltros');

    let ultimoRadio = null;
    form.querySelectorAll('input[type="radio"]').forEach(r => {
        if (r.checked) ultimoRadio = r;
        r.addEventListener('click', function () {
            if (ultimoRadio === this) {
                this.checked = false;
                ultimoRadio = null;
            } else {
                ultimoRadio = this;
            }
            if (window.innerWidth > 760) form.submit();
        });
    });

    form.querySelectorAll('input[type="checkbox"], select').forEach(el => {
        el.addEventListener('change', () => {
            if (window.innerWidth > 760) form.submit();
        });
    });

    abrir?.addEventListener('click', () => {
        panel.classList.add('abierto');
        backdrop.hidden = false;
    });
    const cerrarFn = () => {
        panel.classList.remove('abierto');
        backdrop.hidden = true;
    };
    cerrar?.addEventListener('click', cerrarFn);
    backdrop?.addEventListener('click', cerrarFn);

    // Botón de favoritos
    document.querySelectorAll('.btn-fav').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const datos = new FormData();
            datos.append('accion', 'toggle_favorito');
            datos.append('mascota_id', btn.dataset.mascotaId);

            try {
                const res = await fetch('', { method: 'POST', body: datos });
                const json = await res.json();

                if (json.redirect) {
                    window.location.href = json.redirect;
                    return;
                }

                if (json.ok) {
                    btn.classList.toggle('activo', json.guardado);
                    btn.setAttribute('aria-pressed', json.guardado ? 'true' : 'false');
                    btn.textContent = json.guardado ? '♥' : '♡';
                    btn.title = json.guardado ? 'Quitar de favoritos' : 'Guardar en favoritos';
                }
            } catch (err) {
                console.error('Error al actualizar favoritos', err);
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>