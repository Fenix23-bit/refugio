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

$leer = function (string $campo, array $permitidos): string {
    $v = $_GET[$campo] ?? '';
    return is_string($v) && in_array($v, $permitidos, true) ? $v : '';
};
$filtros = [
    'especie' => $leer('especie', ['perro', 'gato']),
    'edad'    => $leer('edad', ['cachorro', 'joven', 'adulto']),
    'tamano'  => $leer('tamano', ['chico', 'mediano', 'grande']),
];
$hayFiltros = (bool) array_filter($filtros);
$conTodos   = true;

$sql    = 'SELECT m.id, m.nombre, m.edad_meses, m.tamano, m.imagen, m.apto_ninos, m.apto_mascotas,
                  s.nombre AS especie
             FROM mascotas m
             JOIN especies s ON s.id = m.especie_id
            WHERE m.disponible = 1';
$params = [];

if ($filtros['especie'] !== '') {
    $sql .= ' AND LOWER(s.nombre) = ?';
    $params[] = $filtros['especie'];
}
if ($filtros['tamano'] !== '') {
    $sql .= ' AND m.tamano = ?';
    $params[] = $filtros['tamano'];
}

if ($filtros['edad'] === 'cachorro') {
    $sql .= ' AND m.edad_meses < 12';
} elseif ($filtros['edad'] === 'joven') {
    $sql .= ' AND m.edad_meses BETWEEN 12 AND 35';
} elseif ($filtros['edad'] === 'adulto') {
    $sql .= ' AND m.edad_meses >= 36';
}
$sql .= ' ORDER BY m.id DESC LIMIT 60';

$mascotas = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $mascotas = $st->fetchAll();
} catch (PDOException $ex) {
    $mascotas = [];
}

$usuario  = usuario_actual();
$bodyClass = 'inicio';
require __DIR__ . '/../partials/header.php';
?>

<section id="mascotas" class="seccion">
    <div class="contenedor">
        <h2>Mascotas que buscan casa</h2>

        <div class="filtros-barra">
            <?php require __DIR__ . '/../partials/filtros.php'; ?>
            <p class="filtros-info" role="status">
                <?= count($mascotas) ?> <?= count($mascotas) === 1 ? 'resultado' : 'resultados' ?>
                <?php if ($hayFiltros): ?> · <a href="mascotas.php">Quitar filtros</a><?php endif; ?>
            </p>
        </div>

        <?php if (!$mascotas && $hayFiltros): ?>
            <p class="vacio">No encontramos mascotas con esos filtros. Prueba cambiando la edad o el tamaño.</p>
        <?php elseif (!$mascotas): ?>
            <p class="vacio">Aún no hay mascotas publicadas. Cuando el refugio agregue nuevas, aparecerán aquí.</p>
        <?php else: ?>
            <ul class="tarjetas">
                <?php foreach ($mascotas as $m): ?>
                    <li class="tarjeta-mascota">
                        <?php if (!empty($m['imagen'])): ?>
                            <img class="foto" src="../<?= e($m['imagen']) ?>" alt="<?= e($m['nombre']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="foto foto-vacia" aria-hidden="true"><svg><use href="#pata"/></svg></div>
                        <?php endif; ?>
                        <div class="tarjeta-cuerpo">
                            <h3><?= e($m['nombre']) ?></h3>
                            <p class="meta"><?= e($m['especie']) ?>, <?= e($m['tamano']) ?>, <?= e(edad_texto($m['edad_meses'])) ?></p>
                            <?php if ($m['apto_ninos'] || $m['apto_mascotas']): ?>
                                <p class="etiquetas">
                                    <?php if ($m['apto_ninos']): ?><span>Se lleva bien con niños</span><?php endif; ?>
                                    <?php if ($m['apto_mascotas']): ?><span>Se lleva bien con otras mascotas</span><?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
