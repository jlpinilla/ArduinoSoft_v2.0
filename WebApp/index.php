<?php
/**
 * INDEX.PHP - Página de Inicio de Sesión
 * Sistema de Monitoreo Ambiental ArduinoSoft
 * 
 * Funcionalidad:
 * - Autenticación local contra base de datos MySQL/MariaDB
 * - Redirección a callback.php si login exitoso
 * - Redirección a configinicial.php si no hay configuración
 * - Validación de formularios con mensajes de error
 * - Cumplimiento WCAG 2.1 AA para accesibilidad
 */

// ===== INICIALIZACIÓN DE SESIÓN =====
// Iniciar sesión PHP para mantener estado del usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CONFIGURACIÓN DE ZONA HORARIA =====
// Cargar gestor de timezone centralizado
require_once 'includes/timezone_manager.php';

// ===== VERIFICACIÓN DE AUTENTICACIÓN PREVIA =====
// Si el usuario ya está logueado, redirigir al panel principal
if (isset($_SESSION['user_id']) && !isset($_GET['logout'])) {
    header('Location: panel.php');
    exit();
}

// ===== VERIFICACIÓN DE CONFIGURACIÓN DEL SISTEMA =====
// Si no existe config.ini, redirigir a configuración inicial
if (!file_exists('config.ini')) {
    header('Location: configinicial.php');
    exit();
}

// ===== CARGA DE CONFIGURACIÓN =====
// Cargar configuración desde archivo INI (base de datos, etc.)
$config = parse_ini_file('config.ini', true);

// ===== VALIDACIÓN DE CONFIGURACIÓN DE BASE DE DATOS =====
// Verificar que existan los parámetros mínimos de conexión
if (!isset($config['database']) || 
    empty($config['database']['host']) || 
    empty($config['database']['database']) || 
    empty($config['database']['user'])) {
    header('Location: configinicial.php');
    exit();
}

// ===== PRUEBA DE CONEXIÓN A BASE DE DATOS =====
// Verificar que la base de datos sea accesible antes de mostrar el formulario
try {
    $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['database']};charset={$config['database']['charset']}";
    if (isset($config['database']['port'])) {
        $dsn = "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['database']};charset={$config['database']['charset']}";
    }
    
    $test_pdo = new PDO(
        $dsn,
        $config['database']['user'],
        $config['database']['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar que la tabla usuarios existe
    $test_stmt = $test_pdo->query("SHOW TABLES LIKE 'usuarios'");
    if (!$test_stmt->fetch()) {
        // La tabla usuarios no existe, redirigir a configuración
        header('Location: configinicial.php');
        exit();
    }
      // Conexión exitosa
    $db_connected = true;
    // Eliminado mensaje de estado de base de datos MariaDB
    
} catch (PDOException $e) {
    // No se puede conectar a la base de datos, redirigir a configuración
    error_log("Database connection test failed: " . $e->getMessage());
    header('Location: configinicial.php');
    exit();
}

// ===== INICIALIZACIÓN DE VARIABLES PARA EL FORMULARIO =====
// Variables para mostrar mensajes de error y éxito al usuario
$error_message = '';
$success_message = '';

// ===== VERIFICAR SI VIENE DE LOGOUT =====
// Ya no se muestra mensaje de confirmación al cerrar sesión
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    // Eliminado mensaje de cierre de sesión exitoso
}

