<?php
/**
 * PANEL.PHP - Panel Principal de Administración
 * Sistema de Monitoreo Ambiental ArduinoSoft
 * 
 * Funcionalidad:
 * - Hub central de administración del sistema Suite Ambiental
 * - Gestión de usuarios y dispositivos con control de acceso por roles
 * - Acceso directo a monitor público e informes estadísticos
 * - Interfaz con navegación por pestañas y carga dinámica de contenido
 * - Sistema de autenticación con verificación de permisos por sección
 * - Modal de configuración y funciones de logout seguro
 */

// ===== INICIALIZACIÓN DE SESIÓN =====
// Continuar sesión PHP existente para verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CONFIGURACIÓN DE ZONA HORARIA =====
// Cargar gestor de timezone centralizado
require_once 'includes/timezone_manager.php';

// ===== VERIFICACIÓN DE AUTENTICACIÓN =====
// Si no hay sesión activa, redirigir al login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ===== VERIFICACIÓN DE CONFIGURACIÓN DEL SISTEMA =====
// Si no existe config.ini, redirigir a configuración inicial
if (!file_exists('config.ini')) {
    header('Location: configinicial.php');
    exit();
}

// ===== CARGA DE CONFIGURACIÓN =====
// Cargar parámetros de configuración desde archivo INI
$config = parse_ini_file('config.ini', true);

// ===== OBTENCIÓN DE DATOS DE SESIÓN =====
// Extraer información del usuario desde variables de sesión
$usuario = $_SESSION['usuario'] ?? 'Usuario';
$rol = $_SESSION['rol'] ?? 'operador';

// ===== MANEJO DE NAVEGACIÓN =====
// Obtener sección actual desde parámetros GET (por defecto dashboard)
$seccion = $_GET['seccion'] ?? 'dashboard';

// ===== INCLUSIÓN DEL GESTOR DE BASE DE DATOS OPTIMIZADO =====
// Incluir el DatabaseManager optimizado para gestión centralizada de conexiones
require_once 'includes/database_manager.php';

// ===== PROCESAMIENTO DE EXPORTACIÓN CSV =====
// Manejar exportación CSV antes de enviar cualquier HTML
if (isset($_POST['export_csv']) && $seccion === 'informes') {
    // Cargar y ejecutar solo la parte de exportación de informes.php
    require_once 'includes/informes_export.php';
    exit(); // Importante: terminar ejecución después de la exportación
}

// ===== MANEJO DE LOGOUT =====
// Procesar solicitud de cierre de sesión
if (isset($_GET['logout'])) {
    // Redirigir al logout dedicado para manejo seguro
    header('Location: logout.php');
    exit();
}
?>
<!-- ===== ESTRUCTURA HTML SEMÁNTICA ===== -->
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ===== META TAGS Y CONFIGURACIÓN ===== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Panel de Control - ArduinoSoft Monitor Ambiental</title>
    
    <!-- ===== FAVICON ===== -->
    <link rel="icon" type="image/png" sizes="32x32" href="media/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="media/logo.png">
    <link rel="apple-touch-icon" href="media/logo.png">
    <link rel="shortcut icon" href="media/logo.png">
    
    <!-- ===== RECURSOS EXTERNOS ===== -->
    <!-- Framework CSS PopulusWeb -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- ===== SEO Y ACCESIBILIDAD ===== -->
    <meta name="description" content="Panel de control del sistema de monitoreo ambiental">
</head>

