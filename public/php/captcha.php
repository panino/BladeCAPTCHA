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
$claveCaptcha = $data['claveCaptcha'] ?? '';

$ip = getClientIPKey($claveCaptcha);

header('Content-Type: application/json');

switch ($proceso) {
    case 'getPerformanceChallenge':
        try {
            $resp = generatePerformanceChallenge(); // puede lanzar RuntimeException
            echo json_encode(array_merge(['success' => true], $resp));
        } catch (\RuntimeException $e) {
            // Error interno: loggear y devolver mensaje genérico
            error_log('getPerformanceChallenge error: ' . $e->getMessage());
            respondJsonError('Error interno al generar el challenge', 500, 'server_error');
        } catch (\Exception $e) {
            error_log('getPerformanceChallenge unexpected: ' . $e->getMessage());
            respondJsonError('Error inesperado', 500, 'server_error');
        }
        break;

    case 'verifyPerformanceChallenge':
        try {
            $resp = validatePerformanceChallenge($data); // puede lanzar excepciones
            echo json_encode(array_merge(['success' => true], $resp));
        } catch (\InvalidArgumentException $e) {
            // Error del cliente (input inválido; no logueamos el error adrede)
            respondJsonError($e->getMessage(), 400, 'invalid_input');
        } catch (\RuntimeException $e) {
            // Error interno (no exponer detalles)
            error_log('validatePerformanceChallenge runtime error: ' . $e->getMessage());
            respondJsonError('Error interno al validar el challenge', 500, 'server_error');
        } catch (\Exception $e) {
            error_log('validatePerformanceChallenge unexpected: ' . $e->getMessage());
            respondJsonError('Error inesperado', 500, 'server_error');
        }
        break;

    case 'GET_POW_CHALLENGE':
        // Esta función no lanza excepciones en captcha-lib.php, devuelve datos directamente
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
?>