<?php
if (!empty($config['cors_enabled']) && $config['cors_enabled'] === true) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Verificar si el origen está en la lista de permitidos
    if (in_array($origin, $config['cors_allowed_origins'], true)) {
        header("Access-Control-Allow-Origin: {$origin}");

        if (!empty($config['cors_allow_credentials'])) {
            header('Access-Control-Allow-Credentials: true');
        }

        // Métodos permitidos
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

        // Encabezados permitidos
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Responder preflight OPTIONS sin procesar el resto
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
?>