<?php
// ===== GESTIÓN COMPLETA DE DISPOSITIVOS ARDUINO =====
/**
 * INCLUDES/DISPOSITIVOS.PHP - Módulo de Gestión de Dispositivos
 * 
 * Este archivo maneja la gestión completa de dispositivos Arduino conectados
 * al sistema de monitoreo ambiental.
 * 
 * Funcionalidades principales:
 * - Listado de dispositivos con estado en tiempo real
 * - Edición de información de dispositivos
 * - Monitorización individual de sensores
 * - Eliminación de dispositivos y sus datos
 * - Paginación para sistemas con muchos dispositivos
 * - Estados: Activo (datos en última hora) / Inactivo
 */

// ===== INICIALIZACIÓN DE VARIABLES =====
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';  // Acción a realizar
$message = '';  // Mensaje de éxito
$error = '';    // Mensaje de error
// Valores por defecto para evitar undefined
$dispositivos = [];
$total_devices = 0;
$total_pages = 0;

try {
    // === Conexión optimizada usando DatabaseManager ===
    require_once __DIR__ . '/database_manager.php';
    $dbManager = getDBManager($config ?? null);
    $pdo = $dbManager->getConnection();
    
    // ===== PROCESAMIENTO DE ACCIONES DEL USUARIO =====
    switch ($action) {
        
        // === ACCIÓN: EDITAR INFORMACIÓN DEL DISPOSITIVO ===
        case 'edit':
            $device_id = $_GET['id'] ?? $_POST['id'] ?? 0;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Sanitización y actualización de ubicación
                $nueva_ubicacion = trim($_POST['ubicacion'] ?? '');
                
                $stmt = $pdo->prepare("UPDATE dispositivos SET ubicacion=? WHERE id=?");
                if ($stmt->execute([$nueva_ubicacion, $device_id])) {
                    $message = "Ubicación del dispositivo actualizada exitosamente.";
                    $action = 'list';  // Retornar a la lista
                } else {
                    $error = 'Error al actualizar el dispositivo.';
                }
            }
            break;
            
        // === ACCIÓN: ELIMINAR DISPOSITIVO Y SUS REGISTROS ===
        case 'delete':
            $device_id = $_GET['id'] ?? 0;
            if ($device_id && isset($_GET['confirm'])) {
                // Primero eliminar todos los registros relacionados del dispositivo
                $pdo->prepare("DELETE FROM registros WHERE sensor_id = (SELECT nombre FROM dispositivos WHERE id = ?)")->execute([$device_id]);
                // Luego eliminar el dispositivo de la tabla principal
                $stmt = $pdo->prepare("DELETE FROM dispositivos WHERE id = ?");
                if ($stmt->execute([$device_id])) {
                    $message = "Dispositivo eliminado exitosamente.";
                } else {
                    $error = 'Error al eliminar el dispositivo.';
                }
                $action = 'list';
            }
            break;
    }
    
    // ===== OBTENCIÓN DE LISTA DE DISPOSITIVOS CON ESTADO ===
    
    // === Configuración de paginación ===
    $page = max(1, intval($_GET['page'] ?? 1));                                    // Página actual
    $per_page = isset($config['sistema']['max_devices_per_page'])
                ? (int)$config['sistema']['max_devices_per_page']
                : 25;                                                           // Dispositivos por página (por defecto 25)
    $offset = ($page - 1) * $per_page;                                              // Offset para SQL
    
    // === Consulta compleja para obtener dispositivos con estado ===
    $sql = "SELECT d.*, 
                   CASE WHEN r.ultima_lectura IS NOT NULL AND r.ultima_lectura >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
                        THEN 'Activo' 
                        ELSE 'Inactivo' 
                   END as estado,
                   r.ultima_lectura
            FROM dispositivos d 
            LEFT JOIN (
                SELECT sensor_id, MAX(fecha_hora) as ultima_lectura 
                FROM registros 
                GROUP BY sensor_id
            ) r ON d.nombre = r.sensor_id 
            ORDER BY d.id 
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $dispositivos = $stmt->fetchAll();
    
    // === Cálculo de totales para paginación ===
    $total_devices = (int)$pdo->query("SELECT COUNT(*) FROM dispositivos")->fetchColumn();
    $total_pages = $total_devices > 0 ? ceil($total_devices / $per_page) : 1;
    
} catch (Exception $e) {
    // === Manejo de errores de base de datos ===
    $error = "Error: " . $e->getMessage();
}
?>

