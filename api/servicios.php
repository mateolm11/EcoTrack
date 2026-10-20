<?php
/**
 * EcoTrack - Servicios Externos y Degradación Elegante
 * Asignatura: Aplicaciones Móviles y Cloud Computing (UPSO)
 * 
 * Cumple requisitos R4 y R5:
 * - Integración con la API meteorológica externa de Open-Meteo usando cURL nativo.
 * - Soporte de variables de entorno para endpoints externos y credenciales de notificación.
 * - Degradación elegante (Graceful Degradation): Si la API de terceros no responde
 *   o la red está caída, la aplicación captura el fallo, lo registra en error_log
 *   y continúa funcionando sin propagar un error 500 al cliente ni detener el alta de registros.
 */

// Si no se incluyó desde otro script, cargar la configuración base
if (!function_exists('responderJson')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Función auxiliar interna para realizar peticiones HTTP GET mediante cURL
 * Configura timeouts estrictos y opciones SSL para compatibilidad con Windows / XAMPP.
 * 
 * @param string $url URL a consultar
 * @param int $timeout Tiempo máximo de espera en segundos
 * @param int $connectTimeout Tiempo máximo para conectar en segundos
 * @return array Resultado con exito, httpCode, cuerpo, errno y error
 */
function ejecutarGetCurl(string $url, int $timeout = 4, int $connectTimeout = 3): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: EcoTrack-TP1-UPSO/1.0',
        'Accept: application/json'
    ]);

    // Evita fallos de certificados SSL en entornos XAMPP sobre Windows
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }

    $cuerpo = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'exito'    => ($errno === 0 && $httpCode === 200),
        'httpCode' => $httpCode,
        'cuerpo'   => $cuerpo,
        'errno'    => $errno,
        'error'    => $error
    ];
}

/**
 * Consulta el clima actual para coordenadas dadas usando la API externa de Open-Meteo
 * 
 * @param float $latitud Latitud geográfica
 * @param float $longitud Longitud geográfica
 * @return array Estructura estandarizada con datos climáticos o reporte degradado
 */
function obtenerClimaActual(float $latitud, float $longitud): array {
    $apiBase = getenv('WEATHER_API_BASE_URL') ?: 'https://api.open-meteo.com/v1/forecast';
    $url = sprintf(
        "%s?latitude=%.4f&longitude=%.4f&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=auto",
        $apiBase,
        $latitud,
        $longitud
    );

    $res = ejecutarGetCurl($url);

    if (!$res['exito']) {
        // Registro en log del servidor para auditoría técnica (R5 y R8)
        error_log(sprintf(
            "[EcoTrack Servicios Externos] Fallo de API Clima (lat: %.4f, lon: %.4f): HTTP %d, Errno %d (%s)",
            $latitud,
            $longitud,
            $res['httpCode'],
            $res['errno'],
            $res['error']
        ));

        // Degradación elegante: Se devuelve estado degradado sin romper el sistema principal
        return [
            'disponible' => false,
            'degradado'  => true,
            'proveedor'  => 'Open-Meteo Forecast API',
            'datos'      => null,
            'mensaje'    => 'Servicio meteorológico temporalmente no disponible. La aplicación continúa funcionando en modo local.'
        ];
    }

    $datos = json_decode($res['cuerpo'], true);

    if (!is_array($datos) || !isset($datos['current'])) {
        error_log("[EcoTrack Servicios Externos] Respuesta climática sin estructura esperada.");
        return [
            'disponible' => false,
            'degradado'  => true,
            'proveedor'  => 'Open-Meteo Forecast API',
            'datos'      => null,
            'mensaje'    => 'Servicio meteorológico temporalmente no disponible. La aplicación continúa funcionando en modo local.'
        ];
    }

    $current = $datos['current'];

    return [
        'disponible' => true,
        'degradado'  => false,
        'proveedor'  => 'Open-Meteo Forecast API',
        'datos'      => [
            'temperatura' => (float)$current['temperature_2m'],
            'humedad'     => (float)$current['relative_humidity_2m'],
            'viento_kmh'  => (float)($current['wind_speed_10m'] ?? 0),
            'codigo_wmo'  => (int)($current['weather_code'] ?? 0),
            'fecha_hora'  => $current['time'] ?? date('Y-m-d H:i')
        ],
        'mensaje'    => 'Datos meteorológicos actualizados correctamente desde el servicio cloud.'
    ];
}

/**
 * Geocodifica el nombre de una ciudad o localidad para obtener sus coordenadas geográficas
 * Cumple R4 y R5 con soporte para ciudades no encontradas y modo degradado.
 * 
 * @param string $ciudad Nombre de la ciudad o localidad
 * @return array Lista de coincidencias o reporte degradado
 */
