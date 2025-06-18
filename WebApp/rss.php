<?php
/**
 * =====================================================
 * FEED RSS - SISTEMA DE CONTROL AMBIENTAL
 * =====================================================
 * Genera un feed RSS con los datos actuales del monitor público
 */

// Configurar headers para RSS
header('Content-Type: application/rss+xml; charset=utf-8');

// Cargar configuración
$config = parse_ini_file('config.ini', true);
if (!$config) {
    http_response_code(500);
    exit('Error al cargar configuración');
}

// Conectar a base de datos
try {
    require_once 'includes/database_manager.php';
    $db_manager = getDBManager($config);
} catch (Exception $e) {
    http_response_code(500);
    exit('Error de conexión a base de datos');
}

// Obtener datos más recientes
try {
    $latest_data_query = "
        SELECT d.nombre as dispositivo, d.ubicacion,
               r.temperatura, r.humedad, r.ruido, r.co2, r.lux, r.fecha_hora
        FROM dispositivos d
        INNER JOIN registros r ON d.nombre = r.sensor_id
        WHERE r.fecha_hora >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY r.fecha_hora DESC
        LIMIT 10
    ";
    
    $latest_data = $db_manager->query($latest_data_query, []);
    
} catch (Exception $e) {
    $latest_data = [];
}

// Configuración del feed
$feed_title = $config['publico']['titulo'] . ' - Monitor Ambiental';
$feed_description = 'Datos en tiempo real de sensores ambientales - ' . $config['publico']['subtitulo'];
$feed_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/public.php";
$feed_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Generar XML del RSS
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title><?php echo htmlspecialchars($feed_title); ?></title>
    <description><?php echo htmlspecialchars($feed_description); ?></description>
    <link><?php echo htmlspecialchars($feed_link); ?></link>
    <language>es-ES</language>
    <pubDate><?php echo date('r'); ?></pubDate>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <generator><?php echo htmlspecialchars($config['sistema']['nombre'] . ' v' . $config['sistema']['version']); ?></generator>
    <webMaster>admin@<?php echo $_SERVER['HTTP_HOST']; ?></webMaster>
    <ttl><?php echo $config['publico']['refresh_interval']; ?></ttl>

    <?php if (!empty($latest_data)): ?>
        <?php foreach ($latest_data as $index => $reading): ?>
        <item>
            <title>📊 Lectura #<?php echo $index + 1; ?> - <?php echo htmlspecialchars($reading['dispositivo']); ?></title>
            <description><![CDATA[
                <h3>Sensor: <?php echo htmlspecialchars($reading['dispositivo']); ?></h3>
                <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($reading['ubicacion']); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($reading['fecha_hora'])); ?></p>
                
                <h4>Lecturas Ambientales:</h4>
                <ul>
                    <li>🌡️ <strong>Temperatura:</strong> <?php echo $reading['temperatura']; ?>°C
                        <?php if ($reading['temperatura'] > $config['referencias']['temperatura_max']): ?>
                            <span style="color: #ef4444; font-weight: bold;"> ⚠️ ALERTA</span>
                        <?php endif; ?>
                    </li>
                    
                    <li>💧 <strong>Humedad:</strong> <?php echo $reading['humedad']; ?>%
                        <?php if ($reading['humedad'] > $config['referencias']['humedad_max']): ?>
                            <span style="color: #ef4444; font-weight: bold;"> ⚠️ ALERTA</span>
                        <?php endif; ?>
                    </li>
                    
                    <li>🔊 <strong>Ruido:</strong> <?php echo $reading['ruido']; ?>dB
                        <?php if ($reading['ruido'] > $config['referencias']['ruido_max']): ?>
                            <span style="color: #ef4444; font-weight: bold;"> ⚠️ ALERTA</span>
                        <?php endif; ?>
                    </li>
                    
                    <li>☁️ <strong>CO2:</strong> <?php echo $reading['co2']; ?>ppm
                        <?php if ($reading['co2'] > $config['referencias']['co2_max']): ?>
                            <span style="color: #ef4444; font-weight: bold;"> ⚠️ ALERTA</span>
                        <?php endif; ?>
                    </li>
                    
                    <li>💡 <strong>Iluminación:</strong> <?php echo $reading['lux']; ?>lux
                        <?php if ($reading['lux'] < $config['referencias']['lux_min']): ?>
                            <span style="color: #ef4444; font-weight: bold;"> ⚠️ ALERTA</span>
                        <?php endif; ?>
                    </li>
                </ul>
                
                <p><em>Umbrales configurados: T&lt;<?php echo $config['referencias']['temperatura_max']; ?>°C, H&lt;<?php echo $config['referencias']['humedad_max']; ?>%, R&lt;<?php echo $config['referencias']['ruido_max']; ?>dB, CO2&lt;<?php echo $config['referencias']['co2_max']; ?>ppm, Luz&gt;<?php echo $config['referencias']['lux_min']; ?>lux</em></p>
            ]]></description>
            <link><?php echo htmlspecialchars($feed_link); ?></link>
            <guid><?php echo htmlspecialchars($feed_url . '?id=' . $reading['dispositivo'] . '&time=' . strtotime($reading['fecha_hora'])); ?></guid>
            <pubDate><?php echo date('r', strtotime($reading['fecha_hora'])); ?></pubDate>
            <dc:creator><?php echo htmlspecialchars($reading['dispositivo']); ?></dc:creator>
        </item>
        <?php endforeach; ?>
    <?php else: ?>
        <item>
            <title>ℹ️ Sistema de Monitoreo Ambiental</title>
            <description><![CDATA[
                <h3>No hay datos recientes disponibles</h3>
                <p>El sistema de monitoreo ambiental está operativo pero no se han registrado datos en la última hora.</p>
                <p><strong>Sistema:</strong> <?php echo htmlspecialchars($config['sistema']['nombre']); ?> v<?php echo htmlspecialchars($config['sistema']['version']); ?></p>
                <p><strong>Estado:</strong> Esperando datos de sensores</p>
            ]]></description>
            <link><?php echo htmlspecialchars($feed_link); ?></link>
            <guid><?php echo htmlspecialchars($feed_url . '?status=no-data&time=' . time()); ?></guid>
            <pubDate><?php echo date('r'); ?></pubDate>
        </item>
    <?php endif; ?>
    
</channel>
</rss>
