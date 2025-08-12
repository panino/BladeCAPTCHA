<?php
/**
 * Archivo de configuración de ejemplo para el CAPTCHA accesible y privado
 * Copiar este archivo a config/config.php y ajustar los valores según sea necesario.
 */

// Clave secreta utilizada para firmar y validar desafíos
define('CAPTCHA_SECRET_KEY', 'cambia-esta-clave-por-una-segura');

// Dificultad inicial del desafío PoW (número de ceros iniciales en el hash)
define('CAPTCHA_DIFFICULTY', 4);

// Tiempo de expiración del desafío PoW
define('CAPTCHA_EXPIRY', 300); // 5 minutos

// CORS
// Desactivar CORS si el frontend y backend están en el mismo dominio
$config['cors_enabled'] = false;

// Si necesitás habilitar CORS para peticiones desde otros dominios, configurar orígenes permitidos así:
// $config['cors_allowed_origins'] = [
//     'https://tu-frontend-dominio.com',
//     'https://otro-dominio.com'
// ];
// Nota: no usar '*' para permitir todos los orígenes por seguridad y compatibilidad con credenciales.

// Permitir cookies y tokens en solicitudes CORS (solo si cors_enabled = true)
// $config['cors_allow_credentials'] = true;
?>