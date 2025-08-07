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
</html>
';

if (empty($token)) {
	echo str_replace('{{RESULT}}', 'No se recibió token CAPTCHA', $html_template);
    exit;
}

if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
	echo str_replace('{{RESULT}}', 'El CAPTCHA no ha sido resuelto correctamente (formato inválido).', $html_template);
    exit;
}

$isValid = validateToken($token);
if (!$isValid) {
	echo str_replace('{{RESULT}}', 'El CAPTCHA no ha sido resuelto correctamente. Por favor inténtelo nuevamente.', $html_template);
    exit;
}

if (!isset($_POST['nombre'])) {
	echo str_replace('{{RESULT}}', 'Falta el campo "nombre".', $html_template);
    exit;
}

// OK: CAPTCHA válido.
$result = '<h1>CAPTCHA resuelto correctamente</h1>';
$result .= '<pre>';
foreach ($_POST as $k => $v) {
    $result .= sprintf("<strong>%s:</strong> %s\n", htmlspecialchars($k, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
}
$result .= '</pre>';
echo str_replace('{{RESULT}}', $result, $html_template);
exit;
