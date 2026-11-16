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

$mascotas = [];
$total    = 0;
try {
    $mascotas = $pdo->query(
        'SELECT m.id, m.nombre, m.edad_meses, m.tamano, m.imagen, s.nombre AS especie
           FROM mascotas m
           JOIN especies s ON s.id = m.especie_id
          WHERE m.disponible = 1
          ORDER BY m.id DESC
          LIMIT 4'
    )->fetchAll();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM mascotas WHERE disponible = 1')->fetchColumn();
} catch (PDOException $ex) {
    $mascotas = [];
}

$destacada = $mascotas[0] ?? null;
$otras     = array_slice($mascotas, 1, 3);
$restantes = max(0, $total - count($mascotas));

$usuario   = usuario_actual();
$bodyClass = 'inicio';
require __DIR__ . '/../partials/header.php';
?>

<section class="buscador">
    <div class="contenedor">
        <h1>¿A quién vas a adoptar hoy?</h1>

        <?php
        $filtros  = ['especie' => 'perro', 'edad' => '', 'tamano' => ''];
        $conTodos = false;
        require __DIR__ . '/../partials/filtros.php';
        ?>
    </div>
</section>

<section class="espera">
    <div class="contenedor">
        <h2>Te están esperando</h2>

        <div class="espera-grid">
            <a class="ph ph-grande" href="mascotas.php">
                <?php if ($destacada && !empty($destacada['imagen'])): ?>
                    <img src="../<?= e($destacada['imagen']) ?>" alt="<?= e($destacada['nombre']) ?>">
                <?php else: ?>
                    <span>foto grande · destacada de la semana</span>
                <?php endif; ?>
            </a>

            <?php for ($i = 0; $i < 3; $i++): $m = $otras[$i] ?? null; ?>
                <a class="ph" href="mascotas.php">
                    <?php if ($m && !empty($m['imagen'])): ?>
                        <img src="../<?= e($m['imagen']) ?>" alt="<?= e($m['nombre']) ?>" loading="lazy">
                    <?php endif; ?>
                    <span class="<?= ($m && !empty($m['imagen'])) ? 'ph-nombre' : '' ?>"><?= e($m['nombre'] ?? '') ?></span>
                </a>
            <?php endfor; ?>

            <a class="ph ph-mas" href="mascotas.php">
                <span>+ <?= $restantes ?> más →</span>
            </a>
        </div>

        <?php if ($destacada): ?>
            <p class="espera-pie">
                <?= e($destacada['nombre']) ?> · <?= e($destacada['especie']) ?> · <?= e(edad_texto($destacada['edad_meses'])) ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="tres-pasos">
    <div class="contenedor">
        <h2>Adoptar en 3 pasos</h2>
        <ol class="pasos-linea">
            <li><span class="num">1</span><b>Elige</b></li>
            <li><span class="num">2</span><b>Solicita</b></li>
            <li><span class="num">3</span><b>Conócelo</b></li>
        </ol>
    </div>
</section>

<section class="historias">
    <div class="contenedor historias-in">
        
        <div class="ph ph-familia"><img src="../img/mascotas/toby-adoptado.jpg"><span>foto familia</span></div>
        <div>
            <p class="historias-etq">HISTORIAS</p>
            <blockquote>"Toby cambió nuestra casa por completo."</blockquote>
            <p class="historias-autor">— Familia Pérez · ‹ ›</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
