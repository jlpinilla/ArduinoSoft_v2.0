<?php
/**
 * =====================================================
 * OPERACIONES DE CONFIGURACIÓN - SUITE AMBIENTAL
 * =====================================================
 * Maneja las operaciones AJAX para la configuración del sistema
 */

// ===== CONTROL DE SEGURIDAD Y SESIÓN =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit();
}

// ===== CONFIGURACIÓN DE RESPUESTA JSON =====
header('Content-Type: application/json');

// ===== PROCESAMIENTO DE ACCIONES =====
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'reset_logo':
            resetLogoToOriginal();
            break;
            
        case 'change_logo':
            changeLogoFile();
            break;
            
        case 'update_system_config':
            updateSystemConfig();
            break;
            
        case 'update_public_config':
            updatePublicConfig();
            break;
            
        case 'update_thresholds':
            updateThresholds();
            break;
            
        case 'create_backup':
            createManualBackup();
            break;
            
        case 'clean_backups':
            cleanOldBackups();
            break;
            
        case 'delete_backup':
            deleteBackup();
            break;
            
        case 'restore_config':
            restoreConfig();
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ===== FUNCIÓN AUXILIAR: GUARDAR CONFIGURACIÓN CON HEADER ACTUALIZADO =====
function saveConfig($config) {
    // Aplicar timezone de la configuración para el timestamp
    $timezone = $config['sistema']['timezone'] ?? '+02:00';
    $current_timezone = date_default_timezone_get();
    
    // Convertir timezone del formato config (+02:00) a formato PHP
    $timezone_map = [
        '+02:00' => 'Europe/Madrid',
        '+01:00' => 'Europe/Paris', 
        '+00:00' => 'UTC',
        '-05:00' => 'America/New_York'
    ];
    
    if (isset($timezone_map[$timezone])) {
        date_default_timezone_set($timezone_map[$timezone]);
    }
    
    // Generar header actualizado con timestamp y usuario actual usando timezone correcto
    $config_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
    $config_content .= "; Actualizado el " . date('Y-m-d H:i:s') . " por " . $_SESSION['usuario'] . "\n\n";
    
    foreach ($config as $section => $values) {
        $config_content .= "[{$section}]\n";
        foreach ($values as $key => $value) {
            if (is_numeric($value)) {
                $config_content .= "{$key} = {$value}\n";
            } else {
                $config_content .= "{$key} = \"{$value}\"\n";
            }
        }
        $config_content .= "\n";
    }
    
    // Restaurar timezone original
    date_default_timezone_set($current_timezone);
    
    if (!file_put_contents('../config.ini', $config_content)) {
        throw new Exception('Error al guardar la configuración');
    }
}

