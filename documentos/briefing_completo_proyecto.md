# BRIEFING COMPLETO DEL PROYECTO
# ArduinoSoft - Sistema de Control Ambiental v3.1

**Fecha de Creación:** 12 de Junio de 2025  
**Cliente:** Grupo Sorolla Educación - La Devesa School Elche  
**Desarrollador:** Sistema Interno  
**Versión:** 3.1  

---

## ÍNDICE

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Funcionalidades Principales](#funcionalidades-principales)
4. [Páginas y Secciones Detalladas](#páginas-y-secciones-detalladas)
5. [Gestión de Estilos](#gestión-de-estilos)
6. [Base de Datos](#base-de-datos)
7. [Configuración del Sistema](#configuración-del-sistema)
8. [Sistema de Backups](#sistema-de-backups)
9. [Accesibilidad y Responsive](#accesibilidad-y-responsive)
10. [Seguridad](#seguridad)

---

## RESUMEN EJECUTIVO

El **ArduinoSoft - Sistema de Control Ambiental** es una aplicación web completa diseñada para monitorear y gestionar sensores ambientales en tiempo real. Desarrollado específicamente para el Grupo Sorolla Educación - La Devesa School Elche, el sistema permite el seguimiento de parámetros como temperatura, humedad, ruido, CO2 e iluminación.

### Características Principales:
- **Monitoreo en tiempo real** de sensores Arduino
- **Panel de administración completo** con roles de usuario
- **Monitor público accesible** sin autenticación
- **Sistema de alertas configurable** por umbrales
- **Gestión de backups automática**
- **Interfaz completamente responsive** y accesible
- **Configuración personalizable** para la organización

---

## ARQUITECTURA DEL SISTEMA

### Estructura de Archivos:
```
c:\wamp64\www\
├── index.php                 # Página de login
├── panel.php                 # Dashboard principal
├── public.php                # Monitor público
├── logout.php                # Cierre de sesión
├── config.ini                # Configuración del sistema
├── includes/                 # Módulos del sistema
│   ├── dashboard.php          # Panel principal
│   ├── usuarios.php           # Gestión de usuarios
│   ├── dispositivos.php       # Gestión de dispositivos
│   ├── informes.php           # Informes y estadísticas
│   ├── configuracion.php      # Configuración del sistema
│   ├── backups.php            # Sistema de backups
│   ├── database_manager.php   # Gestor de base de datos
│   ├── configuracion_operations.php # Operaciones AJAX
│   └── backup_operations.php  # Operaciones de backup
├── css/                      # Hojas de estilo
│   └── styles.css            # Estilos principales
├── js/                       # JavaScript
│   └── scripts.js            # Funciones principales
├── media/                    # Recursos multimedia
│   └── logo.png              # Logo del sistema
├── configbackup/             # Backups de configuración
├── logs/                     # Archivos de log
└── documentos/               # Documentación
```

### Tecnologías Utilizadas:
- **Backend:** PHP 8.x
- **Base de Datos:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript ES6
- **Servidor:** Apache (WAMP)
- **Responsive:** CSS Grid, Flexbox
- **Accesibilidad:** WCAG 2.1 AA

---

## FUNCIONALIDADES PRINCIPALES

### 1. Autenticación y Autorización
- **Login seguro** con validación de credenciales
- **Sistema de roles:** Administrador y Operador
- **Sesiones seguras** con timeout automático
- **Redirección inteligente** según permisos

### 2. Monitoreo en Tiempo Real
- **Visualización de datos** de sensores Arduino
- **Actualización automática** cada 60 segundos
- **Alertas visuales** por valores fuera de rango
- **Histórico de registros** con filtros avanzados

### 3. Gestión de Usuarios (Solo Admin)
- **CRUD completo** de usuarios
- **Asignación de roles** y permisos
- **Búsqueda y filtrado** avanzado
- **Validación de datos** robusta

### 4. Gestión de Dispositivos
- **Registro de sensores** Arduino
- **Configuración de ubicaciones**
- **Monitorización individual** detallada
- **Estado de conectividad** en tiempo real

### 5. Informes y Estadísticas
- **Gráficos interactivos** de tendencias
- **Exportación a CSV** funcional
- **Filtros por fecha y dispositivo**
- **Ranking de dispositivos** más activos

### 6. Sistema de Configuración
- **Umbrales de alerta** personalizables
- **Configuración del monitor público**
- **Gestión de logos** del sistema
- **Zona horaria** y parámetros generales

### 7. Sistema de Backups
- **Backup automático** de configuración
- **Backup manual** de proyecto y BD
- **Restauración de configuraciones**
- **Limpieza automática** de archivos antiguos

---

## PÁGINAS Y SECCIONES DETALLADAS

### 1. index.php - Página de Login
**Propósito:** Autenticación de usuarios al sistema

**Características:**
- Formulario de login centrado y responsive
- Validación de credenciales en tiempo real
- Mensajes de error personalizados
- Redirección automática según rol
- Diseño moderno con gradientes personalizados

**Campos:**
- Usuario (requerido)
- Contraseña (requerido)
- Botón de acceso con efectos hover

### 2. panel.php - Dashboard Principal
**Propósito:** Hub central de navegación y control

**Secciones Incluidas:**
- **Header dinámico** con información del usuario
- **Navegación por pestañas** según permisos
- **Contenido dinámico** según sección seleccionada
- **Footer con información** del sistema

**Pestañas Disponibles:**
1. **Dashboard** (todos los usuarios)
2. **Usuarios** (solo admin)
3. **Dispositivos** (todos los usuarios)
4. **Informes** (todos los usuarios)
5. **Configuración** (solo admin)
6. **Backups** (solo admin)

### 3. public.php - Monitor Público
**Propósito:** Visualización pública sin autenticación

**Características:**
- **Totalmente accesible** (WCAG 2.1 AA)
- **Navegación por teclado** completa
- **Actualización automática** configurable
- **Diseño personalizado** para la organización
- **Responsive** para todos los dispositivos

**Elementos Personalizables:**
- Título: "Grupo Sorolla Educación"
- Subtítulo: "La Devesa School Elche"
- Colores corporativos configurables
- Tiempo de actualización (60 segundos)

### 4. includes/dashboard.php - Panel Principal
**Propósito:** Página de inicio del sistema logueado

**Elementos:**
- **Estadísticas en tiempo real:**
  - Total de dispositivos
  - Dispositivos activos
  - Total de usuarios
  - Registros del día/semana

- **Tarjetas de funcionalidades:**
  - Gestión de usuarios (con restricciones por rol)
  - Gestión de dispositivos (con umbrales actuales)
  - Monitor público (configuración actual)
  - Informes (análisis configurado)
  - Configuración del sistema (solo admin)
  - Sistema de backups (solo admin)

- **Sistema de alertas recientes:**
  - Monitoreo de últimas 2 horas
  - Valores fuera de umbrales configurados
  - Información detallada por sensor

- **Estado actual del sistema:**
  - Configuración del sistema
  - Umbrales de alerta configurados
  - Monitor público personalizado
  - Base de datos conectada (solo admin)

### 5. includes/usuarios.php - Gestión de Usuarios
**Propósito:** CRUD completo de usuarios del sistema

**Funcionalidades:**
- **Lista de usuarios** con información completa
- **Búsqueda en tiempo real** por nombre/rol
- **Creación de nuevos usuarios** con validación
- **Edición de usuarios existentes**
- **Eliminación con confirmación** de seguridad
- **Gestión de roles** (admin/operador)

**Validaciones:**
- Nombres únicos de usuario
- Longitud mínima de contraseñas
- Roles válidos
- Caracteres permitidos

### 6. includes/dispositivos.php - Gestión de Dispositivos
**Propósito:** Administración de sensores Arduino

**Características:**
- **Vista de lista** con estado en tiempo real
- **Monitorización individual** detallada
- **Edición de configuración** de dispositivos
- **Estadísticas por dispositivo** (24 horas)
- **Alertas visuales** según umbrales
- **Información de conectividad**

**Datos Mostrados:**
- Nombre del dispositivo
- Ubicación
- Último registro
- Estado (activo/inactivo)
- Valores actuales de sensores
- IP y MAC address

### 7. includes/informes.php - Informes y Estadísticas
**Propósito:** Análisis y exportación de datos

**Funcionalidades:**
- **Resumen ejecutivo** de dispositivos
- **Gráficos de tendencias** interactivos
- **Exportación CSV** funcional
- **Filtros avanzados:**
  - Por rango de fechas
  - Por dispositivo específico
  - Por tipo de sensor

- **Estadísticas calculadas:**
  - Promedios por período
  - Valores máximos/mínimos
  - Ranking de dispositivos más activos
  - Detección de alertas

### 8. includes/configuracion.php - Configuración del Sistema
**Propósito:** Personalización completa del sistema

**Secciones:**

#### A. Configuración General
- **Nombre del sistema:** "ArduinoSoft - Sistema de Control Ambiental"
- **Versión:** 3.1
- **Zona horaria:** +02:00 (Europa/Madrid)
- **Nivel de log:** Info/Debug/Warning/Error

#### B. Personalización del Logo
- **Subida de archivos** (PNG, JPG, GIF)
- **Vista previa** en tiempo real
- **Restauración** al logo original
- **Validación de tamaño** (máximo 2MB)

#### C. Umbrales de Alerta Ambiental
- **Temperatura máxima:** 25°C
- **Humedad máxima:** 45%
- **Ruido máximo:** 35dB
- **CO2 máximo:** 750ppm
- **Iluminación mínima:** 195lux

#### D. Configuración del Monitor Público
- **Título:** "Grupo Sorolla Educación"
- **Subtítulo:** "La Devesa School Elche"
- **Colores personalizables:**
  - Fondo: #ffffff
  - Secundario: #9126fd
  - Texto: #000000
- **Tiempo de actualización:** 60 segundos
- **Vista previa** en tiempo real

#### E. Gestión de Backups de Configuración
- **Creación manual** de backups
- **Listado de backups** disponibles
- **Restauración** de configuraciones
- **Limpieza automática** de archivos antiguos
- **Descarga** de archivos de backup

### 9. includes/backups.php - Sistema de Backups
**Propósito:** Gestión completa de copias de seguridad

**Tipos de Backup:**
1. **Backup de Proyecto Completo**
   - Todos los archivos PHP, CSS, JS
   - Configuraciones y media
   - Compresión ZIP automática

2. **Backup de Base de Datos**
   - Exportación SQL completa
   - Estructura y datos
   - Compresión automática

3. **Backup Combinado**
   - Proyecto + Base de datos
   - Archivo único ZIP
   - Información detallada

**Funcionalidades:**
- **Creación automática** con timestamps
- **Descarga directa** de archivos
- **Gestión de archivos** existentes
- **Logs detallados** de operaciones
- **Información del sistema** incluida

---

## GESTIÓN DE ESTILOS

### Estructura CSS

#### 1. Variables CSS (Custom Properties)
```css
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --error-color: #dc3545;
    --info-color: #17a2b8;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --background-color: #ffffff;
    --text-color: #333333;
    --text-light: #666666;
    --border-color: #dee2e6;
    --card-background: rgba(255, 255, 255, 0.9);
    --shadow: 0 2px 10px rgba(0,0,0,0.1);
}
```

#### 2. Reset y Base
- **Reset CSS** completo para consistencia
- **Box-sizing** border-box global
- **Tipografía base** con fallbacks
- **Smooth scrolling** habilitado

#### 3. Layout Principal
- **Grid layout** para estructura principal
- **Flexbox** para componentes
- **Sticky header** y footer
- **Sidebar responsive** con transiciones

#### 4. Componentes Estilizados

##### Formularios
- **Inputs consistentes** con focus states
- **Botones con gradientes** y efectos hover
- **Validación visual** en tiempo real
- **Placeholders estilizados**

##### Tarjetas (Cards)
- **Sombras sutiles** con profundidad
- **Hover effects** con transform
- **Bordes redondeados** consistentes
- **Spacing interno** armonioso

##### Tablas
- **Responsive** con scroll horizontal
- **Zebra striping** para legibilidad
- **Hover states** en filas
- **Sticky headers** en tablas largas

##### Alertas
- **Color coding** por tipo
- **Iconos consistentes**
- **Animaciones de entrada/salida**
- **Dismissible** con JavaScript

#### 5. Responsive Design

##### Breakpoints
```css
/* Mobile First Approach */
@media (min-width: 576px) { /* Small */ }
@media (min-width: 768px) { /* Medium */ }
@media (min-width: 992px) { /* Large */ }
@media (min-width: 1200px) { /* Extra Large */ }
```

##### Estrategias Responsive
- **Mobile-first** approach
- **Fluid typography** con clamp()
- **Flexible grid** con CSS Grid
- **Touch-friendly** targets (44px mínimo)

#### 6. Animaciones y Transiciones
- **Easing functions** naturales
- **Duraciones consistentes** (0.3s estándar)
- **Transform** para mejor performance
- **Reduced motion** respetado

#### 7. Accesibilidad CSS
- **Contrast ratios** WCAG 2.1 AA
- **Focus indicators** visibles
- **Screen reader** friendly
- **Color** no como único indicador

### Tematización

#### Colores Corporativos
El sistema utiliza los colores del Grupo Sorolla Educación:
- **Primario:** Morado corporativo (#9126fd)
- **Secundario:** Blanco (#ffffff)
- **Texto:** Negro (#000000)
- **Gradientes** personalizados para elementos UI

#### Tipografía
- **Fuente principal:** System font stack
- **Jerarquía** clara con escalas modulares
- **Line-height** optimizado para legibilidad
- **Letter-spacing** ajustado por tamaño

---

## BASE DE DATOS

### Estructura Principal

#### Tabla: usuarios
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- usuario (VARCHAR(50), UNIQUE)
- password (VARCHAR(255), HASHED)
- rol (ENUM: 'admin', 'operador')
- fecha_creacion (TIMESTAMP)
```

#### Tabla: dispositivos
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- nombre (VARCHAR(100), UNIQUE)
- ubicacion (VARCHAR(200))
- ip_address (VARCHAR(45))
- mac_address (VARCHAR(17))
- activo (BOOLEAN, DEFAULT TRUE)
- fecha_registro (TIMESTAMP)
```

#### Tabla: registros
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- sensor_id (VARCHAR(100), FK)
- temperatura (DECIMAL(5,2))
- humedad (DECIMAL(5,2))
- ruido (DECIMAL(5,2))
- co2 (INT)
- lux (INT)
- fecha_hora (TIMESTAMP)
```

### Relaciones
- **dispositivos.nombre** → **registros.sensor_id**
- **Índices** optimizados para consultas frecuentes
- **Foreign keys** con ON DELETE CASCADE

---

## CONFIGURACIÓN DEL SISTEMA

### Archivo config.ini

#### [database]
- **host:** localhost
- **port:** 3307
- **user:** root
- **password:** (vacío)
- **database:** suite_ambiental
- **charset:** utf8mb4
- **timezone:** +02:00

#### [sistema]
- **nombre:** "ArduinoSoft - Sistema de Control Ambiental"
- **version:** 3.1
- **timezone:** +02:00
- **log_level:** info
- **logo:** media/logo.png

#### [referencias]
- **temperatura_max:** 25
- **humedad_max:** 45
- **ruido_max:** 35
- **co2_max:** 750
- **lux_min:** 195

#### [publico]
- **titulo:** "Grupo Sorolla Educación"
- **subtitulo:** "La Devesa School Elche"
- **color_fondo:** #ffffff
- **color_secundario:** #9126fd
- **color_texto:** #000000
- **refresh_interval:** 60

### Gestión de Configuración
- **Backup automático** antes de cambios
- **Validación** de parámetros
- **Timestamping** de modificaciones
- **Usuario** que realizó el cambio

---

## SISTEMA DE BACKUPS

### Tipos de Backup

#### 1. Backup de Configuración
- **Automático:** Antes de cada cambio
- **Manual:** Desde el panel de configuración
- **Contenido:** config.ini con metadatos
- **Formato:** INI con comentarios

#### 2. Backup de Proyecto
- **Contenido:** Todos los archivos PHP, CSS, JS, media
- **Formato:** ZIP comprimido
- **Metadatos:** Información del sistema incluida
- **Descarga:** Directa desde navegador

#### 3. Backup de Base de Datos
- **Exportación:** SQL completo
- **Estructura:** Tablas, índices, datos
- **Compresión:** ZIP automática
- **Compatibilidad:** MySQL/MariaDB

### Gestión de Backups
- **Limpieza automática** (mantener 10 más recientes)
- **Logs detallados** de operaciones
- **Restauración** de configuraciones
- **Descarga** de archivos

---

## ACCESIBILIDAD Y RESPONSIVE

### Accesibilidad (WCAG 2.1 AA)

#### Navegación por Teclado
- **Tab order** lógico
- **Focus indicators** visibles
- **Skip links** disponibles
- **Keyboard shortcuts** documentados

#### Lectores de Pantalla
- **Semantic HTML** correcto
- **ARIA labels** apropiados
- **Alt text** en imágenes
- **Headings** jerárquicos

#### Contraste y Color
- **Ratio mínimo:** 4.5:1
- **Color** no como único indicador
- **Hover/focus** states claros
- **Error messaging** accesible

### Responsive Design

#### Mobile First
- **Diseño base:** 320px
- **Breakpoints** progresivos
- **Touch targets:** 44px mínimo
- **Viewport** optimizado

#### Adaptabilidad
- **Grids flexibles**
- **Imágenes responsive**
- **Tipografía fluida**
- **Menús colapsables**

---

## SEGURIDAD

### Autenticación
- **Password hashing** con PHP password_hash()
- **Session management** seguro
- **CSRF protection** implementado
- **Timeout** automático de sesiones

### Autorización
- **Role-based access** control
- **Page-level** restrictions
- **Function-level** permissions
- **Input validation** robusta

### Protección de Datos
- **SQL injection** prevention
- **XSS protection** 
- **File upload** validation
- **Error handling** seguro

### Logs y Auditoría
- **Activity logging**
- **Error logging**
- **Configuration changes** tracked
- **User actions** recorded

---

## CONCLUSIÓN

El **ArduinoSoft - Sistema de Control Ambiental v3.1** es una solución completa y robusta para el monitoreo ambiental del Grupo Sorolla Educación. Ofrece una interfaz moderna, funcionalidades avanzadas, y cumple con los más altos estándares de accesibilidad y seguridad.

### Características Destacadas:
- ✅ **100% Responsive** y accesible
- ✅ **Configuración personalizable** para la organización
- ✅ **Sistema de backups** completo
- ✅ **Monitoreo en tiempo real** con alertas
- ✅ **Interfaz intuitiva** y moderna
- ✅ **Seguridad robusta** con roles de usuario
- ✅ **Documentación completa** y mantenible

### Tecnologías Utilizadas:
- **PHP 8.x** para backend robusto
- **MySQL** para persistencia de datos
- **CSS Grid/Flexbox** para layouts modernos
- **JavaScript ES6** para interactividad
- **WCAG 2.1 AA** para accesibilidad completa

El sistema está listo para producción y puede escalarse fácilmente para futuras necesidades del centro educativo.

---

**Documento generado automáticamente**  
**Sistema:** ArduinoSoft - Sistema de Control Ambiental v3.1  
**Fecha:** 12 de Junio de 2025  
**Para:** Grupo Sorolla Educación - La Devesa School Elche