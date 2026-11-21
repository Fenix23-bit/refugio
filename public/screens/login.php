<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/auth.php';

if (usuario_actual()) {
    header('Location: ' . ((usuario_actual()['rol'] ?? '') === 'admin' ? 'admin.php' : 'panel.php'));
    exit;
}

$error  = '';
$correo = '';
$flash  = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = strtolower(trim($_POST['correo'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!csrf_valido()) {
        $error = 'La sesión expiró. Intenta de nuevo.';
    } else {
        $st = $pdo->prepare('SELECT id, nombre, correo, password_hash, rol FROM usuarios WHERE correo = ?');
        $st->execute([$correo]);
        $u = $st->fetch();

        if ($u && password_verify($password, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id'     => (int) $u['id'],
                'nombre' => $u['nombre'],
                'correo' => $u['correo'],
                'rol'    => $u['rol'],
            ];
            header('Location: ' . ($u['rol'] === 'admin' ? 'admin.php' : 'panel.php'));
            exit;
        }
        $error = 'El correo o la contraseña no coinciden. Revísalos e intenta de nuevo.';
    }
}

$activo    = 'login';
$bodyClass = 'auth';
require __DIR__ . '/../partials/header.php';
?>

<div class="auth-caja">
    <svg class="auth-pata" aria-hidden="true"><use href="#pata"/></svg>
    <h1>Hola de nuevo</h1>
    <p class="sub">Entra para seguir tus solicitudes de adopción.</p>

    <?php if ($flash): ?><div class="alerta ok" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alerta error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="campo">
            <label for="correo">Correo</label>
            <input id="correo" type="email" name="correo" value="<?= e($correo) ?>" placeholder="tu@correo.com" required autocomplete="email">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="campo-pass">
                <input id="password" type="password" name="password" required autocomplete="current-password">
                <button type="button" class="mostrar" data-mostrar="password" aria-pressed="false">Mostrar</button>
            </div>
        </div>

        <button type="submit" class="btn btn-block">Iniciar sesión</button>
    </form>

    <p class="pie-form">¿Aún no tienes cuenta? <a href="registro.php">Crea una</a></p>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