<!-- ===== ENCABEZADO DE LA PÁGINA DE GESTIÓN ===== -->
<h2 class="page-title">Gestión de Dispositivos</h2>

<?php if ($message): ?>
    <!-- Mensaje de éxito -->
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <!-- Mensaje de error -->
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- ===== VISTA DE LISTA DE DISPOSITIVOS ===== -->
    
    <!-- === Banner de estadísticas rápidas === -->
    <div class="stats-banner">
        📊 Total de Dispositivos: <?php echo $total_devices; ?> | 
        Página <?php echo $page; ?> de <?php echo $total_pages; ?>
    </div>
    
    <!-- === Tabla de dispositivos con información completa === -->
    <?php if (count($dispositivos) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 8px; overflow: hidden; box-shadow: var(--shadow);">
                <!-- Encabezados de tabla -->
                <thead style="background: var(--primary-color); color: white;">
                    <tr>
                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color);">ID</th>
                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color);">Nombre</th>
                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color);">Ubicación</th>
                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color);">IP</th>
                        <th style="padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color);">MAC</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 1px solid var(--border-color);">Estado</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 1px solid var(--border-color);">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dispositivos as $dispositivo): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px;"><?php echo $dispositivo['id']; ?></td>
                            <td style="padding: 15px; font-weight: 500;"><?php echo htmlspecialchars($dispositivo['nombre']); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($dispositivo['ubicacion'] ?? 'N/A'); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($dispositivo['direccion_ip'] ?? 'N/A'); ?></td>
                            <td style="padding: 15px; font-family: monospace;"><?php echo htmlspecialchars($dispositivo['direccion_mac'] ?? 'N/A'); ?></td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($dispositivo['estado'] === 'Activo'): ?>
                                    <span style="background: var(--success-color); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        ✅ Activo
                                    </span>
                                <?php else: ?>
                                    <span style="background: var(--secondary-color); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        ⚠️ Inactivo
                                    </span>
                                <?php endif; ?>
                                <?php if ($dispositivo['ultima_lectura']): ?>
                                    <br><small style="color: var(--text-light);">
                                        <?php echo date('d/m H:i', strtotime($dispositivo['ultima_lectura'])); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                    <a href="?seccion=dispositivos&action=monitor&id=<?php echo $dispositivo['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        📊 Monitorizar
                                    </a>
                                    <a href="?seccion=dispositivos&action=edit&id=<?php echo $dispositivo['id']; ?>" 
                                       class="btn btn-sm btn-secondary">
                                        ✏️ Editar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div>
                    Mostrando <?php echo min($per_page, $total_devices - $offset); ?> de <?php echo $total_devices; ?> dispositivos
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?seccion=dispositivos&page=<?php echo $page - 1; ?>" class="pagination-btn">« Anterior</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?seccion=dispositivos&page=<?php echo $i; ?>" 
                           class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?seccion=dispositivos&page=<?php echo $page + 1; ?>" class="pagination-btn">Siguiente »</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📡</div>
            <h3>No hay dispositivos registrados</h3>
            <p>No se encontraron dispositivos en el sistema.</p>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'edit'): ?>
    <?php
    $device_id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM dispositivos WHERE id = ?");
    $stmt->execute([$device_id]);
    $edit_device = $stmt->fetch();
    
    if (!$edit_device) {
        echo '<div class="alert alert-error">Dispositivo no encontrado.</div>';
        echo '<a href="?seccion=dispositivos" class="btn btn-primary">Volver a la lista</a>';
    } else {
    ?>
    <!-- Formulario de edición -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $device_id; ?>">
        
        <div class="form-section">
            <h3>Editar Dispositivo: <?php echo htmlspecialchars($edit_device['nombre']); ?></h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre del Dispositivo</label>
                    <input type="text" class="form-control" readonly 
                           value="<?php echo htmlspecialchars($edit_device['nombre']); ?>">
                    <small style="color: var(--text-light);">El nombre del dispositivo no se puede modificar</small>
                </div>
                
                <div class="form-group">
                    <label for="ubicacion" class="form-label">Ubicación</label>
                    <input type="text" id="ubicacion" name="ubicacion" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['ubicacion'] ?? $edit_device['ubicacion']); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Dirección IP</label>
                    <input type="text" class="form-control" readonly 
                           value="<?php echo htmlspecialchars($edit_device['direccion_ip'] ?? 'N/A'); ?>">
                    <small style="color: var(--text-light);">La dirección IP se actualiza automáticamente</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dirección MAC</label>
                    <input type="text" class="form-control" readonly 
                           value="<?php echo htmlspecialchars($edit_device['direccion_mac'] ?? 'N/A'); ?>"
                           style="font-family: monospace;">
                    <small style="color: var(--text-light);">La dirección MAC se registra automáticamente</small>
                </div>
            </div>
            
            <div class="info-box">
                <strong>Información del Dispositivo:</strong>
                <ul>
                    <li>ID: <?php echo $edit_device['id']; ?></li>
                    <li>Registrado: <?php echo !empty($edit_device['fecha_creacion'])
        ? date('d/m/Y H:i', strtotime($edit_device['fecha_creacion']))
        : 'N/A'; ?></li>
                    <li>Solo se puede modificar la ubicación del dispositivo</li>
                </ul>
            </div>
        </div>
        
        <div class="text-center mt-xl">
            <button type="submit" class="btn btn-primary">Actualizar Ubicación</button>
            <a href="?seccion=dispositivos" class="btn btn-secondary">Cancelar</a>
            <a href="?seccion=dispositivos&action=delete&id=<?php echo $device_id; ?>" 
               onclick="return confirm('¿Está seguro que desea eliminar este dispositivo? Se eliminarán también todos sus registros.')"
               class="btn" style="background: var(--error-color); color: white;">
                Eliminar Dispositivo
            </a>
        </div>
    </form>
    <?php } ?>

