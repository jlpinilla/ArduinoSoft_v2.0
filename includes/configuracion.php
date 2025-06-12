<?php
/**
 * =====================================================
 * MÓDULO DE CONFIGURACIÓN DEL SISTEMA - SUITE AMBIENTAL
 * =====================================================
 * Sistema completo de edición de configuración del sistema
 * Permite modificar parámetros ambientales, configuración general
 * y opciones del monitor público desde el panel de administración.
 * 
 * Funcionalidades principales:
 * - Edición de umbrales ambientales (temperatura, humedad, ruido, CO2, lux)
 * - Configuración general del sistema (nombre, versión, timezone)
 * - Configuración del monitor público (refresh, modo pantalla)
 * - Validación de parámetros y respaldo automático
 * - Interfaz intuitiva con validación en tiempo real
 */

// ===== CONTROL DE SEGURIDAD Y SESIÓN =====
// Verificar que el usuario esté autenticado y sea administrador
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ===== VARIABLES DE CONTROL =====
$action = $_POST['action'] ?? $_GET['action'] ?? 'edit';
$message = '';
$error = '';

// ===== VERIFICAR DIRECTORIO DE BACKUPS =====
$backup_dir = 'configbackup';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        $error = 'No se pudo crear el directorio de backups. Verifique los permisos.';
    }
}

// ===== CARGA DE CONFIGURACIÓN ACTUAL =====
$config_actual = [];
$config_exists = file_exists('config.ini');

if ($config_exists) {
    $config_actual = parse_ini_file('config.ini', true);
} else {
    $error = 'No se encontró el archivo de configuración config.ini';
}

