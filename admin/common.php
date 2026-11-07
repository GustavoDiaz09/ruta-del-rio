<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

const RUTA_AUTH_COOKIE = 'ruta_admin';
const RUTA_AUTH_TTL = 43200;

function appSecret(): string
{
    $secret = env('RUTA_APP_SECRET');
    if ($secret === null || strlen($secret) < 16) {
        throw new RuntimeException('Configura la variable de entorno RUTA_APP_SECRET (mínimo 16 caracteres).');
    }

    return $secret;
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string|false
{
    $padding = strlen($data) % 4;
    if ($padding) {
        $data .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($data, '-_', '+/'), true);
}

function signPayload(string $payload): string
{
    return hash_hmac('sha256', $payload, appSecret());
}

function currentAdmin(): ?array
{
    $raw = $_COOKIE[RUTA_AUTH_COOKIE] ?? '';
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $parts = explode('.', $raw, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$encoded, $signature] = $parts;
    if (!hash_equals(signPayload($encoded), $signature)) {
        return null;
    }

    $json = base64UrlDecode($encoded);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['id']) || empty($data['exp']) || $data['exp'] < time()) {
        return null;
    }

    return $data;
}

function setAuthCookie(array $data): void
{
    $data['exp'] = time() + RUTA_AUTH_TTL;
    $encoded = base64UrlEncode(json_encode($data, JSON_THROW_ON_ERROR));
    $cookie = $encoded . '.' . signPayload($encoded);

    setcookie(RUTA_AUTH_COOKIE, $cookie, [
        'expires' => $data['exp'],
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[RUTA_AUTH_COOKIE] = $cookie;
}

function clearAuthCookie(): void
{
    setcookie(RUTA_AUTH_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[RUTA_AUTH_COOKIE]);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    $admin = currentAdmin();

    return (string) ($admin['csrf'] ?? '');
}

function requireCsrf(): void
{
    $admin = currentAdmin();
    if ($admin === null || !hash_equals((string) ($admin['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }
}

function requireAdmin(): void
{
    if (currentAdmin() === null) {
        header('Location: login.php');
        exit;
    }
}

function redirectWithMessage(string $message, string $type = 'success'): never
{
    header('Location: index.php?mensaje=' . rawurlencode($message) . '&tipo=' . rawurlencode($type));
    exit;
}
