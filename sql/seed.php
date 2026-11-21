<?php
/**
 * sql/seed.php — Datos de prueba: 6 mascotas (con foto) y 2 usuarios.
 *
 * Uso (desde la carpeta del proyecto, con MySQL de XAMPP encendido y la BD ya creada):
 *     C:\xampp\php\php.exe sql\seed.php
 *
 * Se puede ejecutar varias veces: lo que ya existe (mismo nombre / mismo correo) se omite.
 * Las contraseñas se guardan con password_hash(), nunca en texto plano.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta desde la terminal.');
}

require __DIR__ . '/../config/database.php'; // define $pdo

const DIR_PUBLIC = __DIR__ . '/../public/';   // las rutas guardadas en BD son relativas a public/
const CLAVE      = 'prueba123';

/* ---------- Usuarios ---------- */
$usuarios = [
    ['nombre' => 'Admin', 'correo' => 'admin@huellitas.test', 'telefono' => null, 'rol' => 'admin'],
    ['nombre' => 'Alex',  'correo' => 'alex@huellitas.test',  'telefono' => null, 'rol' => 'usuario'],
];

/* ---------- Mascotas ---------- */
// especie: 'Perro' | 'Gato' · tamano: chico | mediano | grande
$mascotas = [
    [
        'nombre' => 'Luna', 'especie' => 'Perro', 'sexo' => 'hembra', 'raza' => 'Mestiza',
        'edad_meses' => 4, 'tamano' => 'mediano', 'imagen' => 'img/mascotas/prueba.jpg',
        'descripcion' => 'Cachorra juguetona y cariñosa, ideal para familias con niños.',
        'apto_ninos' => 1, 'apto_mascotas' => 1, 'vacunado' => 1, 'esterilizado' => 0, 'desparasitado' => 1,
        'estado' => 'disponible',
    ],
    [
        'nombre' => 'Mila', 'especie' => 'Gato', 'sexo' => 'hembra', 'raza' => 'Doméstico',
        'edad_meses' => 12, 'tamano' => 'chico', 'imagen' => 'img/mascotas/gato.jpg',
        'descripcion' => 'Gata curiosa de ojos amarillos. Tranquila y muy independiente.',
        'apto_ninos' => 1, 'apto_mascotas' => 1, 'vacunado' => 1, 'esterilizado' => 1, 'desparasitado' => 1,
        'estado' => 'disponible',
    ],
    [
        'nombre' => 'Nina', 'especie' => 'Gato', 'sexo' => 'hembra', 'raza' => null,
        'edad_meses' => 3, 'tamano' => 'chico', 'imagen' => 'img/mascotas/nina.jpg',
        'descripcion' => 'Gatita rescatada, dulce y juguetona. Busca un hogar con paciencia.',
        'apto_ninos' => 1, 'apto_mascotas' => 0, 'vacunado' => 0, 'esterilizado' => 0, 'desparasitado' => 1,
        'estado' => 'disponible',
    ],
    [
        'nombre' => 'Rocky', 'especie' => 'Perro', 'sexo' => 'macho', 'raza' => 'Mestizo',
        'edad_meses' => 36, 'tamano' => 'grande', 'imagen' => 'img/mascotas/rocky.jpg',
        'descripcion' => 'Perro leal y activo. Necesita espacio y paseos largos.',
        'apto_ninos' => 1, 'apto_mascotas' => 1, 'vacunado' => 1, 'esterilizado' => 1, 'desparasitado' => 1,
        'estado' => 'disponible',
    ],
    [
        'nombre' => 'Toby', 'especie' => 'Perro', 'sexo' => 'macho', 'raza' => 'Mestizo',
        'edad_meses' => 60, 'tamano' => 'mediano', 'imagen' => 'img/mascotas/toby.jpg',
        'descripcion' => 'Rescatado de la calle. Necesita un baño, cariño y una familia paciente.',
        'apto_ninos' => 0, 'apto_mascotas' => 1, 'vacunado' => 1, 'esterilizado' => 0, 'desparasitado' => 1,
        'estado' => 'disponible',
    ],
    [
        'nombre' => 'Toby (adoptado)', 'especie' => 'Perro', 'sexo' => 'macho', 'raza' => 'Mestizo',
        'edad_meses' => 60, 'tamano' => 'mediano', 'imagen' => 'img/mascotas/toby-adoptado.jpg',
        'descripcion' => 'Ya encontró familia. Su historia de adopción es una de nuestras favoritas.',
        'apto_ninos' => 1, 'apto_mascotas' => 1, 'vacunado' => 1, 'esterilizado' => 1, 'desparasitado' => 1,
        'estado' => 'adoptado',
    ],
];

