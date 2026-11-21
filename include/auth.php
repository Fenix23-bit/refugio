<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function acceso_denegado(): void
{
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Error 403 | Huellitas</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&family=Fraunces:opsz,wght@9..144,900&display=swap">

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #000;
            color: #fff;
            font: 400 1rem/1.6 Figtree, system-ui, -apple-system, "Segoe UI", sans-serif;
            text-align: center;
        }
        img {
            display: block;
            max-width: min(320px, 100%);
            height: auto;
            margin: 0 auto 24px;
            border-radius: 24px;
        }
        h1 {
            margin: 0 0 8px;
            font: 900 2rem/1.1 Fraunces, Georgia, serif;
        }
        p { margin: 0 0 24px; }
        a {
            display: inline-block;
            padding: 13px 26px;
            border-radius: 999px;
            background: #b84a2b;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }
        a:hover { background: #a03f23; }
        a:focus-visible { outline: 3px solid #b84a2b; outline-offset: 2px; }
    </style>
    
</head>
<body>
    <main>
        <img src="../img/my-name-is-teto-pear-miku-v0-ht34i7yj0u7f1.webp" alt="">
        <h1>Shh, no deberías acceder aquí</h1>
        <p>Regrésate, de favor.</p>
        <a href="<?= usuario_actual() ? 'panel.php' : 'inicio.php' ?>">Regresar</a>
    </main>
</body>
</html>
<?php
    exit;
}

function requiere_admin(): array
{
    $u = usuario_actual();
    if (!$u || ($u['rol'] ?? '') !== 'admin') {
        acceso_denegado();
    }
    return $u;
}
