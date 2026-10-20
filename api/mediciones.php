<?php
/**
 * EcoTrack - Controlador RESTful para Mediciones Ambientales
 * Asignatura: Aplicaciones Móviles y Cloud Computing (UPSO)
 * 
 * Cumple requisitos R1, R2 y R8:
 * - Operaciones completas CRUD: GET (listar/obtener), POST (crear), PUT (modificar), DELETE (borrar).
 * - Respuestas estrictamente en formato JSON con cabeceras estándar.
 * - Códigos de estado HTTP semánticos:
 *     200 OK: Consulta, actualización o eliminación exitosa.
 *     201 Created: Alta exitosa de medición.
 *     400 Bad Request: Petición malformada o parámetros ausentes.
 *     404 Not Found: Recurso no encontrado por identificador.
 *     422 Unprocessable Entity: Validación semántica fallida en datos de entrada.
 *     500 Internal Server Error: Error imprevisto de base de datos o servidor.
 * - Consultas preparadas con PDO para total protección contra SQL Injection.
 * - Preparado para ser consumido tanto por el frontend web como por la app Android del TP2.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/servicios.php';

// Determinación del verbo HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Para clientes que emulan PUT/DELETE vía POST con parámetro o encabezado _method
if ($metodo === 'POST' && isset($_POST['_method'])) {
    $metodo = strtoupper($_POST['_method']);
}

// Obtener identificador si viene en la query string (ej: ?id=5)
$idParam = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Helper para leer payload JSON o datos de formulario
function obtenerPayload(): array {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    if (is_array($json)) {
        return $json;
    }
    // Fallback a $_POST si no vino JSON puro
    return is_array($_POST) ? $_POST : [];
}

// Helper para validación estricta de datos de una medición ambiental
function validarDatosMedicion(array $datos, bool $esEdicion = false): array {
    $errores = [];

    // Ubicación (obligatoria)
    if (!isset($datos['ubicacion']) || trim($datos['ubicacion']) === '') {
        $errores['ubicacion'] = 'La ubicación o punto de medición es obligatorio.';
    } elseif (mb_strlen(trim($datos['ubicacion'])) > 100) {
        $errores['ubicacion'] = 'La ubicación no puede exceder los 100 caracteres.';
    }

    // Temperatura (obligatoria, numérica, rango físico realista: -60 °C a 70 °C)
    if (!isset($datos['temperatura']) || $datos['temperatura'] === '') {
        $errores['temperatura'] = 'La temperatura es obligatoria.';
    } elseif (!is_numeric($datos['temperatura'])) {
        $errores['temperatura'] = 'La temperatura debe ser un valor numérico.';
    } else {
        $temp = (float)$datos['temperatura'];
        if ($temp < -60.0 || $temp > 70.0) {
            $errores['temperatura'] = 'La temperatura debe estar en el rango de -60.0 °C a 70.0 °C.';
        }
    }

    // Humedad (obligatoria, numérica, porcentaje relativo: 0 % a 100 %)
    if (!isset($datos['humedad']) || $datos['humedad'] === '') {
        $errores['humedad'] = 'La humedad relativa es obligatoria.';
    } elseif (!is_numeric($datos['humedad'])) {
        $errores['humedad'] = 'La humedad debe ser un valor numérico.';
    } else {
        $hum = (float)$datos['humedad'];
        if ($hum < 0.0 || $hum > 100.0) {
            $errores['humedad'] = 'La humedad debe estar comprendida entre 0% y 100%.';
        }
    }

    // Fecha (opcional, si viene debe ser válida YYYY-MM-DD HH:MM:SS)
    if (!empty($datos['fecha'])) {
        $fechaTimestamp = strtotime($datos['fecha']);
        if ($fechaTimestamp === false) {
            $errores['fecha'] = 'El formato de fecha no es válido (use YYYY-MM-DD o ISO 8601).';
        }
    }

    // Coordenadas opcionales
    if (isset($datos['latitud']) && $datos['latitud'] !== '' && !is_numeric($datos['latitud'])) {
        $errores['latitud'] = 'La latitud debe ser un valor decimal.';
    }
    if (isset($datos['longitud']) && $datos['longitud'] !== '' && !is_numeric($datos['longitud'])) {
        $errores['longitud'] = 'La longitud debe ser un valor decimal.';
    }

    return $errores;
}

// Enrutador de verbos HTTP
switch ($metodo) {
    // -------------------------------------------------------------
    // GET: Listar todas las mediciones o consultar una específica
    // -------------------------------------------------------------
    case 'GET':
        try {
            if ($idParam !== null) {
                // Consulta de una sola medición
                $stmt = $pdo->prepare("SELECT id, ubicacion, temperatura, humedad, fecha, latitud, longitud, observaciones FROM mediciones WHERE id = :id");
                $stmt->execute([':id' => $idParam]);
                $medicion = $stmt->fetch();

                if (!$medicion) {
                    responderJson([
                        'exito' => false,
                        'mensaje' => "Medición con identificador {$idParam} no encontrada."
                    ], 404);
                }

                responderJson([
                    'exito' => true,
                    'datos' => $medicion
                ], 200);

            } else {
                // Listado general con soporte de filtro de búsqueda opcional
                $filtro = trim($_GET['buscar'] ?? '');
                if ($filtro !== '') {
                    $stmt = $pdo->prepare("SELECT id, ubicacion, temperatura, humedad, fecha, latitud, longitud, observaciones FROM mediciones WHERE ubicacion LIKE :filtro ORDER BY fecha DESC");
                    $stmt->execute([':filtro' => '%' . $filtro . '%']);
                } else {
                    $stmt = $pdo->query("SELECT id, ubicacion, temperatura, humedad, fecha, latitud, longitud, observaciones FROM mediciones ORDER BY fecha DESC");
                }
                $mediciones = $stmt->fetchAll();

                responderJson([
                    'exito' => true,
                    'total' => count($mediciones),
                    'datos' => $mediciones
                ], 200);
            }
        } catch (PDOException $e) {
            error_log("Error en GET /api/mediciones.php: " . $e->getMessage());
            responderJson([
                'exito' => false,
                'mensaje' => 'Error interno al consultar las mediciones.'
            ], 500);
        }
        break;

    // -------------------------------------------------------------
    // POST: Registrar una nueva medición ambiental
    // -------------------------------------------------------------
    case 'POST':
        $payload = obtenerPayload();
        $errores = validarDatosMedicion($payload);

        if (!empty($errores)) {
            responderJson([
                'exito' => false,
                'mensaje' => 'Los datos enviados contienen errores de validación.',
                'errores' => $errores
            ], 422);
        }

        try {
            $ubicacion     = trim($payload['ubicacion']);
            $temperatura   = round((float)$payload['temperatura'], 1);
            $humedad       = round((float)$payload['humedad'], 1);
            $fecha         = !empty($payload['fecha']) ? date('Y-m-d H:i:s', strtotime($payload['fecha'])) : date('Y-m-d H:i:s');
            $latitud       = isset($payload['latitud']) && is_numeric($payload['latitud']) ? (float)$payload['latitud'] : null;
            $longitud      = isset($payload['longitud']) && is_numeric($payload['longitud']) ? (float)$payload['longitud'] : null;
            $observaciones = trim($payload['observaciones'] ?? '');
            $avisoServicio = null;

            // Geocodificación automática si no se enviaron coordenadas manuales
            if ($latitud === null || $longitud === null) {
                $partesUbicacion = explode(' - ', $ubicacion);
                $ciudadBusqueda = trim($partesUbicacion[0]);
                if ($ciudadBusqueda === '') {
                    $ciudadBusqueda = $ubicacion;
                }

                $geo = geocodificarCiudad($ciudadBusqueda);

                if ($geo['disponible'] === true && $geo['encontrada'] === false) {
                    // Caso 1: Ciudad inexistente -> 422
                    responderJson([
                        'exito' => false,
                        'mensaje' => "No se encontró la ubicación '{$ciudadBusqueda}' en el servicio geográfico. Ingrese una localidad real o seleccione un punto en el mapa.",
                        'errores' => ['ubicacion' => 'Localidad no encontrada.']
                    ], 422);
                } elseif ($geo['disponible'] === true && $geo['encontrada'] === true) {
                    // Caso 2: Coordenadas encontradas
                    $latitud = (float)$geo['resultados'][0]['latitud'];
                    $longitud = (float)$geo['resultados'][0]['longitud'];
                } elseif (!empty($geo['degradado'])) {
                    // Caso 3: Servicio externo caído -> Modo degradado, no bloquea guardado
                    $latitud = null;
                    $longitud = null;
                    $avisoServicio = 'Servicio de geocodificación no disponible; se guardó la medición sin coordenadas.';
                }
            }

            $sql = "INSERT INTO mediciones (ubicacion, temperatura, humedad, fecha, latitud, longitud, observaciones) 
                    VALUES (:ubicacion, :temperatura, :humedad, :fecha, :latitud, :longitud, :observaciones)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ubicacion'     => $ubicacion,
                ':temperatura'   => $temperatura,
                ':humedad'       => $humedad,
                ':fecha'         => $fecha,
                ':latitud'       => $latitud,
                ':longitud'      => $longitud,
                ':observaciones' => $observaciones
            ]);

            $nuevoId = (int)$pdo->lastInsertId();

            $datosRespuesta = [
                'id'            => $nuevoId,
                'ubicacion'     => $ubicacion,
                'temperatura'   => $temperatura,
                'humedad'       => $humedad,
                'fecha'         => $fecha,
                'latitud'       => $latitud,
                'longitud'      => $longitud,
                'observaciones' => $observaciones
            ];

            // Envío o simulación del aviso de alta
            enviarAvisoMedicion($datosRespuesta);

            $respuestaJson = [
                'exito'   => true,
                'mensaje' => 'Medición ambiental registrada exitosamente.',
                'datos'   => $datosRespuesta
            ];

            if ($avisoServicio !== null) {
                $respuestaJson['aviso_servicio'] = $avisoServicio;
            }

            responderJson($respuestaJson, 201); // 201 Created obligatorio según consigna

        } catch (PDOException $e) {
            error_log("Error en POST /api/mediciones.php: " . $e->getMessage());
            responderJson([
                'exito' => false,
                'mensaje' => 'Fallo al guardar la medición ambiental en el servidor.'
            ], 500);
        }
        break;

    // -------------------------------------------------------------
    // PUT: Modificar una medición ambiental existente
    // -------------------------------------------------------------
    case 'PUT':
        $payload = obtenerPayload();

        // El id puede venir por query string (?id=X) o dentro del payload
        $id = $idParam ?? ($payload['id'] ?? null);

        if (!$id || !is_numeric($id)) {
            responderJson([
                'exito' => false,
                'mensaje' => 'Identificador de medición no especificado o inválido.'
            ], 400);
        }

        $id = (int)$id;

        // Comprobar existencia previa del recurso (R2: 404 si no existe)
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM mediciones WHERE id = :id");
            $stmtCheck->execute([':id' => $id]);
            if (!$stmtCheck->fetch()) {
                responderJson([
                    'exito' => false,
                    'mensaje' => "No se encontró la medición con ID {$id} para modificar."
                ], 404);
            }

            // Validar datos
            $errores = validarDatosMedicion($payload, true);
            if (!empty($errores)) {
                responderJson([
                    'exito' => false,
                    'mensaje' => 'Datos inválidos para la actualización.',
                    'errores' => $errores
                ], 422);
            }

            $ubicacion     = trim($payload['ubicacion']);
            $temperatura   = round((float)$payload['temperatura'], 1);
            $humedad       = round((float)$payload['humedad'], 1);
            $fecha         = !empty($payload['fecha']) ? date('Y-m-d H:i:s', strtotime($payload['fecha'])) : date('Y-m-d H:i:s');
            $latitud       = isset($payload['latitud']) && is_numeric($payload['latitud']) ? (float)$payload['latitud'] : null;
            $longitud      = isset($payload['longitud']) && is_numeric($payload['longitud']) ? (float)$payload['longitud'] : null;
            $observaciones = trim($payload['observaciones'] ?? '');
            $avisoServicio = null;

            // Geocodificación automática si no se enviaron coordenadas manuales
            if ($latitud === null || $longitud === null) {
                $partesUbicacion = explode(' - ', $ubicacion);
                $ciudadBusqueda = trim($partesUbicacion[0]);
                if ($ciudadBusqueda === '') {
                    $ciudadBusqueda = $ubicacion;
                }

                $geo = geocodificarCiudad($ciudadBusqueda);

                if ($geo['disponible'] === true && $geo['encontrada'] === false) {
                    // Caso 1: Ciudad inexistente -> 422
                    responderJson([
                        'exito' => false,
                        'mensaje' => "No se encontró la ubicación '{$ciudadBusqueda}' en el servicio geográfico. Ingrese una localidad real o seleccione un punto en el mapa.",
                        'errores' => ['ubicacion' => 'Localidad no encontrada.']
                    ], 422);
                } elseif ($geo['disponible'] === true && $geo['encontrada'] === true) {
                    // Caso 2: Coordenadas encontradas
                    $latitud = (float)$geo['resultados'][0]['latitud'];
                    $longitud = (float)$geo['resultados'][0]['longitud'];
                } elseif (!empty($geo['degradado'])) {
                    // Caso 3: Servicio externo caído -> Modo degradado
                    $latitud = null;
                    $longitud = null;
                    $avisoServicio = 'Servicio de geocodificación no disponible; se guardó la medición sin coordenadas.';
                }
            }

            $sqlUpdate = "UPDATE mediciones 
                          SET ubicacion = :ubicacion, 
                              temperatura = :temperatura, 
                              humedad = :humedad, 
                              fecha = :fecha, 
                              latitud = :latitud, 
                              longitud = :longitud, 
                              observaciones = :observaciones 
                          WHERE id = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':id'            => $id,
                ':ubicacion'     => $ubicacion,
                ':temperatura'   => $temperatura,
                ':humedad'       => $humedad,
                ':fecha'         => $fecha,
                ':latitud'       => $latitud,
                ':longitud'      => $longitud,
                ':observaciones' => $observaciones
            ]);

            $respuestaJson = [
                'exito'   => true,
                'mensaje' => "Medición con ID {$id} actualizada correctamente.",
                'datos'   => [
                    'id'            => $id,
                    'ubicacion'     => $ubicacion,
                    'temperatura'   => $temperatura,
                    'humedad'       => $humedad,
                    'fecha'         => $fecha,
                    'latitud'       => $latitud,
                    'longitud'      => $longitud,
                    'observaciones' => $observaciones
                ]
            ];

            if ($avisoServicio !== null) {
                $respuestaJson['aviso_servicio'] = $avisoServicio;
            }

            responderJson($respuestaJson, 200);

        } catch (PDOException $e) {
            error_log("Error en PUT /api/mediciones.php: " . $e->getMessage());
            responderJson([
                'exito' => false,
                'mensaje' => 'Error interno al actualizar la medición.'
            ], 500);
        }
        break;

    // -------------------------------------------------------------
    // DELETE: Borrar una medición ambiental existente
    // -------------------------------------------------------------
    case 'DELETE':
        $payload = obtenerPayload();
        $id = $idParam ?? ($payload['id'] ?? null);

        if (!$id || !is_numeric($id)) {
            responderJson([
                'exito' => false,
                'mensaje' => 'Identificador de medición no especificado o inválido para eliminación.'
            ], 400);
        }

        $id = (int)$id;

        try {
            // Verificar existencia antes de borrar
            $stmtCheck = $pdo->prepare("SELECT id FROM mediciones WHERE id = :id");
            $stmtCheck->execute([':id' => $id]);
            if (!$stmtCheck->fetch()) {
                responderJson([
                    'exito' => false,
                    'mensaje' => "No se encontró la medición con ID {$id} para borrar."
                ], 404);
            }

            // Eliminación con consulta preparada
            $stmtDelete = $pdo->prepare("DELETE FROM mediciones WHERE id = :id");
            $stmtDelete->execute([':id' => $id]);

            responderJson([
                'exito'   => true,
                'mensaje' => "Medición con ID {$id} eliminada exitosamente."
            ], 200);

        } catch (PDOException $e) {
            error_log("Error en DELETE /api/mediciones.php: " . $e->getMessage());
            responderJson([
                'exito' => false,
                'mensaje' => 'Error interno al eliminar la medición.'
            ], 500);
        }
        break;

    default:
        responderJson([
            'exito' => false,
            'mensaje' => "Método HTTP {$metodo} no permitido en este endpoint."
        ], 405);
        break;
}