<?php elseif ($action === 'delete'): ?>
    <?php
    // ===== SECCIÓN DE ELIMINACIÓN DE DISPOSITIVOS =====
    // Obtener ID del dispositivo a eliminar desde parámetros GET
    $device_id = $_GET['id'] ?? 0;
    
    // Buscar el dispositivo en la base de datos
    $stmt = $pdo->prepare("SELECT * FROM dispositivos WHERE id = ?");
    $stmt->execute([$device_id]);
    $delete_device = $stmt->fetch();
    
    // Verificar si el dispositivo existe
    if (!$delete_device) {
        echo '<div class="alert alert-error">Dispositivo no encontrado.</div>';
        echo '<a href="?seccion=dispositivos" class="btn btn-primary">Volver a la lista</a>';
    } else {
        // === Contar registros asociados al dispositivo ===
        // Importante: mostrar cuántos datos se perderán
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE sensor_id = ?");
        $stmt->execute([$delete_device['nombre']]);
        $total_registros = $stmt->fetchColumn();
    ?>
    <!-- ===== PANTALLA DE CONFIRMACIÓN DE ELIMINACIÓN ===== -->
    <div class="alert alert-error">
        <h3>⚠️ Confirmar Eliminación de Dispositivo</h3>
        <p>¿Está seguro que desea eliminar el dispositivo <strong><?php echo htmlspecialchars($delete_device['nombre']); ?></strong>?</p>
        <p><strong>ATENCIÓN:</strong> Se eliminarán también <?php echo $total_registros; ?> registros asociados.</p>
        <p>Esta acción no se puede deshacer.</p>
    </div>
    
    <!-- === Botones de confirmación === -->
    <div class="text-center mt-xl">
        <a href="?seccion=dispositivos&action=delete&id=<?php echo $device_id; ?>&confirm=1" 
           class="btn" style="background: var(--error-color); color: white;">
            Sí, Eliminar Dispositivo y Registros
        </a>
        <a href="?seccion=dispositivos" class="btn btn-primary">Cancelar</a>
    </div>
    <?php } ?>