<!-- ===== CUERPO PRINCIPAL ===== -->
<body>
    <!-- ===== CONTENEDOR PRINCIPAL ESTILO MICROSOFT 365 ===== -->
    <div class="m365-container">
        
        <!-- ===== ENCABEZADO DEL PANEL ===== -->
        <!-- Header con información del usuario y acciones principales -->        <header class="panel-header">
            <div class="panel-user-info">
                <!-- ===== LOGO Y TÍTULO DE LA APLICACIÓN ===== -->
                <div class="app-header">
                    <div class="logo-container">
                        <?php 
                        // Verificar si existe un logo personalizado en la configuración
                        $logo_path = 'media/logo.png'; // Logo por defecto
                        if (isset($config['sistema']['logo']) && !empty($config['sistema']['logo']) && file_exists($config['sistema']['logo'])) {
                            $logo_path = $config['sistema']['logo'];
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo ArduinoSoft" class="app-logo-small">
                    </div>
                    <div class="title-container">
                        <h1>ArduinoSoft Monitor Ambiental</h1>
                    </div>
                </div>
                
                <!-- ===== INFORMACIÓN DEL USUARIO AUTENTICADO ===== -->
                <!-- Mostrar saludo personalizado y rol del usuario -->
                <div class="panel-user-details">
                    <span>Bienvenido, <strong><?php echo htmlspecialchars($usuario); ?></strong></span>
                    <span class="status-badge"><?php echo htmlspecialchars(ucfirst($rol)); ?></span>
                </div>                <!-- ===== ACCIONES DEL PANEL ===== -->
                <!-- Botón de logout -->
                <div class="panel-actions">
                    <a href="?logout=1" class="btn btn-secondary" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </header>        
        <!-- ===== SISTEMA DE NAVEGACIÓN POR PESTAÑAS ===== -->
        <!-- Navegación principal con pestañas activas según sección actual -->
        <nav class="navigation-tabs">
            <!-- ===== PESTAÑA DASHBOARD ===== -->
            <!-- Vista principal con estadísticas y resumen general -->
            <a href="?seccion=dashboard" class="nav-tab <?php echo ($seccion === 'dashboard') ? 'active' : ''; ?>">
                Dashboard
            </a>
            
            <!-- ===== PESTAÑA GESTIÓN DE USUARIOS ===== -->
            <!-- Solo visible para administradores -->
            <a href="?seccion=usuarios" class="nav-tab <?php echo ($seccion === 'usuarios') ? 'active' : ''; ?>">
                Gestionar Usuarios
            </a>
            
            <!-- ===== PESTAÑA GESTIÓN DE DISPOSITIVOS ===== -->
            <!-- Administración de sensores Arduino -->
            <a href="?seccion=dispositivos" class="nav-tab <?php echo ($seccion === 'dispositivos') ? 'active' : ''; ?>">
                Gestionar Dispositivos
            </a>
            
            <!-- ===== ENLACE AL MONITOR PÚBLICO ===== -->
            <!-- Abre monitor en nueva ventana -->
            <a href="javascript:void(0)" onclick="goToPublicMonitor()" class="nav-tab">
                Monitor Público
            </a>
              <!-- ===== PESTAÑA DE INFORMES ===== -->
            <!-- Sistema de reportes y estadísticas -->
            <a href="?seccion=informes" class="nav-tab <?php echo ($seccion === 'informes') ? 'active' : ''; ?>">
                Informes
            </a>
              <!-- ===== PESTAÑA DE CONFIGURACIÓN ===== -->
            <!-- Configuración del sistema (solo admin) -->
            <?php if ($rol === 'admin'): ?>
            <a href="?seccion=configuracion" class="nav-tab <?php echo ($seccion === 'configuracion') ? 'active' : ''; ?>">
                Configuración
            </a>
            
            <!-- ===== PESTAÑA DE BACKUPS ===== -->
            <!-- Gestión de copias de seguridad (solo admin) -->
            <a href="?seccion=backups" class="nav-tab <?php echo ($seccion === 'backups') ? 'active' : ''; ?>">
                Backups
            </a>
            <?php endif; ?>
        </nav>
        <!-- ===== ÁREA DE CONTENIDO PRINCIPAL ===== -->
        <!-- Contenedor donde se carga dinámicamente el contenido de cada sección -->
        <main class="main-content">
            <div id="content-area" class="content-container">
                <?php
                // ===== SISTEMA DE ENRUTAMIENTO INTERNO =====
                // Incluir contenido según la sección seleccionada con control de acceso
                switch($seccion) {
                    
                    // ===== SECCIÓN USUARIOS (SOLO ADMIN) =====
                    case 'usuarios':
                        // Verificar permisos de administrador
                        if ($rol !== 'admin') {
                            echo '<div class="alert alert-error">No tiene permisos para acceder a esta sección.</div>';
                            // Fallback a dashboard si no tiene permisos
                            $seccion = 'dashboard';
                        } else {
                            // Incluir módulo de gestión de usuarios
                            include 'includes/usuarios.php';
                        }
                        break;
                    
                    // ===== SECCIÓN DISPOSITIVOS =====
                    case 'dispositivos':
                        // Incluir módulo de gestión de dispositivos/sensores
                        include 'includes/dispositivos.php';
                        break;
                      // ===== SECCIÓN INFORMES =====
                    case 'informes':
                        // Incluir módulo de reportes y estadísticas
                        include 'includes/informes.php';
                        break;
                      // ===== SECCIÓN CONFIGURACIÓN (SOLO ADMIN) =====
                    case 'configuracion':
                        // Verificar permisos de administrador
                        if ($rol !== 'admin') {
                            echo '<div class="alert alert-error">No tiene permisos para acceder a esta sección.</div>';
                            // Fallback a dashboard si no tiene permisos
                            $seccion = 'dashboard';
                        } else {
                            // Incluir módulo de configuración del sistema
                            include 'includes/configuracion.php';
                        }
                        break;
                    
                    // ===== SECCIÓN BACKUPS (SOLO ADMIN) =====
                    case 'backups':
                        // Verificar permisos de administrador
                        if ($rol !== 'admin') {
                            echo '<div class="alert alert-error">No tiene permisos para acceder a esta sección.</div>';
                            // Fallback a dashboard si no tiene permisos
                            $seccion = 'dashboard';
                        } else {
                            // Incluir módulo de gestión de backups completos
                            include 'includes/backup_completo.php';
                        }
                        break;
                    
                    // ===== SECCIÓN POR DEFECTO: DASHBOARD =====
                    default:
                        // Incluir panel principal con resumen y estadísticas
                        include 'includes/dashboard.php';
                        break;
                }
                ?>
            </div>
        </main>    </div>    
    <!-- ===== JAVASCRIPT PARA FUNCIONALIDAD INTERACTIVA ===== -->    <script>
        // ===== FUNCIONES DE NAVEGACIÓN =====
        
        // Función para abrir monitor público en nueva ventana
        function goToPublicMonitor() {
            if (confirm('Vas a salir del panel de administración. ¿Continuar?')) {
                // Abrir monitor público en nueva pestaña
                window.open('public.php', '_blank');
            }
        }
        
        // ===== SISTEMA DE AUTO-REFRESH =====
        // Auto-refresh opcional para ciertas secciones (comentado por defecto)
        <?php if (in_array($seccion, ['dashboard', 'dispositivos'])): ?>
        setInterval(function() {
            // Opcional: auto-refresh cada 60 segundos para dashboard y dispositivos
            // Descomentар para activar: location.reload();
        }, 60000);
        <?php endif; ?>
    </script>
</body>
</html>
