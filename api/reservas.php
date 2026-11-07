<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
}

$required = ['nombres', 'apellidos', 'email', 'celular', 'nacimiento', 'ciudad', 'fecha', 'hora', 'recorrido', 'personas', 'nivel'];
foreach ($required as $field) {
    if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
        jsonResponse(['ok' => false, 'mensaje' => 'Completa todos los campos obligatorios.'], 422);
    }
}

$text = static fn (string $value, int $max): string => trim(mb_substr($value, 0, $max));
$email = strtolower($text((string) $input['email'], 254));
$phone = $text((string) $input['celular'], 30);
$routes = ['cienagas' => 3, 'magdalena' => 5, 'completa' => 8];
$levels = ['ninguna', 'basica', 'avanzada'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['fecha'])
    || !preg_match('/^\d{2}:\d{2}$/', (string) $input['hora'])
    || !isset($routes[$input['recorrido']])
    || !in_array($input['nivel'], $levels, true)
    || filter_var($input['personas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) === false
) {
    jsonResponse(['ok' => false, 'mensaje' => 'Revisa los datos ingresados.'], 422);
}

$birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input['nacimiento']);
$date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input['fecha']);
$start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$input['fecha']} {$input['hora']}");
if (!$birthDate || $birthDate->format('Y-m-d') !== $input['nacimiento']
    || $birthDate >= new DateTimeImmutable('today')
    || !$date || !$start || $date->format('Y-m-d') !== $input['fecha'] || $start->format('H:i') !== $input['hora']
    || empty($input['consentimiento'])) {
    jsonResponse(['ok' => false, 'mensaje' => 'La fecha u hora no son válidas.'], 422);
}
if ($date < new DateTimeImmutable('today')) {
    jsonResponse(['ok' => false, 'mensaje' => 'La fecha debe ser futura.'], 422);
}

$end = $start->modify("+{$routes[$input['recorrido']]} hours");
if ($start->format('H:i') < '06:00' || $end->format('H:i') > '20:00' || $end->format('Y-m-d') !== $date->format('Y-m-d')) {
    $latestStart = DateTimeImmutable::createFromFormat('!H:i', '20:00')
        ->modify("-{$routes[$input['recorrido']]} hours")
        ->format('H:i');
    jsonResponse([
        'ok' => false,
        'mensaje' => "Para esta ruta, la salida debe ser entre las 06:00 y las {$latestStart} como máximo.",
    ], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $lock = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:lock))');
    $lock->execute(['lock' => 'ruta-reserva-' . $date->format('Y-m-d')]);

    $customer = $pdo->prepare(
        'INSERT INTO clientes (nombres, apellidos, email, celular, nacimiento, ciudad)
         VALUES (:nombres, :apellidos, :email, :celular, :nacimiento, :ciudad)
         ON CONFLICT (email) DO UPDATE SET
           nombres = EXCLUDED.nombres,
           apellidos = EXCLUDED.apellidos,
           celular = EXCLUDED.celular,
           nacimiento = EXCLUDED.nacimiento,
           ciudad = EXCLUDED.ciudad
         RETURNING id'
    );
    $customer->execute([
        'nombres' => $text((string) $input['nombres'], 80),
        'apellidos' => $text((string) $input['apellidos'], 80),
        'email' => $email,
        'celular' => $phone,
        'nacimiento' => $input['nacimiento'],
        'ciudad' => $text((string) $input['ciudad'], 100),
    ]);
    $customerId = (int) $customer->fetchColumn();

    $overlap = $pdo->prepare(
        'SELECT id FROM reservas
         WHERE fecha = :fecha AND estado <> :cancelada
           AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio
         LIMIT 1 FOR UPDATE'
    );
    $overlap->execute([
        'fecha' => $date->format('Y-m-d'),
        'cancelada' => 'cancelada',
        'hora_fin' => $end->format('H:i:s'),
        'hora_inicio' => $start->format('H:i:s'),
    ]);
    if ($overlap->fetch()) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'mensaje' => 'Ese horario ya está ocupado. Elige otra hora.'], 409);
    }

    $reservation = $pdo->prepare(
        'INSERT INTO reservas
         (cliente_id, email_reserva, celular_reserva, fecha, hora_inicio, hora_fin, recorrido, personas, nivel, chaleco, almuerzo, fotos, comentarios)
         VALUES (:cliente_id, :email_reserva, :celular_reserva, :fecha, :hora_inicio, :hora_fin, :recorrido, :personas, :nivel,
                 CAST(:chaleco AS boolean), CAST(:almuerzo AS boolean), CAST(:fotos AS boolean), :comentarios)'
    );
    $reservation->execute([
        'cliente_id' => $customerId,
        'email_reserva' => $email,
        'celular_reserva' => $phone,
        'fecha' => $date->format('Y-m-d'),
        'hora_inicio' => $start->format('H:i:s'),
        'hora_fin' => $end->format('H:i:s'),
        'recorrido' => $input['recorrido'],
        'personas' => (int) $input['personas'],
        'nivel' => $input['nivel'],
        'chaleco' => !empty($input['chaleco']) ? 'true' : 'false',
        'almuerzo' => !empty($input['almuerzo']) ? 'true' : 'false',
        'fotos' => !empty($input['fotos']) ? 'true' : 'false',
        'comentarios' => $text((string) ($input['comentarios'] ?? ''), 1000) ?: null,
    ]);
    $pdo->commit();
    jsonResponse(['ok' => true, 'mensaje' => 'Reserva recibida. Te confirmaremos por correo.'], 201);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception->getCode() === '23505') {
        jsonResponse(['ok' => false, 'mensaje' => 'Ya existe una reserva para esta persona en esa fecha.'], 409);
    }
    error_log($exception->getMessage());
    jsonResponse(['ok' => false, 'mensaje' => 'No fue posible guardar la reserva. Intenta de nuevo.'], 500);
}
