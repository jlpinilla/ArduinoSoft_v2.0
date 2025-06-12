<?php
/**
 * =====================================================
 * MÓDULO DE INFORMES Y REPORTES - SUITE AMBIENTAL
 * =====================================================
 * Sistema completo de generación de informes estadísticos
 * Incluye: Exportación CSV, análisis de dispositivos, 
 * reportes ambientales y funciones de impresión
 * 
 * Funcionalidades principales:
 * - Generación de reportes por rango de fechas
 * - Filtrado por dispositivos específicos
 * - Exportación de datos en formato CSV
 * - Estadísticas ambientales avanzadas
 * - Análisis comparativo de sensores
 */

// ===== CONTROL DE SEGURIDAD Y SESIÓN =====
// Verificar que el usuario esté autenticado
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// ===== CONFIGURACIÓN DEL SISTEMA =====
// Cargar configuración desde archivo INI
// Si ya está definida en panel.php, usarla; sino cargarla
if (!isset($config)) {
    $config = parse_ini_file('../config.ini', true);
    
    // Validar sección de base de datos en config.ini
    if (!isset($config['database'])) {
        // Configuración inicial no completada
        header('Location: ../configinicial.php');
        exit();
    }
}

// ===== CONEXIÓN A BASE DE DATOS =====
// Establecer conexión PDO con manejo de errores
try {
    // === Conexión optimizada usando DatabaseManager ===
    require_once __DIR__ . '/database_manager.php';
    $dbManager = getDBManager($config ?? null);
    $pdo = $dbManager->getConnection();
} catch (PDOException $e) {
    die("Error de conexión a base de datos: " . $e->getMessage());
}

// ===== PARÁMETROS DE FILTRADO =====
// Obtener parámetros de filtro desde URL o usar valores por defecto
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$dispositivo_filtro = $_GET['dispositivo'] ?? '';

// ===== PROCESAMIENTO DE FORMULARIOS =====
// Procesar exportación CSV y generación de datos de prueba
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_csv'])) {
        // Código de exportación CSV existente...
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $dispositivo_filtro = $_POST['dispositivo_filtro'];
        
        // Recargar datos para exportación
        $sql = "SELECT 
            r.sensor_id,
            d.ubicacion,
            d.direccion_ip,
            d.direccion_mac,
            r.temperatura,
            r.humedad,
            r.ruido,
            r.co2,
            r.lux,
            r.fecha_hora
        FROM registros r
        LEFT JOIN dispositivos d ON r.sensor_id = d.nombre
        WHERE DATE(r.fecha_hora) BETWEEN ? AND ?";

        $params = [$fecha_inicio, $fecha_fin];

        if (!empty($dispositivo_filtro)) {
            $sql .= " AND r.sensor_id = ?";
            $params[] = $dispositivo_filtro;
        }

        $sql .= " ORDER BY r.fecha_hora DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $registros_para_csv = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar CSV y salir
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="informe_sensores_' . date('Y-m-d') . '.csv"');
        
        echo "\xEF\xBB\xBF"; // BOM para UTF-8
        echo "Dispositivo,Ubicacion,IP,MAC,Temperatura,Humedad,Ruido,CO2,Iluminacion,Fecha_Hora\n";
        
        foreach ($registros_para_csv as $registro) {
            echo sprintf("%s,%s,%s,%s,%.1f,%.1f,%.1f,%d,%d,%s\n",
                $registro['sensor_id'],
                $registro['ubicacion'] ?? 'N/A',
                $registro['direccion_ip'] ?? 'N/A',
                $registro['direccion_mac'] ?? 'N/A',
                $registro['temperatura'],
                $registro['humedad'],
                $registro['ruido'],
                $registro['co2'],
                $registro['lux'],
                $registro['fecha_hora']
            );
        }
        exit();
    }
    
    if (isset($_POST['generar_datos_prueba'])) {
        try {
            // Obtener los últimos 5 registros de la base de datos
            $stmt = $pdo->prepare("
                SELECT id, sensor_id, temperatura, humedad, ruido, co2, lux 
                FROM registros 
                ORDER BY fecha_hora DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $registros_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($registros_existentes)) {
                $mensaje_prueba = "❌ No hay registros existentes en la base de datos para actualizar. Debe tener al menos 5 registros previos.";
            } else {
                $registros_actualizados = 0;
                $fecha_base = new DateTime();
                
                // Actualizar cada uno de los 5 registros con nuevas fechas aleatorias
                foreach ($registros_existentes as $registro) {
                    // Generar timestamp aleatorio en las últimas 2 horas
                    $minutos_atras = rand(0, 120); // 0 a 120 minutos (2 horas)
                    $fecha_registro = clone $fecha_base;
                    $fecha_registro->sub(new DateInterval("PT{$minutos_atras}M"));
                    
                    // Actualizar solo la fecha_hora del registro existente
                    $stmt = $pdo->prepare("
                        UPDATE registros 
                        SET fecha_hora = ? 
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([
                        $fecha_registro->format('Y-m-d H:i:s'),
                        $registro['id']
                    ])) {
                        $registros_actualizados++;
                    }
                }
                
                if ($registros_actualizados > 0) {
                    $mensaje_prueba = "✅ Se actualizaron {$registros_actualizados} registros existentes con fechas aleatorias de las últimas 2 horas. Los datos de sensores se mantuvieron intactos.";
                } else {
                    $mensaje_prueba = "❌ Error al actualizar los registros. Verifique la conexión a la base de datos.";
                }
            }
            
            // NO usar header redirect - usar JavaScript redirect después de mostrar el mensaje
            echo '<script>
                setTimeout(function() {
                    window.location.href = "?seccion=informes&fecha_inicio=' . urlencode($fecha_inicio) . '&fecha_fin=' . urlencode($fecha_fin) . '&dispositivo=' . urlencode($dispositivo_filtro) . '&mensaje=' . urlencode($mensaje_prueba) . '";
                }, 2000);
            </script>';
            
        } catch (Exception $e) {
            $mensaje_prueba = "❌ Error al actualizar datos de prueba: " . $e->getMessage();
        }
    }
}