// ===== PROCESAMIENTO DE FORMULARIO =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    try {        // === Validar datos recibidos ===
        $nueva_config = [
            'database' => $config_actual['database'] ?? [], // Preservar config BD
            'sistema' => [
                'nombre' => trim($_POST['sistema_nombre'] ?? 'Suite Ambiental'),
                'version' => trim($_POST['sistema_version'] ?? '2.0'),
                'timezone' => trim($_POST['sistema_timezone'] ?? '+02:00'),
                'log_level' => trim($_POST['sistema_log_level'] ?? 'info'),
                'logo' => $config_actual['sistema']['logo'] ?? 'media/logo.png' // Preservar logo actual
            ],            'referencias' => [
                'temperatura_max' => floatval($_POST['temperatura_max'] ?? 25),
                'humedad_max' => floatval($_POST['humedad_max'] ?? 48),
                'ruido_max' => floatval($_POST['ruido_max'] ?? 35),
                'co2_max' => intval($_POST['co2_max'] ?? 1000),
                'lux_min' => intval($_POST['lux_min'] ?? 195)
            ],            'publico' => [
                'titulo' => trim($_POST['publico_titulo'] ?? 'Monitor Ambiental'),
                'subtitulo' => trim($_POST['publico_subtitulo'] ?? 'Sistema de Sensores Arduino'),
                'color_fondo' => trim($_POST['publico_color_fondo'] ?? '#667eea'),
                'color_secundario' => trim($_POST['publico_color_secundario'] ?? '#764ba2'),
                'color_texto' => trim($_POST['publico_color_texto'] ?? '#ffffff'),
                'refresh_interval' => intval($_POST['publico_refresh_interval'] ?? 60)
            ]
        ];
        
        // === Validaciones de seguridad ===
        $errores_validacion = [];
        
        // Validar umbrales ambientales
        if ($nueva_config['referencias']['temperatura_max'] < 15 || $nueva_config['referencias']['temperatura_max'] > 50) {
            $errores_validacion[] = 'Temperatura máxima debe estar entre 15°C y 50°C';
        }
        if ($nueva_config['referencias']['humedad_max'] < 30 || $nueva_config['referencias']['humedad_max'] > 90) {
            $errores_validacion[] = 'Humedad máxima debe estar entre 30% y 90%';
        }
        if ($nueva_config['referencias']['ruido_max'] < 20 || $nueva_config['referencias']['ruido_max'] > 100) {
            $errores_validacion[] = 'Ruido máximo debe estar entre 20dB y 100dB';
        }
        if ($nueva_config['referencias']['co2_max'] < 300 || $nueva_config['referencias']['co2_max'] > 5000) {
            $errores_validacion[] = 'CO2 máximo debe estar entre 300ppm y 5000ppm';
        }        if ($nueva_config['referencias']['lux_min'] < 50 || $nueva_config['referencias']['lux_min'] > 1000) {
            $errores_validacion[] = 'Iluminación mínima debe estar entre 50lux y 1000lux';
        }
          // Validar configuración pública
        if (strlen($nueva_config['publico']['titulo']) < 3 || strlen($nueva_config['publico']['titulo']) > 50) {
            $errores_validacion[] = 'El título debe tener entre 3 y 50 caracteres';
        }
        if (strlen($nueva_config['publico']['subtitulo']) < 3 || strlen($nueva_config['publico']['subtitulo']) > 80) {
            $errores_validacion[] = 'El subtítulo debe tener entre 3 y 80 caracteres';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $nueva_config['publico']['color_fondo'])) {
            $errores_validacion[] = 'El color de fondo debe ser un código hexadecimal válido (ej: #667eea)';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $nueva_config['publico']['color_secundario'])) {
            $errores_validacion[] = 'El color secundario debe ser un código hexadecimal válido (ej: #764ba2)';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $nueva_config['publico']['color_texto'])) {
            $errores_validacion[] = 'El color del texto debe ser un código hexadecimal válido (ej: #ffffff)';
        }
        if ($nueva_config['publico']['refresh_interval'] < 60 || $nueva_config['publico']['refresh_interval'] > 3600) {
            $errores_validacion[] = 'El tiempo de actualización debe estar entre 60 segundos y 60 minutos (3600 segundos)';
        }
        
        // === Procesamiento del archivo de logo ===
        if (isset($_FILES['sistema_logo']) && $_FILES['sistema_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_file = $_FILES['sistema_logo'];
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
            
            // Validar tipo de archivo
            if (in_array($upload_file['type'], $allowed_types)) {
                // Validar tamaño (máximo 2MB)
                if ($upload_file['size'] <= 2 * 1024 * 1024) {
                    // Crear directorio media si no existe
                    if (!is_dir('media')) {
                        mkdir('media', 0755, true);
                    }
                    
                    // Generar nombre único para el archivo
                    $extension = pathinfo($upload_file['name'], PATHINFO_EXTENSION);
                    $logo_filename = 'logo_' . time() . '.' . $extension;
                    $logo_path = 'media/' . $logo_filename;
                    
                    // Mover archivo subido
                    if (move_uploaded_file($upload_file['tmp_name'], $logo_path)) {
                        // Eliminar logo anterior si no es el por defecto
                        $logo_anterior = $config_actual['sistema']['logo'] ?? '';
                        if (!empty($logo_anterior) && $logo_anterior !== 'media/logo.png' && file_exists($logo_anterior)) {
                            unlink($logo_anterior);
                        }
                        
                        // Actualizar configuración con nuevo logo
                        $nueva_config['sistema']['logo'] = $logo_path;
                    } else {
                        $errores_validacion[] = 'Error al subir el archivo de logo';
                    }
                } else {
                    $errores_validacion[] = 'El archivo de logo debe ser menor a 2MB';
                }
            } else {
                $errores_validacion[] = 'El logo debe ser una imagen válida (PNG, JPG, GIF)';
            }
        }
        
        if (!empty($errores_validacion)) {
            $error = 'Errores de validación: ' . implode(', ', $errores_validacion);} else {
            // === Crear respaldo de la configuración actual ===
            $backup_filename = 'configbackup/config_back_' . date('y_m_d_H_i') . '.ini';
            if ($config_exists) {
                copy('config.ini', $backup_filename);
            }
            
            // === Generar nuevo archivo config.ini ===
            $config_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
            $config_content .= "; Actualizado el " . date('Y-m-d H:i:s') . " por " . $_SESSION['usuario'] . "\n\n";
            
            // Sección de Base de Datos (preservar)
            if (isset($nueva_config['database'])) {
                $config_content .= "[database]\n";
                foreach ($nueva_config['database'] as $key => $value) {
                    $config_content .= "{$key} = \"{$value}\"\n";
                }
                $config_content .= "\n";
            }
            
            // Sección de Sistema
            $config_content .= "[sistema]\n";
            foreach ($nueva_config['sistema'] as $key => $value) {
                $config_content .= "{$key} = \"{$value}\"\n";
            }
            $config_content .= "\n";
              // Sección de Referencias Ambientales
            $config_content .= "[referencias]\n";
            $config_content .= "temperatura_max = {$nueva_config['referencias']['temperatura_max']}\n";
            $config_content .= "humedad_max = {$nueva_config['referencias']['humedad_max']}\n";
            $config_content .= "ruido_max = {$nueva_config['referencias']['ruido_max']}\n";
            $config_content .= "co2_max = {$nueva_config['referencias']['co2_max']}\n";
            $config_content .= "lux_min = {$nueva_config['referencias']['lux_min']}\n\n";
              // Sección de Configuración Pública
            $config_content .= "[publico]\n";
            $config_content .= "; Configuración específica para el monitor público\n";
            $config_content .= "titulo = \"{$nueva_config['publico']['titulo']}\"\n";
            $config_content .= "subtitulo = \"{$nueva_config['publico']['subtitulo']}\"\n";
            $config_content .= "color_fondo = \"{$nueva_config['publico']['color_fondo']}\"\n";
            $config_content .= "color_secundario = \"{$nueva_config['publico']['color_secundario']}\"\n";
            $config_content .= "color_texto = \"{$nueva_config['publico']['color_texto']}\"\n";
            $config_content .= "refresh_interval = {$nueva_config['publico']['refresh_interval']}\n\n";// Sección de Azure/Teams
            if (isset($nueva_config['azure'])) {
                $config_content .= "[azure]\n";
                $config_content .= "; Configuración para alertas en Microsoft Teams\n";
                foreach ($nueva_config['azure'] as $key => $value) {
                    $config_content .= "{$key} = \"{$value}\"\n";
                }
                $config_content .= "\n";
            }
              // === Guardar nueva configuración ===
            if (file_put_contents('config.ini', $config_content)) {
                $message = 'Configuración guardada exitosamente. Respaldo creado: ' . $backup_filename;
                  // Registrar cambio en log
                $log_entry = "\n### " . date('Y-m-d H:i:s') . " - Configuración Actualizada\n";
                $log_entry .= "**Usuario:** " . $_SESSION['usuario'] . "\n";
                $log_entry .= "**Cambios realizados:**\n";
                $log_entry .= "- Umbrales ambientales modificados\n";
                $log_entry .= "- Configuración del sistema actualizada\n";
                if (isset($_FILES['sistema_logo']) && $_FILES['sistema_logo']['error'] === UPLOAD_ERR_OK) {
                    $log_entry .= "- Logo del sistema actualizado\n";
                }                $log_entry .= "- Respaldo generado: {$backup_filename}\n";
                $log_entry .= "---\n";
                
                file_put_contents('logs/cambios.log', $log_entry, FILE_APPEND);
                
                // Recargar configuración
                $config_actual = parse_ini_file('config.ini', true);
                
                // Si se actualizó el logo, marcar para refresh automático
                if (isset($_FILES['sistema_logo']) && $_FILES['sistema_logo']['error'] === UPLOAD_ERR_OK) {
                    $message .= ' El logo se ha actualizado correctamente. La página se refrescará automáticamente para mostrar los cambios.';
                    echo '<script>
                        setTimeout(function() {
                            // Forzar recarga de favicon y logo
                            const favicon = document.querySelector("link[rel*=\'icon\']") || document.createElement("link");
                            favicon.type = "image/png";
                            favicon.rel = "icon";
                            favicon.href = "' . htmlspecialchars($nueva_config['sistema']['logo']) . '?v=' . time() . '";
                            document.getElementsByTagName("head")[0].appendChild(favicon);
                            
                            // Recargar página después de 2 segundos
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }, 1000);
                    </script>';
                }
            } else {
                $error = 'Error al guardar la configuración. Verifique los permisos del archivo.';
            }
        }
        
    } catch (Exception $e) {
        $error = 'Error al procesar la configuración: ' . $e->getMessage();
    }
}

// ===== OBTENER VALORES ACTUALES PARA EL FORMULARIO =====
$valores = [
    'sistema_nombre' => $config_actual['sistema']['nombre'] ?? 'Suite Ambiental',
    'sistema_version' => $config_actual['sistema']['version'] ?? '2.0',
    'sistema_log_level' => $config_actual['sistema']['log_level'] ?? 'info',
    'sistema_timezone' => $config_actual['sistema']['timezone'] ?? '+02:00',
    'sistema_logo' => $config_actual['sistema']['logo'] ?? 'media/logo.png',
    'temperatura_max' => $config_actual['referencias']['temperatura_max'] ?? 25,
    'humedad_max' => $config_actual['referencias']['humedad_max'] ?? 48,
    'ruido_max' => $config_actual['referencias']['ruido_max'] ?? 35,
    'co2_max' => $config_actual['referencias']['co2_max'] ?? 1000,
    'lux_min' => $config_actual['referencias']['lux_min'] ?? 195,
    'publico_titulo' => $config_actual['publico']['titulo'] ?? 'Monitor Ambiental',
    'publico_subtitulo' => $config_actual['publico']['subtitulo'] ?? 'Sistema de Sensores Arduino',
    'publico_color_fondo' => $config_actual['publico']['color_fondo'] ?? '#667eea',
    'publico_color_secundario' => $config_actual['publico']['color_secundario'] ?? '#764ba2',
    'publico_color_texto' => $config_actual['publico']['color_texto'] ?? '#ffffff',
    'publico_refresh_interval' => $config_actual['publico']['refresh_interval'] ?? 60,
    'azure_enabled' => isset($config_actual['azure']['enabled']) ? ($config_actual['azure']['enabled'] === 'true') : false,
    'azure_auth_type' => $config_actual['azure']['auth_type'] ?? 'client_credentials',
    'azure_tenant_id' => $config_actual['azure']['tenant_id'] ?? '',
    'azure_client_id' => $config_actual['azure']['client_id'] ?? '',
    'azure_client_secret' => $config_actual['azure']['client_secret'] ?? '',
    'azure_username' => $config_actual['azure']['username'] ?? '',
    'azure_password' => $config_actual['azure']['password'] ?? '',
    'azure_secret_id' => $config_actual['azure']['secret_id'] ?? '',
    'azure_teams_chat_id' => $config_actual['azure']['teams_chat_id'] ?? '',
    'azure_alert_template' => $config_actual['azure']['alert_template'] ?? 'El sensor {nombre} ubicado en {ubicacion} ha generado una alerta: {parametro} = {valor} (límite: {limite})'
];
?>

