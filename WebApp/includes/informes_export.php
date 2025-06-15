<?php
/**
 * =====================================================
 * EXPORTACIÓN CSV - MÓDULO DE INFORMES
 * =====================================================
 * Maneja la exportación de datos a formato CSV con filtros
 * Incluye validación de parámetros y formato de datos
 */

// ===== CONTROL DE SEGURIDAD =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// ===== CONFIGURACIÓN DE EXPORTACIÓN =====
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="informe_ambiental_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// ===== OBTENER PARÁMETROS DE FILTRO =====
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$dispositivo_filtro = $_POST['dispositivo_filtro'] ?? '';

// ===== CONEXIÓN A BASE DE DATOS =====
try {
    require_once __DIR__ . '/database_manager.php';
    // Use absolute path resolution for config.ini
    $configPath = __DIR__ . '/../config.ini';
    if (!file_exists($configPath)) {
        throw new Exception("Archivo de configuración no encontrado: $configPath");
    }
    $config = parse_ini_file($configPath, true);
    if ($config === false) {
        throw new Exception("Error al leer el archivo de configuración");
    }
    $dbManager = getDBManager($config);
    $pdo = $dbManager->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    exit('Error de conexión: ' . $e->getMessage());
}

// ===== CONSULTA DE DATOS PARA EXPORTACIÓN =====
$sql = "SELECT 
    r.sensor_id as 'Nombre Sensor',
    COALESCE(d.ubicacion, 'Sin ubicación') as 'Ubicación',
    COALESCE(d.direccion_ip, 'N/A') as 'Dirección IP',
    COALESCE(d.direccion_mac, 'N/A') as 'Dirección MAC',
    r.temperatura as 'Temperatura (°C)',
    r.humedad as 'Humedad (%)',
    r.ruido as 'Ruido (dB)',
    r.co2 as 'CO₂ (ppm)',
    r.lux as 'Iluminación (lux)',
    DATE_FORMAT(r.fecha_hora, '%d/%m/%Y %H:%i:%s') as 'Fecha/Hora',
    CASE 
        WHEN (r.temperatura > 25) + (r.humedad > 48) + (r.ruido > 35) + (r.co2 > 1000) + (r.lux < 195) >= 3 THEN 'Crítico'
        WHEN (r.temperatura > 25) + (r.humedad > 48) + (r.ruido > 35) + (r.co2 > 1000) + (r.lux < 195) >= 1 THEN 'Alerta'
        ELSE 'Normal'
    END as 'Estado'
FROM registros r
LEFT JOIN dispositivos d ON r.sensor_id = d.nombre
WHERE DATE(r.fecha_hora) BETWEEN ? AND ?";

$params = [$fecha_inicio, $fecha_fin];

if (!empty($dispositivo_filtro)) {
    $sql .= " AND r.sensor_id = ?";
    $params[] = $dispositivo_filtro;
}

$sql .= " ORDER BY r.fecha_hora DESC";

// ===== EJECUCIÓN DE CONSULTA =====
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== ABRIR SALIDA CSV =====
    $output = fopen('php://output', 'w');
    
    // ===== CONFIGURAR UTF-8 BOM PARA EXCEL =====
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // ===== ESCRIBIR ENCABEZADO INFORMATIVO =====
    fputcsv($output, ['INFORME AMBIENTAL - ARDUINOSOFT MONITOR'], ';');
    fputcsv($output, ['Generado el: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin))], ';');
    fputcsv($output, ['Usuario: ' . $_SESSION['usuario']], ';');
    if (!empty($dispositivo_filtro)) {
        fputcsv($output, ['Dispositivo filtrado: ' . $dispositivo_filtro], ';');
    }
    fputcsv($output, ['Total de registros: ' . count($registros)], ';');
    fputcsv($output, [''], ';'); // Línea vacía
    
    // ===== ESCRIBIR ENCABEZADOS DE DATOS =====
    if (count($registros) > 0) {
        fputcsv($output, array_keys($registros[0]), ';');
        
        // ===== ESCRIBIR DATOS =====
        foreach ($registros as $registro) {
            fputcsv($output, array_values($registro), ';');
        }
    } else {
        fputcsv($output, ['No se encontraron datos para el período seleccionado'], ';');
    }
    
    // ===== ESCRIBIR ESTADÍSTICAS RESUMIDAS =====
    if (count($registros) > 0) {
        fputcsv($output, [''], ';'); // Línea vacía
        fputcsv($output, ['ESTADÍSTICAS RESUMIDAS'], ';');
        
        // Calcular estadísticas
        $temperaturas = array_column($registros, 'Temperatura (°C)');
        $humedades = array_column($registros, 'Humedad (%)');
        $ruidos = array_column($registros, 'Ruido (dB)');
        $co2s = array_column($registros, 'CO₂ (ppm)');
        $luxes = array_column($registros, 'Iluminación (lux)');
        
        fputcsv($output, ['Parámetro', 'Promedio', 'Mínimo', 'Máximo'], ';');
        fputcsv($output, ['Temperatura (°C)', number_format(array_sum($temperaturas)/count($temperaturas), 2), number_format(min($temperaturas), 2), number_format(max($temperaturas), 2)], ';');
        fputcsv($output, ['Humedad (%)', number_format(array_sum($humedades)/count($humedades), 2), number_format(min($humedades), 2), number_format(max($humedades), 2)], ';');
        fputcsv($output, ['Ruido (dB)', number_format(array_sum($ruidos)/count($ruidos), 2), number_format(min($ruidos), 2), number_format(max($ruidos), 2)], ';');
        fputcsv($output, ['CO₂ (ppm)', number_format(array_sum($co2s)/count($co2s), 2), number_format(min($co2s), 2), number_format(max($co2s), 2)], ';');
        fputcsv($output, ['Iluminación (lux)', number_format(array_sum($luxes)/count($luxes), 2), number_format(min($luxes), 2), number_format(max($luxes), 2)], ';');
        
        // Contar alertas
        $estados = array_count_values(array_column($registros, 'Estado'));
        fputcsv($output, [''], ';');
        fputcsv($output, ['DISTRIBUCIÓN DE ALERTAS'], ';');
        fputcsv($output, ['Estado', 'Cantidad', 'Porcentaje'], ';');
        foreach ($estados as $estado => $cantidad) {
            $porcentaje = number_format(($cantidad / count($registros)) * 100, 1);
            fputcsv($output, [$estado, $cantidad, $porcentaje . '%'], ';');
        }
    }
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Error al generar CSV: ' . $e->getMessage());
}
?>