// Mostrar mensaje si viene de redirección
$mensaje_prueba = $_GET['mensaje'] ?? '';

// ===== ESTADÍSTICAS GENERALES DEL SISTEMA =====
// Contar total de dispositivos registrados
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dispositivos");
$stmt->execute();
$total_dispositivos = $stmt->fetch()['total'];

// Contar registros en el período seleccionado
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM registros WHERE DATE(fecha_hora) BETWEEN ? AND ?");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$total_registros = $stmt->fetch()['total'];

// ===== ANÁLISIS DE DISPOSITIVOS ACTIVOS =====
// Contar dispositivos que han enviado datos en la última hora
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sensor_id) as activos 
    FROM registros 
    WHERE fecha_hora > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute();
$dispositivos_activos = $stmt->fetch()['activos'];

// ===== CÁLCULO DE PROMEDIO DE ACTIVIDAD =====
// Calcular promedio de lecturas por día en el período
$stmt = $pdo->prepare("
    SELECT AVG(lecturas_dia) as promedio
    FROM (
        SELECT DATE(fecha_hora) as dia, COUNT(*) as lecturas_dia
        FROM registros 
        WHERE DATE(fecha_hora) BETWEEN ? AND ?
        GROUP BY DATE(fecha_hora)
    ) t
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$promedio_lecturas = $stmt->fetch()['promedio'] ?? 0;

// ===== RANKING DE DISPOSITIVOS MÁS ACTIVOS =====
// Obtener top 10 dispositivos con más registros y sus estadísticas
$stmt = $pdo->prepare("
    SELECT 
        r.sensor_id,
        d.ubicacion,
        COUNT(*) as total_registros,
        AVG(r.temperatura) as temp_promedio,
        AVG(r.humedad) as hum_promedio,
        MAX(r.fecha_hora) as ultima_lectura
    FROM registros r
    LEFT JOIN dispositivos d ON r.sensor_id = d.nombre
    WHERE DATE(r.fecha_hora) BETWEEN ? AND ?
    GROUP BY r.sensor_id
    ORDER BY total_registros DESC
    LIMIT 10
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$dispositivos_mas_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== ANÁLISIS DE ALERTAS AMBIENTALES =====
// Contar alertas por cada parámetro ambiental según umbrales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN temperatura > 25 THEN 1 END) as alertas_temperatura,
        COUNT(CASE WHEN humedad > 48 THEN 1 END) as alertas_humedad,
        COUNT(CASE WHEN ruido > 35 THEN 1 END) as alertas_ruido,
        COUNT(CASE WHEN co2 > 1000 THEN 1 END) as alertas_co2,
        COUNT(CASE WHEN lux < 195 THEN 1 END) as alertas_iluminacion
    FROM registros 
    WHERE DATE(fecha_hora) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$alertas = $stmt->fetch(PDO::FETCH_ASSOC);

// ===== ANÁLISIS DE TENDENCIAS HORARIAS =====
// Calcular patrones de comportamiento por hora del día
$stmt = $pdo->prepare("
    SELECT 
        HOUR(fecha_hora) as hora,
        AVG(temperatura) as temp_promedio,
        AVG(humedad) as hum_promedio,
        AVG(ruido) as ruido_promedio,
        COUNT(*) as registros
    FROM registros 
    WHERE DATE(fecha_hora) BETWEEN ? AND ?
    GROUP BY HOUR(fecha_hora)
    ORDER BY hora
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$tendencias_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== LISTA DE DISPOSITIVOS PARA FILTROS =====
// Obtener todos los dispositivos disponibles para el selector
$stmt = $pdo->prepare("SELECT DISTINCT nombre FROM dispositivos ORDER BY nombre");
$stmt->execute();
$lista_dispositivos = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Datos del período seleccionado
$sql = "SELECT 
    r.sensor_id,
    d.ubicacion,
    d.direccion_ip,
    d.direccion_mac,
    r.temperatura,
    r.humedad,
    r.ruido,
    r.co2,
    r.lux,
    r.fecha_hora
FROM registros r
LEFT JOIN dispositivos d ON r.sensor_id = d.nombre
WHERE DATE(r.fecha_hora) BETWEEN ? AND ?";

$params = [$fecha_inicio, $fecha_fin];

if (!empty($dispositivo_filtro)) {
    $sql .= " AND r.sensor_id = ?";
    $params[] = $dispositivo_filtro;
}

$sql .= " ORDER BY r.fecha_hora DESC LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros_detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="informes-container">
    <div class="page-header">
        <h1>📊 Informes y Estadísticas</h1>
        <p>Análisis detallado de sensores y dispositivos</p>
    </div>
    
    <!-- Filtros -->
    <div class="filters-card">
        <h3>🔍 Filtros de Análisis</h3>
        <form method="GET" class="filters-form">
            <input type="hidden" name="seccion" value="informes">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="fecha_fin">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label for="dispositivo">Dispositivo:</label>
                    <select id="dispositivo" name="dispositivo" class="form-control">
                        <option value="">Todos los dispositivos</option>
                        <?php foreach ($lista_dispositivos as $dispositivo): ?>
                        <option value="<?php echo htmlspecialchars($dispositivo); ?>" 
                                <?php echo $dispositivo_filtro === $dispositivo ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dispositivo); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Estadísticas Generales -->
    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-icon">🔌</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $total_dispositivos; ?></div>
                <div class="stat-label">Dispositivos Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $dispositivos_activos; ?></div>
                <div class="stat-label">Activos Ahora</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($total_registros); ?></div>
                <div class="stat-label">Registros Período</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($promedio_lecturas, 1); ?></div>
                <div class="stat-label">Lecturas/Día Promedio</div>
            </div>
        </div>
    </div>
    
    <!-- Análisis de Alertas -->
    <div class="alerts-analysis">
        <h3>🚨 Análisis de Alertas</h3>
        <div class="alerts-grid">
            <div class="alert-item">
                <div class="alert-icon temp">🌡️</div>
                <div class="alert-content">
                    <div class="alert-number"><?php echo $alertas['alertas_temperatura']; ?></div>
                    <div class="alert-label">Temperatura > 25°C</div>
                </div>
            </div>
            <div class="alert-item">
                <div class="alert-icon humidity">💧</div>
                <div class="alert-content">
                    <div class="alert-number"><?php echo $alertas['alertas_humedad']; ?></div>
                    <div class="alert-label">Humedad > 48%</div>
                </div>
            </div>
            <div class="alert-item">
                <div class="alert-icon noise">🔊</div>
                <div class="alert-content">
                    <div class="alert-number"><?php echo $alertas['alertas_ruido']; ?></div>
                    <div class="alert-label">Ruido > 35dB</div>
                </div>
            </div>
            <div class="alert-item">
                <div class="alert-icon co2">🌫️</div>
                <div class="alert-content">
                    <div class="alert-number"><?php echo $alertas['alertas_co2']; ?></div>
                    <div class="alert-label">CO₂ > 1000ppm</div>
                </div>
            </div>
            <div class="alert-item">
                <div class="alert-icon light">💡</div>
                <div class="alert-content">
                    <div class="alert-number"><?php echo $alertas['alertas_iluminacion']; ?></div>
                    <div class="alert-label">Iluminación < 195lux</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dispositivos Más Activos -->
    <div class="devices-ranking">
        <h3>🏆 Dispositivos Más Activos</h3>
        <div class="ranking-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Posición</th>
                        <th>Dispositivo</th>
                        <th>Ubicación</th>
                        <th>Registros</th>
                        <th>Temp. Promedio</th>
                        <th>Hum. Promedio</th>
                        <th>Última Lectura</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dispositivos_mas_activos as $index => $dispositivo): ?>
                    <tr>
                        <td>
                            <?php 
                            $posicion = $index + 1;
                            $emoji = $posicion <= 3 ? ['🥇', '🥈', '🥉'][$posicion-1] : $posicion;
                            echo $emoji;
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($dispositivo['sensor_id']); ?></td>
                        <td><?php echo htmlspecialchars($dispositivo['ubicacion']); ?></td>
                        <td><?php echo number_format($dispositivo['total_registros']); ?></td>
                        <td><?php echo number_format($dispositivo['temp_promedio'], 1); ?>°C</td>
                        <td><?php echo number_format($dispositivo['hum_promedio'], 1); ?>%</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($dispositivo['ultima_lectura'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
      <!-- Tendencias por Hora -->
    <div class="hourly-trends">
        <h3>🕐 Tendencias por Hora del Día</h3>
        <?php if (count($tendencias_hora) > 0): ?>
            <div class="trends-info">
                <p>📊 Mostrando datos promedio por hora para el período seleccionado (<?php echo count($tendencias_hora); ?> horas con datos)</p>
            </div>            <div class="trends-chart">
                <div id="chartLoading" class="chart-loading">
                    <div class="loading-spinner"></div>
                    <p>Generando gráfico...</p>
                </div>
                <canvas id="hourlyChart" width="800" height="400" style="display: none;"></canvas>
            </div>
        <?php else: ?>
            <div class="no-data-message">
                <div class="no-data-icon">📊</div>
                <h4>No hay datos de tendencias disponibles</h4>
                <p>No se encontraron datos suficientes para generar el gráfico de tendencias por hora.</p>
                <div class="suggestions">
                    <strong>Sugerencias:</strong>
                    <ul>
                        <li>Amplíe el rango de fechas seleccionado</li>
                        <li>Verifique que los dispositivos estén enviando datos</li>
                        <li>Seleccione "Todos los dispositivos" en el filtro</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
      <!-- Acciones de Exportación -->
    <div class="export-actions">
        <h3>📤 Exportar Datos</h3>
        <div class="export-info">
            <p>📋 <strong><?php echo number_format(count($registros_detalle)); ?> registros</strong> disponibles para exportar en el período seleccionado</p>
            <?php if (!empty($dispositivo_filtro)): ?>
                <p>🔍 Filtrado por dispositivo: <strong><?php echo htmlspecialchars($dispositivo_filtro); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="export-buttons">
            <?php if (count($registros_detalle) > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    <input type="hidden" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    <input type="hidden" name="dispositivo_filtro" value="<?php echo $dispositivo_filtro; ?>">
                    <button type="submit" name="export_csv" class="btn btn-success">
                        📊 Exportar CSV
                    </button>
                </form>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Imprimir Informe
                </button>
                <button onclick="toggleFullscreen()" class="btn btn-info">
                    🔍 Pantalla Completa
                </button>
            <?php else: ?>
                <button disabled class="btn btn-secondary" title="No hay datos para exportar">
                    📊 Exportar CSV (Sin datos)
                </button>
                <p style="color: var(--warning-color); margin-top: 10px;">
                    ⚠️ No hay datos disponibles para exportar en el período seleccionado
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detalle de Registros -->
    <div class="records-detail">
        <div class="records-header">
            <h3>📋 Detalle de Registros (Últimos 1000)</h3>
            <div class="records-actions">
                <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <form method="POST" style="display: inline-block; margin-left: 15px;">
                        <input type="hidden" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                        <input type="hidden" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                        <input type="hidden" name="dispositivo_filtro" value="<?php echo $dispositivo_filtro; ?>">
                        <button type="submit" name="generar_datos_prueba" class="btn btn-warning" 
                                onclick="return confirm('¿Está seguro que desea actualizar los últimos 5 registros?\n\nEsto cambiará sus fechas a timestamps aleatorios de las últimas 2 horas.');">
                            🧪 Generar Datos de Prueba
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($mensaje_prueba)): ?>
            <div class="test-data-message">
                <p><?php echo htmlspecialchars($mensaje_prueba); ?></p>
                <?php if (strpos($mensaje_prueba, '✅') === 0): ?>
                    <div class="auto-refresh-notice">
                        <small>🔄 La página se actualizará automáticamente en 2 segundos...</small>
                    </div>
                    <script>
                        // Auto-hide message and redirect without mensaje parameter
                        setTimeout(function() {
                            const currentUrl = new URL(window.location.href);
                            currentUrl.searchParams.delete('mensaje'); // Remove message parameter
                            window.location.href = currentUrl.toString();
                        }, 2000);
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>Ubicación</th>
                        <th>IP</th>
                        <th>MAC</th>
                        <th>Temp. (°C)</th>
                        <th>Hum. (%)</th>
                        <th>Ruido (dB)</th>
                        <th>CO₂ (ppm)</th>
                        <th>Luz (lux)</th>
                        <th>Fecha/Hora</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros_detalle as $registro): 
                        $alertas_registro = [];
                        if ($registro['temperatura'] > 25) $alertas_registro[] = 'temp';
                        if ($registro['humedad'] > 48) $alertas_registro[] = 'hum';
                        if ($registro['ruido'] > 35) $alertas_registro[] = 'ruido';
                        if ($registro['co2'] > 1000) $alertas_registro[] = 'co2';
                        if ($registro['lux'] < 195) $alertas_registro[] = 'luz';
                        
                        $estado_fila = count($alertas_registro) >= 3 ? 'critico' : (count($alertas_registro) >= 1 ? 'alerta' : 'normal');
                    ?>
                    <tr class="row-<?php echo $estado_fila; ?>">
                        <td><?php echo htmlspecialchars($registro['sensor_id']); ?></td>
                        <td><?php echo htmlspecialchars($registro['ubicacion'] ?? 'N/A'); ?></td>
                        <td style="font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($registro['direccion_ip'] ?? 'N/A'); ?></td>
                        <td style="font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($registro['direccion_mac'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $registro['temperatura'] > 25 ? 'value-alert' : ''; ?>">
                            <?php echo number_format($registro['temperatura'], 1); ?>
                        </td>
                        <td class="<?php echo $registro['humedad'] > 48 ? 'value-alert' : ''; ?>">
                            <?php echo number_format($registro['humedad'], 1); ?>
                        </td>
                        <td class="<?php echo $registro['ruido'] > 35 ? 'value-alert' : ''; ?>">
                            <?php echo number_format($registro['ruido'], 1); ?>
                        </td>
                        <td class="<?php echo $registro['co2'] > 1000 ? 'value-alert' : ''; ?>">
                            <?php echo number_format($registro['co2'], 0); ?>
                        </td>
                        <td class="<?php echo $registro['lux'] < 195 ? 'value-alert' : ''; ?>">
                            <?php echo number_format($registro['lux'], 0); ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $estado_fila; ?>">
                                <?php 
                                echo $estado_fila === 'critico' ? '🔴 Crítico' : 
                                    ($estado_fila === 'alerta' ? '🟡 Alerta' : '🟢 Normal');
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para informes */
.informes-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 2.5em;
    color: var(--primary-color);
    margin: 0;
}

.filters-card {
    background: var(--card-background);
    padding: 20px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 30px;
}

.filters-form .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card-background);
    padding: 25px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    font-size: 3em;
    opacity: 0.8;
}