<!-- ===== INTERFAZ DE CONFIGURACIÓN DEL SISTEMA ===== -->
<div class="config-container">
    
    <!-- ===== ENCABEZADO DE LA PÁGINA ===== -->
    <h2 class="page-title">⚙️ Configuración del Sistema</h2>
    <p class="page-subtitle">Modifique los parámetros de funcionamiento del sistema de monitoreo ambiental</p>
    
    <!-- ===== MENSAJES DE ESTADO ===== -->
    <?php if ($message): ?>
        <div class="alert alert-success" role="alert">
            <strong>✅ Éxito:</strong> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== SECCIÓN: CONFIGURACIÓN GENERAL DEL SISTEMA ===== -->
    <div class="form-section">
        <h3>🏢 Configuración General</h3>
        <p>Parámetros básicos del sistema</p>
        
        <div class="form-row">
            <div class="form-group">
                <label for="sistema_nombre" class="form-label">Nombre del Sistema</label>
                <input 
                    type="text" 
                    id="sistema_nombre" 
                    name="sistema_nombre" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($valores['sistema_nombre']); ?>"
                    placeholder="Suite Ambiental"
                    maxlength="50"
                    required
                >
                <small class="form-help">Nombre que identifica el sistema (máximo 50 caracteres)</small>
            </div>
            
            <div class="form-group">
                <label for="sistema_version" class="form-label">Versión del Sistema</label>
                <input 
                    type="text" 
                    id="sistema_version" 
                    name="sistema_version" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($valores['sistema_version']); ?>"
                    placeholder="2.0"
                    maxlength="10"
                    required
                >
                <small class="form-help">Versión actual del sistema (ej: 2.6, 3.0)</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="sistema_log_level" class="form-label">Nivel de Log</label>
                <select id="sistema_log_level" name="sistema_log_level" class="form-control">
                    <option value="error" <?php echo $valores['sistema_log_level'] === 'error' ? 'selected' : ''; ?>>Error - Solo errores críticos</option>
                    <option value="warning" <?php echo $valores['sistema_log_level'] === 'warning' ? 'selected' : ''; ?>>Warning - Errores y advertencias</option>
                    <option value="info" <?php echo $valores['sistema_log_level'] === 'info' ? 'selected' : ''; ?>>Info - Información general</option>
                    <option value="debug" <?php echo $valores['sistema_log_level'] === 'debug' ? 'selected' : ''; ?>>Debug - Información detallada</option>
                </select>
                <small class="form-help">Nivel de detalle para los registros del sistema</small>
            </div>
            
            <div class="form-group">
                <label for="sistema_timezone" class="form-label">Zona Horaria</label>
                <select id="sistema_timezone" name="sistema_timezone" class="form-control">
                    <option value="+02:00" <?php echo $valores['sistema_timezone'] === '+02:00' ? 'selected' : ''; ?>>Europa/Madrid (+02:00) - Hora de Verano</option>
                    <option value="+01:00" <?php echo $valores['sistema_timezone'] === '+01:00' ? 'selected' : ''; ?>>Europa/París (+01:00) - Hora de Invierno</option>
                    <option value="+00:00" <?php echo $valores['sistema_timezone'] === '+00:00' ? 'selected' : ''; ?>>UTC (+00:00)</option>
                    <option value="-05:00" <?php echo $valores['sistema_timezone'] === '-05:00' ? 'selected' : ''; ?>>América/Nueva_York (-05:00)</option>
                </select>
                <small class="form-help">Zona horaria para fechas y registros</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">⚙️ Aplicar Cambios</label>
                <div style="padding-top: 5px;">
                    <button type="button" onclick="applySystemConfig()" class="btn btn-primary">
                        ✅ Aplicar Configuración General
                    </button>
                </div>
                <small class="form-help">Aplica los cambios de configuración general del sistema</small>
            </div>
        </div>
    </div>
    
    <!-- ===== SECCIÓN: PERSONALIZACIÓN DEL LOGO ===== -->
    <div class="form-section">
        <h3>🎨 Personalización del Logo</h3>
        <p>Configure el logo que aparece en la página de inicio</p>
        
        <div class="form-row">
            <div class="form-group">
                <label for="sistema_logo" class="form-label">Archivo de Logo</label>
                <div class="file-input-container">
                    <input 
                        type="file" 
                        id="sistema_logo" 
                        name="sistema_logo" 
                        class="form-control file-input"
                        accept="image/*"
                        onchange="previewLogo(this)"
                    >
                    <small class="form-help">Formatos soportados: PNG, JPG, GIF. Tamaño recomendado: 80x80 píxeles</small>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Vista Previa Actual</label>
                <div class="logo-preview-container">
                    <?php 
                    $current_logo = $valores['sistema_logo'] ?? 'media/logo.png';
                    if (file_exists($current_logo)) {
                    ?>
                        <img id="logo-preview" src="<?php echo htmlspecialchars($current_logo); ?>" 
                             alt="Logo actual" class="logo-preview">
                        <div class="logo-info">
                            <small id="logo-info-text">Logo actual: <?php echo basename($current_logo); ?></small><br>
                            <div class="logo-buttons-container">
                                <button type="button" onclick="resetLogo()" class="btn btn-sm btn-secondary logo-button">
                                    🔄 Restaurar Logo Original
                                </button>
                                <button type="button" onclick="applyLogoChange()" class="btn btn-sm btn-primary logo-button">
                                    ✅ Aplicar Cambio de Logo
                                </button>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="no-logo" id="no-logo-container">
                            <span>📷</span>
                            <p>No hay logo configurado</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECCIÓN: UMBRALES AMBIENTALES ===== -->
    <div class="form-section">
        <h3>🌡️ Umbrales de Alerta Ambiental</h3>
        <p>Configure los límites que determinarán cuándo un sensor está en estado de alerta</p>
        
        <div class="alert alert-info">
            <strong>ℹ️ Información:</strong> Los valores que superen estos umbrales aparecerán marcados en rojo en todo el sistema
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="temperatura_max" class="form-label">🌡️ Temperatura Máxima</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="temperatura_max" 
                        name="temperatura_max" 
                        class="form-control"
                        value="<?php echo $valores['temperatura_max']; ?>"
                        min="15" 
                        max="50" 
                        step="0.1"
                        required
                    >
                    <span class="input-suffix">°C</span>
                </div>
                <small class="form-help">Rango: 15°C - 50°C. Actual: <?php echo $valores['temperatura_max']; ?>°C</small>
            </div>
            
            <div class="form-group">
                <label for="humedad_max" class="form-label">💧 Humedad Máxima</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="humedad_max" 
                        name="humedad_max" 
                        class="form-control"
                        value="<?php echo $valores['humedad_max']; ?>"
                        min="30" 
                        max="90" 
                        step="0.1"
                        required
                    >
                    <span class="input-suffix">%</span>
                </div>
                <small class="form-help">Rango: 30% - 90%. Actual: <?php echo $valores['humedad_max']; ?>%</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="ruido_max" class="form-label">🔊 Ruido Máximo</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="ruido_max" 
                        name="ruido_max" 
                        class="form-control"
                        value="<?php echo $valores['ruido_max']; ?>"
                        min="20" 
                        max="100" 
                        step="0.1"
                        required
                    >
                    <span class="input-suffix">dB</span>
                </div>
                <small class="form-help">Rango: 20dB - 100dB. Actual: <?php echo $valores['ruido_max']; ?>dB</small>
            </div>
            
            <div class="form-group">
                <label for="co2_max" class="form-label">☁️ CO₂ Máximo</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="co2_max" 
                        name="co2_max" 
                        class="form-control"
                        value="<?php echo $valores['co2_max']; ?>"
                        min="300" 
                        max="5000" 
                        step="1"
                        required
                    >
                    <span class="input-suffix">ppm</span>
                </div>
                <small class="form-help">Rango: 300ppm - 5000ppm. Actual: <?php echo $valores['co2_max']; ?>ppm</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="lux_min" class="form-label">💡 Iluminación Mínima</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="lux_min" 
                        name="lux_min" 
                        class="form-control"
                        value="<?php echo $valores['lux_min']; ?>"
                        min="50" 
                        max="1000" 
                        step="1"
                        required
                    >
                    <span class="input-suffix">lux</span>
                </div>
                <small class="form-help">Rango: 50lux - 1000lux. Actual: <?php echo $valores['lux_min']; ?>lux</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">⚙️ Aplicar Cambios</label>
                <div style="padding-top: 5px;">
                    <button type="button" onclick="applyThresholds()" class="btn btn-primary">
                        ✅ Aplicar Umbrales Ambientales
                    </button>
                </div>
                <small class="form-help">Aplica los nuevos umbrales al sistema de monitoreo</small>
            </div>
        </div>
    </div>
    
    <!-- ===== SECCIÓN: CONFIGURACIÓN DEL MONITOR PÚBLICO ===== -->
    <div class="form-section">
        <h3>🖥️ Configuración del Monitor Público</h3>
        <p>Personalice la apariencia y comportamiento de la página pública de monitoreo</p>
        
        <div class="alert alert-info">
            <strong>ℹ️ Información:</strong> Esta configuración afecta únicamente a la página pública (public.php) visible sin autenticación
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="publico_titulo" class="form-label">📝 Título Principal</label>
                <input 
                    type="text" 
                    id="publico_titulo" 
                    name="publico_titulo" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($valores['publico_titulo']); ?>"
                    placeholder="Monitor Ambiental"
                    maxlength="50"
                >
                <small class="form-help">Título que aparece en la parte superior del monitor público</small>
            </div>
            
            <div class="form-group">
                <label for="publico_subtitulo" class="form-label">📄 Subtítulo</label>
                <input 
                    type="text" 
                    id="publico_subtitulo" 
                    name="publico_subtitulo" 
                    class="form-control"
                    value="<?php echo htmlspecialchars($valores['publico_subtitulo']); ?>"
                    placeholder="Sistema de Sensores Arduino"
                    maxlength="80"
                >
                <small class="form-help">Subtítulo descriptivo del sistema</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="publico_color_fondo" class="form-label">🎨 Color de Fondo</label>
                <div class="color-input-container">
                    <input 
                        type="color" 
                        id="publico_color_fondo" 
                        name="publico_color_fondo" 
                        class="form-control color-input"
                        value="<?php echo $valores['publico_color_fondo']; ?>"
                        title="Seleccionar color de fondo"
                    >
                    <input 
                        type="text" 
                        id="color_fondo_text"
                        class="form-control color-text"
                        value="<?php echo $valores['publico_color_fondo']; ?>"
                        readonly
                        style="margin-left: 10px; width: 100px;"
                    >
                </div>
                <small class="form-help">Color de fondo del degradado principal. Color actual: <?php echo $valores['publico_color_fondo']; ?></small>
            </div>
            
            <div class="form-group">
                <label for="publico_color_secundario" class="form-label">🌈 Color Secundario</label>
                <div class="color-input-container">
                    <input 
                        type="color" 
                        id="publico_color_secundario" 
                        name="publico_color_secundario" 
                        class="form-control color-input"
                        value="<?php echo $valores['publico_color_secundario']; ?>"
                        title="Seleccionar color secundario del degradado"
                    >
                    <input 
                        type="text" 
                        id="color_secundario_text"
                        class="form-control color-text"
                        value="<?php echo $valores['publico_color_secundario']; ?>"
                        readonly
                        style="margin-left: 10px; width: 100px;"
                    >
                </div>
                <small class="form-help">Color secundario del degradado. Color actual: <?php echo $valores['publico_color_secundario']; ?></small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="publico_color_texto" class="form-label">📝 Color del Texto</label>
                <div class="color-input-container">
                    <input 
                        type="color" 
                        id="publico_color_texto" 
                        name="publico_color_texto" 
                        class="form-control color-input"
                        value="<?php echo $valores['publico_color_texto']; ?>"
                        title="Seleccionar color del texto"
                    >
                    <input 
                        type="text" 
                        id="color_texto_text"
                        class="form-control color-text"
                        value="<?php echo $valores['publico_color_texto']; ?>"
                        readonly
                        style="margin-left: 10px; width: 100px;"
                    >
                </div>
                <small class="form-help">Color del texto en el monitor público. Color actual: <?php echo $valores['publico_color_texto']; ?></small>
            </div>
            
            <div class="form-group">
                <label for="publico_refresh_interval" class="form-label">⏱️ Tiempo de Actualización</label>
                <div class="input-group">
                    <input 
                        type="number" 
                        id="publico_refresh_interval" 
                        name="publico_refresh_interval" 
                        class="form-control"
                        value="<?php echo $valores['publico_refresh_interval']; ?>"
                        min="60" 
                        max="3600" 
                        step="30"
                        required
                    >
                    <span class="input-suffix">segundos</span>
                </div>
                <small class="form-help">Intervalo de auto-actualización (60 segundos - 60 minutos). Actual: <?php echo floor($valores['publico_refresh_interval'] / 60); ?> min <?php echo $valores['publico_refresh_interval'] % 60; ?>s</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">🎨 Vista Previa del Color</label>
                <div class="color-preview-container">
                    <div id="color-preview" class="color-preview" 
                         style="background: linear-gradient(135deg, <?php echo $valores['publico_color_fondo']; ?> 0%, <?php echo $valores['publico_color_secundario']; ?> 100%);">
                        <div class="preview-content">
                            <h3 id="preview-title" style="color: <?php echo $valores['publico_color_texto']; ?>; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                                <?php echo htmlspecialchars($valores['publico_titulo']); ?>
                            </h3>
                            <p id="preview-subtitle" style="color: <?php echo $valores['publico_color_texto']; ?>; opacity: 0.9;">
                                <?php echo htmlspecialchars($valores['publico_subtitulo']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <small class="form-help">Vista previa de cómo se verá el encabezado del monitor público</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">📊 Configuración Actual</label>
                <div class="config-summary">
                    <div class="summary-item">
                        <strong>Actualización:</strong> Cada <?php echo $valores['publico_refresh_interval']; ?> segundos
                    </div>
                    <div class="summary-item">
                        <strong>Color Principal:</strong> <span style="color: <?php echo $valores['publico_color_fondo']; ?>;">●</span> <?php echo $valores['publico_color_fondo']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>Color Secundario:</strong> <span style="color: <?php echo $valores['publico_color_secundario']; ?>;">●</span> <?php echo $valores['publico_color_secundario']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>Color Texto:</strong> <span style="color: <?php echo $valores['publico_color_texto']; ?>;">●</span> <?php echo $valores['publico_color_texto']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>Textos:</strong> Personalizados
                    </div>
                </div>
                <button type="button" onclick="applyPublicConfig()" class="btn btn-primary" style="margin-top: 15px;">
                    ✅ Aplicar Configuración del Monitor Público
                </button>
            </div>
        </div>
    </div>

    <!-- ===== INFORMACIÓN ADICIONAL ===== -->
    <div class="form-section">
        <h3>📋 Información del Sistema</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Configuración Actual:</span>
                <span class="info-value"><?php echo $config_exists ? 'config.ini encontrado' : 'No encontrado'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Última Modificación:</span>
                <span class="info-value"><?php echo $config_exists ? date('d/m/Y H:i:s', filemtime('config.ini')) : 'N/A'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Usuario Actual:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['usuario']); ?> (<?php echo htmlspecialchars($_SESSION['rol']); ?>)</span>
            </div>
            <div class="info-item">
                <span class="info-label">Base de Datos:</span>
                <span class="info-value"><?php echo htmlspecialchars($config_actual['database']['database'] ?? 'No configurada'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ===== FOOTER CON ENLACE A PHPMYADMIN ===== -->
<div class="config-footer">
    <div class="footer-content">
        <div class="footer-left">
            <small>Sistema de Monitoreo Ambiental - Configuración</small>
        </div>
        <div class="footer-right">
            <a href="http://localhost/phpmyadmin/" target="_blank" class="phpmyadmin-link" title="Abrir phpMyAdmin">
                🗄️ phpMyAdmin
            </a>
        </div>
    </div>
</div>

<!-- ===== SECCIÓN DE GESTIÓN DE BACKUPS ===== -->
<div class="config-panel">
    <h2>💾 Guardar Copia de Config.Ini</h2>
    
    <?php
    // Listar archivos de backup disponibles
    $backup_files = [];
    if (is_dir('configbackup')) {
        $files = scandir('configbackup');
        foreach ($files as $file) {
            if (preg_match('/^config_back_.+\.ini$/', $file)) {
                $backup_files[] = [
                    'filename' => $file,
                    'path' => 'configbackup/' . $file,
                    'date' => filemtime('configbackup/' . $file),
                    'size' => filesize('configbackup/' . $file)
                ];
            }
        }
        // Ordenar por fecha (más reciente primero)
        usort($backup_files, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
    ?>
    
    <div class="backup-info">
        <p><strong>Total de copias de seguridad:</strong> <?php echo count($backup_files); ?></p>
        <p><strong>Ubicación:</strong> <code>configbackup/</code></p>
    </div>
    
    <?php if (!empty($backup_files)): ?>
        <div class="backup-list">
            <h3>Archivos de Backup Disponibles</h3>
            <div class="table-responsive">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>📅 Fecha de Creación</th>
                            <th>📄 Nombre del Archivo</th>
                            <th>📊 Tamaño</th>
                            <th>⚙️ Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $backup): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', $backup['date']); ?></td>
                                <td><code><?php echo htmlspecialchars($backup['filename']); ?></code></td>
                                <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($backup['path']); ?>" 
                                       class="btn btn-sm btn-info" 
                                       download="<?php echo htmlspecialchars($backup['filename']); ?>"
                                       title="Descargar backup">
                                        💾 Descargar
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-warning" 
                                            onclick="previewBackup('<?php echo htmlspecialchars($backup['path']); ?>')"
                                            title="Ver contenido">
                                        👁️ Ver
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-success" 
                                            onclick="showRestoreModal('<?php echo htmlspecialchars($backup['filename']); ?>', '<?php echo htmlspecialchars($backup['path']); ?>')"
                                            title="Restaurar configuración">
                                        🔄 Restaurar Config.Ini
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger" 
                                            onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>', '<?php echo htmlspecialchars($backup['path']); ?>')"
                                            title="Eliminar backup">
                                        🗑️ Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <strong>ℹ️ Información:</strong> 
            No hay copias de seguridad disponibles. Los backups se crearán automáticamente cuando modifique la configuración.
        </div>
    <?php endif; ?>
    
    <div class="backup-actions">
        <button type="button" class="btn btn-primary" onclick="createManualBackup()">
            💾 Crear Backup Manual
        </button>
        <?php if (count($backup_files) > 0): ?>
            <button type="button" class="btn btn-warning" onclick="cleanOldBackups()">
                🧹 Limpiar Backups Antiguos
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL PARA VISTA PREVIA DE BACKUP ===== -->
<div id="backupPreviewModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>👁️ Vista Previa del Backup</h3>
            <span class="close" onclick="closeBackupPreview()">&times;</span>
        </div>
        <div class="modal-body">
            <pre id="backupContent" style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 400px;"></pre>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBackupPreview()">Cerrar</button>
        </div>
    </div>
</div>

<!-- ===== MODAL PARA RESTAURAR CONFIGURACIÓN ===== -->
<div id="restoreModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔄 Restaurar Configuración</h3>
            <span class="close" onclick="closeRestoreModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <strong>⚠️ Advertencia:</strong> Esta acción reemplazará la configuración actual con la del backup seleccionado.
            </div>
            
            <div class="restore-info">
                <h4>Archivo de Backup:</h4>
                <p><strong id="restore-filename"></strong></p>
                
                <h4>Configuración que se aplicará:</h4>
                <div id="restore-config-preview" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    <div class="loading">Cargando configuración...</div>
                </div>
            </div>
            
            <div class="confirm-checkbox" style="margin: 20px 0;">
                <label>
                    <input type="checkbox" id="confirm-restore"> 
                    Confirmo que deseo restaurar esta configuración y entiendo que la configuración actual será reemplazada
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="apply-restore-btn" onclick="applyRestore()" disabled>
                ✅ Aplicar Restauración
            </button>
        </div>
    </div>
</div>

<!-- ===== ESTILOS ESPECÍFICOS PARA CONFIGURACIÓN ===== -->
<style>
/* Estilos para la configuración pública */
.color-input-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.color-input {
    width: 60px !important;
    height: 40px !important;
    border: none !important;
    border-radius: 8px !important;
    cursor: pointer;
}

.color-text {
    font-family: monospace;
    text-transform: uppercase;
}

.color-preview-container {
    margin-top: 10px;
}

.color-preview {
    border-radius: 12px;
    padding: 20px;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
    border: 2px solid rgba(255,255,255,0.2);
}

.preview-content {
    text-align: center;
}

.preview-content h3 {
    margin: 0 0 8px 0;
    font-size: 1.5rem;
    font-weight: 300;
}

.preview-content p {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.config-summary {
    background: rgba(0, 120, 212, 0.1);
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid var(--primary-color);
}

.summary-item {
    margin: 8px 0;
    font-size: 0.9rem;
}

.summary-item strong {
    display: inline-block;
    width: 120px;
}

/* Animaciones para notificaciones */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Estilos para notificaciones de logo */
.logo-notification {
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}

.logo-notification.alert-info {
    background-color: rgba(0, 120, 212, 0.1);
    border: 1px solid rgba(0, 120, 212, 0.3);
    color: #0078d4;
}

.logo-notification.alert-success {
    background-color: rgba(16, 124, 16, 0.1);
    border: 1px solid rgba(16, 124, 16, 0.3);
    color: #107c10;
}

.logo-notification.alert-warning {
    background-color: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
    color: #ff9800;
}

/* Estilos para mejorar la vista previa del logo */
.logo-preview {
    max-width: 100px;
    max-height: 100px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.logo-preview:hover {
    transform: scale(1.05);
}

.logo-info {
    margin-top: 10px;
}

.logo-info small {
    display: block;
    margin-bottom: 8px;
}

.logo-buttons-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-start;
    margin-top: 8px;
}

.logo-button {
    width: 100%;
    max-width: 200px;
    text-align: center;
}

/* Estilos para modales */
.modal {
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

.close:hover {
    color: #000;
}

/* Estilos para configuración de restauración */
.restore-info h4 {
    margin: 15px 0 5px 0;
    color: #333;
}

.config-sections {
    max-height: 200px;
    overflow-y: auto;
}

.config-section {
    margin-bottom: 15px;
}

.config-section ul {
    margin: 5px 0 0 20px;
    font-family: monospace;
    font-size: 0.9em;
}

.config-section li {
    margin: 3px 0;
    color: #666;
}

.confirm-checkbox {
    background: rgba(255, 193, 7, 0.1);
    padding: 15px;
    border-radius: 5px;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.confirm-checkbox label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.confirm-checkbox input[type="checkbox"] {
    margin-right: 10px;
    transform: scale(1.2);
}

/* Footer styles */
.config-footer {
    margin-top: 40px;
    padding: 20px 0;
    border-top: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

.footer-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.footer-left {
    color: var(--text-light);
    font-size: 0.9rem;
}

.footer-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.phpmyadmin-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.phpmyadmin-link:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
    text-decoration: none;
    color: white;
}

.phpmyadmin-link:active {
    transform: translateY(0);
}

/* Responsive footer */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .footer-left,
    .footer-right {
        width: 100%;
        justify-content: center;
    }
}

/* Animaciones */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style>

<!-- ===== JAVASCRIPT PARA FUNCIONALIDAD INTERACTIVA ===== -->
<script>
// Variables globales para el modal de restauración
let currentRestoreFile = '';
let currentRestorePath = '';

// Función para resetear el logo
function resetLogo() {
    if (confirm('¿Está seguro que desea restaurar el logo original? Esto aplicará el cambio inmediatamente.')) {
        // Mostrar indicador de carga
        showNotification('Restaurando logo original...', 'info');
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=reset_logo'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Logo original restaurado exitosamente.', 'success');
                
                // Actualizar preview
                const preview = document.getElementById('logo-preview');
                const logoInfo = document.getElementById('logo-info-text');
                const noLogoContainer = document.getElementById('no-logo-container');
                const fileInput = document.getElementById('sistema_logo');
                
                // Resetear el input file
                if (fileInput) fileInput.value = '';
                
                // Restaurar preview al logo original
                if (preview) {
                    preview.src = data.logo_path + '?v=' + Date.now();
                    preview.style.display = 'block';
                }
                
                // Ocultar contenedor de "no logo"
                if (noLogoContainer) {
                    noLogoContainer.style.display = 'none';
                }
                
                // Actualizar información
                if (logoInfo) {
                    logoInfo.textContent = 'Logo actual: ' + data.logo_filename;
                }
                
                // Actualizar favicon
                updateFavicon(data.logo_path);
                
                // Recargar página después de 2 segundos para mostrar cambios
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error al restaurar logo: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Error de conexión: ' + error.message, 'error');
        });
    }
}

// Función para aplicar cambio de logo
function applyLogoChange() {
    const fileInput = document.getElementById('sistema_logo');
    
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        showNotification('Por favor seleccione un archivo de logo primero.', 'warning');
        return;
    }
    
    if (confirm('¿Está seguro que desea aplicar el nuevo logo?')) {
        const formData = new FormData();
        formData.append('action', 'change_logo');
        formData.append('sistema_logo', fileInput.files[0]);
        
        // Mostrar indicador de carga
        showNotification('Subiendo nuevo logo...', 'info');
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Logo actualizado exitosamente.', 'success');
                updateFavicon(data.logo_path);
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error al actualizar el logo: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Error de conexión: ' + error.message, 'error');
        });
    }
}