// ===== PROCESAMIENTO DEL FORMULARIO DE LOGIN =====
// Verificar si se envió el formulario mediante POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // ===== SANITIZACIÓN DE DATOS DE ENTRADA =====
    // Limpiar y obtener datos del formulario
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    
    // ===== VALIDACIÓN BÁSICA DE CAMPOS =====
    // Verificar que no estén vacíos los campos obligatorios
    if (empty($usuario) || empty($contrasena)) {
        $error_message = 'Por favor, complete todos los campos.';
    } else {          // ===== CONEXIÓN A BASE DE DATOS =====
        try {
            // Crear conexión PDO con configuración de charset y manejo de errores
            $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['database']};charset={$config['database']['charset']}";
            if (isset($config['database']['port'])) {
                $dsn = "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['database']};charset={$config['database']['charset']}";
            }
            
            $pdo = new PDO(
                $dsn,
                $config['database']['user'],
                $config['database']['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
              // ===== CONSULTA DE USUARIO EN BASE DE DATOS =====
            // Preparar consulta SQL para buscar usuario (previene SQL injection)
            $stmt = $pdo->prepare("SELECT id, usuario, contrasena, rol FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
              // ===== VERIFICACIÓN DE CREDENCIALES =====
            // Comparar contraseña (en producción usar password_hash/password_verify)
            if ($user && $user['contrasena'] === $contrasena) {
                  // ===== LOGIN EXITOSO - CREACIÓN DE SESIÓN =====
                // Regenerar ID de sesión por seguridad
                session_regenerate_id(true);
                
                // Almacenar datos del usuario en sesión PHP
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['login_time'] = time();
                
                // Redirigir a página de bienvenida
                header('Location: callback.php');
                exit();
            } else {
                // Credenciales incorrectas
                $error_message = 'Usuario o contraseña incorrectos.';
            }
            
        } catch (PDOException $e) {
            // ===== MANEJO DE ERRORES DE BASE DE DATOS =====
            // Mostrar mensaje genérico al usuario y loggear error específico
            $error_message = 'Error de conexión a la base de datos. Verifique la configuración.';
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>
<!-- ===== ESTRUCTURA HTML SEMÁNTICA ===== -->
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ===== META TAGS BÁSICOS ===== -->
    <!-- Configuración de caracteres y viewport para responsividad -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Inicio de Sesión - ArduinoSoft Monitor Ambiental</title>
    
    <!-- ===== FAVICON ===== -->
    <link rel="icon" type="image/png" sizes="32x32" href="media/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="media/logo.png">
    <link rel="apple-touch-icon" href="media/logo.png">
    <link rel="shortcut icon" href="media/logo.png">    <!-- ===== RECURSOS EXTERNOS ===== -->    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="login-pages.css">
    <!-- ===== SEO Y ACCESIBILIDAD ===== -->
    <!-- Meta descripción para motores de búsqueda -->
    <meta name="description" content="Sistema de monitoreo ambiental basado en Arduino - Inicio de sesión">
</head>

<!-- ===== CUERPO DE LA PÁGINA ===== -->
<!-- Clase especial para página de login con diseño horizontal -->
<body class="login-body index-page">
    
    <!-- ===== CONTENEDOR PRINCIPAL DE LOGIN ===== -->
    <!-- Diseño centrado y responsive para formulario de acceso -->    <div class="login-container">
        <div class="login-card">
            <!-- ===== SECCIÓN IZQUIERDA - BRANDING ===== -->
            <div class="left-section">
                <!-- ===== ENCABEZADO DE LA APLICACIÓN ===== -->                <header class="app-header">
                    <div class="logo-container">
                        <?php 
                        // Verificar si existe un logo personalizado en la configuración
                        $logo_path = 'media/logo.png'; // Logo por defecto
                        if (isset($config['sistema']['logo']) && !empty($config['sistema']['logo']) && file_exists($config['sistema']['logo'])) {
                            $logo_path = $config['sistema']['logo'];
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo ArduinoSoft" class="app-logo">
                    </div>
                    <div class="title-container"> 
                    <h1>ArduinoSoft Monitor</h1>
                        <p class="description-text">Arduino Based</p>
                    </div>
                </header>
                <div class="validation-badge">
                    <a href="https://jigsaw.w3.org/css-validator/check/referer">
                        <img style="border:0;width:70px;height:25px"
                            src="https://jigsaw.w3.org/css-validator/images/vcss"
                            alt="¡CSS Válido!" />
                    </a>
                </div>
            </div>
            
            <!-- ===== DIVISOR VERTICAL ===== -->
            <div class="vertical-divider"></div>
            
            <!-- ===== SECCIÓN DERECHA - FORMULARIO ===== -->
            <div class="right-section">
                <!-- ===== MENSAJES DEL SISTEMA ===== -->
                <!-- ===== MENSAJES DE ERROR ===== -->
                <?php if ($error_message): ?>
                    <div class="alert alert-error" role="alert" aria-live="polite" style="margin: 0 0 20px 0;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                  <!-- ===== MENSAJES DE ÉXITO ===== -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert" aria-live="polite" style="margin: 0 0 20px 0;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <!-- Estado de conexión a base de datos eliminado -->
                
                <!-- ===== CONTENIDO PRINCIPAL ===== -->
                <main>
                    <!-- ===== FORMULARIO DE AUTENTICACIÓN ===== -->                    <form method="POST" action="" novalidate>
                        <h2>Acceso al Sistema</h2>
                        
                        <!-- ===== CAMPO DE USUARIO ===== -->
                        <div class="form-group">
                            <label for="usuario" class="form-label">
                                Usuario:
                            </label>
                            <input 
                                type="text" 
                                id="usuario" 
                                name="usuario" 
                                class="form-control" 
                                required 
                                autocomplete="username"
                                aria-describedby="usuario-help"
                                value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                                placeholder="Nombre de usuario"
                            >
                            <small id="usuario-help" class="sr-only">Ingrese su nombre de usuario</small>
                        </div>
                        
                        <!-- ===== CAMPO DE CONTRASEÑA ===== -->
                        <div class="form-group">
                            <label for="contrasena" class="form-label">
                                Clave:
                            </label>
                            <input 
                                type="password" 
                                id="contrasena" 
                                name="contrasena" 
                                class="form-control" 
                                required 
                                autocomplete="current-password"
                                aria-describedby="contrasena-help"
                                placeholder="Contraseña"
                            >
                            <small id="contrasena-help" class="sr-only">Ingrese su contraseña</small>
                        </div>
                        
                        <!-- ===== BOTÓN DE ENVÍO ===== -->
                        <div class="btn-container">
                            <button type="submit" name="login" class="btn btn-primary">
                                Ingresar
                            </button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </div>
    
    <!-- ===== JAVASCRIPT PARA ACCESIBILIDAD ===== -->
    <!-- Mejoras de experiencia de usuario y accesibilidad -->
    <script>
        // Función para mejorar accesibilidad - Auto focus en primer campo
        document.addEventListener('DOMContentLoaded', function() {
            // Establecer foco en campo de usuario al cargar página
            document.getElementById('usuario').focus();
        });
    </script>
</body>
</html>
