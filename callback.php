<?php
// ===== INICIALIZACIÓN DE SESIÓN =====
// Continuar sesión PHP existente para verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== VERIFICACIÓN DE AUTENTICACIÓN =====
// Si no hay sesión activa, redirigir al login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ===== OBTENCIÓN DE DATOS DE USUARIO =====
// Extraer información del usuario desde variables de sesión con valores por defecto
// Utilizando operador de fusión null para evitar advertencias
$usuario = $_SESSION['usuario'] ?? 'Usuario';
$rol = $_SESSION['rol'] ?? 'Usuario';

// Sanitizar las variables por seguridad
$usuario = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
$rol = htmlspecialchars($rol, ENT_QUOTES, 'UTF-8');

// ===== CARGA DE CONFIGURACIÓN PARA LOGO =====
// Cargar configuración desde archivo INI para obtener el logo personalizado
$config = [];
if (file_exists('config.ini')) {
    $config = parse_ini_file('config.ini', true);
}

// Obtener información del sistema desde configuración
$sistema_nombre = $config['sistema']['nombre'] ?? 'Sistema de Monitoreo Ambiental';
$sistema_version = $config['sistema']['version'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArduinoSoft Monitor - Callback</title>
    <link rel="stylesheet" href="login-pages.css">
</head>
<body class="login-body callback-page">
    <div class="login-container">
        <div class="login-card">

            <!-- IZQUIERDA: Encabezado de Aplicación -->
            <div class="left-section">
			<!-- ===== ENCABEZADO DE APLICACIÓN ===== -->
                <header class="app-header">
                    <div class="logo-container">
                        <?php 
                        // Verificar si existe un logo personalizado en la configuración
                        $logo_path = 'media/logo.png'; // Logo por defecto
                        if (isset($config['sistema']['logo']) && !empty($config['sistema']['logo']) && file_exists($config['sistema']['logo'])) {
                            $logo_path = $config['sistema']['logo'];
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo ArduinoSoft" class="app-logo">
                    </div>                    <div class="title-container">
                        <h1>ArduinoSoft Monitor</h1>
                        <p class="description-text">Arduino Based</p>
                    </div>
                </header>
				<div class="alert alert-success welcome-alert" role="alert">
                    <h2>¡Bienvenido, <?php echo $usuario; ?>!</h2>
                    <p>Sesión iniciada correctamente</p>
                </div>
                <!-- ===== INFO DEL USUARIO ===== -->
                <div class="user-info">
                    <p><strong>Rol:</strong> <?php echo ucfirst($rol); ?></p>
                </div>
            </div>

            <!-- DIVISOR VERTICAL -->
            <div class="vertical-divider"></div>

            <!-- DERECHA: Contenido dinámico -->
            <div class="right-section">

                    <!-- ===== CONTADOR REGRESIVO VISUAL ===== -->
                    <div id="countdown-container" class="countdown-visual compact">
                        <div class="countdown-content">
                            <div class="countdown-circle">
                                <span id="countdown-number">5</span>
                            </div>
                            <div class="countdown-text">
                                <h3>Accediendo al Panel Principal</h3>
                                <p>Preparando su espacio de trabajo...</p>
                            </div>
                            <div class="countdown-status">
                                <span class="status-icon">⚡</span>
                                <div class="status-text">Cargando</div>
                            </div>
                        </div>
                        <!-- Barra de progreso visual mejorada -->
                        <div class="progress-bar-container">
                            <div id="progress" class="progress-bar"></div>
                        </div>
                    </div>

                    <!-- ===== INFORMACIÓN DEL SISTEMA Y BOTÓN DE ACCESO ===== -->
                    <div class="system-info-bar">
                        <div class="system-info">
                            <span class="system-info-item">
                                <span class="icon">🖥️</span> 
                         <p><?php echo htmlspecialchars($sistema_nombre); ?> • Versión <?php echo htmlspecialchars($sistema_version); ?></p>
                            </span>
                        </div>
                        <div class="access-button">
                            <a href="panel.php" class="btn btn-primary btn-sm">
                                Acceder al Panel
                            </a>
                        </div>
                    </div>

            </div>
        </div>
        <footer>
            <p>© <?= date('Y'); ?> ArduinoSoft Monitor. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script>
  // ===== VARIABLES GLOBALES =====
        let countdownTimer = null;
        let currentCountdown = 5;
        
        // ===== MENSAJES DEL COUNTDOWN =====
        const countdownMessages = [
            { 
                title: "Accediendo al Panel Principal", 
                subtitle: "Preparando su espacio de trabajo...",
                icon: "⚡",
                status: "Cargando"
            },
            { 
                title: "Verificando Configuración", 
                subtitle: "Cargando parámetros del sistema...",
                icon: "⚙️",
                status: "Config"
            },
            { 
                title: "Verificando Permisos", 
                subtitle: "Configurando acceso al sistema...",
                icon: "🔐",
                status: "Validando"
            },
            { 
                title: "Iniciando Sesión", 
                subtitle: "Cargando datos del usuario...",
                icon: "👤",
                status: "Iniciando"
            },
            { 
                title: "¡Listo para Comenzar!", 
                subtitle: "Redirigiendo al panel...",
                icon: "✅",
                status: "Completo"
            }
        ];
        
        // ===== FUNCIÓN PARA ACTUALIZAR INTERFAZ =====
        function updateCountdownInterface(messageIndex) {
            try {
                const message = countdownMessages[messageIndex];
                if (!message) return;
                
                const titleElement = document.querySelector('.countdown-text h3');
                const subtitleElement = document.querySelector('.countdown-text p');
                const iconElement = document.querySelector('.status-icon');
                const statusElement = document.querySelector('.status-text');
                
                if (titleElement) titleElement.textContent = message.title;
                if (subtitleElement) subtitleElement.textContent = message.subtitle;
                if (iconElement) iconElement.textContent = message.icon;
                if (statusElement) statusElement.textContent = message.status;
                
                console.log(`Interfaz actualizada: ${message.title}`);
            } catch (error) {
                console.error('Error actualizando interfaz:', error);
            }
        }
        
        // ===== FUNCIÓN PRINCIPAL DEL COUNTDOWN =====
        function startCountdown() {
            console.log('🚀 Iniciando countdown desde', currentCountdown);
            
            // Elementos del DOM
            const numberElement = document.getElementById('countdown-number');
            const progressElement = document.getElementById('progress');
            
            if (!numberElement || !progressElement) {
                console.error('❌ Elementos del countdown no encontrados');
                // Redirección de emergencia
                setTimeout(() => window.location.href = 'panel.php', 1000);
                return;
            }
            
            // Inicializar interfaz
            updateCountdownInterface(0);
            
            // Iniciar barra de progreso
            setTimeout(() => {
                progressElement.style.width = '20%';
            }, 200);
            
            // Timer principal
            countdownTimer = setInterval(() => {
                console.log(`⏰ Countdown tick: ${currentCountdown} -> ${currentCountdown - 1}`);
                
                currentCountdown--;
                numberElement.textContent = currentCountdown;
                      // Actualizar barra de progreso
                const progressPercent = ((5 - currentCountdown) / 5) * 100;
                progressElement.style.width = progressPercent + '%';
                console.log(`Progreso: ${progressPercent}%`);
                
                // Actualizar mensaje si countdown > 0
                if (currentCountdown > 0) {
                    const messageIndex = 5 - currentCountdown;
                    if (messageIndex < countdownMessages.length) {
                        updateCountdownInterface(messageIndex);
                    }
                }
                
                // Cuando llega a 0
                if (currentCountdown <= 0) {
                    console.log('🎯 Countdown completado');
                    clearInterval(countdownTimer);
                    
                    // Estado final
                    updateCountdownInterface(4); // Último mensaje
                    numberElement.textContent = '✓';
                    numberElement.style.color = '#28a745';
                    numberElement.style.fontSize = '28px';
                    
                    // Completar progreso
                    progressElement.style.width = '100%';
                    progressElement.style.background = 'linear-gradient(90deg, #28a745 0%, #20c997 100%)';
                    
                    // Redirección
                    setTimeout(() => {
                        console.log('🔄 Redirigiendo a panel.php');
                        window.location.href = 'panel.php';
                    }, 1000);
                }
            }, 1000);
        }
        
        // ===== INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ DOM cargado, iniciando countdown en 500ms');
            
            // Pequeña pausa para asegurar que todo esté renderizado
            setTimeout(() => {
                try {
                    startCountdown();
                } catch (error) {
                    console.error('Error al iniciar countdown:', error);
                    // En caso de error, redirigir inmediatamente
                    window.location.href = 'panel.php';
                }
            }, 500);
            
            // Fallback de seguridad (reducido a 6 segundos)
            setTimeout(() => {
                if (window.location.pathname.includes('callback.php')) {
                    console.log('⚠️ Fallback activado - redirigiendo');
                    window.location.href = 'panel.php';
                }
            }, 6000);
            
            // Agregar evento al botón de acceso manual
            const accessBtn = document.querySelector('.access-button a');
            if (accessBtn) {
                accessBtn.addEventListener('click', function(e) {
                    console.log('Redirección manual activada');
                    // No es necesario preventDefault ya que queremos que el enlace funcione normalmente
                });
            }
        });
        
        // ===== DEBUG INFO =====
        console.log('📋 Callback.php JavaScript cargado');
    </script>
    
    <!-- Fallback para usuarios sin JavaScript -->
    <noscript>
        <div class="noscript-warning" style="text-align: center; padding: 20px; background: #f8d7da; color: #721c24; margin: 10px;">
            <p><strong>JavaScript está deshabilitado</strong></p>
            <p>Este sistema requiere JavaScript para funcionar correctamente.</p>
            <a href="panel.php" style="display: inline-block; padding: 10px 15px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;">
                Continuar al Panel Principal
            </a>
        </div>
    </noscript>
</body>
</html>