// Función para aplicar configuración del monitor público
function applyPublicConfig() {
    if (confirm('¿Está seguro que desea aplicar la configuración del monitor público?')) {
        const data = {
            action: 'update_public_config',
            publico_titulo: document.getElementById('publico_titulo').value,
            publico_subtitulo: document.getElementById('publico_subtitulo').value,
            publico_color_fondo: document.getElementById('publico_color_fondo').value,
            publico_color_secundario: document.getElementById('publico_color_secundario').value,
            publico_color_texto: document.getElementById('publico_color_texto').value,
            publico_refresh_interval: document.getElementById('publico_refresh_interval').value
        };
        
        showNotification('Actualizando configuración del monitor público...', 'info');
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: Object.keys(data).map(key => key + '=' + encodeURIComponent(data[key])).join('&')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Configuración del monitor público actualizada exitosamente.', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error al actualizar configuración: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Error de conexión: ' + error.message, 'error');
        });
    }
}

// Función para aplicar configuración general del sistema
function applySystemConfig() {
    if (confirm('¿Está seguro que desea aplicar la configuración general del sistema?')) {
        const data = {
            action: 'update_system_config',
            sistema_nombre: document.getElementById('sistema_nombre').value,
            sistema_version: document.getElementById('sistema_version').value,
            sistema_log_level: document.getElementById('sistema_log_level').value,
            sistema_timezone: document.getElementById('sistema_timezone').value
        };
        
        showNotification('Actualizando configuración general del sistema...', 'info');
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: Object.keys(data).map(key => key + '=' + encodeURIComponent(data[key])).join('&')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Configuración general actualizada exitosamente.', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error al actualizar configuración: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Error de conexión: ' + error.message, 'error');
        });
    }
}

