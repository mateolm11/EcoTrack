<?php
/**
 * EcoTrack - Conexión a Base de Datos mediante PDO
 * Asignatura: Aplicaciones Móviles y Cloud Computing (UPSO)
 * 
 * Cumple requisitos R6, R7 y R8:
 * - Credenciales leídas con getenv() sin secretos en el código fuente.
 * - Conexión PDO con sentencias preparadas nativas (PDO::ATTR_EMULATE_PREPARES => false).
 * - Manejo de excepciones retornando HTTP 500 en JSON sin filtrar datos técnicos.
 */

require_once __DIR__ . '/config.php';

// Lectura de variables de entorno para la base de datos (R7)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'ecotrack';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$pdo = null;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Consultas preparadas reales en el motor (R8)
    ]);
} catch (PDOException $e) {
    // Registro técnico del error en el log del servidor
    error_log("Error de conexión a la base de datos: " . $e->getMessage());

    // Respuesta limpia y sin filtrar rutas ni credenciales hacia el cliente (R8)
    responderJson([
        'exito' => false,
        'mensaje' => 'Error de conexión con la base de datos. Verifique la configuración del servidor.'
    ], 500);
}
