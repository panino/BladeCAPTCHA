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
?>