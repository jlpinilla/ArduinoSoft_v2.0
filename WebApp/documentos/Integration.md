# DOCUMENTO DE INTEGRACIÓN TÉCNICA
# ArduinoSoft - Sistema de Control Ambiental v3.1

**Fecha de Creación:** 12 de Junio de 2025  
**Proyecto:** Sistema de Control Ambiental  
**Cliente:** Grupo Sorolla Educación - La Devesa School Elche  
**Versión:** 3.1  

---

## ÍNDICE

1. [Arquitectura General del Sistema](#arquitectura-general-del-sistema)
2. [Estructura por Páginas](#estructura-por-páginas)
3. [Componentes Tecnológicos](#componentes-tecnológicos)
4. [Flujo de Datos](#flujo-de-datos)
5. [APIs y Operaciones AJAX](#apis-y-operaciones-ajax)
6. [Integración de Base de Datos](#integración-de-base-de-datos)
7. [Sistema de Configuración](#sistema-de-configuración)
8. [Manejo de Sesiones](#manejo-de-sesiones)

---

## ARQUITECTURA GENERAL DEL SISTEMA

### Stack Tecnológico
- **Backend:** PHP 8.x con programación orientada a objetos
- **Frontend:** HTML5 semántico, CSS3 moderno, JavaScript ES6+
- **Base de Datos:** MySQL/MariaDB con charset UTF8MB4
- **Servidor:** Apache 2.4 (WAMP Stack)
- **Gestión de Estado:** Sesiones PHP + Local Storage
- **Comunicación:** AJAX con fetch API y JSON

### Patrón de Arquitectura
- **MVC Híbrido:** Separación de lógica, presentación y datos
- **Modular:** Cada funcionalidad en archivos separados
- **Responsive First:** Mobile-first approach
- **Progresivo:** Degradación elegante sin JavaScript

---

## ESTRUCTURA POR PÁGINAS

### 1. index.php - Página de Autenticación

#### Estructura Técnica:
```php
<?php
// Manejo de sesiones
session_start();

// Redirección si ya está autenticado
if (isset($_SESSION['usuario'])) {
    header("Location: panel.php");
    exit();
}

// Procesamiento del formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación y autenticación
    include 'includes/auth.php';
}
?>
```

#### Tecnologías Implementadas:
- **PHP Sessions:** Manejo de estado de autenticación
- **Password Hashing:** Verificación segura con `password_verify()`
- **SQL Prepared Statements:** Prevención de inyección SQL
- **HTML5 Form Validation:** Validación nativa del navegador
- **CSS Grid/Flexbox:** Layout responsive centrado

#### Funcionalidades:
1. **Autenticación de Usuario:**
   - Validación de campos requeridos
   - Verificación de credenciales en base de datos
   - Creación de sesión segura
   - Redirección basada en rol

2. **Validación de Entrada:**
   - Sanitización de datos con `filter_var()`
   - Escape de caracteres especiales
   - Longitud mínima/máxima de campos

3. **Manejo de Errores:**
   - Mensajes de error específicos
   - Logging de intentos fallidos
   - Rate limiting básico

#### Flujo de Datos:
```
Usuario → Formulario → PHP Validation → Base de Datos → Session → Redirección
```

---

### 2. panel.php - Dashboard Principal

#### Estructura Técnica:
```php
<?php
// Control de acceso
require_once 'includes/auth_check.php';

// Carga de configuración
$config = parse_ini_file('config.ini', true);

// Determinación de sección activa
$seccion = $_GET['seccion'] ?? 'dashboard';

// Sistema de enrutamiento
switch($seccion) {
    case 'usuarios':
        $content = 'includes/usuarios.php';
        break;
    case 'dispositivos':
        $content = 'includes/dispositivos.php';
        break;
    // ... más casos
}
?>
```

#### Tecnologías Implementadas:
- **Routing System:** Enrutamiento interno con $_GET
- **Dynamic Content Loading:** Inclusión condicional de módulos
- **Permission Checking:** Verificación de roles por sección
- **Configuration Management:** Lectura de config.ini
- **Template System:** Header/Footer reutilizables

#### Funcionalidades:
1. **Sistema de Navegación:**
   - Pestañas dinámicas según permisos
   - Estado activo visual
   - Breadcrumb navigation

2. **Control de Acceso:**
   - Verificación de sesión activa
   - Restricciones por rol de usuario
   - Timeout de sesión automático

3. **Carga Dinámica:**
   - Contenido modular por sección
   - Preservación de estado entre navegación
   - Cache de configuración

#### Estructura de Layout:
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Meta tags, CSS, scripts -->
</head>
<body>
    <header class="main-header">
        <!-- Logo, usuario, navegación -->
    </header>
    
    <main class="main-content">
        <nav class="sidebar">
            <!-- Navegación por pestañas -->
        </nav>
        
        <section class="content-area">
            <?php include $content; ?>
        </section>
    </main>
    
    <footer class="main-footer">
        <!-- Info del sistema, enlaces -->
    </footer>
</body>
</html>
```

---

### 3. public.php - Monitor Público

#### Estructura Técnica:
```php
<?php
// Sin autenticación requerida
// Carga de configuración pública
$config = parse_ini_file('config.ini', true);
$public_config = $config['publico'];

// Conexión a base de datos para datos en tiempo real
require_once 'includes/database_manager.php';
$db = getDBManager($config);
?>
```

#### Tecnologías Implementadas:
- **Real-time Data:** Consultas en tiempo real sin cache
- **Auto-refresh:** JavaScript setInterval para actualización
- **Responsive Design:** CSS Grid para múltiples dispositivos
- **Accessibility:** ARIA labels, semantic HTML, keyboard navigation
- **Progressive Enhancement:** Funcional sin JavaScript

#### Funcionalidades:
1. **Visualización en Tiempo Real:**
   - Datos actualizados cada 60 segundos (configurable)
   - Indicadores visuales de estado
   - Alertas por valores fuera de rango

2. **Accesibilidad Completa:**
   - Navegación por teclado (Tab, Enter, Escape)
   - Screen reader compatibility
   - Alto contraste configurable
   - Escalado de fuentes

3. **Personalización Corporativa:**
   - Colores del Grupo Sorolla Educación
   - Logo institucional
   - Textos personalizables

#### JavaScript para Auto-actualización:
```javascript
// Auto-refresh cada intervalo configurado
setInterval(async function() {
    try {
        const response = await fetch('api/get_sensor_data.php');
        const data = await response.json();
        updateDisplay(data);
    } catch (error) {
        console.error('Error updating data:', error);
    }
}, 60000); // 60 segundos por defecto
```

---

### 4. includes/dashboard.php - Panel Principal

#### Estructura Técnica:
```php
<?php
// Obtención de estadísticas optimizada
require_once 'database_manager.php';
$db_manager = getDBManager($config);

// Consulta única para todas las estadísticas
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM dispositivos) as total_dispositivos,
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        -- ... más subconsultas optimizadas
";
$stats = $db_manager->fetchRow($stats_query, []);
?>
```

#### Tecnologías Implementadas:
- **Database Manager:** Clase optimizada para consultas
- **Single Query Optimization:** Una consulta para múltiples estadísticas
- **Dynamic Content:** Información en tiempo real del sistema
- **Card-based Layout:** CSS Grid para tarjetas informativas
- **Real-time Alerts:** Sistema de alertas basado en umbrales

#### Funcionalidades:
1. **Estadísticas del Sistema:**
   - Total de dispositivos registrados
   - Dispositivos activos en la última hora
   - Registros procesados hoy/semana
   - Estado de conectividad general

2. **Tarjetas Informativas:**
   - Navegación rápida a secciones
   - Información contextual por módulo
   - Restricciones visuales por rol

3. **Sistema de Alertas:**
   - Monitoreo de últimas 2 horas
   - Comparación con umbrales configurados
   - Detalle por tipo de sensor

#### CSS Grid para Tarjetas:
```css
.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.menu-card {
    background: var(--card-background);
    border-radius: 12px;
    padding: 20px;
    transition: transform 0.3s ease;
    cursor: pointer;
}
```

---

### 5. includes/usuarios.php - Gestión de Usuarios

#### Estructura Técnica:
```php
<?php
// Control de acceso administrativo
if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="alert alert-error">Acceso denegado</div>';
    return;
}

// Procesamiento de operaciones CRUD
$action = $_GET['action'] ?? 'list';

switch($action) {
    case 'create':
        // Lógica de creación
        break;
    case 'edit':
        // Lógica de edición
        break;
    case 'delete':
        // Lógica de eliminación
        break;
    default:
        // Lista de usuarios
}
?>
```

#### Tecnologías Implementadas:
- **CRUD Operations:** Create, Read, Update, Delete completo
- **Form Validation:** Validación client-side y server-side
- **Password Hashing:** Almacenamiento seguro con `password_hash()`
- **Role Management:** Sistema de roles admin/operador
- **Search Functionality:** Búsqueda en tiempo real con JavaScript

#### Funcionalidades:
1. **Gestión Completa de Usuarios:**
   - Listado con paginación
   - Búsqueda y filtrado
   - Creación con validación
   - Edición de datos existentes
   - Eliminación con confirmación

2. **Validaciones Robustas:**
   - Unicidad de nombres de usuario
   - Complejidad de contraseñas
   - Sanitización de datos de entrada
   - Prevención de ataques XSS

3. **Interfaz Intuitiva:**
   - Modales para formularios
   - Confirmaciones de acciones destructivas
   - Feedback visual inmediato

#### JavaScript para Búsqueda:
```javascript
function searchUsers() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
```

---

### 6. includes/dispositivos.php - Gestión de Dispositivos

#### Estructura Técnica:
```php
<?php
// Obtención de datos de dispositivos con última lectura
$devices_query = "
    SELECT d.*, 
           r.temperatura, r.humedad, r.ruido, r.co2, r.lux,
           r.fecha_hora as ultimo_registro
    FROM dispositivos d
    LEFT JOIN registros r ON d.nombre = r.sensor_id
    WHERE r.fecha_hora = (
        SELECT MAX(fecha_hora) 
        FROM registros r2 
        WHERE r2.sensor_id = d.nombre
    )
    ORDER BY d.nombre
";
?>
```

#### Tecnologías Implementadas:
- **JOIN Queries:** Combinación de datos de dispositivos y registros
- **Real-time Status:** Estado actual basado en última actividad
- **Individual Monitoring:** Vista detallada por dispositivo
- **Chart Integration:** Gráficos con Chart.js para tendencias
- **AJAX Updates:** Actualización sin recarga de página

#### Funcionalidades:
1. **Lista de Dispositivos:**
   - Estado en tiempo real
   - Última actividad registrada
   - Indicadores visuales de conectividad
   - Información de red (IP/MAC)

2. **Monitorización Individual:**
   - Gráficos de tendencias (24h)
   - Valores actuales vs umbrales
   - Historial de alertas
   - Estadísticas detalladas

3. **Gestión de Configuración:**
   - Edición de ubicación
   - Configuración de parámetros
   - Activación/desactivación
   - Diagnósticos de conectividad

#### Chart.js Integration:
```javascript
const chartConfig = {
    type: 'line',
    data: {
        labels: timeLabels,
        datasets: [{
            label: 'Temperatura',
            data: temperatureData,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: false
            }
        }
    }
};
```

---

### 7. includes/informes.php - Informes y Estadísticas

#### Estructura Técnica:
```php
<?php
// Generación de informes con filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$dispositivo = $_GET['dispositivo'] ?? 'todos';

// Query con filtros dinámicos
$report_query = "
    SELECT r.*, d.ubicacion
    FROM registros r
    INNER JOIN dispositivos d ON r.sensor_id = d.nombre
    WHERE r.fecha_hora BETWEEN ? AND ?
    " . ($dispositivo !== 'todos' ? "AND r.sensor_id = ?" : "") . "
    ORDER BY r.fecha_hora DESC
";
?>
```

#### Tecnologías Implementadas:
- **Dynamic Filtering:** Filtros dinámicos con SQL prepared statements
- **CSV Export:** Generación de archivos CSV para descarga
- **Chart Visualization:** Múltiples tipos de gráficos con Chart.js
- **Statistical Analysis:** Cálculos de promedios, máximos, mínimos
- **Responsive Tables:** Tablas adaptativas con scroll horizontal

#### Funcionalidades:
1. **Filtros Avanzados:**
   - Rango de fechas personalizable
   - Filtro por dispositivo específico
   - Filtro por tipo de sensor
   - Filtro por valores de alerta

2. **Exportación de Datos:**
   - Generación CSV con headers personalizados
   - Descarga directa desde navegador
   - Formato compatible con Excel
   - Metadatos incluidos

3. **Visualización de Tendencias:**
   - Gráficos de líneas para tendencias temporales
   - Gráficos de barras para comparaciones
   - Gráficos de área para rangos
   - Interactividad con zoom y tooltip

#### CSV Export Function:
```php
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Fecha', 'Dispositivo', 'Temperatura', 'Humedad', 'Ruido', 'CO2', 'Lux']);
    
    // Data rows
    foreach($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}
```

---

### 8. includes/configuracion.php - Configuración del Sistema

#### Estructura Técnica:
```php
<?php
// Carga de configuración actual
$config_actual = parse_ini_file('config.ini', true);

// Procesamiento de cambios de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Backup automático antes de cambios
    $backup_filename = 'configbackup/config_back_' . date('y_m_d_H_i') . '.ini';
    copy('config.ini', $backup_filename);
    
    // Aplicación de nuevos valores
    updateConfiguration($_POST);
}
?>
```

#### Tecnologías Implementadas:
- **INI File Management:** Lectura/escritura de archivos de configuración
- **File Upload Handling:** Gestión de logos con validación
- **Automatic Backup:** Backup automático antes de cambios
- **Real-time Preview:** Vista previa de cambios en tiempo real
- **AJAX Operations:** Operaciones sin recarga de página

#### Funcionalidades:
1. **Configuración General:**
   - Nombre del sistema editable
   - Versión del sistema
   - Zona horaria configurable
   - Nivel de logging

2. **Gestión de Logo:**
   - Subida de archivos de imagen
   - Vista previa en tiempo real
   - Validación de formato y tamaño
   - Restauración al logo original

3. **Umbrales Ambientales:**
   - Configuración de límites por sensor
   - Validación de rangos permitidos
   - Aplicación inmediata al sistema
   - Historial de cambios

4. **Monitor Público:**
   - Personalización de textos
   - Configuración de colores corporativos
   - Vista previa en tiempo real
   - Tiempo de actualización configurable

#### Configuration Update Function:
```php
function updateConfiguration($data) {
    global $config_actual, $_SESSION;
    
    // Validaciones
    validateConfigurationData($data);
    
    // Generación del nuevo archivo
    $config_content = "; Configuración del Sistema de Monitoreo Ambiental\n";
    $config_content .= "; Actualizado el " . date('Y-m-d H:i:s') . " por " . $_SESSION['usuario'] . "\n\n";
    
    // Escritura de secciones
    foreach($sections as $section => $values) {
        $config_content .= "[{$section}]\n";
        foreach($values as $key => $value) {
            $config_content .= "{$key} = \"{$value}\"\n";
        }
        $config_content .= "\n";
    }
    
    // Guardado del archivo
    file_put_contents('config.ini', $config_content);
}
```

---

### 9. includes/backups.php - Sistema de Backups

#### Estructura Técnica:
```php
<?php
// Diferentes tipos de backup disponibles
function createProjectBackup() {
    $backup_name = 'project_backup_' . date('Y-m-d_H-i-s') . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($backup_name, ZipArchive::CREATE) === TRUE) {
        addFilesToZip($zip, '.');
        $zip->close();
        return $backup_name;
    }
}

function createDatabaseBackup() {
    global $config;
    $backup_name = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    $command = "mysqldump --host={$config['database']['host']} " .
               "--user={$config['database']['user']} " .
               "--password={$config['database']['password']} " .
               "{$config['database']['database']} > {$backup_name}";
    
    exec($command);
    return $backup_name;
}
?>
```

#### Tecnologías Implementadas:
- **ZipArchive:** Compresión de archivos de proyecto
- **mysqldump:** Exportación de base de datos
- **File Management:** Gestión de archivos de backup
- **Download Headers:** Descarga directa desde navegador
- **Logging System:** Registro detallado de operaciones

#### Funcionalidades:
1. **Backup de Proyecto:**
   - Compresión de todos los archivos PHP, CSS, JS
   - Inclusión de configuraciones y media
   - Metadatos del sistema incluidos
   - Descarga automática

2. **Backup de Base de Datos:**
   - Exportación SQL completa
   - Estructura y datos incluidos
   - Compresión automática
   - Compatible con importación estándar

3. **Gestión de Archivos:**
   - Listado de backups disponibles
   - Información detallada por archivo
   - Eliminación selectiva
   - Limpieza automática por antigüedad

---

## COMPONENTES TECNOLÓGICOS

### 1. Database Manager (includes/database_manager.php)

#### Características:
```php
class DatabaseManager {
    private $pdo;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    private function connect() {
        $dsn = "mysql:host={$this->config['host']};" .
               "port={$this->config['port']};" .
               "dbname={$this->config['database']};" .
               "charset={$this->config['charset']}";
        
        $this->pdo = new PDO($dsn, $this->config['user'], $this->config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function fetchRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
```

#### Funcionalidades:
- **Connection Pooling:** Reutilización de conexiones
- **Prepared Statements:** Prevención de SQL injection
- **Error Handling:** Manejo robusto de errores
- **Transaction Support:** Soporte para transacciones

### 2. Configuration Operations (includes/configuracion_operations.php)

#### AJAX Endpoints:
```php
// Resetear logo al original
case 'reset_logo':
    resetLogoToOriginal();
    break;

// Cambiar archivo de logo
case 'change_logo':
    changeLogoFile();
    break;

// Actualizar configuración del sistema
case 'update_system_config':
    updateSystemConfig();
    break;

// Actualizar configuración pública
case 'update_public_config':
    updatePublicConfig();
    break;
```

#### Características:
- **JSON Responses:** Respuestas estandarizadas en JSON
- **File Validation:** Validación robusta de archivos subidos
- **Backup Integration:** Backup automático antes de cambios
- **Error Handling:** Manejo detallado de errores

---

## FLUJO DE DATOS

### 1. Autenticación
```
Login Form → PHP Validation → Database Query → Session Creation → Dashboard Redirect
```

### 2. Monitoreo en Tiempo Real
```
Arduino Sensors → Database Insert → AJAX Request → JSON Response → UI Update
```

### 3. Configuración
```
Form Submission → Validation → Backup Creation → Config Update → Database Apply → UI Refresh
```

### 4. Backup Process
```
User Request → File Collection → Compression → Metadata Addition → Download Trigger
```

---

## APIS Y OPERACIONES AJAX

### Endpoints Disponibles:

#### configuracion_operations.php:
- `POST /reset_logo` - Restaurar logo original
- `POST /change_logo` - Cambiar archivo de logo
- `POST /update_system_config` - Actualizar configuración del sistema
- `POST /update_public_config` - Actualizar configuración pública
- `POST /update_thresholds` - Actualizar umbrales ambientales
- `POST /create_backup` - Crear backup manual
- `POST /restore_config` - Restaurar configuración

#### Formato de Respuesta:
```json
{
    "success": true|false,
    "message": "Descripción del resultado",
    "data": { /* datos adicionales */ },
    "error": "Mensaje de error si aplica"
}
```

---

## INTEGRACIÓN DE BASE DE DATOS

### Conexión y Configuración:
```php
// Configuración desde config.ini
$config = parse_ini_file('config.ini', true);
$db_config = $config['database'];

// Conexión PDO con parámetros de configuración
$dsn = "mysql:host={$db_config['host']};" .
       "port={$db_config['port']};" .
       "dbname={$db_config['database']};" .
       "charset={$db_config['charset']}";

$pdo = new PDO($dsn, $db_config['user'], $db_config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);
```

### Consultas Optimizadas:
- **Índices:** Optimización de consultas frecuentes
- **JOINs:** Combinación eficiente de tablas
- **Subconsultas:** Agregaciones complejas
- **Prepared Statements:** Seguridad y performance

---

## SISTEMA DE CONFIGURACIÓN

### Estructura del config.ini:
```ini
[database]
host = "localhost"
port = 3307
user = "root"
password = ""
database = "suite_ambiental"
charset = "utf8mb4"
timezone = "+02:00"

[sistema]
nombre = "ArduinoSoft - Sistema de Control Ambiental"
version = 3.1
timezone = "+02:00"
log_level = "info"
logo = "media/logo.png"

[referencias]
temperatura_max = 25
humedad_max = 45
ruido_max = 35
co2_max = 750
lux_min = 195

[publico]
titulo = "Grupo Sorolla Educación"
subtitulo = "La Devesa School Elche"
color_fondo = "#ffffff"
color_secundario = "#9126fd"
color_texto = "#000000"
refresh_interval = 60
```

### Gestión de Configuración:
- **Backup Automático:** Antes de cada cambio
- **Validación:** Tipos y rangos de datos
- **Aplicación Inmediata:** Sin necesidad de reinicio
- **Historial:** Tracking de cambios por usuario

---

## MANEJO DE SESIONES

### Configuración de Seguridad:
```php
// Configuración segura de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

// Regeneración de ID por seguridad
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}
```

### Variables de Sesión:
- `$_SESSION['usuario']` - Nombre de usuario
- `$_SESSION['rol']` - Rol del usuario (admin/operador)
- `$_SESSION['login_time']` - Timestamp de login
- `$_SESSION['last_activity']` - Última actividad

### Timeout y Seguridad:
- **Timeout automático:** 30 minutos de inactividad
- **Regeneración de ID:** Prevención de session fixation
- **Validación de IP:** Opcional para mayor seguridad
- **Logout seguro:** Destrucción completa de sesión

---

**Documento Técnico Generado**  
**Sistema:** ArduinoSoft - Sistema de Control Ambiental v3.1  
**Fecha:** 12 de Junio de 2025  
**Tipo:** Documentación de Integración Técnica
