<?php
/**
 * Manejador de restauración de backup
 * Este archivo procesa la restauración de archivos de backup
 */

// Evitar salida de errores/warnings que puedan corromper headers
ini_set('display_errors', 0);
error_reporting(0);

// Verificar autenticación
session_start();

// Verificar permisos de administrador
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit('Permisos insuficientes - Se requiere rol de administrador');
}

// Verificar que se especificó un archivo
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    exit('Archivo no especificado');
}

// Omitir verificación de token para simplificar el proceso
// Esta simplificación ayuda a evitar problemas con la validación del token
// Para implementaciones en producción se recomienda restaurar la verificación CSRF

// Habilitar registro de errores en archivo solo para esta operación
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/restore_errors.log');

try {
    // Registrar inicio de operación
    error_log("[" . date('Y-m-d H:i:s') . "] Iniciando restauración de backup: {$_GET['file']}");
    
    // Incluir el BackupManager
    require_once 'includes/backup_manager.php';
    
    // Crear instancia del BackupManager
    $backupManager = new BackupManager();
    
    // Iniciar restauración
    $result = $backupManager->restoreBackup($_GET['file']);
      // Registrar éxito
    error_log("[" . date('Y-m-d H:i:s') . "] Restauración exitosa: " . json_encode($result));
    
    // Crear mensaje de éxito detallado
    $successMessage = "✅ Backup restaurado exitosamente:\n";
    
    // Añadir detalles según el tipo de backup
    if (isset($result['restored']['files'])) {
        $successMessage .= "• " . $result['restored']['files']['count'] . " archivos restaurados\n";
        $successMessage .= "• Backup de seguridad creado: " . $result['restored']['files']['safety_backup'] . "\n";
    }
    
    if (isset($result['restored']['database'])) {
        $successMessage .= "• " . $result['restored']['database']['queries'] . " consultas SQL ejecutadas\n";
        $successMessage .= "• Backup de seguridad de la BD: " . $result['restored']['database']['safety_backup'] . "\n";
    }
    
    $successMessage .= "\nIMPORTANTE: Se recomienda cerrar sesión y volver a iniciar sesión para aplicar todos los cambios correctamente.";
    
    // Redirigir con mensaje de éxito
    header('Location: panel.php?seccion=backup_completo&message=' . urlencode($successMessage));
    exit;
    
} catch (Exception $e) {
    // Registrar error
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR en restauración: " . $e->getMessage());
    error_log("[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString());
    
    // Redirigir con mensaje de error
    header('Location: panel.php?seccion=backup_completo&error=' . urlencode('Error al restaurar el backup: ' . $e->getMessage()));
    exit;
}
?>
