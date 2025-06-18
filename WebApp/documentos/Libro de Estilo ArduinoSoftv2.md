# LIBRO DE ESTILO Y GUÍA DE DISEÑO
# ArduinoSoft - Sistema de Control Ambiental v3.1

**Fecha de Creación:** 12 de Junio de 2025  
**Proyecto:** Sistema de Control Ambiental  
**Cliente:** Grupo Sorolla Educación - La Devesa School Elche  
**Versión:** 3.1  

---

## ÍNDICE

1. [Principios de Diseño](#principios-de-diseño)
2. [Paleta de Colores](#paleta-de-colores)
3. [Tipografía](#tipografía)
4. [Sistema de Espaciado](#sistema-de-espaciado)
5. [Componentes UI](#componentes-ui)
6. [Layout y Grid](#layout-y-grid)
7. [Responsive Design](#responsive-design)
8. [Accesibilidad](#accesibilidad)
9. [Animaciones y Transiciones](#animaciones-y-transiciones)
10. [Iconografía](#iconografía)

---

## PRINCIPIOS DE DISEÑO

### 1. Claridad y Simplicidad
- **Diseño limpio** sin elementos innecesarios
- **Jerarquía visual** clara y consistente
- **Información organizada** de forma lógica
- **Navegación intuitiva** para todos los usuarios

### 2. Consistencia
- **Elementos repetibles** en todo el sistema
- **Patrones de interacción** uniformes
- **Terminología coherente** en todo el proyecto
- **Comportamiento predecible** de componentes

### 3. Accesibilidad Universal
- **Diseño inclusivo** para todos los usuarios
- **Compatibilidad con tecnologías asistivas**
- **Navegación por teclado** completa
- **Alto contraste** y legibilidad

### 4. Responsive y Adaptable
- **Mobile-first** approach
- **Escalabilidad** en todos los dispositivos
- **Performance optimizada** en dispositivos móviles
- **Touch-friendly** interfaces

---

## PALETA DE COLORES

### Colores Corporativos Grupo Sorolla Educación

#### Primarios
```css
:root {
    /* Morado Corporativo */
    --primary-color: #9126fd;
    --primary-light: #a855f7;
    --primary-dark: #7c3aed;
    
    /* Blanco Institucional */
    --white: #ffffff;
    --off-white: #fafafa;
    
    /* Negro Texto */
    --black: #000000;
    --dark-gray: #1f2937;
}
```

#### Secundarios del Sistema
```css
:root {
    /* Colores de Estado */
    --success-color: #10b981;      /* Verde éxito */
    --success-light: #6ee7b7;
    --success-dark: #059669;
    
    --warning-color: #f59e0b;      /* Amarillo advertencia */
    --warning-light: #fcd34d;
    --warning-dark: #d97706;
    
    --error-color: #ef4444;        /* Rojo error */
    --error-light: #fca5a5;
    --error-dark: #dc2626;
    
    --info-color: #3b82f6;         /* Azul información */
    --info-light: #93c5fd;
    --info-dark: #2563eb;
}
```

#### Neutros
```css
:root {
    /* Escala de Grises */
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    
    /* Colores de Texto */
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --text-muted: #9ca3af;
    --text-light: #d1d5db;
    
    /* Colores de Fondo */
    --background-primary: #ffffff;
    --background-secondary: #f9fafb;
    --background-muted: #f3f4f6;
    --background-dark: #1f2937;
}
```

### Uso de Colores

#### Estados de Componentes
```css
/* Botones */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
}

.btn-secondary {
    background: var(--gray-600);
    color: var(--white);
}

.btn-success {
    background: var(--success-color);
    color: var(--white);
}

/* Estados de Hover */
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(145, 38, 253, 0.3);
}
```

#### Alertas y Notificaciones
```css
.alert-success {
    background-color: rgba(16, 185, 129, 0.1);
    border-left: 4px solid var(--success-color);
    color: var(--success-dark);
}

.alert-warning {
    background-color: rgba(245, 158, 11, 0.1);
    border-left: 4px solid var(--warning-color);
    color: var(--warning-dark);
}

.alert-error {
    background-color: rgba(239, 68, 68, 0.1);
    border-left: 4px solid var(--error-color);
    color: var(--error-dark);
}
```

---

## TIPOGRAFÍA

### Fuentes del Sistema

#### Jerarquía de Fuentes
```css
:root {
    /* System Font Stack */
    --font-family-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', 
                         Roboto, 'Helvetica Neue', Arial, sans-serif;
    
    /* Fuente Monospace para Código */
    --font-family-mono: 'SFMono-Regular', Consolas, 'Liberation Mono', 
                         Menlo, Courier, monospace;
}

body {
    font-family: var(--font-family-base);
    line-height: 1.6;
    color: var(--text-primary);
}
```

#### Escalas Tipográficas
```css
:root {
    /* Tamaños de Fuente */
    --font-size-xs: 0.75rem;     /* 12px */
    --font-size-sm: 0.875rem;    /* 14px */
    --font-size-base: 1rem;      /* 16px */
    --font-size-lg: 1.125rem;    /* 18px */
    --font-size-xl: 1.25rem;     /* 20px */
    --font-size-2xl: 1.5rem;     /* 24px */
    --font-size-3xl: 1.875rem;   /* 30px */
    --font-size-4xl: 2.25rem;    /* 36px */
    --font-size-5xl: 3rem;       /* 48px */
    
    /* Pesos de Fuente */
    --font-weight-light: 300;
    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;
    --font-weight-extrabold: 800;
}
```

### Jerarquía de Títulos

```css
/* Títulos Principales */
h1, .h1 {
    font-size: var(--font-size-4xl);
    font-weight: var(--font-weight-bold);
    line-height: 1.2;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

h2, .h2 {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-semibold);
    line-height: 1.3;
    margin-bottom: 1.25rem;
    color: var(--text-primary);
}

h3, .h3 {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-semibold);
    line-height: 1.4;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

h4, .h4 {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-medium);
    line-height: 1.5;
    margin-bottom: 0.75rem;
    color: var(--text-primary);
}

/* Texto de Cuerpo */
p {
    font-size: var(--font-size-base);
    line-height: 1.6;
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

/* Texto Pequeño */
.text-sm {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.text-xs {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}
```

### Tipografía Responsive

```css
/* Escalado Fluido para Títulos */
h1 {
    font-size: clamp(1.875rem, 5vw, 2.25rem);
}

h2 {
    font-size: clamp(1.5rem, 4vw, 1.875rem);
}

h3 {
    font-size: clamp(1.25rem, 3vw, 1.5rem);
}

/* Ajustes para Dispositivos Móviles */
@media (max-width: 768px) {
    body {
        font-size: var(--font-size-sm);
    }
    
    h1, h2, h3, h4 {
        line-height: 1.3;
    }
}
```

---

## SISTEMA DE ESPACIADO

### Escala de Espaciado
```css
:root {
    --space-px: 1px;
    --space-0: 0;
    --space-1: 0.25rem;    /* 4px */
    --space-2: 0.5rem;     /* 8px */
    --space-3: 0.75rem;    /* 12px */
    --space-4: 1rem;       /* 16px */
    --space-5: 1.25rem;    /* 20px */
    --space-6: 1.5rem;     /* 24px */
    --space-8: 2rem;       /* 32px */
    --space-10: 2.5rem;    /* 40px */
    --space-12: 3rem;      /* 48px */
    --space-16: 4rem;      /* 64px */
    --space-20: 5rem;      /* 80px */
    --space-24: 6rem;      /* 96px */
}
```

### Aplicación del Espaciado

#### Márgenes y Padding
```css
/* Utilidades de Espaciado */
.m-0 { margin: var(--space-0); }
.m-1 { margin: var(--space-1); }
.m-2 { margin: var(--space-2); }
.m-4 { margin: var(--space-4); }
.m-8 { margin: var(--space-8); }

.p-0 { padding: var(--space-0); }
.p-1 { padding: var(--space-1); }
.p-2 { padding: var(--space-2); }
.p-4 { padding: var(--space-4); }
.p-8 { padding: var(--space-8); }

/* Direccionales */
.mt-4 { margin-top: var(--space-4); }
.mb-4 { margin-bottom: var(--space-4); }
.ml-4 { margin-left: var(--space-4); }
.mr-4 { margin-right: var(--space-4); }

.pt-4 { padding-top: var(--space-4); }
.pb-4 { padding-bottom: var(--space-4); }
.pl-4 { padding-left: var(--space-4); }
.pr-4 { padding-right: var(--space-4); }
```

#### Espaciado de Componentes
```css
/* Tarjetas */
.card {
    padding: var(--space-6);
    margin-bottom: var(--space-4);
}

/* Formularios */
.form-group {
    margin-bottom: var(--space-4);
}

.form-label {
    margin-bottom: var(--space-2);
}

/* Botones */
.btn {
    padding: var(--space-3) var(--space-6);
    margin: var(--space-1);
}

.btn-lg {
    padding: var(--space-4) var(--space-8);
}

.btn-sm {
    padding: var(--space-2) var(--space-4);
}
```

---

## COMPONENTES UI

### Botones

#### Estilos Base
```css
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-3) var(--space-6);
    border: none;
    border-radius: 6px;
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-medium);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    gap: var(--space-2);
    min-height: 44px;
}

.btn:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}
```

#### Variantes de Botones
```css
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--white);
    box-shadow: 0 2px 8px rgba(145, 38, 253, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(145, 38, 253, 0.4);
}

.btn-secondary {
    background: var(--gray-600);
    color: var(--white);
    box-shadow: 0 2px 8px rgba(107, 114, 128, 0.3);
}

.btn-success {
    background: var(--success-color);
    color: var(--white);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-warning {
    background: var(--warning-color);
    color: var(--white);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-danger {
    background: var(--error-color);
    color: var(--white);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-ghost {
    background: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
    box-shadow: none;
}

.btn-ghost:hover {
    background: var(--primary-color);
    color: var(--white);
}
```

### Formularios

#### Inputs y Controls
```css
.form-control {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    border: 2px solid var(--gray-300);
    border-radius: 6px;
    font-size: var(--font-size-base);
    line-height: 1.5;
    background-color: var(--white);
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    min-height: 44px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(145, 38, 253, 0.1);
}

.form-control:invalid {
    border-color: var(--error-color);
}

.form-control:disabled {
    background-color: var(--gray-100);
    cursor: not-allowed;
    opacity: 0.6;
}

/* Select Dropdown */
select.form-control {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}
```

#### Labels y Help Text
```css
.form-label {
    display: block;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
    margin-bottom: var(--space-2);
}

.form-help {
    display: block;
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: var(--space-1);
}

.form-error {
    display: block;
    font-size: var(--font-size-xs);
    color: var(--error-color);
    margin-top: var(--space-1);
}

/* Required Field Indicator */
.form-label.required::after {
    content: " *";
    color: var(--error-color);
}
```

### Tarjetas (Cards)

```css
.card {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: var(--space-6);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--gray-200);
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.card-header {
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: var(--space-4);
    margin-bottom: var(--space-4);
}

.card-title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    margin: 0;
    color: var(--text-primary);
}

.card-content {
    color: var(--text-secondary);
    line-height: 1.6;
}

.card-footer {
    border-top: 1px solid var(--gray-200);
    padding-top: var(--space-4);
    margin-top: var(--space-4);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Card Variants */
.card-primary {
    border-left: 4px solid var(--primary-color);
}

.card-success {
    border-left: 4px solid var(--success-color);
}

.card-warning {
    border-left: 4px solid var(--warning-color);
}

.card-error {
    border-left: 4px solid var(--error-color);
}
```

### Alertas y Notificaciones

```css
.alert {
    padding: var(--space-4) var(--space-6);
    border-radius: 8px;
    margin-bottom: var(--space-4);
    border-left: 4px solid;
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    position: relative;
}

.alert-success {
    background-color: rgba(16, 185, 129, 0.1);
    border-left-color: var(--success-color);
    color: var(--success-dark);
}

.alert-warning {
    background-color: rgba(245, 158, 11, 0.1);
    border-left-color: var(--warning-color);
    color: var(--warning-dark);
}

.alert-error {
    background-color: rgba(239, 68, 68, 0.1);
    border-left-color: var(--error-color);
    color: var(--error-dark);
}

.alert-info {
    background-color: rgba(59, 130, 246, 0.1);
    border-left-color: var(--info-color);
    color: var(--info-dark);
}

/* Dismissible Alerts */
.alert-dismissible {
    padding-right: var(--space-12);
}

.alert-close {
    position: absolute;
    top: var(--space-4);
    right: var(--space-4);
    background: none;
    border: none;
    font-size: var(--font-size-lg);
    cursor: pointer;
    color: currentColor;
    opacity: 0.7;
}

.alert-close:hover {
    opacity: 1;
}
```

### Tablas

```css
.table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--gray-200);
}

.table th {
    background: var(--gray-50);
    padding: var(--space-4);
    text-align: left;
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    border-bottom: 2px solid var(--gray-200);
    font-size: var(--font-size-sm);
}

.table td {
    padding: var(--space-4);
    border-bottom: 1px solid var(--gray-200);
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.table tbody tr:hover {
    background-color: var(--gray-50);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Tabla Responsive */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 768px) {
    .table-responsive table {
        min-width: 600px;
    }
    
    .table th,
    .table td {
        padding: var(--space-2) var(--space-3);
        font-size: var(--font-size-xs);
    }
}

/* Table Status Indicators */
.status-active {
    color: var(--success-color);
    font-weight: var(--font-weight-semibold);
}

.status-inactive {
    color: var(--error-color);
    font-weight: var(--font-weight-semibold);
}

.status-warning {
    color: var(--warning-color);
    font-weight: var(--font-weight-semibold);
}
```

---

## LAYOUT Y GRID

### Grid Principal

```css
.main-layout {
    display: grid;
    grid-template-areas: 
        "header header"
        "sidebar main"
        "footer footer";
    grid-template-rows: auto 1fr auto;
    grid-template-columns: 250px 1fr;
    min-height: 100vh;
    gap: var(--space-4);
}

.main-header {
    grid-area: header;
    background: var(--white);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: var(--space-4) var(--space-6);
    border-bottom: 1px solid var(--gray-200);
}

.main-sidebar {
    grid-area: sidebar;
    background: var(--gray-50);
    padding: var(--space-6);
    border-right: 1px solid var(--gray-200);
}

.main-content {
    grid-area: main;
    padding: var(--space-6);
    background: var(--background-secondary);
    overflow-y: auto;
}

.main-footer {
    grid-area: footer;
    background: var(--gray-800);
    color: var(--white);
    padding: var(--space-4) var(--space-6);
    text-align: center;
}
```

### Grid de Componentes

```css
/* Grid para Tarjetas */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-6);
    margin: var(--space-6) 0;
}

/* Grid para Estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-8);
}

/* Grid para Formularios */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-4);
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-4);
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
}

/* Grid para Dispositivos */
.device-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-4);
}
```

### Flexbox Utilities

```css
/* Contenedores Flex */
.flex {
    display: flex;
}

.flex-col {
    flex-direction: column;
}

.flex-row {
    flex-direction: row;
}

.flex-wrap {
    flex-wrap: wrap;
}

/* Alineación */
.justify-start {
    justify-content: flex-start;
}

.justify-center {
    justify-content: center;
}

.justify-end {
    justify-content: flex-end;
}

.justify-between {
    justify-content: space-between;
}

.justify-around {
    justify-content: space-around;
}

.items-start {
    align-items: flex-start;
}

.items-center {
    align-items: center;
}

.items-end {
    align-items: flex-end;
}

.items-stretch {
    align-items: stretch;
}

/* Gaps */
.gap-1 {
    gap: var(--space-1);
}

.gap-2 {
    gap: var(--space-2);
}

.gap-4 {
    gap: var(--space-4);
}

.gap-6 {
    gap: var(--space-6);
}

.gap-8 {
    gap: var(--space-8);
}
```

---

## RESPONSIVE DESIGN

### Breakpoints

```css
:root {
    --breakpoint-sm: 576px;
    --breakpoint-md: 768px;
    --breakpoint-lg: 992px;
    --breakpoint-xl: 1200px;
    --breakpoint-2xl: 1400px;
}

/* Mobile First Media Queries */
@media (min-width: 576px) {
    /* Small devices (landscape phones) */
    .container {
        max-width: 540px;
    }
}

@media (min-width: 768px) {
    /* Medium devices (tablets) */
    .container {
        max-width: 720px;
    }
}

@media (min-width: 992px) {
    /* Large devices (desktops) */
    .container {
        max-width: 960px;
    }
}

@media (min-width: 1200px) {
    /* Extra large devices */
    .container {
        max-width: 1140px;
    }
}
```

### Layout Responsive

```css
/* Grid Adaptativo */
@media (max-width: 768px) {
    .main-layout {
        grid-template-areas: 
            "header"
            "main"
            "footer";
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
    }
    
    .main-sidebar {
        display: none;
    }
    
    .menu-grid {
        grid-template-columns: 1fr;
        gap: var(--space-4);
    }
    
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .device-grid {
        grid-template-columns: 1fr;
    }
}

/* Extra Small Devices */
@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .flex-wrap-mobile {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Navegación Móvil */
@media (max-width: 768px) {
    .main-nav {
        position: fixed;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100vh;
        background: var(--white);
        z-index: 1000;
        transition: left 0.3s ease;
        padding: var(--space-6);
    }
    
    .main-nav.active {
        left: 0;
    }
    
    .nav-toggle {
        display: block;
        background: none;
        border: none;
        font-size: var(--font-size-xl);
        cursor: pointer;
    }
    
    .nav-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    }
    
    .nav-overlay.active {
        display: block;
    }
}
```

### Tipografía Responsive

```css
/* Escalado Fluido */
html {
    font-size: clamp(14px, 2.5vw, 16px);
}

/* Títulos Adaptativos */
h1 {
    font-size: clamp(1.875rem, 5vw, 2.25rem);
}

h2 {
    font-size: clamp(1.5rem, 4vw, 1.875rem);
}

h3 {
    font-size: clamp(1.25rem, 3vw, 1.5rem);
}

/* Espaciado Adaptativo */
@media (max-width: 768px) {
    .container {
        padding: var(--space-4);
    }
    
    .card {
        padding: var(--space-4);
    }
    
    .btn {
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-sm);
    }
    
    .main-content {
        padding: var(--space-4);
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: var(--space-2);
    }
    
    .card {
        padding: var(--space-3);
    }
}
```

---

## ACCESIBILIDAD

### Focus States

```css
/* Focus Visible */
*:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    border-radius: 4px;
}

/* Botones */
.btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Inputs */
.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(145, 38, 253, 0.1);
}

/* Links */
a:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    text-decoration: underline;
}

/* Navigation Links */
.nav-link:focus-visible {
    background: rgba(145, 38, 253, 0.1);
    outline: 2px solid var(--primary-color);
}
```

### Contraste y Legibilidad

```css
/* Alto Contraste */
.high-contrast {
    --text-primary: #000000;
    --background-primary: #ffffff;
    --primary-color: #0000ff;
    --error-color: #ff0000;
    --success-color: #008000;
}

/* Texto Legible */
body {
    color: var(--text-primary);
    background: var(--background-primary);
    font-size: var(--font-size-base);
    line-height: 1.6;
}

/* Tamaños Mínimos Touch */
.btn, .form-control, .nav-link, .clickable {
    min-height: 44px;
    min-width: 44px;
}

/* Estados de Contraste */
.text-contrast-high {
    color: var(--text-primary);
    font-weight: var(--font-weight-semibold);
}

.bg-contrast-high {
    background: var(--white);
    color: var(--text-primary);
}
```

### Screen Reader Support

```css
/* Contenido Solo para Screen Readers */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.sr-only:focus {
    position: static;
    width: auto;
    height: auto;
    padding: inherit;
    margin: inherit;
    overflow: visible;
    clip: auto;
    white-space: normal;
}

/* Skip Links */
.skip-link {
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--primary-color);
    color: var(--white);
    padding: var(--space-2) var(--space-4);
    text-decoration: none;
    border-radius: 4px;
    z-index: 1000;
    font-weight: var(--font-weight-semibold);
}

.skip-link:focus {
    top: 6px;
}

/* Focus Trap */
.focus-trap:focus {
    outline: 3px solid var(--primary-color);
    outline-offset: 2px;
}
```

---

## ANIMACIONES Y TRANSICIONES

### Transiciones Base

```css
:root {
    --transition-fast: 0.15s ease;
    --transition-base: 0.3s ease;
    --transition-slow: 0.5s ease;
    
    --easing-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
    --easing-ease-out: cubic-bezier(0, 0, 0.2, 1);
    --easing-ease-in: cubic-bezier(0.4, 0, 1, 1);
    --easing-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* Transiciones por Defecto */
* {
    transition: background-color var(--transition-base),
                border-color var(--transition-base),
                color var(--transition-base),
                box-shadow var(--transition-base),
                transform var(--transition-base),
                opacity var(--transition-base);
}

/* Respeto a Preferencias de Movimiento Reducido */
@media (prefers-reduced-motion: reduce) {
    *,
    ::before,
    ::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

### Animaciones de Entrada

```css
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Clases de Animación */
.animate-fade-in {
    animation: fadeIn 0.5s var(--easing-ease-out);
}

.animate-slide-in-right {
    animation: slideInRight 0.5s var(--easing-ease-out);
}

.animate-slide-in-left {
    animation: slideInLeft 0.5s var(--easing-ease-out);
}

.animate-scale-in {
    animation: scaleIn 0.3s var(--easing-bounce);
}

.animate-pulse {
    animation: pulse 2s infinite;
}

.animate-spin {
    animation: spin 1s linear infinite;
}
```

### Efectos Hover

```css
/* Hover para Tarjetas */
.card {
    transition: transform var(--transition-base), 
                box-shadow var(--transition-base);
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Hover para Botones */
.btn {
    transition: all var(--transition-base);
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn:active {
    transform: translateY(0);
}

/* Efectos de Ripple */
.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.3s, height 0.3s;
}

.btn:active::before {
    width: 300px;
    height: 300px;
}

/* Hover para Links */
.nav-link {
    position: relative;
    transition: color var(--transition-base);
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary-color);
    transition: width var(--transition-base);
}

.nav-link:hover::after,
.nav-link.active::after {
    width: 100%;
}
```

### Loading States

```css
/* Loading Spinner */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid var(--gray-300);
    border-radius: 50%;
    border-top-color: var(--primary-color);
    animation: spin 1s linear infinite;
}

.loading-lg {
    width: 40px;
    height: 40px;
    border-width: 4px;
}

/* Loading Skeleton */
.skeleton {
    background: linear-gradient(90deg, 
                var(--gray-200) 25%, 
                var(--gray-100) 50%, 
                var(--gray-200) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.skeleton-text {
    height: 1em;
    border-radius: 4px;
    margin-bottom: 0.5em;
}

.skeleton-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}
```

---

## ICONOGRAFÍA

### Sistema de Iconos

```css
/* Iconos Base */
.icon {
    display: inline-block;
    width: 1em;
    height: 1em;
    vertical-align: middle;
    fill: currentColor;
}

.icon-sm {
    width: 0.875em;
    height: 0.875em;
}

.icon-lg {
    width: 1.25em;
    height: 1.25em;
}

.icon-xl {
    width: 1.5em;
    height: 1.5em;
}

/* Iconos de Estado */
.icon-success {
    color: var(--success-color);
}

.icon-warning {
    color: var(--warning-color);
}

.icon-error {
    color: var(--error-color);
}

.icon-info {
    color: var(--info-color);
}

.icon-primary {
    color: var(--primary-color);
}
```

### Iconos Emoji (Utilizados en el Sistema)

```css
/* Mapeo de Iconos Emoji */
.emoji-icon {
    font-style: normal;
    font-size: 1.2em;
    display: inline-block;
    margin-right: var(--space-2);
}

/* Iconos de Navegación */
.icon-dashboard::before { content: "🎛️"; }
.icon-users::before { content: "👥"; }
.icon-devices::before { content: "📡"; }
.icon-reports::before { content: "📈"; }
.icon-settings::before { content: "⚙️"; }
.icon-backup::before { content: "💾"; }
.icon-monitor::before { content: "🖥️"; }

/* Iconos de Estado */
.icon-online::before { content: "✅"; }
.icon-offline::before { content: "❌"; }
.icon-warning::before { content: "⚠️"; }
.icon-info::before { content: "ℹ️"; }

/* Iconos de Sensores */
.icon-temperature::before { content: "🌡️"; }
.icon-humidity::before { content: "💧"; }
.icon-noise::before { content: "🔊"; }
.icon-co2::before { content: "☁️"; }
.icon-light::before { content: "💡"; }

/* Iconos de Acciones */
.icon-edit::before { content: "✏️"; }
.icon-delete::before { content: "🗑️"; }
.icon-view::before { content: "👁️"; }
.icon-download::before { content: "💾"; }
.icon-refresh::before { content: "🔄"; }
.icon-search::before { content: "🔍"; }
```

### Estados de Conectividad

```css
/* Indicadores de Estado */
.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: var(--space-2);
}

.status-online {
    background: var(--success-color);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
}

.status-offline {
    background: var(--error-color);
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.3);
}

.status-warning {
    background: var(--warning-color);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3);
}

/* Indicador Pulsante */
.status-pulse {
    animation: pulse 2s infinite;
}

@keyframes statusPulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.7;
        transform: scale(1.1);
    }
}
```

---

## UTILITIES Y HELPERS

### Visibility y Display

```css
/* Display Utilities */
.hidden { display: none !important; }
.block { display: block !important; }
.inline { display: inline !important; }
.inline-block { display: inline-block !important; }
.flex { display: flex !important; }
.inline-flex { display: inline-flex !important; }
.grid { display: grid !important; }

/* Visibility */
.visible { visibility: visible !important; }
.invisible { visibility: hidden !important; }

/* Screen Reader Only */
.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
```

### Positioning

```css
/* Position */
.relative { position: relative !important; }
.absolute { position: absolute !important; }
.fixed { position: fixed !important; }
.sticky { position: sticky !important; }

/* Z-Index */
.z-0 { z-index: 0 !important; }
.z-10 { z-index: 10 !important; }
.z-20 { z-index: 20 !important; }
.z-30 { z-index: 30 !important; }
.z-40 { z-index: 40 !important; }
.z-50 { z-index: 50 !important; }
```

### Text Utilities

```css
/* Text Alignment */
.text-left { text-align: left !important; }
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.text-justify { text-align: justify !important; }

/* Text Transform */
.uppercase { text-transform: uppercase !important; }
.lowercase { text-transform: lowercase !important; }
.capitalize { text-transform: capitalize !important; }

/* Font Weight */
.font-light { font-weight: var(--font-weight-light) !important; }
.font-normal { font-weight: var(--font-weight-normal) !important; }
.font-medium { font-weight: var(--font-weight-medium) !important; }
.font-semibold { font-weight: var(--font-weight-semibold) !important; }
.font-bold { font-weight: var(--font-weight-bold) !important; }

/* Text Colors */
.text-primary { color: var(--text-primary) !important; }
.text-secondary { color: var(--text-secondary) !important; }
.text-muted { color: var(--text-muted) !important; }
.text-success { color: var(--success-color) !important; }
.text-warning { color: var(--warning-color) !important; }
.text-error { color: var(--error-color) !important; }
.text-info { color: var(--info-color) !important; }
```

---

## MODO OSCURO (PREPARACIÓN FUTURA)

### Variables para Modo Oscuro

```css
/* Dark Mode Variables */
[data-theme="dark"] {
    --background-primary: #1f2937;
    --background-secondary: #111827;
    --background-muted: #374151;
    --text-primary: #f9fafb;
    --text-secondary: #d1d5db;
    --text-muted: #9ca3af;
    --card-background: rgba(31, 41, 55, 0.9);
    --border-color: #374151;
    
    /* Ajustes de Colores de Estado para Dark Mode */
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
    --info-color: #3b82f6;
}

/* Toggle Dark Mode */
.dark-mode-toggle {
    background: none;
    border: 2px solid var(--gray-300);
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-base);
}

.dark-mode-toggle:hover {
    border-color: var(--primary-color);
    background: rgba(145, 38, 253, 0.1);
}
```

---

**Documento de Estilo Generado**  
**Sistema:** ArduinoSoft - Sistema de Control Ambiental v3.1  
**Fecha:** 12 de Junio de 2025  
**Tipo:** Guía de Estilo y Diseño Completa  
**Para:** Grupo Sorolla Educación - La Devesa School Elche
