<?php
/**
 * LOGOUT.PHP - Cierre de Sesión Seguro
 * Sistema de Monitoreo Ambiental ArduinoSoft
 * 
 * Funcionalidad:
 * - Cierre seguro de sesión de usuario
 * - Limpieza completa de datos de sesión
 * - Redirección controlada al login
 * - Logging de actividad de logout
 */

// ===== INICIALIZACIÓN DE SESIÓN =====
// Iniciar o continuar sesión existente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== LOGGING DE ACTIVIDAD =====
// Registrar el logout si hay una sesión activa
if (isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] LOGOUT - Usuario: $usuario - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    
    // Intentar escribir al log (sin fallar si no se puede)
    @file_put_contents(__DIR__ . '/logs/access.log', $log_entry, FILE_APPEND | LOCK_EX);
    @error_log("ArduinoSoft Logout: $usuario");
}

// ===== LIMPIEZA COMPLETA DE SESIÓN =====
// Eliminar todas las variables de sesión
$_SESSION = array();

// Eliminar cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión completamente
session_destroy();

// ===== REDIRECCIÓN CONTROLADA =====
// Redirigir al login con parámetro de confirmación
header('Location: index.php?logout=1');
exit();
?>
