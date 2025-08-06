<?php
// captcha-lib.php
// Librería reusable con las funciones del captcha (no imprime JSON ni responde HTTP).
namespace Captcha;

$configPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

if (!file_exists($configPath)) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración: config.php no encontrado.'
    ]);
    exit;
}

require_once $configPath;

/* ------------------------- Key derivation ------------------------- */
function derive_keys(string $master_key): array {
    $enc = hash_hmac('sha256', 'enc', $master_key, true);
    $mac = hash_hmac('sha256', 'mac', $master_key, true);
    return ['enc' => $enc, 'mac' => $mac];
}

/* ------------------------- Rate / temp file helpers ------------------------- */
function cleanOldRateLogs($maxAge = 3600) {
    $dir = sys_get_temp_dir();
    foreach (glob($dir . '/captcha_*.log') as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime + $maxAge < time()) {
            @unlink($file);
        }
    }
}

function getClientIP(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    return trim($ip);
}

function getRateLimitFile(string $ip): string {
    return sys_get_temp_dir() . '/captcha_' . md5($ip) . '.log';
}

function readRateLimitData(string $ip): array {
    $file = getRateLimitFile($ip);
    if (!file_exists($file)) return ['last_request' => 0, 'difficulty' => CAPTCHA_DIFFICULTY];

    $fp = @fopen($file, 'r');
    if (!$fp) return ['last_request' => 0, 'difficulty' => CAPTCHA_DIFFICULTY];

    $data = null;
    if (flock($fp, LOCK_SH)) {
        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) $data = $decoded;
    }
    fclose($fp);
    if (!is_array($data)) return ['last_request' => 0, 'difficulty' => CAPTCHA_DIFFICULTY];
    return $data + ['last_request' => 0, 'difficulty' => CAPTCHA_DIFFICULTY];
}

function writeRateLimitData(string $ip, array $data): bool {
    $file = getRateLimitFile($ip);
    $fp = @fopen($file, 'c');
    if (!$fp) return false;
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = true;
    }
    fclose($fp);
    return $ok;
}

function rateLimitCheck(string $ip): bool {
    $data = readRateLimitData($ip);
    if (time() - ($data['last_request'] ?? 0) < 10) return false;
    $data['last_request'] = time();
    writeRateLimitData($ip, $data);
    return true;
}

/* ------------------------- Performance challenge ------------------------- */
/**
 * Devuelve un array con token y target_iterations.
 */
function generatePerformanceChallenge(): array {
    $keys = derive_keys(CAPTCHA_SECRET_KEY);
    $key = $keys['enc'];
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $payload = json_encode(['ts' => time()]);
    $cipher = openssl_encrypt($payload, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new \RuntimeException('Error interno de cifrado');
    }
    $hmac = hash_hmac('sha256', $cipher . $iv, $keys['mac'], true);
    $token = base64_encode($hmac . $iv . $cipher);
    return ['token' => $token, 'target_iterations' => 1000000];
}

/**
 * Valida un performance token (devuelve array con ok, difficulty y delta)
 */
function validatePerformanceChallenge(array $data): array {
    $keys = derive_keys(CAPTCHA_SECRET_KEY);
    $hmac_key = $keys['mac'];
    $iv_len = openssl_cipher_iv_length('aes-256-cbc');

    $raw = base64_decode($data['token'] ?? '', true);
    if ($raw === false) throw new \InvalidArgumentException('Token inválido (base64)');

    $hmac_len = 32;
    if (strlen($raw) < ($hmac_len + $iv_len + 1)) throw new \InvalidArgumentException('Token demasiado corto');

    $hmac = substr($raw, 0, $hmac_len);
    $iv = substr($raw, $hmac_len, $iv_len);
    $cipher = substr($raw, $hmac_len + $iv_len);
    $expected_hmac = hash_hmac('sha256', $cipher . $iv, $hmac_key, true);
    if (!hash_equals($expected_hmac, $hmac)) throw new \RuntimeException('Token alterado o inválido');

    $key = $keys['enc'];
    $decrypted = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) throw new \RuntimeException('No se pudo descifrar token');
    $payload = json_decode($decrypted, true);
    if (!is_array($payload) || !isset($payload['ts'])) throw new \RuntimeException('Payload inválido');

    $start_time = (int)$payload['ts'];
    $delta = time() - $start_time;
    if ($delta > 10) {
        $dificultad = 2;
    } elseif ($delta > 5) {
        $dificultad = 3;
    } else {
        $dificultad = CAPTCHA_DIFFICULTY;
    }

    $ip = getClientIP();
    $info = readRateLimitData($ip);
    $info['difficulty'] = $dificultad;
    writeRateLimitData($ip, $info);

    return ['ok' => $delta, 'difficulty' => $dificultad];
}

/* ------------------------- Signed PoW challenge ------------------------- */

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

/* ------------------------- PoW validation ------------------------- */

function validatePoW(string $challenge, string $nonce, int $difficulty): bool {
    $hash = hash('sha256', $challenge . $nonce);
    return substr($hash, 0, $difficulty) === str_repeat('0', $difficulty);
}

/* ------------------------- Token handling (one-time with expiry) ------------------------- */

function validateToken(string $token, int $maxAge = 300): bool {
    if (!$token || !preg_match('/^[a-f0-9]{32}$/i', $token)) return false;
    $token_file = sys_get_temp_dir() . "/captcha_token_$token.log";
    if (!file_exists($token_file)) return false;

    $mtime = @filemtime($token_file);
    if ($mtime === false) { @unlink($token_file); return false; }
    if ($mtime + $maxAge < time()) { @unlink($token_file); return false; }

    $fp = @fopen($token_file, 'c');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            clearstatcache(true, $token_file);
            $mtime2 = @filemtime($token_file);
            if ($mtime2 === false || $mtime2 + $maxAge < time()) {
                flock($fp, LOCK_UN);
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
        fclose($fp);
    }
    @unlink($token_file);
    return true;
}

/* ------------------------- Helper para procesar validación PoW (server-side) ------------------------- */

/**
 * Procesa una sumisión de PoW: valida challenge y nonce, aplica rate limit por IP,
 * crea token en disco si es válido y retorna un array con success/message y token_validacion (solo cuando success=true).
 */
function processValidatePoW(string $signedChallenge, string $nonce, string $ip): array {
    // rate limit
    if (!rateLimitCheck($ip)) {
        return ['success' => false, 'message' => 'Demasiadas solicitudes','status' => 429];
    }

    $challengeData = validateChallenge($signedChallenge);
    if (!$challengeData) return ['success' => false, 'message' => 'Challenge inválido o expirado'];

    $info = readRateLimitData($ip);
    $dificultad = (int)($info['difficulty'] ?? CAPTCHA_DIFFICULTY);
    $isValid = validatePoW($signedChallenge, $nonce, $dificultad);

    if ($isValid) {
        $token = bin2hex(random_bytes(16));
        $token_file = sys_get_temp_dir() . "/captcha_token_$token.log";
        $fp = @fopen($token_file, 'c');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                fwrite($fp, (string)time());
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            return ['success' => false, 'message' => 'Error interno al crear token'];
        }

        return ['success' => true, 'message' => 'Verificación exitosa', 'token_validacion' => $token];
    } else {
        // devuelvo un token "señuelo" con el mismo formato pero no persistido
        $fakeToken = bin2hex(random_bytes(16));
        return ['success' => false, 'message' => 'Proof of Work incorrecto', 'token_validacion' => $fakeToken];
    }
}
?>