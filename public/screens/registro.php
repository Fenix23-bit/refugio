<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/auth.php';

if (usuario_actual()) {
    header('Location: panel.php');
    exit;
}

$errores = [];
$old     = ['nombre' => '', 'telefono' => '', 'correo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nombre']   = trim($_POST['nombre'] ?? '');
    $old['telefono'] = trim($_POST['telefono'] ?? '');
    $old['correo']   = strtolower(trim($_POST['correo'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!csrf_valido()) {
        $errores['general'] = 'La sesión expiró. Intenta de nuevo.';
    }
    if ($old['nombre'] === '' || mb_strlen($old['nombre']) > 100) {
        $errores['nombre'] = 'Escribe tu nombre (máximo 100 caracteres).';
    }
    if (!filter_var($old['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['correo']) > 150) {
        $errores['correo'] = 'Escribe un correo válido, por ejemplo tu@correo.com.';
    }
    if ($old['telefono'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['telefono'])) {
        $errores['telefono'] = 'Escribe un teléfono de 7 a 20 dígitos.';
    }
    if (strlen($password) < 8) {
        $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $password2) {
        $errores['password2'] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $st = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
        $st->execute([$old['correo']]);
        if ($st->fetch()) {
            $errores['correo'] = 'Ya existe una cuenta con ese correo. Inicia sesión.';
        }
    }

    if (!$errores) {
        $st = $pdo->prepare(
            'INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol)
             VALUES (?, ?, ?, ?, \'usuario\')'
        );
        $st->execute([
            $old['nombre'],
            $old['telefono'] !== '' ? $old['telefono'] : null,
            $old['correo'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $_SESSION['flash'] = 'Cuenta creada. Ya puedes iniciar sesión.';
        header('Location: login.php');
        exit;
    }
}

function attr_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="err-' . $campo . '"' : '';
}

$activo    = 'registro';
$bodyClass = 'auth';
require __DIR__ . '/../partials/header.php';
?>

<div class="auth-caja">
    <svg class="auth-pata" aria-hidden="true"><use href="#pata"/></svg>
    <h1>Crea tu cuenta</h1>
    <p class="sub">Con ella podrás enviar solicitudes de adopción y seguirlas.</p>

    <?php if (isset($errores['general'])): ?>
        <div class="alerta error" role="alert"><?= e($errores['general']) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" value="<?= e($old['nombre']) ?>" maxlength="100" required autocomplete="name"<?= attr_error($errores, 'nombre') ?>>
            <?php if (isset($errores['nombre'])): ?><p class="campo-error" id="err-nombre"><?= e($errores['nombre']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
            <label for="correo">Correo</label>
            <input id="correo" type="email" name="correo" value="<?= e($old['correo']) ?>" placeholder="tu@correo.com" maxlength="150" required autocomplete="email"<?= attr_error($errores, 'correo') ?>>
            <?php if (isset($errores['correo'])): ?><p class="campo-error" id="err-correo"><?= e($errores['correo']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
            <label for="telefono">Teléfono <span class="opc">(opcional)</span></label>
            <input id="telefono" type="tel" name="telefono" value="<?= e($old['telefono']) ?>" maxlength="20" autocomplete="tel"<?= attr_error($errores, 'telefono') ?>>
            <?php if (isset($errores['telefono'])): ?><p class="campo-error" id="err-telefono"><?= e($errores['telefono']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="campo-pass">
                <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"<?= attr_error($errores, 'password') ?>>
                <button type="button" class="mostrar" data-mostrar="password" aria-pressed="false">Mostrar</button>
            </div>
            <?php if (isset($errores['password'])): ?>
                <p class="campo-error" id="err-password"><?= e($errores['password']) ?></p>
            <?php else: ?>
                <p class="ayuda">Mínimo 8 caracteres.</p>
            <?php endif; ?>
        </div>

        <div class="campo">
            <label for="password2">Repite la contraseña</label>
            <input id="password2" type="password" name="password2" minlength="8" required autocomplete="new-password"<?= attr_error($errores, 'password2') ?>>
            <?php if (isset($errores['password2'])): ?><p class="campo-error" id="err-password2"><?= e($errores['password2']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-block">Crear cuenta</button>
    </form>

    <p class="pie-form">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
