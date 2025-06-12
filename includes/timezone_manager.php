<?php
/**
 * Gestor de Zona Horaria
 * Configuración centralizada de timezone para toda la aplicación ArduinoSoft20
 */

/**
 * Configura la zona horaria del sistema basada en la configuración
 */
function configurar_timezone() {
    // Intentar cargar configuración desde config.ini
    if (file_exists(__DIR__ . '/../config.ini')) {
        $config = parse_ini_file(__DIR__ . '/../config.ini', true);
        
        if (isset($config['sistema']['timezone'])) {
            $timezone_offset = $config['sistema']['timezone'];
              // Convertir offset a nombre de zona horaria
            switch ($timezone_offset) {
                case '+02:00':
                    date_default_timezone_set('Europe/Madrid');
                    break;
                case '+01:00':
                    date_default_timezone_set('Europe/Paris');
                    break;
                case '+00:00':
                    date_default_timezone_set('UTC');
                    break;
                case '-05:00':
                    date_default_timezone_set('America/New_York');
                    break;
                default:
                    // Si no reconoce el offset, usar Madrid por defecto (Madrid siempre +02:00 en verano)
                    date_default_timezone_set('Europe/Madrid');
                    break;
            }
        } else {
            // Si no existe configuración, usar Madrid por defecto
            date_default_timezone_set('Europe/Madrid');
        }
    } else {
        // Si no existe config.ini, usar Madrid por defecto
        date_default_timezone_set('Europe/Madrid');
    }
}

/**
 * Obtiene la fecha y hora actual formateada
 */
function obtener_fecha_actual($formato = 'Y-m-d H:i:s') {
    return date($formato);
}

/**
 * Obtiene la zona horaria configurada actualmente
 */
function obtener_timezone_actual() {
    return date_default_timezone_get();
}

/**
 * Convierte un timestamp a fecha con la zona horaria configurada
 */
function timestamp_to_fecha($timestamp, $formato = 'Y-m-d H:i:s') {
    return date($formato, $timestamp);
}

// Configurar timezone automáticamente al incluir este archivo
configurar_timezone();
?>
