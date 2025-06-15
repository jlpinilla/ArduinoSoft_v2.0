<?php
// ===== DASHBOARD PRINCIPAL DEL SISTEMA DE MONITOREO AMBIENTAL =====
/**
 * INCLUDES/DASHBOARD.PHP - Panel Principal del Dashboard
 * 
 * Este archivo contiene la página principal del dashboard que muestra:
 * - Estadísticas generales del sistema
 * - Tarjetas informativas de funcionalidades
 * - Información del usuario actual
 * - Alertas recientes del sistema
 * 
 * Funcionalidades principales:
 * - Conteo de dispositivos, usuarios y registros
 * - Estado de dispositivos activos en tiempo real
 * - Enlaces de navegación a todas las secciones
 * - Monitoreo de alertas por valores fuera de rango
 */

// ===== OBTENCIÓN DE ESTADÍSTICAS DEL SISTEMA OPTIMIZADA =====
try {
    // Incluir gestor optimizado de base de datos
    require_once __DIR__ . '/database_manager.php';
    
    // Obtener instancia del gestor de base de datos optimizado
    $db_manager = getDBManager($config);
    
    // === Consulta optimizada única para obtener todas las estadísticas ===
    // Una sola consulta para reducir el overhead de múltiples consultas
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM dispositivos) as total_dispositivos,
            (SELECT COUNT(*) FROM usuarios) as total_usuarios,
            (SELECT COUNT(DISTINCT d.id) 
             FROM dispositivos d 
             INNER JOIN registros r ON d.nombre = r.sensor_id 
             WHERE r.fecha_hora >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as dispositivos_activos,
            (SELECT COUNT(*) 
             FROM registros 
             WHERE DATE(fecha_hora) = CURDATE()) as registros_hoy,
            (SELECT COUNT(*) 
             FROM registros 
             WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as registros_semana
    ";
      // Ejecutar consulta optimizada
    $stats = $db_manager->fetchRow($stats_query, []);
    
    // Asignar variables para compatibilidad con código existente
    $total_dispositivos = $stats['total_dispositivos'] ?? 0;
    $total_usuarios = $stats['total_usuarios'] ?? 0;
    $dispositivos_activos = $stats['dispositivos_activos'] ?? 0;
    $registros_hoy = $stats['registros_hoy'] ?? 0;
    $registros_semana = $stats['registros_semana'] ?? 0;
    
} catch (Exception $e) {
    // === Manejo robusto de errores con logging ===
    $error_message = "Error al cargar estadísticas: " . $e->getMessage();
    error_log("Dashboard Stats Error: " . $e->getMessage());
    
    // Valores por defecto en caso de error
    $total_dispositivos = 0;
    $total_usuarios = 0;
    $dispositivos_activos = 0;
    $registros_hoy = 0;
    $registros_semana = 0;
}

// Obtener información del sistema desde configuración
$sistema_nombre = $config['sistema']['nombre'] ?? 'Sistema de Monitoreo Ambiental';
$sistema_version = $config['sistema']['version'] ?? '';
?>


<!-- ===== ENCABEZADO DE LA PÁGINA PRINCIPAL ===== -->
<h2 class="page-title">🎛️ Panel de Control</h2>
<p><?php echo htmlspecialchars($sistema_nombre); ?> • Versión <?php echo htmlspecialchars($sistema_version); ?> </p>

<?php if (isset($error_message)): ?>
    <!-- Mensaje de error si falla la conexión a BD -->
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- ===== BANNER DE ESTADÍSTICAS RÁPIDAS ===== -->
<!-- Muestra resumen ejecutivo del estado del sistema -->
<div class="stats-banner">
    📊 Estado del Sistema - 
    Dispositivos: <strong><?php echo $total_dispositivos; ?></strong> | 
    Activos Ahora: <strong style="color: var(--success-color);"><?php echo $dispositivos_activos; ?></strong> | 
    Usuarios: <strong><?php echo $total_usuarios; ?></strong> | 
    Registros Hoy: <strong><?php echo number_format($registros_hoy); ?></strong> |
    Esta Semana: <strong><?php echo number_format($registros_semana); ?></strong>
</div>

<!-- ===== TARJETAS DE FUNCIONALIDADES PRINCIPALES ===== -->
<!-- Grid de tarjetas navegables con información detallada de cada módulo -->
<div class="menu-grid">
    
    <!-- === TARJETA: GESTIÓN DE USUARIOS === -->
    <div class="menu-card" onclick="location.href='?seccion=usuarios'">
        <h3>👥 Gestionar Usuarios</h3>
        <p>Administre los usuarios del sistema: crear, editar, eliminar y gestionar roles de acceso.</p>
        <div class="info-box">
            <strong>Funcionalidades:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Crear nuevos usuarios</li>
                <li>Editar información existente</li>
                <li>Eliminar usuarios</li>
                <li>Buscar por nombre o rol</li>
                <li>Gestión de roles (admin/operador)</li>
            </ul>
        </div>
        <?php if ($rol !== 'admin'): ?>
        <!-- Restricción de acceso para usuarios no administradores -->
        <div class="alert alert-error" style="margin-top: 10px;">
            <small>⚠️ Requiere permisos de administrador</small>
        </div>
        <?php endif; ?>
    </div>
      
    <!-- === TARJETA: GESTIÓN DE DISPOSITIVOS === -->
    <div class="menu-card" onclick="location.href='?seccion=dispositivos'">
        <h3>📡 Gestionar Dispositivos</h3>
        <p>Visualice y administre todos los sensores ambientales conectados al sistema en tiempo real.</p>
        <div class="info-box">
            <strong>Funcionalidades Avanzadas:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Lista completa con estado en vivo</li>
                <li>Monitorización individual detallada</li>
                <li>Estadísticas por dispositivo (24h)</li>
                <li>Edición de ubicación y configuración</li>
                <li>Detección automática de inactividad</li>
                <li>Alertas visuales por umbrales configurados</li>
                <li>Información de red (IP/MAC)</li>
            </ul>
        </div>
        <!-- Indicador de estado de dispositivos con umbrales actuales -->
        <div class="stats-banner" style="margin-top: 10px;">
            <?php echo $dispositivos_activos; ?> de <?php echo $total_dispositivos; ?> dispositivos activos ahora
            <br><small>Umbrales: T:<?php echo $config['referencias']['temperatura_max']; ?>°C | H:<?php echo $config['referencias']['humedad_max']; ?>% | CO2:<?php echo $config['referencias']['co2_max']; ?>ppm</small>
        </div>
    </div>
    
    <!-- === TARJETA: MONITOR PÚBLICO === -->
    <div class="menu-card" onclick="goToPublicMonitor()">
        <h3>🖥️ Monitor Público</h3>
        <p>Pantalla de visualización pública para "<?php echo htmlspecialchars($config['publico']['titulo']); ?>" optimizada para monitores grandes.</p>
        <div class="info-box">
            <strong>Configuración Actual:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Título: <?php echo htmlspecialchars($config['publico']['titulo']); ?></li>
                <li>Subtítulo: <?php echo htmlspecialchars($config['publico']['subtitulo']); ?></li>
                <li>Actualización cada <?php echo $config['publico']['refresh_interval']; ?> segundos</li>
                <li>Navegación por teclado completa</li>
                <li>Compatible con lectores de pantalla</li>
                <li>Diseño responsive 100% accesible</li>
                <li>No requiere autenticación</li>
            </ul>
        </div>
        <!-- Indicador de acceso público -->
        <div class="alert alert-success" style="margin-top: 10px;">
            <small>✅ Configurado para <?php echo htmlspecialchars($config['publico']['titulo']); ?></small>
        </div>
    </div>
      
    <!-- === TARJETA: INFORMES Y ESTADÍSTICAS === -->
    <div class="menu-card" onclick="location.href='?seccion=informes'">
        <h3>📈 Informes y Estadísticas</h3>
        <p>Genere reportes detallados basados en los umbrales configurados del sistema de monitoreo ambiental.</p>
        <div class="info-box">
            <strong>Análisis Configurado:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Alertas por Temperatura > <?php echo $config['referencias']['temperatura_max']; ?>°C</li>
                <li>Alertas por Humedad > <?php echo $config['referencias']['humedad_max']; ?>%</li>
                <li>Alertas por Ruido > <?php echo $config['referencias']['ruido_max']; ?>dB</li>
                <li>Alertas por CO2 > <?php echo $config['referencias']['co2_max']; ?>ppm</li>
                <li>Alertas por Iluminación < <?php echo $config['referencias']['lux_min']; ?>lux</li>
                <li>Exportación CSV funcional</li>
                <li>Gráficos de tendencias interactivos</li>
            </ul>
        </div>
        <!-- Contador de registros procesados -->
        <div class="stats-banner" style="margin-top: 10px;">
            <?php echo number_format($registros_hoy); ?> registros procesados hoy
        </div>
    </div>
    
    <?php if ($rol === 'admin'): ?>
    <!-- === TARJETA: CONFIGURACIÓN DEL SISTEMA === -->
    <div class="menu-card" onclick="location.href='?seccion=configuracion'">
        <h3>⚙️ Configuración del Sistema</h3>
        <p>Configure <?php echo htmlspecialchars($sistema_nombre); ?> v<?php echo htmlspecialchars($sistema_version); ?> - parámetros, integraciones y umbrales de alerta.</p>
        <div class="info-box">
            <strong>Configuración Actual:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Sistema: <?php echo htmlspecialchars($sistema_nombre); ?></li>
                <li>Versión: <?php echo htmlspecialchars($sistema_version); ?></li>
                <li>Zona Horaria: <?php echo $config['sistema']['timezone']; ?></li>
                <li>Nivel de Log: <?php echo ucfirst($config['sistema']['log_level']); ?></li>
                <li>Monitor público personalizable</li>
                <li>Backups automáticos de configuración</li>
                <li>Gestión de logos del sistema</li>
            </ul>
        </div>
        <!-- Indicador de estado de configuración -->
        <div class="alert alert-info" style="margin-top: 10px;">
            <small>🔧 Última actualización: <?php 
                $config_file = 'config.ini';
                if (file_exists($config_file)) {
                    echo date('d/m/Y H:i:s', filemtime($config_file));
                } else {
                    echo 'Archivo no encontrado';
                }
            ?></small>
        </div>
    </div>
    
    <!-- === TARJETA: SISTEMA DE BACKUPS === -->
    <div class="menu-card" onclick="location.href='?seccion=backups'">
        <h3>💾 Sistema de Backups</h3>
        <p>Gestión completa de copias de seguridad para <?php echo htmlspecialchars($config['database']['database']); ?> y configuraciones.</p>
        <div class="info-box">
            <strong>Tipos de Backup Disponibles:</strong>
            <ul style="text-align: left; margin-top: 10px;">
                <li>Backup completo del proyecto <?php echo htmlspecialchars($sistema_nombre); ?></li>
                <li>Backup de base de datos <?php echo htmlspecialchars($config['database']['database']); ?></li>
                <li>Backup de configuración (config.ini)</li>
                <li>Backup combinado (proyecto + BD)</li>
                <li>Descarga directa de archivos ZIP</li>
                <li>Gestión y restauración de archivos</li>
                <li>Logs detallados de operaciones</li>
            </ul>
        </div>
        <!-- Indicador de backups disponibles -->
        <div class="stats-banner" style="margin-top: 10px;">
            Base de datos: <?php echo htmlspecialchars($config['database']['host']); ?>:<?php echo $config['database']['port']; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== INFORMACIÓN DEL SISTEMA Y USUARIO ACTUAL ===== -->
<!-- Panel informativo con datos de sesión y configuración -->
<div class="system-info">
    <h3>Información del Sistema</h3>
    <div class="system-info-content">
        <!-- Usuario autenticado actualmente -->
        <div class="system-info-item">
            <span class="system-info-label">Usuario Actual:</span>
            <span><?php echo htmlspecialchars($usuario); ?></span>
        </div>
        <!-- Rol del usuario (admin/operador) -->
        <div class="system-info-item">
            <span class="system-info-label">Rol:</span>
            <span><?php echo htmlspecialchars(ucfirst($rol)); ?></span>
        </div>
        <!-- Timestamp de la sesión actual -->
        <div class="system-info-item">
            <span class="system-info-label">Última Conexión:</span>
            <span><?php echo date('d/m/Y H:i:s'); ?></span>
        </div>
        <!-- Base de datos suite_ambiental en uso -->
        <div class="system-info-item">
            <span class="system-info-label">Base de Datos:</span>
            <span><?php echo htmlspecialchars($config['database']['database']); ?></span>
        </div>        <!-- Información de versión del sistema -->
        <div class="system-info-item">
            <span class="system-info-label">Versión:</span>
            <span><?php echo htmlspecialchars($sistema_nombre); ?> v<?php echo htmlspecialchars($sistema_version); ?> - Edición Completa</span>
        </div>
        <!-- Estado de las funcionalidades -->
        <div class="system-info-item">
            <span class="system-info-label">Estado:</span>
            <span style="color: var(--success-color); font-weight: 600;">✅ Totalmente Operativo</span>
        </div>
        <!-- Funcionalidades implementadas -->
        <div class="system-info-item">
            <span class="system-info-label">Módulos Activos:</span>
            <span>Monitoreo • Informes • Backups • Configuración</span>
        </div>
    </div>
</div>

<!-- ===== SISTEMA DE ALERTAS RECIENTES ===== -->
<!-- Monitoreo de valores fuera de rango en las últimas 2 horas -->
<?php
try {
    // === Consulta de registros con valores de alerta ===
    // Busca registros que excedan los límites configurados en config.ini
    $alertas_query = "
        SELECT r.*, d.nombre as dispositivo_nombre 
        FROM registros r 
        INNER JOIN dispositivos d ON r.sensor_id = d.nombre 
        WHERE r.fecha_hora >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        AND (
            r.temperatura > {$config['referencias']['temperatura_max']} OR 
            r.humedad > {$config['referencias']['humedad_max']} OR 
            r.ruido > {$config['referencias']['ruido_max']} OR 
            r.co2 > {$config['referencias']['co2_max']} OR 
            r.lux < {$config['referencias']['lux_min']}        )
        ORDER BY r.fecha_hora DESC 
        LIMIT 5
    ";
    $alertas = $db_manager->query($alertas_query, []);
    
    // === Renderizado de alertas si existen ===
    if (count($alertas) > 0):
?>
<div class="alert alert-error" style="margin-top: 24px;">
    <h3>⚠️ Alertas Recientes (Últimas 2 horas)</h3>
    <div style="margin-top: 12px;">
        <?php foreach ($alertas as $alerta): ?>
            <div style="border-left: 3px solid var(--error-color); padding-left: 12px; margin-bottom: 8px;">
                <!-- Información del dispositivo y timestamp -->
                <strong><?php echo htmlspecialchars($alerta['dispositivo_nombre']); ?></strong> - 
                <?php echo date('d/m/Y H:i', strtotime($alerta['fecha_hora'])); ?>
                <br>
                <small>
                    <!-- Indicadores específicos por tipo de sensor fuera de rango -->
                    <?php if ($alerta['temperatura'] > $config['referencias']['temperatura_max']): ?>
                        🌡️ Temperatura: <?php echo $alerta['temperatura']; ?>°C 
                    <?php endif; ?>
                    <?php if ($alerta['humedad'] > $config['referencias']['humedad_max']): ?>
                        💧 Humedad: <?php echo $alerta['humedad']; ?>% 
                    <?php endif; ?>
                    <?php if ($alerta['ruido'] > $config['referencias']['ruido_max']): ?>
                        🔊 Ruido: <?php echo $alerta['ruido']; ?>dB 
                    <?php endif; ?>
                    <?php if ($alerta['co2'] > $config['referencias']['co2_max']): ?>
                        ☁️ CO2: <?php echo $alerta['co2']; ?>ppm 
                    <?php endif; ?>
                    <?php if ($alerta['lux'] < $config['referencias']['lux_min']): ?>
                        💡 Luz: <?php echo $alerta['lux']; ?>lux 
                    <?php endif; ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php    endif;
} catch (Exception $e) {
    // === Manejo silencioso de errores en alertas ===
    // No interrumpe la carga del dashboard si falla el sistema de alertas
}
?>

<!-- ===== SECCIÓN DE ACTUALIZACIONES RECIENTES ===== -->
<div class="system-updates" style="margin-top: 24px;">
    <h3>🆕 Estado Actual del Sistema - <?php echo htmlspecialchars($sistema_nombre); ?> v<?php echo htmlspecialchars($sistema_version); ?></h3>
    <div class="updates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
        
        <!-- Estado: Configuración del Sistema -->
        <div class="update-card" style="background: var(--card-background); padding: 20px; border-radius: 10px; border-left: 4px solid var(--primary-color);">
            <h4 style="margin: 0 0 10px 0; color: var(--primary-color);">⚙️ Configuración del Sistema</h4>
            <p style="margin: 0; font-size: 0.9em; color: var(--text-light);">
                <strong><?php echo htmlspecialchars($sistema_nombre); ?></strong><br>
                Versión <?php echo htmlspecialchars($sistema_version); ?> | Zona horaria <?php echo $config['sistema']['timezone']; ?><br>
                Nivel de registro: <?php echo ucfirst($config['sistema']['log_level']); ?>
            </p>
            <small style="color: var(--text-light); font-style: italic;">✅ Configurado y operativo</small>
        </div>
        
        <!-- Estado: Umbrales Ambientales -->
        <div class="update-card" style="background: var(--card-background); padding: 20px; border-radius: 10px; border-left: 4px solid var(--warning-color);">
            <h4 style="margin: 0 0 10px 0; color: var(--warning-color);">🌡️ Umbrales de Alerta Configurados</h4>
            <p style="margin: 0; font-size: 0.9em; color: var(--text-light);">
                Temperatura máx: <?php echo $config['referencias']['temperatura_max']; ?>°C<br>
                Humedad máx: <?php echo $config['referencias']['humedad_max']; ?>%<br>
                Ruido máx: <?php echo $config['referencias']['ruido_max']; ?>dB<br>
                CO2 máx: <?php echo $config['referencias']['co2_max']; ?>ppm<br>
                Iluminación mín: <?php echo $config['referencias']['lux_min']; ?>lux
            </p>
            <small style="color: var(--text-light); font-style: italic;">✅ Umbrales personalizados activos</small>
        </div>
        
        <!-- Estado: Monitor Público -->
        <div class="update-card" style="background: var(--card-background); padding: 20px; border-radius: 10px; border-left: 4px solid var(--success-color);">
            <h4 style="margin: 0 0 10px 0; color: var(--success-color);">🖥️ Monitor Público Personalizado</h4>
            <p style="margin: 0; font-size: 0.9em; color: var(--text-light);">
                <strong><?php echo htmlspecialchars($config['publico']['titulo']); ?></strong><br>
                <?php echo htmlspecialchars($config['publico']['subtitulo']); ?><br>
                Actualización cada <?php echo $config['publico']['refresh_interval']; ?> segundos<br>
                Colores personalizados configurados
            </p>
            <small style="color: var(--text-light); font-style: italic;">✅ Personalizado para la organización</small>
        </div>
        
        <?php if ($rol === 'admin'): ?>
        <!-- Estado: Base de Datos -->
        <div class="update-card" style="background: var(--card-background); padding: 20px; border-radius: 10px; border-left: 4px solid var(--info-color);">
            <h4 style="margin: 0 0 10px 0; color: var(--info-color);">🗄️ Base de Datos Conectada</h4>
            <p style="margin: 0; font-size: 0.9em; color: var(--text-light);">
                Servidor: <?php echo htmlspecialchars($config['database']['host']); ?>:<?php echo $config['database']['port']; ?><br>
                Base de datos: <?php echo htmlspecialchars($config['database']['database']); ?><br>
                Charset: <?php echo $config['database']['charset']; ?><br>
                Timezone BD: <?php echo $config['database']['timezone']; ?>
            </p>
            <small style="color: var(--text-light); font-style: italic;">✅ Conexión estable y operativa</small>
        </div>
        <?php endif; ?>
        
    </div>
</div>
