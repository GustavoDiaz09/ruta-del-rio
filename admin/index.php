<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
requireAdmin();
$admin = currentAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'estado') {
        $status = (string) ($_POST['estado'] ?? '');
        if (!in_array($status, ['pendiente', 'confirmada', 'cancelada'], true)) {
            redirectWithMessage('Estado no válido.', 'error');
        }
        $update = $pdo->prepare('UPDATE reservas SET estado = :estado WHERE id = :id');
        $update->execute(['estado' => $status, 'id' => (int) $_POST['id']]);
        redirectWithMessage('Estado actualizado.');
    }
    if ($action === 'eliminar_cliente') {
        $delete = $pdo->prepare('DELETE FROM clientes WHERE id = :id AND NOT EXISTS (SELECT 1 FROM reservas WHERE cliente_id = clientes.id)');
        $delete->execute(['id' => (int) $_POST['id']]);
        redirectWithMessage($delete->rowCount() ? 'Cliente eliminado.' : 'No se puede eliminar: tiene reservas asociadas.', $delete->rowCount() ? 'success' : 'error');
    }
}

$filterDate = (string) ($_GET['fecha'] ?? '');
$filterStatus = (string) ($_GET['estado'] ?? '');
$reservationSql = "SELECT r.*, CONCAT(c.nombres, ' ', c.apellidos) AS cliente FROM reservas r JOIN clientes c ON c.id = r.cliente_id WHERE 1=1";
$params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $reservationSql .= ' AND r.fecha = :fecha';
    $params['fecha'] = $filterDate;
}
if (in_array($filterStatus, ['pendiente', 'confirmada', 'cancelada'], true)) {
    $reservationSql .= ' AND r.estado = :estado';
    $params['estado'] = $filterStatus;
}
$reservationSql .= ' ORDER BY r.fecha DESC, r.hora_inicio DESC LIMIT 200';
$reservations = $pdo->prepare($reservationSql);
$reservations->execute($params);
$reservationRows = $reservations->fetchAll();
$clients = $pdo->query('SELECT c.*, COUNT(r.id) AS total_reservas FROM clientes c LEFT JOIN reservas r ON r.cliente_id = c.id GROUP BY c.id ORDER BY c.creado_en DESC LIMIT 200')->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) AS total,
    COUNT(*) FILTER (WHERE estado = 'pendiente') AS pendientes,
    COUNT(*) FILTER (WHERE estado = 'confirmada') AS confirmadas,
    COUNT(DISTINCT cliente_id) AS clientes
  FROM reservas")->fetch();
$message = (string) ($_GET['mensaje'] ?? '');
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Panel | Ruta del Río</title><link rel="stylesheet" href="admin.css"></head>
<body>
<header class="topbar"><div><strong>Ruta del Río</strong><span>Panel administrativo</span></div><div>Hola, <?= e($admin['nombre'] ?? '') ?> · <a href="logout.php">Salir</a></div></header>
<main class="dashboard">
  <?php if ($message): ?><p class="alert <?= e($_GET['tipo'] ?? 'success') ?>"><?= e($message) ?></p><?php endif; ?>
  <section class="stats"><article><b><?= (int) $stats['total'] ?></b><span>Reservas</span></article><article><b><?= (int) $stats['pendientes'] ?></b><span>Pendientes</span></article><article><b><?= (int) $stats['confirmadas'] ?></b><span>Confirmadas</span></article><article><b><?= (int) $stats['clientes'] ?></b><span>Clientes</span></article></section>
  <section class="panel"><div class="section-head"><div><p class="eyebrow">Agenda</p><h1>Reservas</h1></div><form class="filters"><input type="date" name="fecha" value="<?= e($filterDate) ?>"><select name="estado"><option value="">Todos los estados</option><?php foreach (['pendiente','confirmada','cancelada'] as $status): ?><option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select><button class="button" type="submit">Filtrar</button><a class="muted-link" href="index.php">Limpiar</a></form></div>
    <div class="table-wrap"><table><thead><tr><th>Fecha / hora</th><th>Cliente</th><th>Recorrido</th><th>Personas</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    <?php foreach ($reservationRows as $r): ?><tr><td><?= e($r['fecha']) ?><br><?= e(substr($r['hora_inicio'], 0, 5)) ?>–<?= e(substr($r['hora_fin'], 0, 5)) ?></td><td><?= e($r['cliente']) ?><small><?= e($r['email_reserva']) ?><br><?= e($r['celular_reserva']) ?></small></td><td><?= e(ucfirst($r['recorrido'])) ?></td><td><?= (int) $r['personas'] ?></td><td><span class="badge <?= e($r['estado']) ?>"><?= e(ucfirst($r['estado'])) ?></span></td><td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="estado"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><select name="estado"><option value="pendiente">Pendiente</option><option value="confirmada">Confirmada</option><option value="cancelada">Cancelada</option></select><button type="submit">Guardar</button></form></td></tr>
    <?php endforeach; if (!$reservationRows): ?><tr><td colspan="6">No hay reservas con estos filtros.</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <section class="panel"><div class="section-head"><div><p class="eyebrow">Registro</p><h1>Clientes</h1></div></div><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Contacto</th><th>Ciudad</th><th>Reservas</th><th>Acción</th></tr></thead><tbody>
  <?php foreach ($clients as $client): ?><tr><td><?= e($client['nombres'] . ' ' . $client['apellidos']) ?><small>Nacimiento: <?= e($client['nacimiento']) ?></small></td><td><?= e($client['email']) ?><br><?= e($client['celular']) ?></td><td><?= e($client['ciudad']) ?></td><td><?= (int) $client['total_reservas'] ?></td><td><?php if (!(int) $client['total_reservas']): ?><form method="post" onsubmit="return confirm('¿Eliminar este cliente?')"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="eliminar_cliente"><input type="hidden" name="id" value="<?= (int) $client['id'] ?>"><button class="danger" type="submit">Eliminar</button></form><?php else: ?><span class="muted">Con reservas</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</main>
</body>
</html>