// ===== FUNCIÓN: CREAR BACKUP MANUAL CON CONFIGURACIÓN ACTUAL =====
function createManualBackup() {
    try {
        // Cargar configuración actual completa
        $config_actual = parse_ini_file('../config.ini', true);
        if (!$config_actual) {
            throw new Exception('No se pudo cargar la configuración actual');
        }
        
        $backup_dir = '../configbackup';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Aplicar timezone para el nombre del archivo
        $timezone = $config_actual['sistema']['timezone'] ?? '+02:00';
        $current_timezone = date_default_timezone_get();
        
        $timezone_map = [
            '+02:00' => 'Europe/Madrid',
            '+01:00' => 'Europe/Paris', 
            '+00:00' => 'UTC',
            '-05:00' => 'America/New_York'
        ];
        
        if (isset($timezone_map[$timezone])) {
            date_default_timezone_set($timezone_map[$timezone]);
        }
        
        $backup_filename = 'config_back_manual_' . date('y_m_d_H_i_s') . '.ini';
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Generar contenido del backup con toda la configuración actual
        $backup_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
        $backup_content .= "; Backup manual creado el " . date('Y-m-d H:i:s') . " por " . $_SESSION['usuario'] . "\n";
        $backup_content .= "; Timezone: " . $timezone . " (" . date('T') . ")\n\n";
        
        // Preservar todas las secciones de configuración
        $sections_order = ['database', 'sistema', 'referencias', 'publico', 'azure'];
        
        foreach ($sections_order as $section) {
            if (isset($config_actual[$section])) {
                $backup_content .= "[{$section}]\n";
                
                // Añadir comentarios descriptivos por sección
                if ($section === 'database') {
                    $backup_content .= "; Configuración de conexión a base de datos\n";
                } elseif ($section === 'sistema') {
                    $backup_content .= "; Configuración general del sistema\n";
                } elseif ($section === 'referencias') {
                    $backup_content .= "; Umbrales de alerta ambiental\n";
                } elseif ($section === 'publico') {
                    $backup_content .= "; Configuración del monitor público\n";
                } elseif ($section === 'azure') {
                    $backup_content .= "; Configuración de integración con Microsoft Teams\n";
                }
                
                foreach ($config_actual[$section] as $key => $value) {
                    if (is_numeric($value)) {
                        $backup_content .= "{$key} = {$value}\n";
                    } else {
                        $backup_content .= "{$key} = \"{$value}\"\n";
                    }
                }
                $backup_content .= "\n";
            }
        }
        
        // Restaurar timezone original
        date_default_timezone_set($current_timezone);
        
        // Escribir el archivo de backup
        if (file_put_contents($backup_path, $backup_content)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Backup manual creado exitosamente',
                'filename' => $backup_filename,
                'sections_included' => array_keys($config_actual)
            ]);
        } else {
            throw new Exception('Error al crear el archivo de backup');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIÓN: ACTUALIZAR CONFIGURACIÓN GENERAL DEL SISTEMA =====
function updateSystemConfig() {
    try {
        $config = parse_ini_file('../config.ini', true);
        if (!$config) {
            throw new Exception('No se pudo cargar la configuración');
        }
        
        // Validar y actualizar configuración del sistema
        $nombre = trim($_POST['sistema_nombre'] ?? 'Suite Ambiental');
        $version = trim($_POST['sistema_version'] ?? '2.0');
        $log_level = trim($_POST['sistema_log_level'] ?? 'info');
        $timezone = trim($_POST['sistema_timezone'] ?? '+02:00');
        
        // Validaciones
        if (strlen($nombre) < 3 || strlen($nombre) > 50) {
            throw new Exception('El nombre del sistema debe tener entre 3 y 50 caracteres');
        }
        
        if (strlen($version) < 1 || strlen($version) > 10) {
            throw new Exception('La versión debe tener entre 1 y 10 caracteres');
        }
        
        if (!in_array($log_level, ['error', 'warning', 'info', 'debug'])) {
            throw new Exception('Nivel de log no válido');
        }
        
        if (!in_array($timezone, ['+02:00', '+01:00', '+00:00', '-05:00'])) {
            throw new Exception('Zona horaria no válida');
        }
        
        // Actualizar configuración del sistema preservando logo actual
        $config['sistema']['nombre'] = $nombre;
        $config['sistema']['version'] = $version;
        $config['sistema']['log_level'] = $log_level;
        $config['sistema']['timezone'] = $timezone;
        // Preservar logo actual
        $config['sistema']['logo'] = $config['sistema']['logo'] ?? 'media/logo.png';
        
        // Guardar configuración con header actualizado
        saveConfig($config);
        
        echo json_encode(['success' => true, 'message' => 'Configuración general del sistema actualizada']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIÓN: RESETEAR LOGO AL ORIGINAL =====
function resetLogoToOriginal() {
    try {
        // Cargar configuración actual
        $config = parse_ini_file('../config.ini', true);
        if (!$config) {
            throw new Exception('No se pudo cargar la configuración');
        }
        
        // Logo original por defecto
        $original_logo = 'media/logo.png';
        
        // Eliminar logo actual si no es el original
        $current_logo = $config['sistema']['logo'] ?? '';
        if (!empty($current_logo) && $current_logo !== $original_logo && file_exists('../' . $current_logo)) {
            unlink('../' . $current_logo);
        }
        
        // Actualizar configuración
        $config['sistema']['logo'] = $original_logo;
        
        // Guardar configuración con header actualizado
        saveConfig($config);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Logo restaurado al original',
            'logo_path' => $original_logo,
            'logo_filename' => 'logo.png'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIÓN: CAMBIAR ARCHIVO DE LOGO =====
function changeLogoFile() {
    try {
        if (!isset($_FILES['sistema_logo']) || $_FILES['sistema_logo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se recibió un archivo válido');
        }
        
        $upload_file = $_FILES['sistema_logo'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        
        // Validar tipo
        if (!in_array($upload_file['type'], $allowed_types)) {
            throw new Exception('Tipo de archivo no permitido');
        }
        
        // Validar tamaño (máximo 2MB)
        if ($upload_file['size'] > 2 * 1024 * 1024) {
            throw new Exception('El archivo es demasiado grande (máximo 2MB)');
        }
        
        // Crear directorio si no existe
        if (!is_dir('../media')) {
            mkdir('../media', 0755, true);
        }
        
        // Generar nombre único
        $extension = pathinfo($upload_file['name'], PATHINFO_EXTENSION);
        $logo_filename = 'logo_' . time() . '.' . $extension;
        $logo_path = 'media/' . $logo_filename;
        $full_path = '../' . $logo_path;
        
        // Mover archivo
        if (move_uploaded_file($upload_file['tmp_name'], $full_path)) {
            // Actualizar configuración
            $config = parse_ini_file('../config.ini', true);
            
            // Eliminar logo anterior
            $old_logo = $config['sistema']['logo'] ?? '';
            if (!empty($old_logo) && $old_logo !== 'media/logo.png' && file_exists('../' . $old_logo)) {
                unlink('../' . $old_logo);
            }
            
            $config['sistema']['logo'] = $logo_path;
            
            // Guardar configuración con header actualizado
            saveConfig($config);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Logo actualizado exitosamente',
                'logo_path' => $logo_path,
                'logo_filename' => $logo_filename
            ]);
        } else {
            throw new Exception('Error al subir el archivo');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIÓN: ACTUALIZAR CONFIGURACIÓN PÚBLICA =====
function updatePublicConfig() {
    try {
        $config = parse_ini_file('../config.ini', true);
        if (!$config) {
            throw new Exception('No se pudo cargar la configuración');
        }
        
        // Validar y actualizar configuración pública
        $config['publico']['titulo'] = trim($_POST['publico_titulo'] ?? 'Monitor Ambiental');
        $config['publico']['subtitulo'] = trim($_POST['publico_subtitulo'] ?? 'Sistema de Sensores Arduino');
        $config['publico']['color_fondo'] = trim($_POST['publico_color_fondo'] ?? '#667eea');
        $config['publico']['color_secundario'] = trim($_POST['publico_color_secundario'] ?? '#764ba2');
        $config['publico']['color_texto'] = trim($_POST['publico_color_texto'] ?? '#ffffff');
        $config['publico']['refresh_interval'] = intval($_POST['publico_refresh_interval'] ?? 60);
        
        // Validaciones
        if (strlen($config['publico']['titulo']) < 3 || strlen($config['publico']['titulo']) > 50) {
            throw new Exception('El título debe tener entre 3 y 50 caracteres');
        }
        
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $config['publico']['color_fondo'])) {
            throw new Exception('Color de fondo inválido');
        }
        
        if ($config['publico']['refresh_interval'] < 60 || $config['publico']['refresh_interval'] > 3600) {
            throw new Exception('Intervalo de actualización debe estar entre 60 y 3600 segundos');
        }
        
        // Guardar configuración con header actualizado
        saveConfig($config);
        
        echo json_encode(['success' => true, 'message' => 'Configuración pública actualizada']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIÓN: ACTUALIZAR UMBRALES =====
function updateThresholds() {
    try {
        $config = parse_ini_file('../config.ini', true);
        if (!$config) {
            throw new Exception('No se pudo cargar la configuración');
        }
        
        // Actualizar umbrales
        $config['referencias']['temperatura_max'] = floatval($_POST['temperatura_max'] ?? 25);
        $config['referencias']['humedad_max'] = floatval($_POST['humedad_max'] ?? 48);
        $config['referencias']['ruido_max'] = floatval($_POST['ruido_max'] ?? 35);
        $config['referencias']['co2_max'] = intval($_POST['co2_max'] ?? 1000);
        $config['referencias']['lux_min'] = intval($_POST['lux_min'] ?? 195);
        
        // Validaciones
        if ($config['referencias']['temperatura_max'] < 15 || $config['referencias']['temperatura_max'] > 50) {
            throw new Exception('Temperatura debe estar entre 15°C y 50°C');
        }
        
        if ($config['referencias']['humedad_max'] < 30 || $config['referencias']['humedad_max'] > 90) {
            throw new Exception('Humedad debe estar entre 30% y 90%');
        }
        
        // Guardar configuración con header actualizado
        saveConfig($config);
        
        echo json_encode(['success' => true, 'message' => 'Umbrales actualizados']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ===== FUNCIONES DE GESTIÓN DE BACKUPS =====
function cleanOldBackups() {
    try {
        $backup_dir = '../configbackup';
        if (!is_dir($backup_dir)) {
            throw new Exception('Directorio de backups no existe');
        }
        
        $files = scandir($backup_dir);
        $backup_files = [];
        
        foreach ($files as $file) {
            if (preg_match('/^config_back_.+\.ini$/', $file)) {
                $backup_files[] = [
                    'filename' => $file,
                    'path' => $backup_dir . '/' . $file,
                    'date' => filemtime($backup_dir . '/' . $file)
                ];
            }
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backup_files, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        $deleted = 0;
        $deleted_files = [];
        
        // Mantener solo los 10 más recientes
        if (count($backup_files) > 10) {
            for ($i = 10; $i < count($backup_files); $i++) {
                if (unlink($backup_files[$i]['path'])) {
                    $deleted++;
                    $deleted_files[] = [
                        'filename' => $backup_files[$i]['filename'],
                        'date' => date('d/m/Y H:i:s', $backup_files[$i]['date'])
                    ];
                }
            }
        }
        
        $message = $deleted > 0 ? 
            "Se eliminaron {$deleted} archivos antiguos. Se mantuvieron los 10 más recientes." :
            "No hay archivos antiguos para eliminar.";
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'deleted' => $deleted,
            'deleted_files' => $deleted_files
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteBackup() {
    try {
        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            throw new Exception('Nombre de archivo no especificado');
        }
        
        $backup_path = '../configbackup/' . $filename;
        
        if (!file_exists($backup_path)) {
            throw new Exception('Archivo de backup no encontrado');
        }
        
        if (unlink($backup_path)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Backup eliminado exitosamente',
                'filename' => $filename
            ]);
        } else {
            throw new Exception('Error al eliminar el archivo');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function restoreConfig() {
    try {
        $backup_file = $_POST['backup_file'] ?? '';
        if (empty($backup_file)) {
            throw new Exception('Archivo de backup no especificado');
        }
        
        $backup_path = '../configbackup/' . $backup_file;
        
        if (!file_exists($backup_path)) {
            throw new Exception('Archivo de backup no encontrado');
        }
        
        // Crear backup de la configuración actual antes de restaurar
        $current_backup = '../configbackup/config_before_restore_' . date('y_m_d_H_i_s') . '.ini';
        if (file_exists('../config.ini')) {
            copy('../config.ini', $current_backup);
        }
        
        // Cargar configuración del backup
        $restored_config = parse_ini_file($backup_path, true);
        if (!$restored_config) {
            throw new Exception('No se pudo leer el archivo de backup');
        }
        
        // Guardar configuración restaurada con header actualizado
        saveConfig($restored_config);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Configuración restaurada exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