// Función para aplicar umbrales ambientales
function applyThresholds() {
    if (confirm('¿Está seguro que desea aplicar los nuevos umbrales ambientales?')) {
        const data = {
            action: 'update_thresholds',
            temperatura_max: document.getElementById('temperatura_max').value,
            humedad_max: document.getElementById('humedad_max').value,
            ruido_max: document.getElementById('ruido_max').value,
            co2_max: document.getElementById('co2_max').value,
            lux_min: document.getElementById('lux_min').value
        };
        
        showNotification('Actualizando umbrales ambientales...', 'info');
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: Object.keys(data).map(key => key + '=' + encodeURIComponent(data[key])).join('&')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Umbrales ambientales actualizados exitosamente.', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error al actualizar umbrales: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Error de conexión: ' + error.message, 'error');
        });
    }
}

// ===== FUNCIONES PARA GESTIÓN DE BACKUPS =====
function createManualBackup() {
    if (confirm('¿Está seguro de que desea crear una copia de seguridad manual de la configuración actual?')) {
        // Mostrar indicador de carga
        const button = event.target;
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳ Creando backup...';
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_backup'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('✅ Backup creado exitosamente!\n\nArchivo: ' + data.filename, 'success');
                setTimeout(() => location.reload(), 2000); // Recargar para mostrar el nuevo backup
            } else {
                showNotification('❌ Error al crear backup: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error completo:', error);
            showNotification('❌ Error de conexión: ' + error.message, 'error');
        })
        .finally(() => {
            // Restaurar botón
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
}

function cleanOldBackups() {
    if (confirm('¿Está seguro de que desea eliminar los backups antiguos manteniendo solo los 10 más recientes?')) {
        // Mostrar indicador de carga
        const button = event.target;
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳ Limpiando backups...';
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clean_backups'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                let message = '✅ Limpieza completada!\n\n' + data.message;
                if (data.deleted > 0 && data.deleted_files) {
                    message += '\n\nArchivos eliminados:\n';
                    data.deleted_files.forEach(file => {
                        message += `- ${file.filename} (${file.date})\n`;
                    });
                }
                showNotification(message, 'success');
                setTimeout(() => location.reload(), 2000); // Recargar para actualizar la lista
            } else {
                showNotification('❌ Error en la limpieza: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error completo:', error);
            showNotification('❌ Error de conexión: ' + error.message, 'error');
        })
        .finally(() => {
            // Restaurar botón
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
}

function deleteBackup(filename, backupPath) {
    if (confirm(`¿Está seguro de que desea eliminar el backup "${filename}"?\n\nEsta acción no se puede deshacer.`)) {
        // Mostrar indicador de carga
        const button = event.target;
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '⏳ Eliminando...';
        
        fetch('includes/configuracion_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_backup&filename=${encodeURIComponent(filename)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification(`✅ Backup eliminado exitosamente!\n\nArchivo: ${filename}`, 'success');
                setTimeout(() => location.reload(), 1000); // Recargar para actualizar la lista
            } else {
                showNotification('❌ Error al eliminar backup: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error completo:', error);
            showNotification('❌ Error de conexión: ' + error.message, 'error');
        })
        .finally(() => {
            // Restaurar botón
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
}

// Función para previsualizar backup
function previewBackup(backupPath) {
    fetch(backupPath)
    .then(response => {
        if (!response.ok) {
            throw new Error('No se pudo cargar el archivo');
        }
        return response.text();
    })
    .then(content => {
        document.getElementById('backupContent').textContent = content;
        document.getElementById('backupPreviewModal').style.display = 'block';
    })
    .catch(error => {
        showNotification('Error al cargar el backup: ' + error.message, 'error');
    });
}

// Función para cerrar modal de preview
function closeBackupPreview() {
    document.getElementById('backupPreviewModal').style.display = 'none';
}

// Función para mostrar modal de restauración
function showRestoreModal(filename, backupPath) {
    currentRestoreFile = filename;
    currentRestorePath = backupPath;
    
    document.getElementById('restore-filename').textContent = filename;
    document.getElementById('confirm-restore').checked = false;
    document.getElementById('apply-restore-btn').disabled = true;
    
    // Cargar preview de la configuración
    loadRestorePreview(backupPath);
    
    document.getElementById('restoreModal').style.display = 'block';
}

// Función para cargar preview de restauración
function loadRestorePreview(backupPath) {
    const previewContainer = document.getElementById('restore-config-preview');
    previewContainer.innerHTML = '<div class="loading">Cargando configuración...</div>';
    
    fetch(backupPath)
    .then(response => response.text())
    .then(content => {
        // Parsear el contenido INI y mostrar la configuración relevante
        const configLines = content.split('\n');
        let configHtml = '<div class="config-sections">';
        
        let currentSection = '';
        let sectionContent = {};
        
        configLines.forEach(line => {
            line = line.trim();
            if (line.startsWith('[') && line.endsWith(']')) {
                currentSection = line.slice(1, -1);
                sectionContent[currentSection] = [];
            } else if (line && !line.startsWith(';') && currentSection) {
                sectionContent[currentSection].push(line);
            }
        });
        
        // Mostrar secciones importantes
        if (sectionContent['sistema']) {
            configHtml += '<div class="config-section"><strong>🏢 Sistema:</strong><ul>';
            sectionContent['sistema'].forEach(item => {
                configHtml += `<li>${item}</li>`;
            });
            configHtml += '</ul></div>';
        }
        
        if (sectionContent['referencias']) {
            configHtml += '<div class="config-section"><strong>🌡️ Umbrales:</strong><ul>';
            sectionContent['referencias'].forEach(item => {
                configHtml += `<li>${item}</li>`;
            });
            configHtml += '</ul></div>';
        }
        
        if (sectionContent['publico']) {
            configHtml += '<div class="config-section"><strong>🖥️ Monitor Público:</strong><ul>';
            sectionContent['publico'].forEach(item => {
                configHtml += `<li>${item}</li>`;
            });
            configHtml += '</ul></div>';
        }
        
        configHtml += '</div>';
        previewContainer.innerHTML = configHtml;
    })
    .catch(error => {
        previewContainer.innerHTML = '<div class="error">Error al cargar la configuración: ' + error.message + '</div>';
    });
}

// Función para cerrar modal de restauración
function closeRestoreModal() {
    document.getElementById('restoreModal').style.display = 'none';
    currentRestoreFile = '';
    currentRestorePath = '';
}

// Función para aplicar restauración
function applyRestore() {
    if (!document.getElementById('confirm-restore').checked) {
        showNotification('Debe confirmar la restauración marcando la casilla.', 'warning');
        return;
    }
    
    if (!currentRestoreFile || !currentRestorePath) {
        showNotification('Error: No se ha seleccionado un archivo de backup válido.', 'error');
        return;
    }
    
    // Mostrar indicador de carga
    const applyBtn = document.getElementById('apply-restore-btn');
    applyBtn.disabled = true;
    applyBtn.textContent = '⏳ Restaurando...';
    
    fetch('includes/configuracion_operations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=restore_config&backup_file=${encodeURIComponent(currentRestoreFile)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Configuración restaurada exitosamente.', 'success');
            closeRestoreModal();
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('Error al restaurar configuración: ' + data.error, 'error');
            applyBtn.disabled = false;
            applyBtn.textContent = '✅ Aplicar Restauración';
        }
    })
    .catch(error => {
        showNotification('Error de conexión: ' + error.message, 'error');
        applyBtn.disabled = false;
        applyBtn.textContent = '✅ Aplicar Restauración';
    });
}

// Event listener para el checkbox de confirmación
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirm-restore');
    const applyBtn = document.getElementById('apply-restore-btn');
    
    if (confirmCheckbox && applyBtn) {
        confirmCheckbox.addEventListener('change', function() {
            applyBtn.disabled = !this.checked;
        });
    }
    
    // Actualizar vista previa de colores en tiempo real
    updateColorPreview();
    
    // Event listeners para actualización en tiempo real
    const colorInputs = ['publico_color_fondo', 'publico_color_secundario', 'publico_color_texto'];
    colorInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updateColorPreview);
        }
    });
    
    const textInputs = ['publico_titulo', 'publico_subtitulo'];
    textInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updateTextPreview);
        }
    });
});

