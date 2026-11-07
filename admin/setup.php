<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

$exists = (int) db()->query('SELECT COUNT(*) FROM administradores')->fetchColumn() > 0;
$error = '';
if (!$exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['nombre'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $error = 'Usa un nombre, correo válido y una contraseña de mínimo 10 caracteres.';
    } else {
        $insert = db()->prepare('INSERT INTO administradores (nombre, email, password_hash) VALUES (:nombre, :email, :password_hash)');
        $insert->execute(['nombre' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        header('Location: login.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Crear administrador | Ruta del Río</title><link rel="stylesheet" href="admin.css"></head>
<body class="auth-page">
<main class="auth-card">
  <p class="eyebrow">Configuración inicial</p><h1>Crear administrador</h1>
  <?php if ($exists): ?><p class="alert error">Ya existe un administrador. Usa el inicio de sesión.</p><a class="button" href="login.php">Ir al acceso</a>
  <?php else: ?>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <label>Nombre<input name="nombre" required maxlength="100"></label>
      <label>Correo electrónico<input type="email" name="email" required maxlength="254"></label>
      <label>Contraseña<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <button class="button" type="submit">Crear administrador</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
