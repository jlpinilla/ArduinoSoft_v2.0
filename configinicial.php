<?php
/**
 * =====================================================
 * CONFIGURACIÓN INICIAL DEL SISTEMA - SUITE AMBIENTAL
 * =====================================================
 * Archivo de setup y configuración inicial del sistema
 * de monitoreo ambiental Arduino.
 * 
 * Funcionalidades principales:
 * - Configuración inicial cuando el sistema no está configurado
 * - Formulario interactivo para datos de conexión a BD
 * - Creación automática del archivo config.ini
 * - Validación de conexión a base de datos
 * - Creación automática de tablas base del sistema
 * - Configuración de usuario administrador inicial
 */

// ===== VARIABLES DE CONTROL =====
$error_message = '';
$success_message = '';
$config_exists = file_exists('config.ini');

// ===== VERIFICACIÓN DE CONFIGURACIÓN EXISTENTE =====
// Si ya existe configuración válida, redirigir al sistema principal
if ($config_exists) {
    $config = parse_ini_file('config.ini', true);
    // Verificar que la configuración esté completa
    if (!empty($config['database']['host']) && !empty($config['database']['database'])) {
        header('Location: index.php');
        exit();
    }
}

// ===== PROCESAMIENTO DEL FORMULARIO DE CONFIGURACIÓN =====
// Manejar el envío del formulario de configuración inicial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configurar'])) {            // === Obtener datos del formulario ===
            $db_host = trim($_POST['db_host'] ?? '');
            $db_port = trim($_POST['db_port'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_password = $_POST['db_password'] ?? '';
            $db_name = trim($_POST['db_name'] ?? '');
    
    // === Validar campos obligatorios ===
    if (empty($db_host) || empty($db_user) || empty($db_name)) {
        $error_message = 'Por favor, complete todos los campos obligatorios (Host, Usuario y Base de Datos).';
    } else {        try {
            // ===== PRUEBA DE CONEXIÓN A BASE DE DATOS =====
            // Intentar conectar con los parámetros proporcionados
            $dsn = "mysql:host={$db_host};charset=utf8mb4";
            if (isset($_POST['db_port']) && !empty($_POST['db_port'])) {
                $dsn = "mysql:host={$db_host};port={$_POST['db_port']};charset=utf8mb4";
            }
            
            $pdo = new PDO(
                $dsn,
                $db_user,
                $db_password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // ===== CREACIÓN DE BASE DE DATOS =====
            // Crear la base de datos con charset UTF-8 español
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci");
            $pdo->exec("USE `{$db_name}`");
            
            // ===== CREACIÓN DE TABLAS BÁSICAS =====
            // Crear tabla de usuarios (compatible con nueva estructura)
            $sql_users = "CREATE TABLE IF NOT EXISTS `usuarios` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `usuario` varchar(50) NOT NULL UNIQUE,
                `contrasena` varchar(255) NOT NULL,
                `rol` enum('admin','operador') NOT NULL DEFAULT 'operador',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
            
            // Crear tabla de dispositivos
            $sql_dispositivos = "CREATE TABLE IF NOT EXISTS `dispositivos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nombre` varchar(100) NOT NULL,
                `ubicacion` varchar(200) DEFAULT NULL,
                `direccion_ip` varchar(45) DEFAULT NULL,
                `direccion_mac` varchar(17) DEFAULT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
            
            // Crear tabla de registros ambientales
            $sql_registros = "CREATE TABLE IF NOT EXISTS `registros` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `sensor_id` varchar(100) NOT NULL,
                `temperatura` decimal(5,2) DEFAULT NULL,
                `humedad` decimal(5,2) DEFAULT NULL,
                `ruido` decimal(5,2) DEFAULT NULL,
                `co2` int(11) DEFAULT NULL,
                `lux` int(11) DEFAULT NULL,
                `fecha_hora` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sensor_fecha` (`sensor_id`, `fecha_hora`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
            
            // === Ejecutar creación de tablas ===
            $pdo->exec($sql_users);
            $pdo->exec($sql_dispositivos);
            $pdo->exec($sql_registros);
            
            // ===== CREAR USUARIO ADMINISTRADOR INICIAL =====
            // Verificar si ya existe un usuario administrador
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'");
            $stmt->execute();
            $admin_exists = $stmt->fetchColumn() > 0;
              if (!$admin_exists) {
                // Crear usuario administrador por defecto (usando contraseña sin hash para compatibilidad)
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)");
                $stmt->execute(['admin', 'admin123', 'admin']);
            }
            
            // ===== GENERACIÓN DEL ARCHIVO CONFIG.INI =====
            // Crear contenido del archivo de configuración
            $config_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
            $config_content .= "; Generado automáticamente el " . date('Y-m-d H:i:s') . "\n\n";              // === Sección de Base de Datos ===
            $config_content .= "[database]\n";
            $config_content .= "host = \"{$db_host}\"\n";
            if (!empty($db_port)) {
                $config_content .= "port = {$db_port}\n";
            }
            $config_content .= "user = \"{$db_user}\"\n";
            $config_content .= "password = \"{$db_password}\"\n";
            $config_content .= "database = \"{$db_name}\"\n";
            $config_content .= "charset = \"utf8mb4\"\n";
            $config_content .= "timezone = \"+02:00\"\n\n";
            
            // === Sección de Sistema ===
            $config_content .= "[sistema]\n";
            $config_content .= "nombre = \"Suite Ambiental\"\n";
            $config_content .= "version = \"2.0\"\n";
            $config_content .= "timezone = \"+02:00\"\n";
            $config_content .= "log_level = \"info\"\n\n";
            
            // === Sección de Referencias Ambientales ===
            $config_content .= "[referencias]\n";
            $config_content .= "temperatura_max = 25\n";
            $config_content .= "humedad_max = 48\n";
            $config_content .= "ruido_max = 35\n";
            $config_content .= "co2_max = 1000\n";
            $config_content .= "lux_min = 195\n\n";
            
            // === Sección de Configuración Pública ===
            $config_content .= "[publico]\n";
            $config_content .= "; Configuración específica para el monitor público\n";
            $config_content .= "refresh_interval = 60\n";
            $config_content .= "display_mode = \"fullscreen\"\n";
            $config_content .= "distance_optimization = \"2m\"\n";
            $config_content .= "screen_ratio = \"16:9\"\n";
            
            // === Guardar archivo de configuración ===
            if (file_put_contents('config.ini', $config_content)) {
                $success_message = 'Configuración completada exitosamente. Puede proceder al inicio de sesión.';
                
                // ===== LOGGING DEL PROCESO DE CONFIGURACIÓN =====
                $log_entry = "\n### " . date('Y-m-d H:i:s') . " - Configuración Inicial Completada\n";
                $log_entry .= "**Proceso:** Configuración Automática del Sistema\n";
                $log_entry .= "**Cambios realizados:**\n";
                $log_entry .= "- Base de datos configurada: {$db_name}\n";
                $log_entry .= "- Tablas creadas: usuarios, dispositivos, registros\n";
                $log_entry .= "- Usuario administrador creado (admin/admin123)\n";
                $log_entry .= "- Archivo config.ini generado automáticamente\n";                $log_entry .= "- Charset configurado: UTF-8 español\n";
                $log_entry .= "---\n";
                
                file_put_contents('logs/cambios.log', $log_entry, FILE_APPEND);
            } else {
                $error_message = 'Error al crear el archivo de configuración. Verifique los permisos del directorio.';
            }
            
        } catch (PDOException $e) {
            $error_message = 'Error de conexión a base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Inicial - Suite Ambiental</title>
    <link rel="stylesheet" href="styles.css">
    <meta name="description" content="Configuración inicial del sistema de monitoreo ambiental Arduino">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="content-container">
            <!-- ===== ENCABEZADO DE LA PÁGINA DE CONFIGURACIÓN ===== -->
            <header class="text-center">
                <h1>🏭 Suite Ambiental</h1>
                <h2 class="page-title">⚙️ Configuración Inicial del Sistema</h2>
                <p>Configure los parámetros básicos para comenzar a usar el sistema</p>
            </header>
            
            <main>
                <!-- === Mensaje de éxito === -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                    <div class="text-center mt-xl">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            Ir al Inicio de Sesión
                        </a>
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <p><strong>Bienvenido al sistema de configuración inicial</strong></p>
                        <p>Este asistente le ayudará a configurar la conexión a la base de datos y crear las tablas necesarias para el funcionamiento del sistema.</p>
                    </div>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-error" role="alert" aria-live="polite">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" novalidate>
                        <div class="form-section">
                            <h3>Configuración de Base de Datos</h3>
                              <div class="form-row">
                                <div class="form-group">
                                    <label for="db_host" class="form-label">
                                        Servidor (Host) <span aria-label="requerido">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="db_host" 
                                        name="db_host" 
                                        class="form-control" 
                                        required 
                                        value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>"
                                        placeholder="localhost o IP del servidor"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_port" class="form-label">
                                        Puerto
                                    </label>
                                    <input 
                                        type="number" 
                                        id="db_port" 
                                        name="db_port" 
                                        class="form-control" 
                                        value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3307'); ?>"
                                        placeholder="3306 o 3307"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_name" class="form-label">
                                        Nombre de Base de Datos <span aria-label="requerido">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="db_name" 
                                        name="db_name" 
                                        class="form-control" 
                                        required 
                                        value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'suite_ambiental'); ?>"
                                        placeholder="suite_ambiental"
                                    >
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="db_user" class="form-label">
                                        Usuario de Base de Datos <span aria-label="requerido">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="db_user" 
                                        name="db_user" 
                                        class="form-control" 
                                        required 
                                        value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>"
                                        placeholder="Usuario de MySQL"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_password" class="form-label">
                                        Contraseña de Base de Datos
                                    </label>
                                    <input 
                                        type="password" 
                                        id="db_password" 
                                        name="db_password" 
                                        class="form-control" 
                                        value="<?php echo htmlspecialchars($_POST['db_password'] ?? ''); ?>"
                                        placeholder="Contraseña de MySQL (opcional)"
                                    >
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Usuario Administrador</h3>
                            <p>Se creará un usuario administrador por defecto si no existe ninguno:</p>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="admin_user" class="form-label">Usuario Administrador</label>
                                    <input 
                                        type="text" 
                                        id="admin_user" 
                                        name="admin_user" 
                                        class="form-control" 
                                        value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="admin_pass" class="form-label">Contraseña Administrador</label>
                                    <input 
                                        type="password" 
                                        id="admin_pass" 
                                        name="admin_pass" 
                                        class="form-control" 
                                        value="<?php echo htmlspecialchars($_POST['admin_pass'] ?? 'admin123'); ?>"
                                    >
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-xl">
                            <button type="submit" name="configurar" class="btn btn-primary btn-lg">
                                Configurar Sistema
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </main>
            
            <footer class="text-center" style="margin-top: 24px; font-size: 0.9em; color: var(--text-light);">
                <p>ArduinoSoft Monitor © 2025 - Configuración Inicial</p>
            </footer>
        </div>
    </div>
</body>
</html>
