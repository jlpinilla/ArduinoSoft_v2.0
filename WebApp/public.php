<?php
/**
 * =====================================================
 * MONITOR PÚBLICO - PANTALLA DE VISUALIZACIÓN AMBIENTAL
 * =====================================================
 * Sistema de visualización pública optimizado para pantallas grandes
 * Diseñado específicamente para:
 * - Pantallas 16:9 a 2 metros de distancia
 * - Auto-refresh cada 60 segundos
 * - Visualización en tiempo real sin autenticación
 * - Interfaz optimizada para lectura a distancia
 * - Indicadores visuales de estado ambiental
 */

// ===== CONFIGURACIÓN DE ZONA HORARIA =====
// Cargar gestor de timezone centralizado
require_once 'includes/timezone_manager.php';

// ===== CONFIGURACIÓN DEL SISTEMA =====
// Cargar configuración desde archivo INI
$config = parse_ini_file('config.ini', true);
if (!$config) {
    die("Error: No se pudo cargar la configuración del sistema.");
}

// ===== CONFIGURACIÓN ESPECÍFICA DEL MONITOR PÚBLICO =====
$publico_config = [
    'titulo' => $config['publico']['titulo'] ?? 'Monitor Ambiental',
    'subtitulo' => $config['publico']['subtitulo'] ?? 'Sistema de Sensores Arduino',
    'color_fondo' => $config['publico']['color_fondo'] ?? '#667eea',
    'color_secundario' => $config['publico']['color_secundario'] ?? '#764ba2',
    'color_texto' => $config['publico']['color_texto'] ?? '#ffffff',
    'refresh_interval' => intval($config['publico']['refresh_interval'] ?? 60)
];

// Validar que el tiempo de refresco esté en rango válido
if ($publico_config['refresh_interval'] < 60 || $publico_config['refresh_interval'] > 3600) {
    $publico_config['refresh_interval'] = 60;
}

// ===== CONEXIÓN A BASE DE DATOS =====
// Establecer conexión PDO para consultas de datos públicos
try {
    $dbHost = $config['database']['host'] ?? 'localhost';
    $dbName = $config['database']['database'] ?? '';
    $dbUser = $config['database']['user'] ?? '';
    $dbPass = $config['database']['password'] ?? '';
    $dbPort = $config['database']['port'] ?? '3306';

    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a base de datos: " . $e->getMessage());
}

// ===== OBTENER DATOS ACTUALES DE SENSORES =====
// Consultar últimos datos de todos los dispositivos activos
$stmt = $pdo->prepare("
    SELECT 
        d.nombre as dispositivo,
        d.ubicacion,
        r.temperatura,
        r.humedad,
        r.ruido,
        r.co2,
        r.lux,
        r.fecha_hora,
        CASE 
            WHEN r.fecha_hora > DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 'activo'
            ELSE 'inactivo'
        END as estado
    FROM dispositivos d
    INNER JOIN (
        SELECT sensor_id, 
               temperatura, humedad, ruido, co2, lux, fecha_hora,
               ROW_NUMBER() OVER (PARTITION BY sensor_id ORDER BY fecha_hora DESC) as rn
        FROM registros 
        WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 12 HOUR)
    ) r ON d.nombre = r.sensor_id AND r.rn = 1
    ORDER BY r.fecha_hora DESC
");
$stmt->execute();
$dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== CALCULAR ESTADÍSTICAS GLOBALES AMBIENTALES =====
// Obtener estadísticas de todos los sensores en las últimas 2 horas
$stmt = $pdo->prepare("
    SELECT 
        ROUND(AVG(temperatura), 1) as temp_promedio,
        ROUND(MAX(temperatura), 1) as temp_max,
        ROUND(MIN(temperatura), 1) as temp_min,
        ROUND(AVG(humedad), 1) as hum_promedio,
        ROUND(MAX(humedad), 1) as hum_max,
        ROUND(MIN(humedad), 1) as hum_min,
        ROUND(AVG(ruido), 1) as ruido_promedio,
        ROUND(MAX(ruido), 1) as ruido_max,
        ROUND(MIN(ruido), 1) as ruido_min,
        ROUND(AVG(co2), 0) as co2_promedio,
        MAX(co2) as co2_max,
        MIN(co2) as co2_min,
        ROUND(AVG(lux), 0) as lux_promedio,
        MAX(lux) as lux_max,
        MIN(lux) as lux_min,
        COUNT(*) as total_lecturas
    FROM registros 
    WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 2 HOUR)
