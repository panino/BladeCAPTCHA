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

$html_template = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BladeCAPTCHA - Ejemplo de verificación</title>
</head>
<body>
{{RESULT}}
</body>
</html>';

// helper
function render_result($message) {
    global $html_template;
    echo str_replace('{{RESULT}}', $message, $html_template);
    exit;
}

if (empty($token)) {
    render_result('No se recibió token CAPTCHA');
    exit;
}

if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    render_result('El CAPTCHA no ha sido resuelto correctamente (formato inválido).');
    exit;
}

if (!validateToken($token)) {
    render_result('El CAPTCHA no ha sido resuelto correctamente. Por favor inténtelo nuevamente.');
    exit;
}

if (!isset($_POST['nombre'])) {
    render_result('Falta el campo "nombre".');
    exit;
}

// OK: CAPTCHA válido.
$result = '<h1>CAPTCHA resuelto correctamente</h1>';
$result .= '<pre>';

foreach ($_POST as $k => $v) {
    $result .= sprintf(
        "<strong>%s:</strong> %s\n",
        htmlspecialchars($k, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')
    );
}

$result .= '</pre>';
render_result($result);
exit;
?>