.stat-number {
    font-size: 2.5em;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0;
}

.stat-label {
    color: var(--text-light);
    font-size: 1.1em;
}

.alerts-analysis {
    background: var(--card-background);
    padding: 20px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 30px;
}

.alerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-radius: 8px;
    background: rgba(0,0,0,0.02);
}

.alert-icon {
    font-size: 2em;
}

.alert-number {
    font-size: 1.8em;
    font-weight: 700;
    color: var(--warning-color);
}

.devices-ranking, .hourly-trends, .records-detail {
    background: var(--card-background);
    padding: 20px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 30px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.data-table th, .data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--background-color);
    font-weight: 600;
    color: var(--text-color);
    white-space: nowrap;
}

.data-table tr:hover {
    background: rgba(0,120,212,0.05);
}

/* Estilos específicos para las columnas de red */
.data-table td[style*="font-family: monospace"] {
    font-size: 0.85em;
    background: rgba(0,0,0,0.02);
    color: #555;
    max-width: 120px;
    word-break: break-all;
}

.table-container {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.export-actions {
    background: var(--card-background);
    padding: 20px;
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 30px;
}

.export-info {
    background: rgba(0, 120, 212, 0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--primary-color);
}

.export-info p {
    margin: 5px 0;
}

.export-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.export-buttons button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.trends-chart {
    margin-top: 20px;
    text-align: center;
}

.trends-info {
    background: rgba(0, 120, 212, 0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--primary-color);
}

.no-data-message {
    text-align: center;
    padding: 40px 20px;
    background: rgba(255, 152, 0, 0.05);
    border-radius: 10px;
    border: 2px dashed rgba(255, 152, 0, 0.3);
}

.no-data-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.6;
}