function geocodificarCiudad(string $ciudad): array {
    $apiBase = getenv('GEOCODING_API_BASE_URL') ?: 'https://geocoding-api.open-meteo.com/v1/search';
    $url = $apiBase . '?name=' . urlencode($ciudad) . '&count=5&language=es&format=json';

    $res = ejecutarGetCurl($url);

    // Fallo de red o respuesta HTTP distinta de 200
    if (!$res['exito']) {
        error_log(sprintf(
            "[EcoTrack Servicios Externos] Fallo Geocodificación para '%s': HTTP %d, Errno %d (%s)",
            $ciudad,
            $res['httpCode'],
            $res['errno'],
            $res['error']
        ));
        return [
            'disponible' => false,
            'degradado'  => true,
            'encontrada' => false,
            'resultados' => [],
            'mensaje'    => 'Servicio de geocodificación no disponible.'
        ];
    }

    $datos = json_decode($res['cuerpo'], true);

    // Open-Meteo devuelve 200 pero sin la clave 'results' (o vacía) cuando la localidad no existe
    if (!is_array($datos) || !isset($datos['results']) || empty($datos['results'])) {
        return [
            'disponible' => true,
            'degradado'  => false,
            'encontrada' => false,
            'resultados' => []
        ];
    }

    $resultados = array_map(function($item) {
        return [
            'nombre'    => $item['name'] ?? '',
            'pais'      => $item['country'] ?? '',
            'provincia' => $item['admin1'] ?? '',
            'latitud'   => (float)($item['latitude'] ?? 0),
            'longitud'  => (float)($item['longitude'] ?? 0)
        ];
    }, $datos['results']);

    return [
        'disponible' => true,
        'degradado'  => false,
        'encontrada' => true,
        'resultados' => $resultados
    ];
}

/**
 * Envía notificación de nueva medición ambiental registrada
 * Si RESEND_API_KEY no está configurada, opera en modo simulación escribiendo en api/avisos.log.
 * Si está configurada, despacha por email vía Resend API con cURL sin detener el flujo si falla (R5).
 * 
 * @param array $medicion Datos de la medición registrada
 */
function enviarAvisoMedicion(array $medicion): void {
    $apiKey = getenv('RESEND_API_KEY') ?: '';
    $emailDestino = getenv('EMAIL_AVISOS') ?: '';

    $fecha = $medicion['fecha'] ?? date('Y-m-d H:i:s');
    $ubicacion = $medicion['ubicacion'] ?? 'Desconocida';
    $temp = isset($medicion['temperatura']) ? (float)$medicion['temperatura'] : 0.0;
    $hum = isset($medicion['humedad']) ? (float)$medicion['humedad'] : 0.0;

    // Modo simulación (por defecto): guarda en log local sin requerir credenciales externas
    if (empty($apiKey) || empty($emailDestino)) {
        $logPath = __DIR__ . '/avisos.log';
        $linea = sprintf(
            "[%s] Aviso de nueva medición (SIMULACIÓN): Ubicación '%s', Temperatura %.1f °C, Humedad %.1f %%\n",
            $fecha,
            $ubicacion,
            $temp,
            $hum
        );
        @file_put_contents($logPath, $linea, FILE_APPEND);
        error_log("[EcoTrack Aviso] " . trim($linea));
        return;
    }

    // Modo real: Envío mediante API REST de Resend usando cURL
    try {
        $cuerpoEmail = sprintf(
            "<p>Se ha registrado una nueva medición ambiental en el sistema <strong>EcoTrack</strong>:</p>" .
            "<ul>" .
            "<li><strong>Ubicación:</strong> %s</li>" .
            "<li><strong>Temperatura:</strong> %.1f °C</li>" .
            "<li><strong>Humedad:</strong> %.1f %%</li>" .
            "<li><strong>Fecha y Hora:</strong> %s</li>" .
            "</ul>",
            htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8'),
            $temp,
            $hum,
            htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8')
        );

        $payload = json_encode([
            'from'    => 'EcoTrack <onboarding@resend.dev>',
            'to'      => [$emailDestino],
            'subject' => "EcoTrack: Nueva medición registrada en {$ubicacion}",
            'html'    => $cuerpoEmail
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: EcoTrack-TP1-UPSO/1.0'
        ]);

        if (defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        $respuesta = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $httpCode < 200 || $httpCode >= 300) {
            error_log(sprintf(
                "[EcoTrack Aviso] Fallo al enviar email con Resend: HTTP %d, Errno %d, Error: %s, Respuesta: %s",
                $httpCode,
                $errno,
                $error,
                $respuesta
            ));
        }
    } catch (Throwable $e) {
        // R5: Degradación elegante, no interrumpir el flujo principal
        error_log("[EcoTrack Aviso] Excepción al enviar aviso por email: " . $e->getMessage());
    }
}

// Si este archivo es invocado directamente como endpoint HTTP GET:
// Ejemplo: /api/servicios.php?accion=clima&lat=-38.7183&lon=-62.2663
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $accion = $_GET['accion'] ?? 'clima';

    if ($accion === 'clima') {
        // Coordenadas por defecto (Tres Arroyos) si no se especifican
        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : -38.3739;
        $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -60.2798;
        $resultado = obtenerClimaActual($lat, $lon);
        responderJson($resultado, 200);
    } elseif ($accion === 'geocodificar') {
        $ciudad = trim($_GET['ciudad'] ?? '');
        if ($ciudad === '') {
            responderJson([
                'exito' => false,
                'mensaje' => 'Debe ingresar un nombre de ciudad para geocodificar.'
            ], 400);
        }
        $resultado = geocodificarCiudad($ciudad);
        responderJson($resultado, 200);
    } else {
        responderJson([
            'exito' => false,
            'mensaje' => 'Acción no reconocida en servicios externos.'
        ], 400);
    }
}
