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

function ruta_foto(?string $archivo): string
{
    if (!$archivo) {
        return '';
    }
    return '../img/mascotas/' . basename($archivo);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: mascotas.php');
    exit;
}

$usuario      = usuario_actual();
$mensajeOk    = '';
$mensajeError = '';
$yaSolicito   = false;

// ===============================================
// PROCESAR "INICIAR SOLICITUD"

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar_solicitud') {
    //Si no está logueado, guardar aviso flash y mandar a login.php
    if (!$usuario) {
        $_SESSION['flash'] = 'Inicia sesión para poder enviar tu solicitud de adopción.';
        header('Location: login.php');
        exit;
    }

    //Validar CSRF igual que en login.php
    if (!csrf_valido()) {
        $mensajeError = 'La sesión expiró. Recarga la página e intenta de nuevo.';
    } else {
        try {
            // 3. Revisar si la mascota sigue disponible
            $stCheck = $pdo->prepare('SELECT disponible, estado FROM mascotas WHERE id = ? LIMIT 1');
            $stCheck->execute([$id]);
            $estadoMascota = $stCheck->fetch();

            if (!$estadoMascota || !$estadoMascota['disponible'] || $estadoMascota['estado'] !== 'disponible') {
                $mensajeError = 'Esta mascota ya no se encuentra disponible para adopción.';
            } else {
                // 4. Evitar solicitudes duplicadas del mismo usuario para la misma mascota
                $stDup = $pdo->prepare("
                    SELECT id FROM solicitudes_adopcion
                     WHERE usuario_id = ? AND mascota_id = ? AND estado IN ('pendiente', 'aceptada')
                     LIMIT 1
                ");
                $stDup->execute([$usuario['id'], $id]);

                if ($stDup->fetch()) {
                    $mensajeError = 'Ya tienes una solicitud activa para esta mascota.';
                } else {
                    // 5. Insertar en solicitudes_adopcion
                    $stIns = $pdo->prepare("
                        INSERT INTO solicitudes_adopcion (usuario_id, mascota_id, motivo, estado)
                        VALUES (?, ?, ?, 'pendiente')
                    ");
                    $stIns->execute([
                        $usuario['id'],
                        $id,
                        'Solicitud iniciada desde la ficha de mascota.'
                    ]);

                    $mensajeOk = '¡Tu solicitud de adopción fue enviada! Puedes verla en tu panel.';
                }
            }
        } catch (PDOException $ex) {
            $mensajeError = 'Ocurrió un error al registrar tu solicitud. Inténtalo de nuevo.';
        }
    }
}

// ========================================================= el led se la come
// CARGAR DATOS DE LA MASCOTA Y SUS FOTOS
$mascota = null;
$fotos   = [];

try {
    $st = $pdo->prepare('
        SELECT m.*, s.nombre AS especie
          FROM mascotas m
          JOIN especies s ON s.id = m.especie_id
         WHERE m.id = ?
         LIMIT 1
    ');
    $st->execute([$id]);
    $mascota = $st->fetch();

    if ($mascota) {
        if ($usuario) {
            $stSol = $pdo->prepare("
                SELECT id FROM solicitudes_adopcion
                 WHERE usuario_id = ? AND mascota_id = ? AND estado IN ('pendiente', 'aceptada')
                 LIMIT 1
            ");
            $stSol->execute([$usuario['id'], $id]);
            $yaSolicito = (bool) $stSol->fetch();
        }

        // Fotos de la galería
        $stFotos = $pdo->prepare('
            SELECT id, ruta, es_portada
              FROM mascota_fotos
             WHERE mascota_id = ?
             ORDER BY es_portada DESC, id ASC
        ');
        $stFotos->execute([$id]);
        $filasFotos = $stFotos->fetchAll();

        $rutasVistas = [];
        if (!empty($mascota['imagen'])) {
            $r = ruta_foto($mascota['imagen']);
            $fotos[] = $r;
            $rutasVistas[basename($r)] = true;
        }
        foreach ($filasFotos as $f) {
            $base = basename($f['ruta']);
            if (!isset($rutasVistas[$base])) {
                $fotos[] = ruta_foto($f['ruta']);
                $rutasVistas[$base] = true;
            }
        }
    }
} catch (PDOException $ex) {
    $mascota = null;
}

if (!$mascota) {
    header('Location: mascotas.php');
    exit;
}

$esHembra = ($mascota['sexo'] === 'hembra');
$especieGenero = match (strtolower($mascota['especie'])) {
    'perro' => $esHembra ? 'Perra' : 'Perro',
    'gato'  => $esHembra ? 'Gata' : 'Gato',
    default => $mascota['especie'],
};
$tamanoGenero = match (strtolower($mascota['tamano'])) {
    'chico'   => $esHembra ? 'Chica' : 'Chico',
    'mediano' => $esHembra ? 'Mediana' : 'Mediano',
    'grande'  => 'Grande',
    default   => ucfirst($mascota['tamano']),
};

$titulo    = $mascota['nombre'];
$activo    = 'mascotas';
$bodyClass = 'ficha-page';
require __DIR__ . '/../partials/header.php';
?>

<section class="ficha-seccion">
    <div class="contenedor">

        <!-- Alertas de confirmación o error -->
        <?php if ($mensajeOk !== ''): ?>
            <p class="alerta ok" role="status"><?= e($mensajeOk) ?></p>
        <?php endif; ?>
        <?php if ($mensajeError !== ''): ?>
            <p class="alerta error" role="alert"><?= e($mensajeError) ?></p>
        <?php endif; ?>

        <!-- Migas de pan -->
        <nav class="ficha-breadcrumb" aria-label="Ruta de navegación">
            <a href="mascotas.php">Catálogo</a>
            <span aria-hidden="true">/</span>
            <a href="mascotas.php?especie=<?= urlencode(strtolower($mascota['especie'])) ?>">
                <?= e($mascota['especie']) ?>s
            </a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($mascota['nombre']) ?></span>
        </nav>

        <!-- MARCO DE FOTOS -->
        <div class="ficha-hero">
            <?php if (!empty($fotos)): ?>
                <div class="ficha-slides" id="fichaSlides">
                    <?php foreach ($fotos as $idx => $src): ?>
                        <img
                            src="<?= e($src) ?>"
                            alt="<?= e($mascota['nombre']) ?> (foto <?= $idx + 1 ?> de <?= count($fotos) ?>)"
                            class="ficha-slide <?= $idx === 0 ? 'activa' : '' ?>"
                            data-indice="<?= $idx ?>"
                            <?= $idx > 0 ? 'loading="lazy"' : '' ?>
                        >
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ficha-vacia" aria-hidden="true">
                    <svg><use href="#pata"/></svg>
                </div>
            <?php endif; ?>

            <div class="ficha-hero-pie">
                <h1 class="ficha-nombre"><?= e($mascota['nombre']) ?></h1>

                <?php if (count($fotos) > 1): ?>
                    <div class="ficha-contador" aria-label="Control de galería">
                        <button type="button" id="fotoPrev" aria-label="Foto anterior">&lsaquo;</button>
                        <span id="fotoIndicador">1/<?= count($fotos) ?></span>
                        <button type="button" id="fotoNext" aria-label="Foto siguiente">&rsaquo;</button>
                    </div>
                <?php elseif (count($fotos) === 1): ?>
                    <span class="ficha-contador">1/1</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- PESTAÑAS -->
        <div class="ficha-tabs" role="tablist" aria-label="Información de <?= e($mascota['nombre']) ?>">
            <button type="button" class="ficha-tab activa" role="tab" aria-selected="true" data-tab="sobre-mi">Sobre mí</button>
            <button type="button" class="ficha-tab" role="tab" aria-selected="false" data-tab="salud">Salud</button>
            <button type="button" class="ficha-tab" role="tab" aria-selected="false" data-tab="convivencia">Convivencia</button>
            <button type="button" class="ficha-tab" role="tab" aria-selected="false" data-tab="fotos">
                Fotos<?= count($fotos) > 0 ? ' (' . count($fotos) . ')' : '' ?>
            </button>
        </div>

        <!-- CONTENIDO DE PESTAÑAS -->
        <div class="ficha-cuerpo">
            <div class="ficha-paneles">

                <!-- TAB 1: SOBRE MÍ -->
                <div class="ficha-panel activo" id="panel-sobre-mi" role="tabpanel">
                    <p class="ficha-meta">
                        <?= e($especieGenero) ?>
                        <?php if (!empty($mascota['raza'])): ?>
                            · <?= e($mascota['raza']) ?>
                        <?php endif; ?>
                        · <?= e(edad_texto($mascota['edad_meses'])) ?>
                        · <?= e($tamanoGenero) ?>
                    </p>

                    <div class="ficha-descripcion">
                        <?php if (!empty($mascota['descripcion'])): ?>
                            <p><?= nl2br(e($mascota['descripcion'])) ?></p>
                        <?php else: ?>
                            <p><?= e($mascota['nombre']) ?> está buscando un hogar lleno de cariño y cuidados.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($mascota['fecha_ingreso'])): ?>
                        <p class="ficha-ingreso">En el refugio desde: <?= e(date('d/m/Y', strtotime($mascota['fecha_ingreso']))) ?></p>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: SALUD -->
                <div class="ficha-panel" id="panel-salud" role="tabpanel" hidden>
                    <ul class="ficha-checklist">
                        <li class="<?= $mascota['vacunado'] ? 'si' : 'no' ?>">
                            <span><?= $mascota['vacunado'] ? '✓' : '✕' ?></span>
                            Vacunas al día
                        </li>
                        <li class="<?= $mascota['esterilizado'] ? 'si' : 'no' ?>">
                            <span><?= $mascota['esterilizado'] ? '✓' : '✕' ?></span>
                            <?= $esHembra ? 'Esterilizada' : 'Esterilizado' ?>
                        </li>
                        <li class="<?= $mascota['desparasitado'] ? 'si' : 'no' ?>">
                            <span><?= $mascota['desparasitado'] ? '✓' : '✕' ?></span>
                            <?= $esHembra ? 'Desparasitada' : 'Desparasitado' ?>
                        </li>
                    </ul>
                </div>

                <!-- TAB 3: CONVIVENCIA -->
                <div class="ficha-panel" id="panel-convivencia" role="tabpanel" hidden>
                    <ul class="ficha-checklist">
                        <li class="<?= $mascota['apto_ninos'] ? 'si' : 'no' ?>">
                            <span><?= $mascota['apto_ninos'] ? '✓' : '✕' ?></span>
                            Convive bien con niños
                        </li>
                        <li class="<?= $mascota['apto_mascotas'] ? 'si' : 'no' ?>">
                            <span><?= $mascota['apto_mascotas'] ? '✓' : '✕' ?></span>
                            Convive bien con otras mascotas
                        </li>
                    </ul>
                </div>

                <!-- TAB 4: FOTOS -->
                <div class="ficha-panel" id="panel-fotos" role="tabpanel" hidden>
                    <?php if (!empty($fotos)): ?>
                        <div class="ficha-galeria-grid">
                            <?php foreach ($fotos as $idx => $src): ?>
                                <button type="button" class="ficha-thumb" data-ir-foto="<?= $idx ?>" aria-label="Ver foto <?= $idx + 1 ?>">
                                    <img src="<?= e($src) ?>" alt="<?= e($mascota['nombre']) ?> foto <?= $idx + 1 ?>" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Aún no hay fotografías adicionales cargadas para <?= e($mascota['nombre']) ?>.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- COLUMNA DERECHA: APTA PARA -->
            <aside class="ficha-apto">
                <h2><?= $esHembra ? 'APTA PARA' : 'APTO PARA' ?></h2>
                <ul class="ficha-apto-lista">
                    <li class="<?= $mascota['apto_ninos'] ? 'si' : 'no' ?>">
                        <span aria-hidden="true"><?= $mascota['apto_ninos'] ? '✓' : '✕' ?></span> Niños
                    </li>
                    <li class="<?= $mascota['apto_mascotas'] ? 'si' : 'no' ?>">
                        <span aria-hidden="true"><?= $mascota['apto_mascotas'] ? '✓' : '✕' ?></span> Otras mascotas
                    </li>
                </ul>
            </aside>
        </div>

    </div>
</section>

<!-- SECCIÓN INFERIOR SALVIA: PROGRESO DE ADOPCIÓN -->
<section class="ficha-adopcion seccion-salvia">
    <div class="contenedor">
        <h2>¿Cómo adoptar a <?= e($mascota['nombre']) ?>?</h2>

        <ol class="pasos-linea ficha-pasos">
            <li>
                <span class="num">1</span>
                <span>Elige</span>
            </li>
            <li>
                <span class="num">2</span>
                <span>Solicita</span>
            </li>
            <li>
                <span class="num">3</span>
                <span><?= $esHembra ? 'Conócela' : 'Conócelo' ?></span>
            </li>
        </ol>

        <div class="ficha-acciones">
            <?php if ($mascota['disponible'] && $mascota['estado'] === 'disponible'): ?>
                <?php if ($yaSolicito): ?>
                    <span class="pill pill-ok">Ya enviaste una solicitud para <?= e($mascota['nombre']) ?></span>
                <?php else: ?>
                    <form method="post" action="fichaMascota.php?id=<?= (int) $mascota['id'] ?>" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="iniciar_solicitud">
                        <button type="submit" class="btn">Iniciar solicitud</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <span class="pill">Estado actual: <?= e(ucfirst($mascota['estado'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.ficha-tab');
    const paneles = document.querySelectorAll('.ficha-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => {
                t.classList.remove('activa');
                t.setAttribute('aria-selected', 'false');
            });
            paneles.forEach(p => {
                p.classList.remove('activo');
                p.hidden = true;
            });

            tab.classList.add('activa');
            tab.setAttribute('aria-selected', 'true');
            const objetivo = document.getElementById('panel-' + tab.dataset.tab);
            if (objetivo) {
                objetivo.classList.add('activo');
                objetivo.hidden = false;
            }
        });
    });

    const slides = document.querySelectorAll('.ficha-slide');
    const indicador = document.getElementById('fotoIndicador');
    const btnPrev = document.getElementById('fotoPrev');
    const btnNext = document.getElementById('fotoNext');
    let actual = 0;

    const mostrarSlide = (nuevoIndice) => {
        if (!slides.length) return;
        actual = (nuevoIndice + slides.length) % slides.length;
        slides.forEach((s, i) => s.classList.toggle('activa', i === actual));
        if (indicador) indicador.textContent = `${actual + 1}/${slides.length}`;
    };

    btnPrev?.addEventListener('click', () => mostrarSlide(actual - 1));
    btnNext?.addEventListener('click', () => mostrarSlide(actual + 1));

    document.querySelectorAll('[data-ir-foto]').forEach(btn => {
        btn.addEventListener('click', () => {
            mostrarSlide(parseInt(btn.dataset.irFoto, 10));
            document.querySelector('.ficha-hero')?.scrollIntoView({ behavior: 'smooth' });
        });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>