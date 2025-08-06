<?php
// captcha.php (endpoint público)
namespace Captcha;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'captcha-lib.php';

// Ejecutar limpieza ocasional (no cada request)
if (rand(1, 100) === 1) {
    cleanOldRateLogs();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   
	header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
if (!is_array($data)) $data = [];

$proceso = $data['proceso'] ?? '';

$ip = getClientIP();

header('Content-Type: application/json');

try {
    switch ($proceso) {
        case 'getPerformanceChallenge':
            $resp = generatePerformanceChallenge();
            echo json_encode($resp);
            break;

        case 'verifyPerformanceChallenge':
            $resp = validatePerformanceChallenge($data); // puede lanzar excepciones
            echo json_encode(array_merge(['success' => true], $resp));
            break;

        case 'GET_POW_CHALLENGE':
            $info = readRateLimitData($ip);
            echo json_encode([
                'challenge' => generateSignedChallenge(),
                'difficulty' => $info['difficulty'] ?? CAPTCHA_DIFFICULTY,
                'instructions' => 'Resuelve el desafío criptográfico para continuar'
            ]);
            break;

        case 'VALIDATE_POW_CHALLENGE':
            $signedChallenge = $data['challenge'] ?? '';
            $nonce = (string)($data['nonce'] ?? '');
            $resp = processValidatePoW($signedChallenge, $nonce, $ip);
			if (isset($resp['status']) && is_int($resp['status'])) {
				http_response_code($resp['status']);
			}
            echo json_encode($resp);
            break;

        case 'VALIDATE_POW_TOKEN':
            $token = (string)($data['token'] ?? '');
            $isValid = validateToken($token);
            echo json_encode(['success' => $isValid, 'message' => $isValid ? 'El token es válido' : 'El token no es válido o ha caducado']);
            break;

        default:
            http_response_code(400);
           
            echo json_encode(['success' => false, 'message' => 'Acción desconocida']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>