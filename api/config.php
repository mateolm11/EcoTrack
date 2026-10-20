<?php
/**
 * EcoTrack - Configuración Central y Helpers
 * Asignatura: Aplicaciones Móviles y Cloud Computing (UPSO)
 * 
 * Cumple requisitos R6, R7 y R8:
 * - Zona horaria local configurada.
 * - Credenciales leídas con getenv() sin datos sensibles en código.
 * - display_errors apagado en producción y registro en error_log.
 * - Headers REST y CORS controlados por entorno.
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Carga opcional de archivo .env para desarrollo local si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Configuración de entorno (acepta ENTORNO de la cátedra o APP_ENV)
$entorno = getenv('ENTORNO') ?: getenv('APP_ENV') ?: 'local';
$esProduccion = in_array(strtolower($entorno), ['produccion', 'production'], true);

if ($esProduccion) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');

// Variables opcionales para avisos por email (simulación si está vacía)
$resendApiKey = getenv('RESEND_API_KEY') ?: '';
$emailAvisos = getenv('EMAIL_AVISOS') ?: '';

// Cabeceras HTTP estándar para API REST
header('Content-Type: application/json; charset=UTF-8');

// CORS controlado según variable de entorno (R8: no exponer '*' sin justificación)
$corsOrigin = getenv('CORS_ORIGIN');
if ($corsOrigin !== false && trim($corsOrigin) !== '') {
    header('Access-Control-Allow-Origin: ' . trim($corsOrigin));
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejo de solicitudes preliminares (pre-flight OPTIONS)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Función auxiliar para emitir respuestas JSON estandarizadas
 * @param mixed $data Datos o mensaje a enviar
 * @param int $statusCode Código de estado HTTP (200, 201, 400, 404, 500, etc.)
 */
function responderJson($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Inclusión de la conexión PDO para compatibilidad total con scripts existentes
if (!isset($pdo)) {
    require_once __DIR__ . '/conexion.php';
}