try {
    $pdo->beginTransaction();

    // Especies base
    $pdo->exec("INSERT IGNORE INTO especies (id, nombre) VALUES (1, 'Perro'), (2, 'Gato')");
    $idEspecie = [];
    foreach ($pdo->query('SELECT id, nombre FROM especies') as $f) {
        $idEspecie[$f['nombre']] = (int) $f['id'];
    }

    // Usuarios
    $existeU = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
    $nuevoU  = $pdo->prepare(
        'INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($usuarios as $u) {
        $existeU->execute([$u['correo']]);
        if ($existeU->fetchColumn()) {
            echo "= Usuario ya existe: {$u['correo']}\n";
            continue;
        }
        $nuevoU->execute([$u['nombre'], $u['telefono'], $u['correo'], password_hash(CLAVE, PASSWORD_DEFAULT), $u['rol']]);
        echo "+ Usuario creado: {$u['correo']} ({$u['rol']})\n";
    }

    // Mascotas + foto de portada
    $existeM = $pdo->prepare('SELECT 1 FROM mascotas WHERE nombre = ?');
    $nuevaM  = $pdo->prepare(
        'INSERT INTO mascotas
         (especie_id, nombre, sexo, raza, descripcion, edad_meses, tamano, imagen,
          apto_ninos, apto_mascotas, esterilizado, vacunado, desparasitado,
          disponible, estado, fecha_ingreso)
         VALUES
         (:especie_id, :nombre, :sexo, :raza, :descripcion, :edad_meses, :tamano, :imagen,
          :apto_ninos, :apto_mascotas, :esterilizado, :vacunado, :desparasitado,
          :disponible, :estado, CURDATE())'
    );
    $nuevaF = $pdo->prepare('INSERT INTO mascota_fotos (mascota_id, ruta, es_portada) VALUES (?, ?, 1)');

    foreach ($mascotas as $m) {
        $existeM->execute([$m['nombre']]);
        if ($existeM->fetchColumn()) {
            echo "= Mascota ya existe: {$m['nombre']}\n";
            continue;
        }
        if (!is_file(DIR_PUBLIC . $m['imagen'])) {
            echo "! Aviso: no se encontró la imagen public/{$m['imagen']} (se registra igual)\n";
        }
        $nuevaM->execute([
            ':especie_id'    => $idEspecie[$m['especie']],
            ':nombre'        => $m['nombre'],
            ':sexo'          => $m['sexo'],
            ':raza'          => $m['raza'],
            ':descripcion'   => $m['descripcion'],
            ':edad_meses'    => $m['edad_meses'],
            ':tamano'        => $m['tamano'],
            ':imagen'        => $m['imagen'],
            ':apto_ninos'    => $m['apto_ninos'],
            ':apto_mascotas' => $m['apto_mascotas'],
            ':esterilizado'  => $m['esterilizado'],
            ':vacunado'      => $m['vacunado'],
            ':desparasitado' => $m['desparasitado'],
            ':disponible'    => (int) ($m['estado'] === 'disponible'),
            ':estado'        => $m['estado'],
        ]);
        $nuevaF->execute([(int) $pdo->lastInsertId(), $m['imagen']]);
        echo "+ Mascota creada: {$m['nombre']}\n";
    }

    $pdo->commit();
    echo "\nListo. Usuarios de prueba (contraseña: " . CLAVE . "):\n";
    echo "  admin@huellitas.test  -> administrador\n";
    echo "  alex@huellitas.test   -> usuario\n";
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error: ' . $ex->getMessage() . "\n");
    exit(1);
}
