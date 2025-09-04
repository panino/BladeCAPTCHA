<?php
namespace Captcha;
// Evitar caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Contenido JSON por defecto
header('Content-Type: application/json; charset=utf-8');

// Seguridad adicional opcional
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'captcha-lib.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?: [];

$proceso = $data['proceso'] ?? '';
$claveCaptcha = $data['claveCaptcha'] ?? '';

// Limpieza ocasional de archivos antiguos
if (rand(1, 100) === 1) {
    cleanOldRateLogs();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests']);
    exit;
}

switch ($proceso) {

    case 'GET_POW_CHALLENGE':
        if (!registerCaptchaKey($claveCaptcha)) {
            respondJsonError('Could not register captcha key', 500, 'server_error');
        }
        echo json_encode([
            'challenge' => generateSignedChallenge(),
            'difficulty' => CAPTCHA_DIFFICULTY,
            'instructions' => 'Solve the cryptographic challenge to continue'
        ]);
        break;

    case 'VALIDATE_POW_CHALLENGE':
		if (!validateCaptchaKey($claveCaptcha)) {
			respondJsonError('Captcha key not registered', 400, 'invalid_key');
		}

		if (!validateCaptchaTiming($claveCaptcha, 10)) {
			respondJsonError('Please wait before retrying', 429, 'too_soon');
		}

		$signedChallenge = $data['challenge'] ?? '';
		$nonce = (string)($data['nonce'] ?? '');
		$resp = processValidatePoW($signedChallenge, $nonce, $claveCaptcha);
		echo json_encode($resp);
		break;


    case 'VALIDATE_POW_TOKEN':
        $token = (string)($data['token'] ?? '');
        $isValid = validateToken($token);
        echo json_encode([
            'success' => $isValid,
            'message' => $isValid ? 'The token is valid' : 'The token is not valid or has expired'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

