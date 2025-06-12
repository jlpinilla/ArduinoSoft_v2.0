<?php
/**
 * =====================================================
 * API ENDPOINT PARA RECEPCIÓN DE DATOS ARDUINO
 * =====================================================
 * Sistema de recepción y procesamiento de datos ambientales
 * enviados por dispositivos Arduino ESP32/ESP8266
 * 
 * Funcionalidades principales:
 * - Recepción de datos JSON vía HTTP POST
 * - Validación y sanitización de datos
 * - Registro automático de nuevos dispositivos
 * - Almacenamiento de lecturas ambientales
 * - Control de errores y logging
 * - Respuestas JSON estructuradas
 */

// ===== CONFIGURACIÓN DE ZONA HORARIA =====
// Cargar gestor de timezone centralizado
require_once '../includes/timezone_manager.php';

// ===== CONFIGURACIÓN DE HEADERS HTTP =====
// Configurar headers para API REST y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ===== CONTROL DE MÉTODO HTTP =====
// Solo permitir método POST para recepción de datos
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Método no permitido. Use POST.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ===== CARGAR CONFIGURACIÓN DEL SISTEMA =====
// Cargar parámetros de configuración desde archivo INI
$config = parse_ini_file('../config.ini', true);
if (!$config) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de configuración del servidor',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ===== CONEXIÓN A BASE DE DATOS =====
// Establecer conexión PDO con manejo de errores
try {
    $pdo = new PDO(
        "mysql:host={$config['database']['host']};dbname={$config['database']['database']};charset=utf8mb4",
        $config['database']['username'],
        $config['database']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de conexión a base de datos',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ===== PROCESAMIENTO DE DATOS JSON =====
// Leer y decodificar datos JSON del cuerpo de la petición
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// === Validar formato JSON ===
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Formato JSON inválido',
        'json_error' => json_last_error_msg(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ===== VALIDACIÓN DE CAMPOS REQUERIDOS =====
// Verificar que todos los campos necesarios estén presentes
$required_fields = ['sensor_id', 'temperatura', 'humedad', 'ruido', 'co2', 'lux'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'error' => "Campo requerido faltante: $field",
            'campos_requeridos' => $required_fields,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// ===== VALIDACIÓN DE TIPOS DE DATOS =====
// Verificar que los valores numéricos sean válidos
$numeric_fields = ['temperatura', 'humedad', 'ruido', 'co2', 'lux'];
foreach ($numeric_fields as $field) {
    if (!is_numeric($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'error' => "El campo $field debe ser numérico",
            'valor_recibido' => $data[$field],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// ===== VALIDACIÓN DE RANGOS AMBIENTALES =====
// Validar que los valores estén dentro de rangos realistas
$validations = [
    'temperatura' => [-50, 100],  // Rango de temperatura en °C
    'humedad' => [0, 100],        // Porcentaje de humedad relativa
    'ruido' => [0, 150],          // Nivel de ruido en decibelios
    'co2' => [0, 5000],           // Concentración CO2 en ppm
    'lux' => [0, 100000]          // Iluminación en lux
];

foreach ($validations as $field => $range) {
    $value = floatval($data[$field]);
    if ($value < $range[0] || $value > $range[1]) {
        http_response_code(400);
        echo json_encode([
            'error' => "Valor de $field fuera de rango válido ({$range[0]} - {$range[1]})",
            'valor_recibido' => $value,
            'rango_valido' => $range,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// ===== VERIFICACIÓN Y REGISTRO AUTOMÁTICO DE DISPOSITIVOS =====
// Verificar si el dispositivo existe, si no, crearlo automáticamente
try {
    $stmt = $pdo->prepare("SELECT id FROM dispositivos WHERE nombre = ?");
    $stmt->execute([trim($data['sensor_id'])]);
    $dispositivo = $stmt->fetch();
    
    // === Registrar dispositivo nuevo automáticamente ===
    if (!$dispositivo) {
        $stmt = $pdo->prepare("INSERT INTO dispositivos (nombre, ubicacion, direccion_ip, direccion_mac) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($data['sensor_id']),
            'Ubicación no especificada - Auto-registrado',
            $_SERVER['REMOTE_ADDR'] ?? 'IP desconocida',
            'MAC no especificada'
        ]);
        
        // Log del nuevo dispositivo
        $log_entry = date('Y-m-d H:i:s') . " - NUEVO DISPOSITIVO REGISTRADO: {$data['sensor_id']} desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . "\n";
        file_put_contents('../logs/new_devices.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al verificar/registrar dispositivo',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ===== INSERCIÓN DE DATOS AMBIENTALES =====
// Almacenar las lecturas del sensor en la base de datos
try {
    $stmt = $pdo->prepare("
        INSERT INTO registros (sensor_id, temperatura, humedad, ruido, co2, lux, fecha_hora) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        trim($data['sensor_id']),
        floatval($data['temperatura']),
        floatval($data['humedad']),
        floatval($data['ruido']),
        floatval($data['co2']),
        floatval($data['lux'])
    ]);
    
    $registro_id = $pdo->lastInsertId();
      // ===== ANÁLISIS DE ALERTAS AMBIENTALES =====
    // Verificar si algún parámetro supera los umbrales críticos usando configuración
    $alertas = [];
    
    // Obtener umbrales desde configuración
    $temp_max = floatval($config['referencias']['temperatura_max'] ?? 25);
    $hum_max = floatval($config['referencias']['humedad_max'] ?? 48);
    $ruido_max = floatval($config['referencias']['ruido_max'] ?? 35);
    $co2_max = floatval($config['referencias']['co2_max'] ?? 1000);
    $lux_min = floatval($config['referencias']['lux_min'] ?? 195);
    
    // Verificar cada parámetro contra su umbral configurado
    if (floatval($data['temperatura']) > $temp_max) $alertas[] = 'temperatura';
    if (floatval($data['humedad']) > $hum_max) $alertas[] = 'humedad';
    if (floatval($data['ruido']) > $ruido_max) $alertas[] = 'ruido';
    if (floatval($data['co2']) > $co2_max) $alertas[] = 'co2';
    if (floatval($data['lux']) < $lux_min) $alertas[] = 'iluminacion';    // === Determinar estado general del ambiente ===
    $estado = count($alertas) >= 3 ? 'critico' : (count($alertas) >= 1 ? 'alerta' : 'normal');
    
    // ===== LOG DE ALERTAS AMBIENTALES =====
    // Registrar alertas detectadas para análisis posterior
    if (!empty($alertas)) {
        $sensor_name = trim($data['sensor_id']);
        
        // Log de alertas detectadas
        $alertas_str = implode(', ', $alertas);
        $log_entry = date('Y-m-d H:i:s') . " - Alertas detectadas - Sensor: {$sensor_name} - Parámetros: {$alertas_str} - Estado: {$estado}\n";
        file_put_contents('../logs/environmental_alerts.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    // ===== RESPUESTA EXITOSA CON DETALLES =====
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Datos ambientales recibidos y procesados correctamente',
        'registro_id' => $registro_id,
        'sensor_id' => trim($data['sensor_id']),
        'timestamp' => date('Y-m-d H:i:s'),
        'estado_ambiental' => $estado,
        'alertas_detectadas' => $alertas,
        'total_alertas' => count($alertas),
        'datos_procesados' => [
            'temperatura' => floatval($data['temperatura']) . '°C',
            'humedad' => floatval($data['humedad']) . '%',
            'ruido' => floatval($data['ruido']) . ' dB',
            'co2' => floatval($data['co2']) . ' ppm',
            'lux' => floatval($data['lux']) . ' lux'
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al insertar datos en base de datos',
        'detalles' => 'Contacte al administrador del sistema',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ===== LOGGING DE ACTIVIDAD =====
// Registrar actividad en archivo de log para auditoria
$log_entry = date('Y-m-d H:i:s') . " - Datos recibidos de {$data['sensor_id']} - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . " - Estado: $estado\n";
file_put_contents('../logs/api_activity.log', $log_entry, FILE_APPEND | LOCK_EX);
?>