// Función para actualizar vista previa de colores
function updateColorPreview() {
    const colorPreview = document.getElementById('color-preview');
    const previewTitle = document.getElementById('preview-title');
    const previewSubtitle = document.getElementById('preview-subtitle');
    
    if (!colorPreview) return;
    
    const colorFondo = document.getElementById('publico_color_fondo')?.value || '#667eea';
    const colorSecundario = document.getElementById('publico_color_secundario')?.value || '#764ba2';
    const colorTexto = document.getElementById('publico_color_texto')?.value || '#ffffff';
    
    // Actualizar fondo
    colorPreview.style.background = `linear-gradient(135deg, ${colorFondo} 0%, ${colorSecundario} 100%)`;
    
    // Actualizar colores de texto
    if (previewTitle) previewTitle.style.color = colorTexto;
    if (previewSubtitle) previewSubtitle.style.color = colorTexto;
    
    // Actualizar campos de texto
    const textFields = [
        { input: 'publico_color_fondo', text: 'color_fondo_text' },
        { input: 'publico_color_secundario', text: 'color_secundario_text' },
        { input: 'publico_color_texto', text: 'color_texto_text' }
    ];
    
    textFields.forEach(field => {
        const input = document.getElementById(field.input);
        const textField = document.getElementById(field.text);
        if (input && textField) {
            textField.value = input.value;
        }
    });
}

