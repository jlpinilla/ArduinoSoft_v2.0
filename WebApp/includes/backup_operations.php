<?php
/**
 * =====================================================
 * OPERACIONES DE BACKUP - SUITE AMBIENTAL
 * =====================================================
 * Maneja las operaciones de creación y limpieza de backups
 * de configuración del sistema.
 */

// ===== CONTROL DE SEGURIDAD =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Acceso denegado. Se requieren permisos de administrador.'
    ]);
    exit();
}

// Establecer header JSON
header('Content-Type: application/json');

try {
    // Obtener acción solicitada
    $action = $_POST['action'] ?? '';
      switch ($action) {
        case 'create_backup':
            createManualBackup();
            break;
            
        case 'clean_backups':
            cleanOldBackups();
            break;
            
        case 'delete_backup':
            deleteBackupFile();
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Crear backup manual de la configuración
 */
function createManualBackup() {
    try {
        // Verificar que existe el archivo de configuración
        if (!file_exists('../config.ini')) {
            throw new Exception('No se encontró el archivo config.ini para respaldar');
        }
        
        // Crear directorio de backups si no existe
        $backup_dir = '../configbackup';
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de backups');
            }
        }
        
        // Generar nombre único para el backup
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "config_backup_manual_{$timestamp}.ini";
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Cargar configuración actual completa
        $config_actual = parse_ini_file('../config.ini', true);
        if (!$config_actual) {
            throw new Exception('No se pudo leer la configuración actual');
        }
        
        // Generar contenido del backup con header actualizado
        $backup_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
        $backup_content .= "; Backup manual creado el " . date('Y-m-d H:i:s') . " por " . $_SESSION['usuario'] . "\n\n";
        
        // Preservar todas las secciones de configuración en orden
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
        
        // Añadir metadatos del backup al final
        $backup_content .= "; === METADATOS DEL BACKUP ===\n";
        $backup_content .= "; Tipo: Backup Manual\n";
        $backup_content .= "; Fecha de creación: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "; Usuario: " . $_SESSION['usuario'] . "\n";
        $backup_content .= "; Versión del sistema: " . ($config_actual['sistema']['version'] ?? 'Desconocida') . "\n";
        
        // Escribir el archivo de backup
        if (!file_put_contents($backup_path, $backup_content)) {
            throw new Exception('Error al escribir el archivo de backup');
        }
        
        // Registrar en log
        $log_entry = "\n### " . date('Y-m-d H:i:s') . " - Backup Manual Creado\n";
        $log_entry .= "**Usuario:** " . $_SESSION['usuario'] . "\n";
        $log_entry .= "**Archivo:** {$backup_filename}\n";        $log_entry .= "**Tamaño:** " . filesize($backup_path) . " bytes\n";
        $log_entry .= "---\n";
        
        file_put_contents('cambios.log', $log_entry, FILE_APPEND);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Backup creado exitosamente',
            'filename' => $backup_filename,
            'path' => $backup_path,
            'size' => filesize($backup_path),
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['usuario']
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al crear backup manual: ' . $e->getMessage());
    }
}

/**
 * Limpiar backups antiguos (más de 30 días)
 */
function cleanOldBackups() {
    try {
        $backup_dir = '../configbackup';
        
        if (!is_dir($backup_dir)) {
            throw new Exception('El directorio de backups no existe');
        }
        
        $files = scandir($backup_dir);
        $deleted_count = 0;
        $deleted_files = [];
        $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 días atrás
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_path = $backup_dir . '/' . $file;
            
            // Verificar que es un archivo de backup
            if (preg_match('/^config_backup_.+\.ini$/', $file)) {
                $file_time = filemtime($file_path);
                
                if ($file_time < $cutoff_time) {
                    if (unlink($file_path)) {
                        $deleted_count++;
                        $deleted_files[] = [
                            'filename' => $file,
                            'date' => date('Y-m-d H:i:s', $file_time),
                            'size' => filesize($file_path) ?: 0
                        ];
                    }
                }
            }
        }
        
        // Registrar limpieza en log si se eliminaron archivos
        if ($deleted_count > 0) {
            $log_entry = "\n### " . date('Y-m-d H:i:s') . " - Limpieza de Backups\n";
            $log_entry .= "**Usuario:** " . $_SESSION['usuario'] . "\n";
            $log_entry .= "**Archivos eliminados:** {$deleted_count}\n";
            foreach ($deleted_files as $file) {
                $log_entry .= "- {$file['filename']} ({$file['date']})\n";            }
            $log_entry .= "---\n";
            
            file_put_contents('cambios.log', $log_entry, FILE_APPEND);
        }
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => $deleted_count > 0 ? 
                "Limpieza completada. Se eliminaron {$deleted_count} archivos antiguos." : 
                "No se encontraron archivos para eliminar.",
            'deleted' => $deleted_count,
            'deleted_files' => $deleted_files,
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['usuario']
        ]);    } catch (Exception $e) {
        throw new Exception('Error al limpiar backups: ' . $e->getMessage());
    }
}

/**
 * Eliminar un archivo de backup específico
 */
function deleteBackupFile() {
    try {
        // Obtener el nombre del archivo a eliminar
        $filename = $_POST['filename'] ?? '';
        
        if (empty($filename)) {
            throw new Exception('No se especificó el archivo a eliminar');
        }
        
        // Validar que el archivo tenga el formato correcto de backup
        if (!preg_match('/^config_backup_.+\.ini$/', $filename)) {
            throw new Exception('Nombre de archivo no válido');
        }
        
        $backup_dir = '../configbackup';
        $file_path = $backup_dir . '/' . $filename;
        
        // Verificar que el archivo existe
        if (!file_exists($file_path)) {
            throw new Exception('El archivo de backup no existe: ' . $filename);
        }
        
        // Verificar que es realmente un archivo y no un directorio
        if (!is_file($file_path)) {
            throw new Exception('La ruta especificada no es un archivo válido');
        }
        
        // Obtener información del archivo antes de eliminarlo
        $file_size = filesize($file_path);
        $file_date = date('Y-m-d H:i:s', filemtime($file_path));
        
        // Eliminar el archivo
        if (!unlink($file_path)) {
            throw new Exception('No se pudo eliminar el archivo. Verifique los permisos.');
        }
        
        // Registrar eliminación en log
        $log_entry = "\n### " . date('Y-m-d H:i:s') . " - Backup Eliminado\n";
        $log_entry .= "**Usuario:** " . $_SESSION['usuario'] . "\n";
        $log_entry .= "**Archivo eliminado:** {$filename}\n";
        $log_entry .= "**Tamaño:** {$file_size} bytes\n";        $log_entry .= "**Fecha original:** {$file_date}\n";
        $log_entry .= "---\n";
        
        file_put_contents('cambios.log', $log_entry, FILE_APPEND);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Archivo de backup eliminado exitosamente',
            'filename' => $filename,
            'size' => $file_size,
            'date' => $file_date,
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['usuario']
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al eliminar backup: ' . $e->getMessage());
    }
}
?>