");
$stmt->execute();
$estadisticas_globales = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no hay datos en las últimas 2 horas, buscar datos más antiguos para mostrar algo
if (!$estadisticas_globales || $estadisticas_globales['total_lecturas'] == 0) {
    // Intentar obtener estadísticas de las últimas 24 horas
    $stmt = $pdo->prepare("
        SELECT 
            ROUND(AVG(temperatura), 1) as temp_promedio,
            ROUND(MAX(temperatura), 1) as temp_max,
            ROUND(MIN(temperatura), 1) as temp_min,
            ROUND(AVG(humedad), 1) as hum_promedio,
            ROUND(MAX(humedad), 1) as hum_max,
            ROUND(MIN(humedad), 1) as hum_min,
            ROUND(AVG(ruido), 1) as ruido_promedio,
            ROUND(MAX(ruido), 1) as ruido_max,
            ROUND(MIN(ruido), 1) as ruido_min,
            ROUND(AVG(co2), 0) as co2_promedio,
            MAX(co2) as co2_max,
            MIN(co2) as co2_min,
            ROUND(AVG(lux), 0) as lux_promedio,
            MAX(lux) as lux_max,
            MIN(lux) as lux_min,
            COUNT(*) as total_lecturas
        FROM registros 
        WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $estadisticas_globales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si aún no hay datos, usar valores por defecto
    if (!$estadisticas_globales || $estadisticas_globales['total_lecturas'] == 0) {
        $estadisticas_globales = [
            'temp_promedio' => 0, 'temp_max' => 0, 'temp_min' => 0,
            'hum_promedio' => 0, 'hum_max' => 0, 'hum_min' => 0,
            'ruido_promedio' => 0, 'ruido_max' => 0, 'ruido_min' => 0,
            'co2_promedio' => 0, 'co2_max' => 0, 'co2_min' => 0,
            'lux_promedio' => 0, 'lux_max' => 0, 'lux_min' => 0,
            'total_lecturas' => 0
        ];
    }
}

// ===== DETERMINAR PERÍODO DE DATOS MOSTRADO =====
// Calcular qué período de tiempo se está mostrando en las estadísticas
$periodo_mostrado = "últimas 2 horas";
if ($estadisticas_globales['total_lecturas'] == 0) {
    $periodo_mostrado = "sin datos recientes";
} else {
    // Verificar si los datos son de las últimas 2 horas o de un período más amplio
    $stmt_periodo = $pdo->prepare("SELECT COUNT(*) as count_2h FROM registros WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $stmt_periodo->execute();
    $count_2h = $stmt_periodo->fetch()['count_2h'];
    
    if ($count_2h == 0) {
        $periodo_mostrado = "últimas 24 horas";
    }        }
        // ===== CALCULAR ESTADÍSTICAS GENERALES DEL SISTEMA =====
// Contar total de dispositivos registrados
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dispositivos");
$stmt->execute();
$total_dispositivos = $stmt->fetch()['total'];

// Contar dispositivos activos en las últimas 2 horas
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sensor_id) as activos 
    FROM registros 
    WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 2 HOUR)
");
$stmt->execute();
$dispositivos_activos = $stmt->fetch()['activos'];

// ===== OBTENER UMBRALES DE CONFIGURACIÓN =====
// Cargar umbrales ambientales desde la configuración
$umbrales = [
    'temperatura_max' => floatval($config['referencias']['temperatura_max'] ?? 25),
    'humedad_max' => floatval($config['referencias']['humedad_max'] ?? 48),
    'ruido_max' => floatval($config['referencias']['ruido_max'] ?? 35),
    'co2_max' => intval($config['referencias']['co2_max'] ?? 1000),
    'lux_min' => intval($config['referencias']['lux_min'] ?? 195)
];

// ===== FUNCIÓN DE ANÁLISIS DE ALERTAS AMBIENTALES =====
// Determinar nivel de alerta basado en umbrales ambientales configurados
function getAlertStatus($temp, $hum, $ruido, $co2, $lux, $umbrales) {
    $alerts = [];
    
    // Verificar cada parámetro contra sus umbrales configurados
    if ($temp > $umbrales['temperatura_max']) $alerts[] = 'temp';
    if ($hum > $umbrales['humedad_max']) $alerts[] = 'hum';
    if ($ruido > $umbrales['ruido_max']) $alerts[] = 'ruido';
    if ($co2 > $umbrales['co2_max']) $alerts[] = 'co2';
    if ($lux < $umbrales['lux_min']) $alerts[] = 'lux';
    
    // Determinar nivel de criticidad
    if (count($alerts) >= 3) return 'critico';  // 3+ alertas = crítico
    if (count($alerts) >= 1) return 'alerta';   // 1-2 alertas = alerta
    return 'normal';                             // Sin alertas = normal
}