// Función para actualizar vista previa de texto
function updateTextPreview() {
    const previewTitle = document.getElementById('preview-title');
    const previewSubtitle = document.getElementById('preview-subtitle');
    
    const titulo = document.getElementById('publico_titulo')?.value || 'Monitor Ambiental';
    const subtitulo = document.getElementById('publico_subtitulo')?.value || 'Sistema de Sensores Arduino';
    
    if (previewTitle) previewTitle.textContent = titulo;
    if (previewSubtitle) previewSubtitle.textContent = subtitulo;
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
    // Remover notificación anterior
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease;
    `;
    
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';
    notification.innerHTML = `
        <strong>${icon}</strong> ${message}
        <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Función para previsualizar el logo seleccionado
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tipo de archivo
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showNotification('❌ Por favor seleccione una imagen válida (PNG, JPG, GIF)', 'error');
            input.value = '';
            return;
        }
        
        // Validar tamaño (máximo 2MB)
        if (file.size > 2 * 1024 * 1024) {
            showNotification('❌ El archivo debe ser menor a 2MB', 'error');
            input.value = '';
            return;
        }
        
        // Crear preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logo-preview');
            const noLogo = document.querySelector('.no-logo');
            
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            if (noLogo) {
                noLogo.style.display = 'none';
            }
            
            // Actualizar información del archivo
            const logoInfo = document.getElementById('logo-info-text');
            if (logoInfo) {
                logoInfo.innerHTML = 'Nuevo logo: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)<br>' +

                    '<span style="color: var(--warning-color); font-weight: 600;">⚠️ Recuerde aplicar el cambio para guardar</span>';
            }
            
            showNotification('Logo seleccionado correctamente. Use "Aplicar Cambio de Logo" para guardarlo.', 'info');
        };
        reader.readAsDataURL(file);
    }
}

// Función para actualizar favicon dinámicamente
function updateFavicon(logoPath) {
    // Remover favicons existentes
    const existingFavicons = document.querySelectorAll('link[rel*="icon"]');
    existingFavicons.forEach(favicon => favicon.remove());
    
    // Crear nuevos favicons
    const faviconSizes = [
        { size: '16x16', rel: 'icon' },
        { size: '32x32', rel: 'icon' },
        { size: '', rel: 'shortcut icon' },
        { size: '', rel: 'apple-touch-icon' }
    ];
    
    faviconSizes.forEach(favicon => {
        const link = document.createElement('link');
        link.type = 'image/png';
        link.rel = favicon.rel;
        if (favicon.size) {
            link.sizes = favicon.size;
        }
        link.href = logoPath + '?v=' + Date.now(); // Cache busting
        document.head.appendChild(link);
    });
}
</script>
