<?php
declare(strict_types=1);

date_default_timezone_set('America/Bogota');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

function db(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = env('RUTA_DB_HOST', '127.0.0.1');
    $port = env('RUTA_DB_PORT', '5432');
    $name = env('RUTA_DB_NAME', 'postgres');
    $user = env('RUTA_DB_USER', 'postgres');
    $password = env('RUTA_DB_PASSWORD', '');
    $sslmode = env('RUTA_DB_SSLMODE', 'require');

    $url = env('RUTA_DATABASE_URL');
    if ($url !== null) {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new RuntimeException('RUTA_DATABASE_URL no es una URL de conexión válida.');
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (string) $parts['port'] : $port;
        $name = isset($parts['path']) ? ltrim($parts['path'], '/') : $name;
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : $user;
        $password = isset($parts['pass']) ? rawurldecode($parts['pass']) : $password;
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['sslmode'])) {
                $sslmode = (string) $query['sslmode'];
            }
        }
    }

    $connection = new PDO(
        "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );

    return $connection;
}

function jsonResponse(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
