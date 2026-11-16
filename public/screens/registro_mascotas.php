<?php
/**
 * registro_mascotas.php
 * Formulario de alta de mascotas (solo administradores).
 * Guarda en: mascotas y mascota_fotos (ver esquema.sql).
 *
 * AJUSTAR SEGÚN TU PROYECTO:
 *  - Ruta de auth.php (la misma que usa header.php).
 *  - Ruta de la conexión a BD: se espera una variable $pdo (PDO).
 */
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../config/database.php'; // debe definir $pdo

/* ---------- Acceso: solo admin ---------- */
$usuario = usuario_actual();
if (!$usuario) {
    header('Location: login.php');
    exit;
}
if (($usuario['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso restringido a administradores.');
}

/* ---------- Constantes ---------- */
const MAX_FOTOS   = 6;
const MAX_BYTES   = 5 * 1024 * 1024; // 5 MB por foto
const MIMES_OK    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const DIR_FOTOS   = __DIR__ . '/../img/mascotas/';   // ruta física
const RUTA_FOTOS  = 'img/mascotas/';                 // ruta relativa guardada en BD
const ESTADOS_PUB = [
    'disponible'     => 'Disponible',
    'reservado'      => 'Reservado',
    'adoptado'       => 'Adoptado',
    'en_tratamiento' => 'En tratamiento',
];
const TAMANOS = ['chico' => 'S', 'mediano' => 'M', 'grande' => 'L'];

/* ---------- Datos de apoyo ---------- */
$especies   = $pdo->query('SELECT id, nombre FROM especies ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$idsEspecie = array_column($especies, 'id');

/* ---------- Valores por defecto ---------- */
$v = [
    'especie_id'    => $idsEspecie[0] ?? '',
    'nombre'        => '',
    'sexo'          => 'macho',
    'raza'          => '',
    'edad_meses'    => '',
    'tamano'        => 'mediano',
    'apto_ninos'    => false,
    'apto_mascotas' => false,
    'vacunado'      => false,
    'esterilizado'  => false,
    'desparasitado' => false,
    'descripcion'   => '',
    'estado'        => 'disponible',
    'fecha_ingreso' => date('Y-m-d'),
];
$errores = [];
$exito   = isset($_GET['ok']);

/* ---------- Modo edición (?id=N) ---------- */
$id            = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$editando      = $id > 0;
$fotosActuales = [];
if ($editando) {
    $st = $pdo->prepare('SELECT * FROM mascotas WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        header('Location: lista_mascotas.php');
        exit;
    }
    $st = $pdo->prepare('SELECT id, ruta FROM mascota_fotos WHERE mascota_id = ? ORDER BY es_portada DESC, id');
    $st->execute([$id]);
    $fotosActuales = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        foreach (['nombre', 'sexo', 'raza', 'edad_meses', 'tamano', 'descripcion', 'fecha_ingreso'] as $c) {
            $v[$c] = (string) ($m[$c] ?? '');
        }
        $v['especie_id'] = (int) $m['especie_id'];
        foreach (['apto_ninos', 'apto_mascotas', 'vacunado', 'esterilizado', 'desparasitado'] as $b) {
            $v[$b] = (bool) $m[$b];
        }
        $v['estado'] = isset(ESTADOS_PUB[$m['estado']]) ? $m['estado'] : 'disponible';
    }
}

/* ---------- Procesar envío ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals((string) csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        $errores['general'] = 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.';
    }

    $accion = ($_POST['accion'] ?? '') === 'borrador' ? 'borrador' : 'publicar';

    $v['especie_id']    = (int) ($_POST['especie_id'] ?? 0);
    $v['nombre']        = trim((string) ($_POST['nombre'] ?? ''));
    $v['sexo']          = (string) ($_POST['sexo'] ?? '');
    $v['raza']          = trim((string) ($_POST['raza'] ?? ''));
    $v['edad_meses']    = trim((string) ($_POST['edad_meses'] ?? ''));
    $v['tamano']        = (string) ($_POST['tamano'] ?? '');
    $v['apto_ninos']    = isset($_POST['apto_ninos']);
    $v['apto_mascotas'] = isset($_POST['apto_mascotas']);
    $v['vacunado']      = isset($_POST['vacunado']);
    $v['esterilizado']  = isset($_POST['esterilizado']);
    $v['desparasitado'] = isset($_POST['desparasitado']);
    $v['descripcion']   = trim((string) ($_POST['descripcion'] ?? ''));
    $v['estado']        = (string) ($_POST['estado'] ?? 'disponible');
    $v['fecha_ingreso'] = trim((string) ($_POST['fecha_ingreso'] ?? ''));

    // Validaciones
    if (!in_array($v['especie_id'], array_map('intval', $idsEspecie), true)) {
        $errores['especie_id'] = 'Elige una especie.';
    }
    if ($v['nombre'] === '') {
        $errores['nombre'] = 'Escribe el nombre de la mascota.';
    } elseif (mb_strlen($v['nombre']) > 100) {
        $errores['nombre'] = 'Máximo 100 caracteres.';
    }
    if (!in_array($v['sexo'], ['macho', 'hembra'], true)) {
        $errores['sexo'] = 'Elige el sexo.';
    }
    if (mb_strlen($v['raza']) > 80) {
        $errores['raza'] = 'Máximo 80 caracteres.';
    }
    if ($v['edad_meses'] !== '') {
        if (!ctype_digit($v['edad_meses']) || (int) $v['edad_meses'] > 600) {
            $errores['edad_meses'] = 'Escribe la edad en meses (0 a 600).';
        }
    }
    if (!array_key_exists($v['tamano'], TAMANOS)) {
        $errores['tamano'] = 'Elige el tamaño.';
    }
    if (!array_key_exists($v['estado'], ESTADOS_PUB)) {
        $errores['estado'] = 'Estado no válido.';
    }
    if ($v['fecha_ingreso'] !== '') {
        $f = DateTime::createFromFormat('Y-m-d', $v['fecha_ingreso']);
        if (!$f || $f->format('Y-m-d') !== $v['fecha_ingreso']) {
            $errores['fecha_ingreso'] = 'Fecha no válida.';
        }
    }

    // Fotos
    $idsActuales = array_map('intval', array_column($fotosActuales, 'id'));
    $quitar      = array_values(array_intersect($idsActuales, array_map('intval', (array) ($_POST['quitar'] ?? []))));
    $cupo        = MAX_FOTOS - (count($idsActuales) - count($quitar));
    $fotos = [];
    if (!empty($_FILES['fotos']['name'][0])) {
        $total = count($_FILES['fotos']['name']);
        if ($total > $cupo) {
            $errores['fotos'] = 'Máximo ' . MAX_FOTOS . ' fotos por mascota.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            for ($i = 0; $i < $total; $i++) {
                $err = $_FILES['fotos']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $tmp  = $_FILES['fotos']['tmp_name'][$i];
                $mime = ($err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) ? $finfo->file($tmp) : '';
                if ($err !== UPLOAD_ERR_OK || !isset(MIMES_OK[$mime])) {
                    $errores['fotos'] = 'Solo se permiten imágenes JPG, PNG o WebP.';
                    break;
                }
                if ($_FILES['fotos']['size'][$i] > MAX_BYTES) {
                    $errores['fotos'] = 'Cada foto debe pesar máximo 5 MB.';
                    break;
                }
                $fotos[] = ['tmp' => $tmp, 'ext' => MIMES_OK[$mime]];
            }
        }
    }

    // Guardar
    if (!$errores) {
        $estadoFinal = $accion === 'borrador' ? 'borrador' : $v['estado'];
        $guardados   = [];
        $rutasBorrar = [];

        try {
            if ($fotos && !is_dir(DIR_FOTOS) && !mkdir(DIR_FOTOS, 0755, true)) {
                throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
            }

            $pdo->beginTransaction();

            $params = [
                ':especie_id'    => $v['especie_id'],
                ':nombre'        => $v['nombre'],
                ':sexo'          => $v['sexo'],
                ':raza'          => $v['raza'] !== '' ? $v['raza'] : null,
                ':descripcion'   => $v['descripcion'] !== '' ? $v['descripcion'] : null,
                ':edad_meses'    => $v['edad_meses'] !== '' ? (int) $v['edad_meses'] : null,
                ':tamano'        => $v['tamano'],
                ':apto_ninos'    => (int) $v['apto_ninos'],
                ':apto_mascotas' => (int) $v['apto_mascotas'],
                ':esterilizado'  => (int) $v['esterilizado'],
                ':vacunado'      => (int) $v['vacunado'],
                ':desparasitado' => (int) $v['desparasitado'],
                ':disponible'    => (int) ($estadoFinal === 'disponible'),
                ':estado'        => $estadoFinal,
                ':fecha_ingreso' => $v['fecha_ingreso'] !== '' ? $v['fecha_ingreso'] : null,
            ];

            if ($editando) {
                $st = $pdo->prepare(
                    'UPDATE mascotas SET
                        especie_id = :especie_id, nombre = :nombre, sexo = :sexo, raza = :raza,
                        descripcion = :descripcion, edad_meses = :edad_meses, tamano = :tamano,
                        apto_ninos = :apto_ninos, apto_mascotas = :apto_mascotas,
                        esterilizado = :esterilizado, vacunado = :vacunado, desparasitado = :desparasitado,
                        disponible = :disponible, estado = :estado, fecha_ingreso = :fecha_ingreso
                     WHERE id = :id'
                );
                $params[':id'] = $id;
                $st->execute($params);
                $mascotaId = $id;
            } else {
                $st = $pdo->prepare(
                    'INSERT INTO mascotas
                     (especie_id, nombre, sexo, raza, descripcion, edad_meses, tamano,
                      apto_ninos, apto_mascotas, esterilizado, vacunado, desparasitado,
                      disponible, estado, fecha_ingreso)
                     VALUES
                     (:especie_id, :nombre, :sexo, :raza, :descripcion, :edad_meses, :tamano,
                      :apto_ninos, :apto_mascotas, :esterilizado, :vacunado, :desparasitado,
                      :disponible, :estado, :fecha_ingreso)'
                );
                $st->execute($params);
                $mascotaId = (int) $pdo->lastInsertId();
            }

            // Fotos marcadas para quitar
            if ($quitar) {
                $marcas = implode(',', array_fill(0, count($quitar), '?'));
                $args   = array_merge([$mascotaId], $quitar);
                $sel = $pdo->prepare("SELECT ruta FROM mascota_fotos WHERE mascota_id = ? AND id IN ($marcas)");
                $sel->execute($args);
                $rutasBorrar = $sel->fetchAll(PDO::FETCH_COLUMN);
                $pdo->prepare("DELETE FROM mascota_fotos WHERE mascota_id = ? AND id IN ($marcas)")->execute($args);
            }

            // Fotos nuevas
            if ($fotos) {
                $stFoto = $pdo->prepare('INSERT INTO mascota_fotos (mascota_id, ruta, es_portada) VALUES (?, ?, 0)');
                foreach ($fotos as $foto) {
                    $nombreArchivo = $mascotaId . '-' . bin2hex(random_bytes(6)) . '.' . $foto['ext'];
                    if (!move_uploaded_file($foto['tmp'], DIR_FOTOS . $nombreArchivo)) {
                        throw new RuntimeException('No se pudo guardar una de las fotos.');
                    }
                    $guardados[] = DIR_FOTOS . $nombreArchivo;
                    $stFoto->execute([$mascotaId, RUTA_FOTOS . $nombreArchivo]);
                }
            }

            // Portada = la primera foto que quede (la portada actual si se conserva)
            $todas = $pdo->prepare('SELECT id, ruta FROM mascota_fotos WHERE mascota_id = ? ORDER BY es_portada DESC, id');
            $todas->execute([$mascotaId]);
            $lista = $todas->fetchAll(PDO::FETCH_ASSOC);
            if ($lista) {
                $pdo->prepare('UPDATE mascota_fotos SET es_portada = 0 WHERE mascota_id = ?')->execute([$mascotaId]);
                $pdo->prepare('UPDATE mascota_fotos SET es_portada = 1 WHERE id = ?')->execute([$lista[0]['id']]);
                $pdo->prepare('UPDATE mascotas SET imagen = ? WHERE id = ?')->execute([$lista[0]['ruta'], $mascotaId]);
            } elseif ($quitar) {
                $pdo->prepare('UPDATE mascotas SET imagen = NULL WHERE id = ?')->execute([$mascotaId]);
            }

            $pdo->commit();

            foreach ($rutasBorrar as $r) {
                if (strpos($r, RUTA_FOTOS) === 0) {
                    @unlink(__DIR__ . '/../' . $r);
                }
            }

            header('Location: ' . ($editando ? 'lista_mascotas.php?ok=1' : 'registro_mascotas.php?ok=1'));
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($guardados as $archivo) {
                @unlink($archivo);
            }
            $errores['general'] = 'No se pudo guardar la mascota. Inténtalo de nuevo.';
        }
    }
}

/* ---------- Helpers de vista ---------- */
$chk = fn(bool $c): string => $c ? 'checked' : '';
$inv = fn(string $campo): string => isset($errores[$campo]) ? 'aria-invalid="true" aria-describedby="err-' . $campo . '"' : '';
$err = function (string $campo) use ($errores): string {
    return isset($errores[$campo])
        ? '<p class="campo-error" id="err-' . $campo . '">' . e($errores[$campo]) . '</p>'
        : '';
};

$titulo = $editando ? 'Editar mascota' : 'Registrar mascota';
$activo = 'mascotas';
require __DIR__ . '/../partials/header.php';
?>

<style>
    /* Estilos propios de este formulario (usan las variables de huellitas.css) */
    .rm { padding-block: 32px 64px; }

    .rm-cab {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 32px;
    }
    .rm-ruta { margin: 0 0 6px; font-size: .9rem; }
    .rm-ruta a { text-decoration: none; }
    .rm-ruta a:hover { text-decoration: underline; }
    .rm h1 { font-size: clamp(2rem, 5vw, 2.8rem); }
    .rm-acciones { display: flex; flex-wrap: wrap; gap: 12px; }

    .rm-grid { display: grid; gap: 36px; }
    @media (min-width: 860px) {
        .rm-grid { grid-template-columns: 1.1fr .9fr; gap: 48px; }
    }

    .rm-sec { margin: 0 0 32px; padding: 0; border: 0; min-width: 0; }
    .rm-sec legend, .rm-sec .rm-tit {
        display: block;
        margin: 0 0 14px;
        padding: 0;
        color: var(--cafe);
        font: 700 .85rem var(--sans);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .rm-fila { display: grid; gap: 0 16px; }
    @media (min-width: 560px) {
        .rm-fila { grid-template-columns: 1fr 1fr; }
    }

    .rm-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .rm-chips-etq { margin: 0 6px 0 0; color: var(--verde); font-weight: 400; }

    .chip { position: relative; display: inline-block; margin: 0; }
    .chip input {
        position: absolute; inset: 0; width: 100%; height: 100%;
        margin: 0; padding: 0; border: 0; opacity: 0; cursor: pointer;
    }
    .chip span {
        display: block;
        min-width: 48px;
        padding: 8px 22px;
        border: 1.5px solid var(--verde);
        border-radius: 999px;
        background: var(--papel);
        color: var(--verde);
        font-weight: 600;
        text-align: center;
    }
    .chip input:checked + span { background: var(--verde); color: var(--bg); }
    .chip input:focus-visible + span { outline: 3px solid var(--naranja); outline-offset: 2px; }

    .rm-checks { display: grid; gap: 12px 24px; }
    @media (min-width: 560px) {
        .rm-checks { grid-template-columns: 1fr 1fr; }
    }
    .check {
        display: flex; align-items: center; gap: 10px;
        margin: 0; color: var(--verde); font-size: 1rem; font-weight: 400; cursor: pointer;
    }
    .check input {
        flex: none; width: 20px; height: 20px; margin: 0; padding: 0;
        accent-color: var(--naranja);
    }

    textarea {
        width: 100%;
        min-height: 150px;
        padding: 16px 20px;
        border: 1.5px solid var(--verde);
        border-radius: 28px;
        background: var(--bg);
        color: var(--verde);
        font: inherit;
        resize: vertical;
    }
    textarea:focus { outline: 3px solid var(--naranja); outline-offset: 1px; }

    .dropzone {
        position: relative;
        display: grid;
        place-items: center;
        min-height: 220px;
        margin: 0;
        padding: 24px;
        border: 2px dashed var(--verde);
        border-radius: 32px;
        background: var(--papel);
        color: var(--verde);
        font-weight: 400;
        text-align: center;
        cursor: pointer;
    }
    .dropzone:hover, .dropzone.sobre { background: var(--salvia); }
    .dropzone:focus-within { outline: 3px solid var(--naranja); outline-offset: 2px; }
    .dropzone input {
        position: absolute; inset: 0; width: 100%; height: 100%;
        margin: 0; padding: 0; border: 0; opacity: 0; cursor: pointer;
    }
    .dropzone strong { display: block; font-weight: 600; }

    .previas {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
    }
    .previas li {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
        border: 2px dashed var(--verde);
        border-radius: 24px;
        background: var(--papel);
    }
    .previas li.llena { border-style: solid; }
    .previas img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .previas .portada {
        position: absolute; left: 8px; bottom: 8px;
        padding: 2px 10px;
        border-radius: 999px;
        background: var(--verde); color: var(--bg);
        font-size: .72rem; font-weight: 600;
    }
    .previas.actuales { margin: 0 0 12px; }
    .quitar {
        position: absolute; top: 8px; right: 8px;
        display: flex; align-items: center; gap: 4px;
        margin: 0; padding: 2px 10px 2px 8px;
        border-radius: 999px; background: var(--papel);
        color: var(--verde); font-size: .72rem; font-weight: 600; cursor: pointer;
    }
    .quitar input { width: auto; margin: 0; padding: 0; accent-color: var(--naranja); }
</style>

<form class="contenedor_footer" method="post" action="registro_mascotas.php<?= $editando ? '?id=' . $id : '' ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="rm-cab">
        <div>
            <p class="rm-ruta"><a href="lista_mascotas.php">Mascotas</a> / <?= $editando ? 'Editar' : 'Nueva' ?></p>
            <h1><?= $editando ? 'Editar mascota' : 'Registrar mascota' ?></h1>
        </div>
        <div class="rm-acciones">
            <button class="btn btn-sec" type="submit" name="accion" value="borrador">Guardar borrador</button>
            <button class="btn" type="submit" name="accion" value="publicar"><?= $editando ? 'Guardar cambios' : 'Publicar' ?></button>
        </div>
    </div>

    <?php if ($exito): ?>
        <p class="alerta ok" role="status">La mascota se registró correctamente.</p>
    <?php endif; ?>
    <?php if (isset($errores['general'])): ?>
        <p class="alerta error" role="alert"><?= e($errores['general']) ?></p>
    <?php elseif ($errores): ?>
        <p class="alerta error" role="alert">Revisa los campos marcados.</p>
    <?php endif; ?>

    <div class="rm-grid">

        <!-- Columna izquierda -->
        <div>
            <fieldset class="rm-sec">
                <legend>Datos básicos</legend>

                <div class="campo" style="margin-top:0">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required
                           value="<?= e($v['nombre']) ?>" <?= $inv('nombre') ?>>
                    <?= $err('nombre') ?>
                </div>

                <div class="campo">
                    <div class="rm-chips" role="radiogroup" aria-label="Especie">
                        <?php foreach ($especies as $esp): ?>
                            <label class="chip">
                                <input type="radio" name="especie_id" value="<?= (int) $esp['id'] ?>"
                                       <?= $chk((int) $v['especie_id'] === (int) $esp['id']) ?>>
                                <span><?= e($esp['nombre']) ?></span>
                            </label>
                        <?php endforeach; ?>

                        <span style="flex:1"></span>

                        <label class="chip">
                            <input type="radio" name="sexo" value="macho" <?= $chk($v['sexo'] === 'macho') ?>>
                            <span>Macho</span>
                        </label>
                        <label class="chip">
                            <input type="radio" name="sexo" value="hembra" <?= $chk($v['sexo'] === 'hembra') ?>>
                            <span>Hembra</span>
                        </label>
                    </div>
                    <?= $err('especie_id') ?>
                    <?= $err('sexo') ?>
                </div>

                <div class="rm-fila">
                    <div class="campo">
                        <label for="raza">Raza <span class="opc">(opcional)</span></label>
                        <input type="text" id="raza" name="raza" maxlength="80"
                               value="<?= e($v['raza']) ?>" <?= $inv('raza') ?>>
                        <?= $err('raza') ?>
                    </div>
                    <div class="campo">
                        <label for="edad_meses">Edad aprox. en meses <span class="opc">(opcional)</span></label>
                        <input type="number" id="edad_meses" name="edad_meses" min="0" max="600" step="1"
                               inputmode="numeric" value="<?= e($v['edad_meses']) ?>" <?= $inv('edad_meses') ?>>
                        <?= $err('edad_meses') ?>
                    </div>
                </div>

                <div class="campo">
                    <div class="rm-chips" role="radiogroup" aria-label="Tamaño">
                        <span class="rm-chips-etq">Tamaño</span>
                        <?php foreach (TAMANOS as $valor => $letra): ?>
                            <label class="chip" title="<?= e(ucfirst($valor)) ?>">
                                <input type="radio" name="tamano" value="<?= e($valor) ?>"
                                       aria-label="<?= e(ucfirst($valor)) ?>" <?= $chk($v['tamano'] === $valor) ?>>
                                <span><?= e($letra) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('tamano') ?>
                </div>
            </fieldset>

            <fieldset class="rm-sec">
                <legend>Convivencia y salud</legend>
                <div class="rm-checks">
                    <label class="check">
                        <input type="checkbox" name="apto_ninos" value="1" <?= $chk($v['apto_ninos']) ?>>
                        Apto con niños
                    </label>
                    <label class="check">
                        <input type="checkbox" name="apto_mascotas" value="1" <?= $chk($v['apto_mascotas']) ?>>
                        Apto con otras mascotas
                    </label>
                    <label class="check">
                        <input type="checkbox" name="vacunado" value="1" <?= $chk($v['vacunado']) ?>>
                        Vacunado
                    </label>
                    <label class="check">
                        <input type="checkbox" name="esterilizado" value="1" <?= $chk($v['esterilizado']) ?>>
                        Esterilizado
                    </label>
                    <label class="check">
                        <input type="checkbox" name="desparasitado" value="1" <?= $chk($v['desparasitado']) ?>>
                        Desparasitado
                    </label>
                </div>
            </fieldset>

            <fieldset class="rm-sec">
                <legend>Historia</legend>
                <label for="descripcion" class="opc" style="position:absolute;left:-999px">Historia</label>
                <textarea id="descripcion" name="descripcion"
                          placeholder="Cómo llegó, personalidad, cuidados…"><?= e($v['descripcion']) ?></textarea>
            </fieldset>
        </div>

        <!-- Columna derecha -->
        <div>
            <fieldset class="rm-sec">
                <legend>Fotos</legend>

                <label class="dropzone" id="dropzone">
                    <span>
                        <strong>Arrastra fotos aquí</strong>
                        o haz clic para elegirlas<br>
                        La primera es la portada · JPG, PNG o WebP · máx. 5 MB
                    </span>
                    <input type="file" id="fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple
                           aria-label="Fotos de la mascota" <?= $inv('fotos') ?>>
                </label>
                <?= $err('fotos') ?>

                <?php if ($fotosActuales): ?>
                    <ul class="previas actuales" aria-label="Fotos actuales">
                        <?php foreach ($fotosActuales as $i => $f): ?>
                            <li class="llena">
                                <img src="../<?= e($f['ruta']) ?>" alt="Foto actual <?= $i + 1 ?>">
                                <?php if ($i === 0): ?><span class="portada">Portada</span><?php endif; ?>
                                <label class="quitar">
                                    <input type="checkbox" name="quitar[]" value="<?= (int) $f['id'] ?>"> Quitar
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <ul class="previas" id="previas" aria-live="polite">
                    <li></li><li></li><li></li>
                </ul>
            </fieldset>

            <fieldset class="rm-sec">
                <legend>Estado</legend>

                <div class="campo" style="margin-top:0">
                    <label for="estado">Estado al publicar</label>
                    <select id="estado" name="estado" <?= $inv('estado') ?>>
                        <?php foreach (ESTADOS_PUB as $valor => $texto): ?>
                            <option value="<?= e($valor) ?>" <?= $v['estado'] === $valor ? 'selected' : '' ?>>
                                <?= e($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('estado') ?>
                    <p class="ayuda">«Guardar borrador» ignora este valor y lo guarda como borrador.</p>
                </div>

                <div class="campo">
                    <label for="fecha_ingreso">Fecha de ingreso</label>
                    <input type="date" id="fecha_ingreso" name="fecha_ingreso"
                           value="<?= e($v['fecha_ingreso']) ?>" <?= $inv('fecha_ingreso') ?>>
                    <?= $err('fecha_ingreso') ?>
                </div>
            </fieldset>
        </div>
    </div>
</form>

<script>
/* Vista previa de las fotos seleccionadas (la primera es la portada) */
(function () {
    var input = document.getElementById('fotos');
    var lista = document.getElementById('previas');
    var zona  = document.getElementById('dropzone');
    if (!input || !lista) return;

    var MAX = <?= MAX_FOTOS ?>;

    function pintar() {
        lista.innerHTML = '';
        var archivos = Array.prototype.slice.call(input.files).slice(0, MAX);

        archivos.forEach(function (f, i) {
            var li = document.createElement('li');
            li.className = 'llena';
            var img = document.createElement('img');
            img.alt = 'Vista previa ' + (i + 1);
            img.src = URL.createObjectURL(f);
            img.onload = function () { URL.revokeObjectURL(img.src); };
            li.appendChild(img);
            if (i === 0) {
                var tag = document.createElement('span');
                tag.className = 'portada';
                tag.textContent = 'Portada';
                li.appendChild(tag);
            }
            lista.appendChild(li);
        });

        // Casillas vacías para completar al menos 3
        for (var j = archivos.length; j < 3; j++) {
            lista.appendChild(document.createElement('li'));
        }
    }

    input.addEventListener('change', pintar);
    ['dragenter', 'dragover'].forEach(function (ev) {
        zona.addEventListener(ev, function () { zona.classList.add('sobre'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zona.addEventListener(ev, function () { zona.classList.remove('sobre'); });
    });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>