// ===== FUNCIÓN DE EVALUACIÓN DE UMBRALES =====
// Función para evaluar si un valor cumple con los umbrales configurados
function evaluateThreshold($value, $parameter, $umbrales) {
    switch($parameter) {
        case 'temperatura':
            if ($value > $umbrales['temperatura_max']) return 'critical';
            if ($value > ($umbrales['temperatura_max'] - 2)) return 'warning';
            return 'normal';
        
        case 'humedad':
            if ($value > $umbrales['humedad_max']) return 'critical';
            if ($value > ($umbrales['humedad_max'] - 3)) return 'warning';
            return 'normal';
        
        case 'ruido':
            if ($value > $umbrales['ruido_max']) return 'critical';
            if ($value > ($umbrales['ruido_max'] - 5)) return 'warning';
            return 'normal';
        
        case 'co2':
            if ($value > $umbrales['co2_max']) return 'critical';
            if ($value > ($umbrales['co2_max'] - 200)) return 'warning';
            return 'normal';
        
        case 'lux':
            if ($value < $umbrales['lux_min']) return 'critical';
            if ($value < ($umbrales['lux_min'] + 50)) return 'warning';
            return 'normal';
        
        default:
            return 'normal';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Público - Sensores Ambientales</title>
    
    <!-- ===== FAVICON ===== -->
    <link rel="icon" type="image/png" sizes="32x32" href="media/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="media/logo.png">
    <link rel="apple-touch-icon" href="media/logo.png">
    <link rel="shortcut icon" href="media/logo.png">
      <link rel="stylesheet" href="styles.css">
    <meta http-equiv="refresh" content="<?php echo $publico_config['refresh_interval']; ?>">
    <meta name="description" content="Monitor público de sensores ambientales Arduino en tiempo real">    <meta name="theme-color" content="<?php echo $publico_config['color_fondo']; ?>">
    <style>        /* Estilos específicos para monitor público */
        .public-monitor {
            min-height: 100vh;
            background: linear-gradient(135deg, <?php echo $publico_config['color_fondo']; ?> 0%, <?php echo $publico_config['color_secundario']; ?> 100%);
            padding: clamp(10px, 2vw, 20px);
            font-size: clamp(1rem, 1.5vw, 1.2rem);
        }
          .monitor-header {
            text-align: center;
            color: <?php echo $publico_config['color_texto']; ?>;
            margin-bottom: clamp(20px, 4vw, 30px);
        }
        
        .monitor-title {
            font-size: clamp(2rem, 6vw, 3rem);
            font-weight: 300;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            line-height: 1.2;
        }
        
        .monitor-subtitle {
            font-size: clamp(1rem, 3vw, 1.5rem);
            opacity: 0.9;
            margin: clamp(5px, 1vw, 10px) 0;
        }
        
        .monitor-time {
            font-size: clamp(1.2rem, 3.5vw, 1.8rem);
            font-weight: 600;
            margin: clamp(10px, 2vw, 20px) 0;
        }
        
        .status-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
            gap: clamp(15px, 3vw, 20px);
            margin-bottom: clamp(30px, 5vw, 40px);
        }        .status-card {
            background: rgba(255,255,255,0.95);
            border-radius: clamp(10px, 2vw, 15px);
            padding: clamp(20px, 4vw, 30px);
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-height: clamp(120px, 15vw, 150px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .status-card:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        .status-number {
            font-size: clamp(2rem, 6vw, 3rem);
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
            line-height: 1;
        }
        
        .status-label {
            font-size: clamp(0.9rem, 2.5vw, 1.2rem);
            color: var(--text-light);
            margin: clamp(3px, 1vw, 5px) 0;
            line-height: 1.3;
        }
        
        /* ===== TARJETAS DE ESTADÍSTICAS AMBIENTALES ===== */
        .environmental-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr));
            gap: clamp(15px, 3vw, 20px);
            margin: clamp(30px, 5vw, 40px) 0;
        }
        
        .env-stat-card {
            background: rgba(255,255,255,0.95);
            border-radius: clamp(10px, 2vw, 15px);
            padding: clamp(20px, 4vw, 25px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .env-stat-card:hover,
        .env-stat-card:focus-within {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .env-stat-card:focus-within {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        .env-stat-header {
            display: flex;
            align-items: center;
            margin-bottom: clamp(15px, 3vw, 20px);
            gap: clamp(8px, 2vw, 12px);
            flex-wrap: wrap;
        }
        
        .env-stat-icon {
            font-size: clamp(2rem, 4vw, 2.5rem);
            width: clamp(50px, 8vw, 60px);
            height: clamp(50px, 8vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: clamp(8px, 2vw, 12px);
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }
        
        .env-stat-title {
            font-size: clamp(1.1rem, 3vw, 1.4rem);
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            line-height: 1.2;
        }
        
        .env-stat-unit {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: var(--text-light);
            margin: 0;
            line-height: 1.3;
        }
        
        .env-stat-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: clamp(10px, 2vw, 15px);
            margin-top: clamp(15px, 3vw, 20px);
        }
        
        .env-stat-item {
            text-align: center;
            padding: clamp(10px, 2vw, 15px) clamp(5px, 1vw, 10px);
            border-radius: clamp(6px, 1.5vw, 10px);
            background: rgba(0,0,0,0.02);
            transition: background-color 0.3s ease;
        }
        
        .env-stat-item:hover {
            background: rgba(0,0,0,0.05);
        }
        
        .env-stat-value {
            font-size: clamp(1.2rem, 3.5vw, 1.8rem);
            font-weight: 700;
            margin: 0 0 clamp(3px, 1vw, 5px) 0;
            color: var(--primary-color);
            line-height: 1;
        }
        
        .env-stat-label {
            font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.2;
        }
        
        /* Estados de valores críticos */
        .value-critical { color: var(--error-color) !important; }
        .value-warning { color: var(--warning-color) !important; }
        .value-normal { color: var(--success-color) !important; }
        
        /* Indicadores de estado para tarjetas ambientales */
        .env-stat-card.temp-critical { border-left: 5px solid var(--error-color); }
        .env-stat-card.temp-warning { border-left: 5px solid var(--warning-color); }
        .env-stat-card.temp-normal { border-left: 5px solid var(--success-color); }
        
        .env-stat-card.hum-critical { border-left: 5px solid var(--error-color); }
        .env-stat-card.hum-warning { border-left: 5px solid var(--warning-color); }
        .env-stat-card.hum-normal { border-left: 5px solid var(--success-color); }
        
        .env-stat-card.noise-critical { border-left: 5px solid var(--error-color); }
        .env-stat-card.noise-warning { border-left: 5px solid var(--warning-color); }
        .env-stat-card.noise-normal { border-left: 5px solid var(--success-color); }
        
        .env-stat-card.co2-critical { border-left: 5px solid var(--error-color); }
        .env-stat-card.co2-warning { border-left: 5px solid var(--warning-color); }
        .env-stat-card.co2-normal { border-left: 5px solid var(--success-color); }
        
        .env-stat-card.lux-critical { border-left: 5px solid var(--error-color); }
        .env-stat-card.lux-warning { border-left: 5px solid var(--warning-color); }
        .env-stat-card.lux-normal { border-left: 5px solid var(--success-color); }
          .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(350px, 100%), 1fr));
            gap: clamp(20px, 4vw, 25px);
        }
        
        .device-card {
            background: rgba(255,255,255,0.95);
            border-radius: clamp(10px, 2vw, 15px);
            padding: clamp(20px, 4vw, 25px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-5px);
        }
        
        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: clamp(15px, 3vw, 20px);
            gap: clamp(10px, 2vw, 15px);
            flex-wrap: wrap;
        }
        
        .device-name {
            font-size: clamp(1.1rem, 3vw, 1.4rem);
            font-weight: 600;
            color: var(--text-color);
            line-height: 1.2;
            word-break: break-word;
        }
        
        .device-location {
            color: var(--text-light);
            font-size: clamp(0.9rem, 2.2vw, 1rem);
            line-height: 1.3;
            word-break: break-word;
        }
        
        .device-status {
            padding: clamp(6px, 1.5vw, 8px) clamp(12px, 3vw, 16px);
            border-radius: clamp(15px, 3vw, 20px);
            font-weight: 600;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .status-activo {
            background: var(--success-color);
            color: white;
        }
        
        .status-inactivo {
            background: var(--error-color);
            color: white;
        }
        
        .sensor-readings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: clamp(10px, 2vw, 15px);
        }
        
        .sensor-item {
            text-align: center;
            padding: clamp(12px, 3vw, 15px);
            border-radius: clamp(6px, 1.5vw, 10px);
            background: rgba(0,0,0,0.02);
        }
        
        .sensor-value {
            font-size: clamp(1.3rem, 3.5vw, 1.8rem);
            font-weight: 700;
            margin: clamp(3px, 1vw, 5px) 0;
            line-height: 1;
        }
        
        .sensor-label {
            font-size: clamp(0.75rem, 2vw, 0.9rem);
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.2;
        }
        
        .sensor-unit {
            font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            color: var(--text-light);
            line-height: 1.2;
        }
        
        /* Estados de alerta */
        .alert-normal { border-left: 5px solid var(--success-color); }
        .alert-alerta { border-left: 5px solid var(--warning-color); }
        .alert-critico { border-left: 5px solid var(--error-color); }
        
        .value-normal { color: var(--success-color); }
        .value-warning { color: var(--warning-color); }
        .value-critical { color: var(--error-color); }        .last-update {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-top: clamp(20px, 4vw, 30px);
            font-size: clamp(0.9rem, 2.2vw, 1.1rem);
            line-height: 1.3;
        }
          .data-period-info {
            text-align: center;
            color: rgba(255,255,255,0.7);
            margin-top: clamp(8px, 1.5vw, 10px);
            font-size: clamp(0.75rem, 1.8vw, 0.9rem);
            line-height: 1.4;
            padding: clamp(10px, 2vw, 12px);
            background: rgba(255,255,255,0.08);
            border-radius: clamp(8px, 2vw, 12px);
            margin-left: auto;
            margin-right: auto;
            max-width: 600px;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
        }
        
        .data-period-info strong {
            color: rgba(255,255,255,0.9);
        }
        
        .data-period-info small {
            opacity: 0.8;
            font-size: 0.85em;
        }
        
        .refresh-indicator {
            position: fixed;
            top: clamp(10px, 2vw, 20px);
            right: clamp(10px, 2vw, 20px);
            background: rgba(255,255,255,0.9);
            padding: clamp(8px, 2vw, 10px) clamp(12px, 3vw, 15px);
            border-radius: clamp(15px, 3vw, 20px);
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: var(--text-color);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        /* ===== MEDIA QUERIES ESPECÍFICAS ===== */
          /* Pantallas muy pequeñas (móviles en vertical) */
        @media (max-width: 480px) {
            .public-monitor { 
                padding: 10px; 
                font-size: 0.9rem; 
            }
            .status-overview { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 10px; 
            }
            .status-card { 
                padding: 15px; 
                min-height: 100px;
            }
            .status-number { 
                font-size: 2rem; 
            }
            .status-label { 
                font-size: 0.8rem; 
            }
            .env-stat-card { 
                padding: 15px; 
            }
            .env-stat-header { 
                flex-direction: column; 
                gap: 8px; 
                text-align: center; 
            }
            .env-stat-values {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .device-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .sensor-readings {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .refresh-indicator {
                position: relative;
                top: auto;
                right: auto;
                margin: 10px auto;
                display: block;
                width: fit-content;
            }
            .data-period-info {
                font-size: 0.7rem;
                padding: 8px;
                margin-top: 8px;
            }
        }
        
        /* Pantallas pequeñas (móviles en horizontal / tablets en vertical) */
        @media (min-width: 481px) and (max-width: 768px) {
            .monitor-title { 
                font-size: 2.5rem; 
            }
            .monitor-subtitle { 
                font-size: 1.2rem; 
            }
            .status-overview {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .environmental-stats { 
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            }
            .env-stat-values { 
                grid-template-columns: repeat(3, 1fr); 
                gap: 8px; 
            }
            .devices-grid { 
                grid-template-columns: 1fr; 
            }
            .sensor-readings { 
                grid-template-columns: repeat(3, 1fr); 
            }
        }
        
        /* Tablets en horizontal */
        @media (min-width: 769px) and (max-width: 1024px) {
            .environmental-stats {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            .devices-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
            .env-stat-values {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Pantallas grandes (escritorio) */
        @media (min-width: 1025px) and (max-width: 1440px) {
            .environmental-stats {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                max-width: 1200px;
                margin-left: auto;
                margin-right: auto;
            }
            .devices-grid {
                max-width: 1200px;
                margin-left: auto;
                margin-right: auto;
            }
            .status-overview {
                max-width: 1000px;
                margin-left: auto;
                margin-right: auto;
            }
        }
        
        /* Pantallas extra grandes (monitores grandes) */
        @media (min-width: 1441px) {
            .public-monitor {
                padding: 40px;
                max-width: 1600px;
                margin: 0 auto;
            }
            .environmental-stats {
                grid-template-columns: repeat(5, 1fr);
                gap: 30px;
            }
            .devices-grid {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            }
        }
        
        /* Orientación landscape en móviles */
        @media (max-height: 500px) and (orientation: landscape) {
            .monitor-header {
                margin-bottom: 15px;
            }
            .monitor-title {
                font-size: 2rem;
                margin-bottom: 5px;
            }
            .monitor-subtitle {
                font-size: 1rem;
            }
            .monitor-time {
                font-size: 1.2rem;
                margin: 10px 0;
            }
            .environmental-stats {
                margin: 20px 0;
            }
            .env-stat-card {
                padding: 15px;
            }
        }          /* ===== ACCESIBILIDAD WCAG 2.1 ===== */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Reducir movimiento para usuarios que lo prefieren */
        @media (prefers-reduced-motion: reduce) {
            .status-card, .env-stat-card, .device-card {
                transition: none;
            }
            .refresh-indicator {
                animation: none;
            }
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Alto contraste para mejor legibilidad */
        @media (prefers-contrast: high) {
            .status-card, .env-stat-card, .device-card {
                border: 2px solid #000;
                background: #fff;
            }
            .env-stat-icon {
                background: #000;
                color: #fff;
            }
            .refresh-indicator {
                background: #fff;
                border: 2px solid #000;
            }
        }
          /* Modo oscuro automático */
        @media (prefers-color-scheme: dark) {
            .public-monitor {
                background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            }
            .status-card, .env-stat-card, .device-card {
                background: rgba(45, 55, 72, 0.95);
                color: #e2e8f0;
            }
            .refresh-indicator {
                background: rgba(45, 55, 72, 0.95);
                color: #e2e8f0;
            }
            .data-period-info {
                background: rgba(45, 55, 72, 0.2);
                border-color: rgba(226, 232, 240, 0.1);
                color: rgba(226, 232, 240, 0.8);
            }
        }
        
        /* Focus visible mejorado y responsivo */
        .status-card:focus-visible,
        .env-stat-card:focus-visible,
        .device-card:focus-visible {
            outline: clamp(2px, 0.5vw, 3px) solid #667eea;
            outline-offset: clamp(1px, 0.3vw, 2px);
        }
        
        /* Tamaños de texto grandes para accesibilidad */
        @media (min-resolution: 192dpi) {
            .public-monitor {
                font-size: clamp(1.1rem, 2vw, 1.4rem);
            }
        }
        
        /* Mejoras para pantallas táctiles */
        @media (pointer: coarse) {
            .status-card, .env-stat-card, .device-card {
                padding: clamp(25px, 5vw, 35px);
                margin-bottom: 5px;
            }
            .refresh-indicator {
                padding: clamp(12px, 3vw, 15px) clamp(18px, 4vw, 20px);
            }
        }
        
        /* Optimización para impresión */
        @media print {
            .public-monitor {
                background: white !important;
                color: black !important;
                padding: 20px;
            }
            .refresh-indicator {
                display: none;
            }
            .status-card, .env-stat-card, .device-card {
                background: white !important;
                border: 1px solid #ccc;
                break-inside: avoid;
                margin-bottom: 20px;
            }
            .env-stat-icon {
                background: #ccc !important;
                color: black !important;
            }
        }
    </style>
</head>
<body class="public-monitor">    <div class="refresh-indicator">
        🔄 Auto-actualización: <?php echo $publico_config['refresh_interval']; ?>s
    </div>
      <div class="monitor-header">
        <h1 class="monitor-title"><?php echo htmlspecialchars($publico_config['titulo']); ?></h1>
        <p class="monitor-subtitle"><?php echo htmlspecialchars($publico_config['subtitulo']); ?></p>
        <div class="monitor-time" id="currentTime"></div>
    </div>
      <div class="status-overview">
        <div class="status-card" role="region" aria-labelledby="total-devices">
            <div class="status-number" id="total-devices"><?php echo $total_dispositivos; ?></div>
            <div class="status-label">Dispositivos Total</div>
        </div>
        <div class="status-card" role="region" aria-labelledby="active-devices">
            <div class="status-number" id="active-devices"><?php echo $dispositivos_activos; ?></div>
            <div class="status-label">Activos Ahora</div>
        </div>
        <div class="status-card" role="region" aria-labelledby="inactive-devices">
            <div class="status-number" id="inactive-devices"><?php echo $total_dispositivos - $dispositivos_activos; ?></div>
            <div class="status-label">Inactivos</div>
        </div>
        <div class="status-card" role="region" aria-labelledby="recent-data">
            <div class="status-number" id="recent-data"><?php echo count($dispositivos); ?></div>
            <div class="status-label">Con Datos Recientes</div>
        </div>
    </div>
    
    <!-- ===== ESTADÍSTICAS AMBIENTALES GLOBALES ===== -->
    <section class="environmental-stats" aria-labelledby="env-stats-title">
        <h2 id="env-stats-title" class="sr-only">Estadísticas Ambientales Globales</h2>
        
        <!-- Tarjeta de Temperatura -->
        <div class="env-stat-card temp-<?php 
            echo $estadisticas_globales['temp_max'] > $umbrales['temperatura_max'] ? 'critical' : 
                ($estadisticas_globales['temp_promedio'] > ($umbrales['temperatura_max'] - 2) ? 'warning' : 'normal'); 
        ?>" role="region" aria-labelledby="temp-title" tabindex="0">
            <div class="env-stat-header">
                <div class="env-stat-icon" aria-hidden="true">🌡️</div>
                <div>
                    <h3 class="env-stat-title" id="temp-title">Temperatura</h3>
                    <p class="env-stat-unit">Grados Celsius (°C)</p>
                </div>
            </div>
            <div class="env-stat-values">
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['temp_promedio'], 'temperatura', $umbrales); ?>" 
                         aria-label="Temperatura promedio: <?php echo $estadisticas_globales['temp_promedio']; ?> grados celsius">
                        <?php echo $estadisticas_globales['temp_promedio']; ?>°
                    </div>
                    <div class="env-stat-label">Promedio</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['temp_max'], 'temperatura', $umbrales); ?>"
                         aria-label="Temperatura máxima: <?php echo $estadisticas_globales['temp_max']; ?> grados celsius">
                        <?php echo $estadisticas_globales['temp_max']; ?>°
                    </div>
                    <div class="env-stat-label">Máximo</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['temp_min'], 'temperatura', $umbrales); ?>"
                         aria-label="Temperatura mínima: <?php echo $estadisticas_globales['temp_min']; ?> grados celsius">
                        <?php echo $estadisticas_globales['temp_min']; ?>°
                    </div>
                    <div class="env-stat-label">Mínimo</div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de Humedad -->
        <div class="env-stat-card hum-<?php 
            echo $estadisticas_globales['hum_max'] > $umbrales['humedad_max'] ? 'critical' : 
                ($estadisticas_globales['hum_promedio'] > ($umbrales['humedad_max'] - 3) ? 'warning' : 'normal'); 
        ?>" role="region" aria-labelledby="hum-title" tabindex="0">
            <div class="env-stat-header">
                <div class="env-stat-icon" aria-hidden="true">💧</div>
                <div>
                    <h3 class="env-stat-title" id="hum-title">Humedad</h3>
                    <p class="env-stat-unit">Porcentaje (%)</p>
                </div>
            </div>
            <div class="env-stat-values">
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['hum_promedio'], 'humedad', $umbrales); ?>"
                         aria-label="Humedad promedio: <?php echo $estadisticas_globales['hum_promedio']; ?> por ciento">
                        <?php echo $estadisticas_globales['hum_promedio']; ?>%
                    </div>
                    <div class="env-stat-label">Promedio</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['hum_max'], 'humedad', $umbrales); ?>"
                         aria-label="Humedad máxima: <?php echo $estadisticas_globales['hum_max']; ?> por ciento">
                        <?php echo $estadisticas_globales['hum_max']; ?>%
                    </div>
                    <div class="env-stat-label">Máximo</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['hum_min'], 'humedad', $umbrales); ?>"
                         aria-label="Humedad mínima: <?php echo $estadisticas_globales['hum_min']; ?> por ciento">
                        <?php echo $estadisticas_globales['hum_min']; ?>%
                    </div>
                    <div class="env-stat-label">Mínimo</div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de Ruido -->
        <div class="env-stat-card noise-<?php 
            echo $estadisticas_globales['ruido_max'] > $umbrales['ruido_max'] ? 'critical' : 
                ($estadisticas_globales['ruido_promedio'] > ($umbrales['ruido_max'] - 5) ? 'warning' : 'normal'); 
        ?>" role="region" aria-labelledby="noise-title" tabindex="0">
            <div class="env-stat-header">
                <div class="env-stat-icon" aria-hidden="true">🔊</div>
                <div>
                    <h3 class="env-stat-title" id="noise-title">Nivel de Ruido</h3>
                    <p class="env-stat-unit">Decibelios (dB)</p>
                </div>
            </div>
            <div class="env-stat-values">
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['ruido_promedio'], 'ruido', $umbrales); ?>"
                         aria-label="Ruido promedio: <?php echo $estadisticas_globales['ruido_promedio']; ?> decibelios">
                        <?php echo $estadisticas_globales['ruido_promedio']; ?> dB
                    </div>
                    <div class="env-stat-label">Promedio</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['ruido_max'], 'ruido', $umbrales); ?>"
                         aria-label="Ruido máximo: <?php echo $estadisticas_globales['ruido_max']; ?> decibelios">
                        <?php echo $estadisticas_globales['ruido_max']; ?> dB
                    </div>
                    <div class="env-stat-label">Máximo</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['ruido_min'], 'ruido', $umbrales); ?>"
                         aria-label="Ruido mínimo: <?php echo $estadisticas_globales['ruido_min']; ?> decibelios">
                        <?php echo $estadisticas_globales['ruido_min']; ?> dB
                    </div>
                    <div class="env-stat-label">Mínimo</div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de CO2 -->
        <div class="env-stat-card co2-<?php 
            echo $estadisticas_globales['co2_max'] > $umbrales['co2_max'] ? 'critical' : 
                ($estadisticas_globales['co2_promedio'] > ($umbrales['co2_max'] - 200) ? 'warning' : 'normal'); 
        ?>" role="region" aria-labelledby="co2-title" tabindex="0">
            <div class="env-stat-header">
                <div class="env-stat-icon" aria-hidden="true">🌫️</div>
                <div>
                    <h3 class="env-stat-title" id="co2-title">Dióxido de Carbono</h3>
                    <p class="env-stat-unit">Partes por millón (ppm)</p>
                </div>
            </div>
            <div class="env-stat-values">
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['co2_promedio'], 'co2', $umbrales); ?>"
                         aria-label="CO2 promedio: <?php echo $estadisticas_globales['co2_promedio']; ?> partes por millón">
                        <?php echo $estadisticas_globales['co2_promedio']; ?>
                    </div>
                    <div class="env-stat-label">Promedio</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['co2_max'], 'co2', $umbrales); ?>"
                         aria-label="CO2 máximo: <?php echo $estadisticas_globales['co2_max']; ?> partes por millón">
                        <?php echo $estadisticas_globales['co2_max']; ?>
                    </div>
                    <div class="env-stat-label">Máximo</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['co2_min'], 'co2', $umbrales); ?>"
                         aria-label="CO2 mínimo: <?php echo $estadisticas_globales['co2_min']; ?> partes por millón">
                        <?php echo $estadisticas_globales['co2_min']; ?>
                    </div>
                    <div class="env-stat-label">Mínimo</div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de Iluminación -->
        <div class="env-stat-card lux-<?php 
            echo $estadisticas_globales['lux_min'] < $umbrales['lux_min'] ? 'critical' : 
                ($estadisticas_globales['lux_promedio'] < ($umbrales['lux_min'] + 50) ? 'warning' : 'normal'); 
        ?>" role="region" aria-labelledby="lux-title" tabindex="0">
            <div class="env-stat-header">
                <div class="env-stat-icon" aria-hidden="true">💡</div>
                <div>
                    <h3 class="env-stat-title" id="lux-title">Iluminación</h3>
                    <p class="env-stat-unit">Luxes (lux)</p>
                </div>
            </div>
            <div class="env-stat-values">
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['lux_promedio'], 'lux', $umbrales); ?>"
                         aria-label="Iluminación promedio: <?php echo $estadisticas_globales['lux_promedio']; ?> luxes">
                        <?php echo $estadisticas_globales['lux_promedio']; ?>
                    </div>
                    <div class="env-stat-label">Promedio</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['lux_max'], 'lux', $umbrales); ?>"
                         aria-label="Iluminación máxima: <?php echo $estadisticas_globales['lux_max']; ?> luxes">
                        <?php echo $estadisticas_globales['lux_max']; ?>
                    </div>
                    <div class="env-stat-label">Máximo</div>
                </div>
                <div class="env-stat-item">
                    <div class="env-stat-value value-<?php echo evaluateThreshold($estadisticas_globales['lux_min'], 'lux', $umbrales); ?>"
                         aria-label="Iluminación mínima: <?php echo $estadisticas_globales['lux_min']; ?> luxes">
                        <?php echo $estadisticas_globales['lux_min']; ?>
                    </div>
                    <div class="env-stat-label">Mínimo</div>
                </div>
            </div>
        </div>
    </section>
    
    <div class="last-update">
        Última actualización: <?php echo date('d/m/Y H:i:s'); ?>
    </div>
    
    <!-- Indicador del período de datos mostrado -->
    <div class="data-period-info">
        📊 Datos de las <strong><?php echo $periodo_mostrado; ?></strong><br>
        <small>(<?php echo $estadisticas_globales['total_lecturas']; ?> lecturas procesadas)</small>
    </div>

    <script>
        // ===== UTILIDADES DE ACCESIBILIDAD =====
        function announceToScreenReader(message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('aria-live', 'polite');
            announcement.setAttribute('aria-atomic', 'true');
            announcement.className = 'sr-only';
            announcement.textContent = message;
            document.body.appendChild(announcement);
            
            setTimeout(() => {
                if (document.body.contains(announcement)) {
                    document.body.removeChild(announcement);
                }
            }, 1000);
        }
        
        // ===== ACTUALIZACIÓN DE RELOJ EN TIEMPO REAL =====
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-CO', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            const dateString = now.toLocaleDateString('es-CO', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const fullDateTime = `${dateString} - ${timeString}`;
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = fullDateTime;
                timeElement.setAttribute('aria-label', `Fecha y hora actual: ${fullDateTime}`);
            }
        }
          // ===== INDICADOR DE PRÓXIMA ACTUALIZACIÓN =====
        let countdown = <?php echo $publico_config['refresh_interval']; ?>;
        const refreshIndicator = document.querySelector('.refresh-indicator');
        
        function updateCountdown() {
            countdown--;
            if (refreshIndicator) {
                refreshIndicator.innerHTML = `🔄 Auto-actualización: ${countdown}s`;
                refreshIndicator.setAttribute('aria-label', `La página se actualizará automáticamente en ${countdown} segundos`);
            }
            
            if (countdown <= 0) {
                if (refreshIndicator) {
                    refreshIndicator.innerHTML = '🔄 Actualizando...';
                    refreshIndicator.setAttribute('aria-label', 'Actualizando página');
                }
                announceToScreenReader('La página se está actualizando automáticamente');
                location.reload();
            }
            
            // Anunciar a lectores de pantalla cada 15 segundos
            if (countdown > 0 && countdown % 15 === 0) {
                announceToScreenReader(`Actualización automática en ${countdown} segundos`);
            }
        }
        
        // ===== NAVEGACIÓN POR TECLADO MEJORADA =====
        function setupKeyboardNavigation() {
            const cards = document.querySelectorAll('.status-card, .env-stat-card, .device-card');
            
            cards.forEach((card, index) => {
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', card.getAttribute('role') || 'region');
                
                card.addEventListener('keydown', function(e) {
                    let nextIndex = index;
                    
                    switch(e.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            e.preventDefault();
                            nextIndex = (index + 1) % cards.length;
                            break;
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            e.preventDefault();
                            nextIndex = (index - 1 + cards.length) % cards.length;
                            break;
                        case 'Home':
                            e.preventDefault();
                            nextIndex = 0;
                            break;
                        case 'End':
                            e.preventDefault();
                            nextIndex = cards.length - 1;
                            break;
                        case 'Enter':
                        case ' ':
                            e.preventDefault();
                            // Anunciar información de la tarjeta
                            const title = card.querySelector('h3, .status-label, .device-name');
                            if (title) {
                                announceToScreenReader(`Información de ${title.textContent}`);
                            }
                            break;
                    }
                    
                    if (nextIndex !== index) {
                        cards[nextIndex].focus();
                    }
                });
            });
        }
        
        // ===== INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', function() {
            // Actualizar reloj
            updateTime();
            setInterval(updateTime, 1000);
            
            // Iniciar countdown
            setInterval(updateCountdown, 1000);
            
            // Configurar navegación por teclado
            setupKeyboardNavigation();
            
            // Anuncio inicial para lectores de pantalla
            announceToScreenReader('Monitor público de sensores ambientales cargado correctamente');
            
            // ===== ANIMACIONES DE ENTRADA ACCESIBLES =====
            const userPrefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            
            if (!userPrefersReducedMotion) {
                // Animación de entrada solo si el usuario no prefiere movimiento reducido
                const cards = document.querySelectorAll('.device-card, .env-stat-card');
                cards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 50);
                });
            }
            
            // ===== MANEJO DE ERRORES DE ACCESIBILIDAD =====
            const checkAccessibility = () => {
                // Verificar que todas las imágenes tengan alt text
                const images = document.querySelectorAll('img:not([alt])');
                images.forEach(img => {
                    img.setAttribute('alt', 'Imagen decorativa');
                });
                
                // Verificar que todos los elementos interactivos tengan roles apropiados
                const interactiveElements = document.querySelectorAll('[tabindex]:not([role])');
                interactiveElements.forEach(el => {
                    if (!el.getAttribute('role')) {
                        el.setAttribute('role', 'button');
                    }
                });
            };
            
            checkAccessibility();
        });
        
        // ===== MANEJO DE CAMBIOS EN PREFERENCIAS DE MOVIMIENTO =====
        const motionMediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        motionMediaQuery.addEventListener('change', function(e) {
            if (e.matches) {
                // Deshabilitar animaciones
                const style = document.createElement('style');
                style.textContent = '* { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }';
                document.head.appendChild(style);
            }
        });
    </script>
</body>
</html>
