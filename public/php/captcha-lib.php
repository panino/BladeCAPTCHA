<?php
namespace Captcha;

$configPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($configPath)) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error: config.php not found'
    ]);
    exit;
}
require_once $configPath;
require_once 'cors.php';

/* ------------------------- Check rate limits ------------------------- */

define('CAPTCHA_RATE_LIMIT_WINDOW', 60); // ventana en segundos
define('CAPTCHA_RATE_LIMIT_MAX', 50); 

function getClientIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getRateLimitFile(): string {
    $ip = getClientIP();
    $hash = md5($ip);
    return sys_get_temp_dir() . "/captcha_rl_{$hash}.tmp";
}

function checkRateLimit(): bool {
    $file = getRateLimitFile();
    $now = time();
    $timestamps = [];

    // Abrir el archivo con bloqueo exclusivo
    $fp = fopen($file, 'c+'); // crea si no existe
    if (!$fp) {
        // Si no podemos abrir el archivo, bloquear no es posible; considerar negar
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    // Leer contenido
    $content = stream_get_contents($fp);
    $lines = $content ? explode("\n", trim($content)) : [];

    // Filtrar timestamps válidos
    foreach ($lines as $line) {
        $t = (int)$line;
        if ($t > 0 && ($now - $t) < CAPTCHA_RATE_LIMIT_WINDOW) {
            $timestamps[] = $t;
        }
    }

    // Comprobar límite
    if (count($timestamps) >= CAPTCHA_RATE_LIMIT_MAX) {
        // Liberar lock y cerrar
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    // Registrar envío actual
    $timestamps[] = $now;

    // Reescribir el archivo desde cero
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, implode("\n", $timestamps) . "\n");

    // Liberar lock y cerrar
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}


/* ------------------------- Cleanup helper ------------------------- */
function cleanOldRateLogs($maxAge = 3600) {
    $dir = sys_get_temp_dir();
    foreach (glob($dir . '/captcha_*.log') as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime + $maxAge < time()) {
            @unlink($file);
        }
    }
}

/* ------------------------- Key handling ------------------------- */

function registerCaptchaKey(string $clave): bool {
    $file = sys_get_temp_dir() . '/captcha_key_' . $clave . '.log';
	if (!file_exists($file)) {
			
		$data = [
			'created_at'   => time(),  // cuando se generó la clave
			'last_request' => 0        // timestamp del último VALIDATE_POW_CHALLENGE
		];
		return file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR)) !== false;
	} 
	return true;
}

function validateCaptchaKey(string $clave): bool {
    $file = sys_get_temp_dir() . '/captcha_key_' . $clave . '.log';
    return file_exists($file);
}

/**
 * Devuelve true si pasó al menos $minDelay segundos desde el último request.
 * Solo actualiza el timestamp si pasó el tiempo mínimo.
 */
function validateCaptchaTiming(string $clave, int $minDelay = 10): bool {
    $file = sys_get_temp_dir() . '/captcha_key_' . $clave . '.log';
    if (!file_exists($file)) return false;

    $fp = fopen($file, 'c+'); // abrir para lectura/escritura, crear si no existe
    if (!$fp) return false;

    $ok = false;
    if (flock($fp, LOCK_EX)) { // lock exclusivo
        $content = stream_get_contents($fp);
        $data = [];
        if ($content !== false && strlen($content)) {
            $data = json_decode($content, true) ?: [];
        }

        $last = $data['last_request'] ?? 0;
        if ((time() - $last) >= $minDelay) {
            // actualizamos el timestamp solo si pasó el tiempo mínimo
            $data['last_request'] = time();
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_THROW_ON_ERROR));
            $ok = true;
        }

        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $ok;
}


/* ------------------------- PoW helpers ------------------------- */
function generateSignedChallenge(): string {
    $data = [
        'rnd' => bin2hex(random_bytes(8)),
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'time' => time(),
        'expiry' => CAPTCHA_EXPIRY
    ];
    $payload = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $payload, CAPTCHA_SECRET_KEY);
    return base64_encode(json_encode(['payload' => $payload, 'sig' => $signature]));
}

function validateChallenge(string $signedChallenge) {
    $raw = base64_decode($signedChallenge, true);
    if ($raw === false) return false;
    $decoded = json_decode($raw, true);
    if (!$decoded || !isset($decoded['payload']) || !isset($decoded['sig'])) return false;
    $expectedSig = hash_hmac('sha256', $decoded['payload'], CAPTCHA_SECRET_KEY);
    if (!hash_equals($expectedSig, $decoded['sig'])) return false;
    $data = json_decode(base64_decode($decoded['payload'], true), true);
    if (!$data) return false;
    if (($data['domain'] ?? '') !== ($_SERVER['HTTP_HOST'] ?? '')) return false;
    if (time() > ($data['time'] ?? 0) + ($data['expiry'] ?? 0)) return false;
    return $data;
}

function validatePoW(string $challenge, string $nonce, int $difficulty): bool {
    $hash = hash('sha256', $challenge . $nonce);
    return substr($hash, 0, $difficulty) === str_repeat('0', $difficulty);
}

/* ------------------------- Token handling ------------------------- */
function validateToken(string $token, int $maxAge = 60): bool {
    if (!$token || !preg_match('/^[a-f0-9]{32}$/i', $token)) return false;
    $token_file = sys_get_temp_dir() . "/captcha_token_$token.log";
    if (!file_exists($token_file)) return false;

    $mtime = @filemtime($token_file);
    if ($mtime === false || $mtime + $maxAge < time()) { 
        @unlink($token_file); 
        return false; 
    }

    $fp = @fopen($token_file, 'c');
    if (!$fp) {
        @unlink($token_file);
        return false;
    }

    $locked = flock($fp, LOCK_EX);
    if (!$locked) {
        fclose($fp);
        @unlink($token_file);
        return false;
    }

    ftruncate($fp, 0);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($token_file);

    return true;
}


/* ------------------------- PoW validation server-side ------------------------- */
function processValidatePoW(string $signedChallenge, string $nonce, string $claveCaptcha, int $difficulty = CAPTCHA_DIFFICULTY): array {
    if (!validateCaptchaKey($claveCaptcha)) {
        return ['success' => false, 'message' => 'Invalid captcha key'];
    }

    $challengeData = validateChallenge($signedChallenge);
    if (!$challengeData) return ['success' => false, 'message' => 'Invalid or expired challenge'];

    $isValid = validatePoW($signedChallenge, $nonce, $difficulty);

    if ($isValid) {
        $token = bin2hex(random_bytes(16));
        $token_file = sys_get_temp_dir() . "/captcha_token_$token.log";
        file_put_contents($token_file, (string)time());
        return ['success' => true, 'message' => 'Verification successful', 'token_validacion' => $token];
    } else {
        $fakeToken = bin2hex(random_bytes(16));
        return ['success' => false, 'message' => 'Proof of Work incorrecto', 'token_validacion' => $fakeToken];
    }
}

/* ------------------------- Error helper ------------------------- */
function respondJsonError(string $message, int $httpCode = 400, string $code = 'error') {
    if (!headers_sent()) header('Content-Type: application/json');
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'code' => $code, 'message' => $message]);
    exit;
}
