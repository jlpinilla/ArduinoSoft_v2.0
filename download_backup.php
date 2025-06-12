<?php
/**
 * Manejador de descargas de backup
 * Este archivo maneja las descargas de archivos de backup sin generar HTML
 */

// Evitar salida de errores/warnings que puedan corromper headers
ini_set('display_errors', 0);
error_reporting(0);

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado - No hay sesión activa');
}

// Verificar permisos de administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit('Permisos insuficientes - Se requiere rol de administrador');
}

// Verificar que se especificó un archivo
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    exit('Archivo no especificado');
}

try {
    // Definir modo de descarga para evitar verificaciones de sesión en BackupManager
    define('BACKUP_DOWNLOAD_MODE', true);
    
    // Incluir el BackupManager
    require_once 'includes/backup_manager.php';
    
    // Crear instancia del BackupManager
    $backupManager = new BackupManager();
    
    // Iniciar descarga
    $backupManager->downloadBackup($_GET['file']);
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Error al descargar el archivo: ' . $e->getMessage());
}
?>
