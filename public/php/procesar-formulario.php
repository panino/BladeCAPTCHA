<?php
// procesar-formulario.php
// Valida el token usando la librería local

require_once __DIR__ . DIRECTORY_SEPARATOR . 'captcha-lib.php';

use function Captcha\validateToken;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    exit;
}

$token = trim($_POST['captcha_token'] ?? '');

if (empty($token)) {
    echo 'No se recibió token CAPTCHA';
    exit;
}

if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    echo 'El CAPTCHA no ha sido resuelto correctamente (formato inválido).';
    exit;
}

$isValid = validateToken($token);
if (!$isValid) {
    echo 'El CAPTCHA no ha sido resuelto correctamente. Por favor inténtelo nuevamente.';
    exit;
}

if (!isset($_POST['nombre'])) {
    echo 'Falta el campo "nombre"';
    exit;
}

// OK: CAPTCHA válido. Procesar formulario con cuidado (escapando la salida).
echo '<h1>CAPTCHA resuelto correctamente</h1>';
echo '<pre>';
foreach ($_POST as $k => $v) {
    printf("%s: %s\n", htmlspecialchars($k, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
}
echo '</pre>';
exit;