.no-data-message h4 {
    color: var(--warning-color);
    margin-bottom: 15px;
}

.no-data-message .suggestions {
    text-align: left;
    max-width: 400px;
    margin: 20px auto 0;
    background: rgba(255, 255, 255, 0.7);
    padding: 15px;
    border-radius: 8px;
}

.no-data-message ul {
    margin: 10px 0 0 20px;
}

.chart-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    color: var(--text-light);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(0, 120, 212, 0.1);
    border-left: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.no-data-message li {
    margin: 8px 0;
}

/* Estados de fila */
.row-normal { background: rgba(16, 124, 16, 0.1); }
.row-alerta { background: rgba(255, 152, 0, 0.1); }
.row-critico { background: rgba(209, 52, 56, 0.1); }

.value-alert {
    color: var(--error-color);
    font-weight: 600;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
}

.status-normal { background: rgba(16, 124, 16, 0.2); color: var(--success-color); }
.status-alerta { background: rgba(255, 152, 0, 0.2); color: var(--warning-color); }
.status-critico { background: rgba(209, 52, 56, 0.2); color: var(--error-color); }

/* Estilos de impresión */
@media print {
    .filters-card, .export-actions { display: none; }
    .page-header h1 { font-size: 2em; }
    .table-container { max-height: none; overflow: visible; }
    .trends-chart { page-break-inside: avoid; }
}

