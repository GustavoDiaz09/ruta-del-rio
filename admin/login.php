<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

if (currentAdmin() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$hasAdmin = (int) db()->query('SELECT COUNT(*) FROM administradores')->fetchColumn() > 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $query = db()->prepare('SELECT id, nombre, password_hash FROM administradores WHERE email = :email LIMIT 1');
    $query->execute(['email' => $email]);
    $admin = $query->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        setAuthCookie([
            'id' => (int) $admin['id'],
            'nombre' => $admin['nombre'],
            'csrf' => bin2hex(random_bytes(32)),
        ]);
        header('Location: index.php');
        exit;
    }
    $error = 'Correo o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso administrativo | Ruta del Río</title><link rel="stylesheet" href="admin.css"></head>
<body class="auth-page">
<main class="auth-card">
  <p class="eyebrow">Ruta del Río</p><h1>Administración</h1>
  <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Correo electrónico<input type="email" name="email" required autocomplete="username"></label>
    <label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="button" type="submit">Ingresar</button>
  </form>
  <?php if (!$hasAdmin): ?><a class="muted-link" href="setup.php">Crear primer administrador</a><?php endif; ?>
</main>
</body>
</html>