<?php elseif ($action === 'monitor'): ?>
    <?php
    // ===== SECCIÓN DE MONITORIZACIÓN DE DISPOSITIVOS =====
    // Obtener ID del dispositivo a monitorizar
    $device_id = $_GET['id'] ?? 0;
    
    // Buscar información del dispositivo
    $stmt = $pdo->prepare("SELECT * FROM dispositivos WHERE id = ?");
    $stmt->execute([$device_id]);
    $monitor_device = $stmt->fetch();
    
    // Verificar si el dispositivo existe
    if (!$monitor_device) {
        echo '<div class="alert alert-error">Dispositivo no encontrado.</div>';
        echo '<a href="?seccion=dispositivos" class="btn btn-primary">Volver a la lista</a>';
    } else {
        // === Obtener registros de las últimas 24 horas ===
        // Consulta para obtener datos recientes del sensor
        $stmt = $pdo->prepare("
            SELECT * FROM registros 
            WHERE sensor_id = ? AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY fecha_hora DESC
        ");
        $stmt->execute([$monitor_device['nombre']]);
        $registros = $stmt->fetchAll();
        
        // === Calcular estadísticas del dispositivo ===
        // Generar métricas de rendimiento y estado
        if (count($registros) > 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    AVG(temperatura) as avg_temp, MAX(temperatura) as max_temp, MIN(temperatura) as min_temp,
                    AVG(humedad) as avg_hum, MAX(humedad) as max_hum, MIN(humedad) as min_hum,
                    AVG(ruido) as avg_ruido, MAX(ruido) as max_ruido, MIN(ruido) as min_ruido,
                    AVG(co2) as avg_co2, MAX(co2) as max_co2, MIN(co2) as min_co2,
                    AVG(lux) as avg_lux, MAX(lux) as max_lux, MIN(lux) as min_lux
                FROM registros 
                WHERE sensor_id = ? AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$monitor_device['nombre']]);
            $stats = $stmt->fetch();
        }
    ?>
    
    <!-- ===== PANTALLA DE MONITORIZACIÓN DEL DISPOSITIVO ===== -->
    <div class="info-box">
        <h3>📊 Monitor del Dispositivo: <?php echo htmlspecialchars($monitor_device['nombre']); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <!-- === Información básica del dispositivo === -->
            <div>
                <strong>📍 Ubicación:</strong><br>
                <?php echo htmlspecialchars($monitor_device['ubicacion'] ?? 'No especificada'); ?>
            </div>
            
            <div>
                <strong>🌐 Dirección IP:</strong><br>
                <code><?php echo htmlspecialchars($monitor_device['direccion_ip'] ?? 'N/A'); ?></code>
            </div>
            
            <div>
                <strong>🏷️ MAC Address:</strong><br>
                <code><?php echo htmlspecialchars($monitor_device['direccion_mac'] ?? 'N/A'); ?></code>
            </div>
            
            <div>
                <strong>📊 Total de Registros:</strong><br>
                <?php echo count($registros); ?> (últimas 24h)
            </div>
        </div>
    </div>
    
    <?php if (count($registros) > 0): ?>
        <!-- ===== ESTADÍSTICAS AMBIENTALES DEL DISPOSITIVO ===== -->
        <div class="form-section">
            <h4>📈 Estadísticas Ambientales (Últimas 24 horas)</h4>
            <!-- Grid de métricas organizadas por tipo de sensor -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                
                <!-- === Estadísticas de Temperatura === -->
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5>🌡️ Temperatura</h5>
                    <p><strong>Promedio:</strong> <?php echo number_format($stats['avg_temp'], 1); ?>°C</p>
                    <!-- Indicador visual de alerta si supera límites -->
                    <p><strong>Máximo:</strong> <span style="color: <?php echo $stats['max_temp'] > $config['referencias']['temperatura_max'] ? 'var(--error-color)' : 'var(--success-color)'; ?>;"><?php echo number_format($stats['max_temp'], 1); ?>°C</span></p>
                    <p><strong>Mínimo:</strong> <?php echo number_format($stats['min_temp'], 1); ?>°C</p>
                </div>
                
                <!-- === Estadísticas de Humedad === -->
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5>💧 Humedad</h5>
                    <p><strong>Promedio:</strong> <?php echo number_format($stats['avg_hum'], 1); ?>%</p>
                    <!-- Validación contra parámetros de referencia -->
                    <p><strong>Máximo:</strong> <span style="color: <?php echo $stats['max_hum'] > $config['referencias']['humedad_max'] ? 'var(--error-color)' : 'var(--success-color)'; ?>;"><?php echo number_format($stats['max_hum'], 1); ?>%</span></p>
                    <p><strong>Mínimo:</strong> <?php echo number_format($stats['min_hum'], 1); ?>%</p>
                </div>
                
                <!-- === Estadísticas de Ruido === -->
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5>🔊 Ruido</h5>
                    <p><strong>Promedio:</strong> <?php echo number_format($stats['avg_ruido'], 1); ?> dB</p>
                    <!-- Control de contaminación acústica -->
                    <p><strong>Máximo:</strong> <span style="color: <?php echo $stats['max_ruido'] > $config['referencias']['ruido_max'] ? 'var(--error-color)' : 'var(--success-color)'; ?>;"><?php echo number_format($stats['max_ruido'], 1); ?> dB</span></p>
                    <p><strong>Mínimo:</strong> <?php echo number_format($stats['min_ruido'], 1); ?> dB</p>
                </div>
                
                <!-- === Estadísticas de CO2 === -->
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5>☁️ CO2</h5>
                    <p><strong>Promedio:</strong> <?php echo number_format($stats['avg_co2'], 0); ?> ppm</p>
                    <!-- Monitoreo de calidad del aire -->
                    <p><strong>Máximo:</strong> <span style="color: <?php echo $stats['max_co2'] > $config['referencias']['co2_max'] ? 'var(--error-color)' : 'var(--success-color)'; ?>;"><?php echo number_format($stats['max_co2'], 0); ?> ppm</span></p>
                    <p><strong>Mínimo:</strong> <?php echo number_format($stats['min_co2'], 0); ?> ppm</p>
                </div>
                
                <!-- === Estadísticas de Iluminación === -->
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                    <h5>💡 Iluminación</h5>
                    <p><strong>Promedio:</strong> <?php echo number_format($stats['avg_lux'], 0); ?> lux</p>
                    <p><strong>Máximo:</strong> <?php echo number_format($stats['max_lux'], 0); ?> lux</p>
                    <!-- Control de niveles mínimos de iluminación -->
                    <p><strong>Mínimo:</strong> <span style="color: <?php echo $stats['min_lux'] < $config['referencias']['lux_min'] ? 'var(--error-color)' : 'var(--success-color)'; ?>;"><?php echo number_format($stats['min_lux'], 0); ?> lux</span></p>
                </div>
            </div>
        </div>
        
        <!-- ===== TABLA DETALLADA DE REGISTROS ===== -->
        <div class="form-section">
            <h4>📋 Registros Detallados (Últimas 24 horas) - <?php echo count($registros); ?> registros</h4>
            <!-- Tabla scrolleable con registros históricos -->
            <div style="overflow-x: auto; max-height: 400px;">
                <table style="width: 100%; border-collapse: collapse; background: white;">
                    <!-- === Encabezados fijos de la tabla === -->
                    <thead style="background: var(--background-color); position: sticky; top: 0;">
                        <tr>
                            <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Fecha/Hora</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid var(--border-color);">Temp (°C)</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid var(--border-color);">Humedad (%)</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid var(--border-color);">Ruido (dB)</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid var(--border-color);">CO2 (ppm)</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid var(--border-color);">Luz (lux)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $registro): ?>
                            <!-- === Fila de datos con codificación de colores === -->
                            <tr>
                                <!-- Timestamp formateado para España -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); font-size: 14px;">
                                    <?php echo date('d/m/Y H:i:s', strtotime($registro['fecha_hora'])); ?>
                                </td>
                                <!-- Temperatura con validación de límites -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); text-align: center; color: <?php echo ($registro['temperatura'] > $config['referencias']['temperatura_max']) ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo $registro['temperatura'] ?? 'N/A'; ?>
                                </td>
                                <!-- Humedad con indicador visual -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); text-align: center; color: <?php echo ($registro['humedad'] > $config['referencias']['humedad_max']) ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo $registro['humedad'] ?? 'N/A'; ?>
                                </td>
                                <!-- Ruido con control de contaminación acústica -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); text-align: center; color: <?php echo ($registro['ruido'] > $config['referencias']['ruido_max']) ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo $registro['ruido'] ?? 'N/A'; ?>
                                </td>
                                <!-- CO2 con validación de calidad del aire -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); text-align: center; color: <?php echo ($registro['co2'] > $config['referencias']['co2_max']) ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo $registro['co2'] ?? 'N/A'; ?>
                                </td>
                                <!-- Iluminación con control de mínimos -->
                                <td style="padding: 8px; border: 1px solid var(--border-color); text-align: center; color: <?php echo ($registro['lux'] < $config['referencias']['lux_min']) ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo $registro['lux'] ?? 'N/A'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ===== ESTADO VACÍO - SIN DATOS ===== -->
        <div class="empty-state">
            <div class="empty-icon">📊</div>
            <h3>Sin registros en las últimas 24 horas</h3>
            <p>Este dispositivo no ha enviado datos en las últimas 24 horas.</p>
        </div>
    <?php endif; ?>
    
    <div class="text-center mt-xl">
        <a href="?seccion=dispositivos&action=edit&id=<?php echo $device_id; ?>" class="btn btn-secondary">
            ✏️ Editar Dispositivo
        </a>
        <a href="?seccion=dispositivos" class="btn btn-primary">
            ← Volver a Lista
        </a>
    </div>
    
    <?php } ?>

<?php endif; ?>