@media (max-width: 768px) {
    .export-buttons { flex-direction: column; }
    .data-table { font-size: 0.9em; }
    .stats-overview { grid-template-columns: repeat(2, 1fr); }
}

/* Estilos para la sección de registros mejorada */
.records-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.records-header h3 {
    margin: 0;
}

.records-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.test-data-message {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid #ffc107;
}

.test-data-message p {
    margin: 0;
    font-weight: 500;
}

.auto-refresh-notice {
    margin-top: 10px;
    padding: 8px 12px;
    background: rgba(0, 120, 212, 0.1);
    border-radius: 4px;
    border-left: 3px solid var(--primary-color);
}

.auto-refresh-notice small {
    color: var(--primary-color);
    font-weight: 500;
}
</style>

<script>
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Gráfico de tendencias por hora
document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const canvas = document.getElementById('hourlyChart');
    const loadingDiv = document.getElementById('chartLoading');
    
    if (!canvas) {
        console.error('Canvas hourlyChart no encontrado');
        return;
    }
    
    // Simular un pequeño delay para mostrar el loading
    setTimeout(function() {
        try {
            const ctx = canvas.getContext('2d');
            const tendencias = <?php echo json_encode($tendencias_hora); ?>;
            
            console.log('Datos de tendencias:', tendencias);
            
            // Ocultar loading y mostrar canvas
            if (loadingDiv) loadingDiv.style.display = 'none';
            canvas.style.display = 'block';
            
            // Configuración del gráfico
            const width = canvas.width;
            const height = canvas.height;
            const padding = 70;
            const chartWidth = width - 2 * padding;
            const chartHeight = height - 2 * padding;
            
            // Limpiar canvas
            ctx.clearRect(0, 0, width, height);
            
            // Fondo del gráfico
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            
            // Título
            ctx.fillStyle = '#333';
            ctx.font = 'bold 18px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Tendencias Promedio por Hora del Día', width/2, 25);
            
            // Verificar si hay datos
            if (!tendencias || tendencias.length === 0) {
                ctx.fillStyle = '#666';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No hay datos disponibles para el período seleccionado', width/2, height/2);
                return;
            }
            
            // Procesar datos
            const horas = tendencias.map(t => parseInt(t.hora));
            const temperaturas = tendencias.map(t => parseFloat(t.temp_promedio) || 0);
            const humedades = tendencias.map(t => parseFloat(t.hum_promedio) || 0);
            const ruidos = tendencias.map(t => parseFloat(t.ruido_promedio) || 0);
            
            // Validar que hay datos válidos
            const hasValidData = temperaturas.some(v => v > 0) || humedades.some(v => v > 0) || ruidos.some(v => v > 0);
            if (!hasValidData) {
                ctx.fillStyle = '#666';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No hay datos válidos para mostrar', width/2, height/2);
                return;
            }
            
            // Calcular rangos para escalas normalizadas
            const maxTemp = Math.max(...temperaturas, 1);
            const maxHum = Math.max(...humedades, 1);
            const maxRuido = Math.max(...ruidos, 1);
            const minTemp = Math.min(...temperaturas, 0);
            const minHum = Math.min(...humedades, 0);
            const minRuido = Math.min(...ruidos, 0);
            
            // Escalas normalizadas (0-1) para poder superponer
            function normalize(value, min, max) {
                if (max === min) return 0.5;
                return (value - min) / (max - min);
            }
            
            const normalizedTemp = temperaturas.map(v => normalize(v, minTemp, maxTemp));
            const normalizedHum = humedades.map(v => normalize(v, minHum, maxHum));
            const normalizedRuido = ruidos.map(v => normalize(v, minRuido, maxRuido));
            
            // Calcular posiciones
            const stepX = chartWidth / Math.max(horas.length - 1, 1);
            
            // Dibujar grid vertical
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 1;
            for (let i = 0; i < horas.length; i++) {
                const x = padding + i * stepX;
                ctx.beginPath();
                ctx.moveTo(x, padding);
                ctx.lineTo(x, height - padding);
                ctx.stroke();
            }
            
            // Dibujar grid horizontal
            for (let i = 0; i <= 4; i++) {
                const y = padding + (chartHeight * i / 4);
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(width - padding, y);
                ctx.stroke();
            }
            
            // Función para dibujar línea mejorada
            function drawLine(data, color, label, originalData) {
                if (!data || data.length === 0) return;
                
                ctx.strokeStyle = color;
                ctx.lineWidth = 3;
                ctx.beginPath();
                
                for (let i = 0; i < data.length; i++) {
                    const x = padding + i * stepX;
                    const y = height - padding - (data[i] * chartHeight);
                    
                    if (i === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                }
                ctx.stroke();
                
                // Dibujar puntos
                ctx.fillStyle = color;
                for (let i = 0; i < data.length; i++) {
                    const x = padding + i * stepX;
                    const y = height - padding - (data[i] * chartHeight);
                    
                    ctx.beginPath();
                    ctx.arc(x, y, 4, 0, 2 * Math.PI);
                    ctx.fill();
                    
                    // Tooltip al hover (simplificado)
                    ctx.fillStyle = '#333';
                    ctx.font = '10px Arial';
                    ctx.textAlign = 'center';
                    if (i % 3 === 0) { // Solo cada 3 puntos para no saturar
                        ctx.fillText(originalData[i].toFixed(1), x, y - 8);
                    }
                    ctx.fillStyle = color;
                }
            }
            
            // Dibujar las líneas
            drawLine(normalizedTemp, '#dc3545', 'Temperatura', temperaturas);
            drawLine(normalizedHum, '#28a745', 'Humedad', humedades);
            drawLine(normalizedRuido, '#ffc107', 'Ruido', ruidos);
            
            // Leyenda mejorada
            const legendY = height - padding + 40;
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'left';
            
            // Temperatura
            ctx.fillStyle = '#dc3545';
            ctx.fillRect(padding, legendY, 15, 10);
            ctx.fillStyle = '#333';
            ctx.fillText(`🌡️ Temperatura (${minTemp.toFixed(1)}°C - ${maxTemp.toFixed(1)}°C)`, padding + 20, legendY + 8);
            
            // Humedad
            ctx.fillStyle = '#28a745';
            ctx.fillRect(padding + 250, legendY, 15, 10);
            ctx.fillStyle = '#333';
            ctx.fillText(`💧 Humedad (${minHum.toFixed(1)}% - ${maxHum.toFixed(1)}%)`, padding + 270, legendY + 8);
            
            // Ruido
            ctx.fillStyle = '#ffc107';
            ctx.fillRect(padding + 480, legendY, 15, 10);
            ctx.fillStyle = '#333';
            ctx.fillText(`🔊 Ruido (${minRuido.toFixed(1)}dB - ${maxRuido.toFixed(1)}dB)`, padding + 500, legendY + 8);
            
            // Etiquetas del eje X (horas)
            ctx.fillStyle = '#666';
            ctx.font = 'bold 11px Arial';
            ctx.textAlign = 'center';
            for (let i = 0; i < horas.length; i++) {
                if (i % 2 === 0 || horas.length <= 12) { // Mostrar cada 2 horas o todas si son pocas
                    const x = padding + i * stepX;
                    ctx.fillText(horas[i] + ':00', x, height - padding + 20);
                }
            }
            
            // Etiquetas del eje Y (porcentaje normalizado)
            ctx.fillStyle = '#666';
            ctx.font = '10px Arial';
            ctx.textAlign = 'right';
            for (let i = 0; i <= 4; i++) {
                const y = padding + (chartHeight * i / 4);
                const percentage = 100 - (i * 25);
                ctx.fillText(percentage + '%', padding - 10, y + 3);
            }
            
            console.log('Gráfico renderizado exitosamente');
            
        } catch (error) {
            console.error('Error al generar el gráfico:', error);
            if (loadingDiv) {
                loadingDiv.innerHTML = '<div style="color: var(--error-color);">❌ Error al cargar el gráfico</div>';
            }
        }
    }, 500); // 500ms delay
});
</script>
