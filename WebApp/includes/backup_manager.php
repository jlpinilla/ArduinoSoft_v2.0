<?php
/**
 * =====================================================
 * GESTOR DE BACKUPS COMPLETOS - SUITE AMBIENTAL
 * =====================================================
 * Sistema avanzado de creación de copias de seguridad completas
 * Permite crear backups del proyecto completo y de la base de datos
 * con exportación a archivos ZIP descargables.
 * 
 * Funcionalidades principales:
 * - Backup completo del proyecto web (archivos PHP, CSS, JS, imágenes)
 * - Backup completo de la base de datos (estructura + datos)
 * - Generación de archivos ZIP para descarga
 * - Selección de ubicación de destino
 * - Logs detallados de operaciones
 * - Validación de integridad
 */

// ===== CONTROL DE SEGURIDAD Y SESIÓN =====
// Solo verificar sesión si no estamos en un contexto de descarga
if (!defined('BACKUP_DOWNLOAD_MODE')) {
    if (session_status() === PHP_SESSION_NONE) {
       session_start();
    }

    if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
        header("Location: index.php");
        exit();
    }
}

// ===== CONFIGURACIÓN Y VARIABLES =====
require_once 'database_manager.php';

class BackupManager {
    private $config;
    private $db;
    private $backup_dir;
    private $temp_dir;
    private $log_file;
      public function __construct() {
        // Cargar configuración
        $this->config = parse_ini_file(__DIR__ . '/../config.ini', true);
        
        // Configurar directorios
        $this->backup_dir = __DIR__ . '/../backups';
        $this->temp_dir = __DIR__ . '/../temp_backup';
        $this->log_file = __DIR__ . '/../logs/backup_operations.log';
        
        // Crear directorios si no existen
        $this->createDirectories();
        
        // Conectar a la base de datos
        $this->connectDatabase();
    }
    
    /**
     * Crear directorios necesarios
     */
    private function createDirectories() {
        $dirs = [$this->backup_dir, $this->temp_dir, dirname($this->log_file)];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("No se pudo crear el directorio: $dir");
                }
            }
        }
    }
    
    /**
     * Conectar a la base de datos
     */
    private function connectDatabase() {
        try {
            $dsn = "mysql:host={$this->config['database']['host']};port={$this->config['database']['port']};dbname={$this->config['database']['database']};charset={$this->config['database']['charset']}";
            $this->db = new PDO($dsn, $this->config['database']['user'], $this->config['database']['password']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar operación en log
     */
    private function log($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $user = $_SESSION['usuario'] ?? 'Sistema';
        $log_entry = "[$timestamp] [$type] [$user] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Crear backup completo del proyecto
     */
    public function createProjectBackup($include_uploads = true, $include_logs = false) {
        $this->log("Iniciando backup completo del proyecto");
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_name = "proyecto_backup_$timestamp";
        $temp_project_dir = $this->temp_dir . "/$backup_name";
        
        try {
            // Crear directorio temporal
            if (!mkdir($temp_project_dir, 0755, true)) {
                throw new Exception("No se pudo crear directorio temporal");
            }
            
            // Definir archivos y directorios a incluir
            $include_patterns = [
                '*.php',
                '*.css',
                '*.js',
                '*.html',
                '*.ini',
                '*.md',
                'api/*',
                'includes/*',
                'media/*',
                'configbackup/*'
            ];
            
            // Incluir logs si se solicita
            if ($include_logs) {
                $include_patterns[] = 'logs/*';
            }
            
            // Definir archivos y directorios a excluir
            $exclude_patterns = [
                'temp_backup/*',
                'backups/*',
                '.git/*',
                '*.tmp',
                '*.log' // Excluir logs por defecto
            ];
            
            if (!$include_logs) {
                $exclude_patterns[] = 'logs/*';
            }
              // Copiar archivos del proyecto
            $this->copyProjectFiles(__DIR__ . '/../', $temp_project_dir, $include_patterns, $exclude_patterns);
            
            // Crear archivo de información del backup
            $this->createBackupInfo($temp_project_dir, 'proyecto');
            
            // Crear ZIP
            $zip_path = $this->backup_dir . "/$backup_name.zip";
            $this->createZip($temp_project_dir, $zip_path);
            
            // Limpiar directorio temporal
            $this->removeDirectory($temp_project_dir);
            
            $this->log("Backup del proyecto completado: $backup_name.zip");
            
            return [
                'success' => true,
                'filename' => "$backup_name.zip",
                'path' => $zip_path,
                'size' => filesize($zip_path)
            ];
            
        } catch (Exception $e) {
            $this->log("Error en backup del proyecto: " . $e->getMessage(), 'ERROR');
            
            // Limpiar en caso de error
            if (is_dir($temp_project_dir)) {
                $this->removeDirectory($temp_project_dir);
            }
            
            throw $e;
        }
    }
    
    /**
     * Crear backup de la base de datos
     */
    public function createDatabaseBackup() {
        $this->log("Iniciando backup de la base de datos");
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_name = "database_backup_$timestamp";
        $temp_db_dir = $this->temp_dir . "/$backup_name";
        
        try {
            // Crear directorio temporal
            if (!mkdir($temp_db_dir, 0755, true)) {
                throw new Exception("No se pudo crear directorio temporal");
            }
            
            // Generar dump SQL
            $sql_file = $temp_db_dir . "/database_dump.sql";
            $this->createSQLDump($sql_file);
            
            // Crear archivo de información del backup
            $this->createBackupInfo($temp_db_dir, 'database');
            
            // Crear script de restauración
            $this->createRestoreScript($temp_db_dir);
            
            // Crear ZIP
            $zip_path = $this->backup_dir . "/$backup_name.zip";
            $this->createZip($temp_db_dir, $zip_path);
            
            // Limpiar directorio temporal
            $this->removeDirectory($temp_db_dir);
            
            $this->log("Backup de la base de datos completado: $backup_name.zip");
            
            return [
                'success' => true,
                'filename' => "$backup_name.zip",
                'path' => $zip_path,
                'size' => filesize($zip_path)
            ];
            
        } catch (Exception $e) {
            $this->log("Error en backup de la base de datos: " . $e->getMessage(), 'ERROR');
            
            // Limpiar en caso de error
            if (is_dir($temp_db_dir)) {
                $this->removeDirectory($temp_db_dir);
            }
            
            throw $e;
        }
    }
    
    /**
     * Crear backup completo (proyecto + base de datos)
     */
    public function createCompleteBackup($include_uploads = true, $include_logs = false) {
        $this->log("Iniciando backup completo (proyecto + base de datos)");
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_name = "complete_backup_$timestamp";
        $temp_complete_dir = $this->temp_dir . "/$backup_name";
        
        try {
            // Crear directorio temporal
            if (!mkdir($temp_complete_dir, 0755, true)) {
                throw new Exception("No se pudo crear directorio temporal");
            }
            
            // Crear subdirectorios
            $project_dir = $temp_complete_dir . "/proyecto";
            $database_dir = $temp_complete_dir . "/database";
            
            mkdir($project_dir, 0755, true);
            mkdir($database_dir, 0755, true);
            
            // Backup del proyecto
            $this->log("Copiando archivos del proyecto...");
            $include_patterns = [
                '*.php', '*.css', '*.js', '*.html', '*.ini', '*.md',
                'api/*', 'includes/*', 'media/*', 'configbackup/*'
            ];
            
            $exclude_patterns = [
                'temp_backup/*', 'backups/*', '.git/*', '*.tmp'
            ];
            
            if ($include_logs) {
                $include_patterns[] = 'logs/*';
            } else {
                $exclude_patterns[] = 'logs/*';
            }
            
            $this->copyProjectFiles(__DIR__ . '/../', $project_dir, $include_patterns, $exclude_patterns);
            
            // Backup de la base de datos
            $this->log("Generando dump de la base de datos...");
            $sql_file = $database_dir . "/database_dump.sql";
            $this->createSQLDump($sql_file);
            $this->createRestoreScript($database_dir);
            
            // Crear archivo de información del backup completo
            $this->createBackupInfo($temp_complete_dir, 'complete');
            
            // Crear archivo README
            $this->createReadme($temp_complete_dir);
            
            // Crear ZIP
            $zip_path = $this->backup_dir . "/$backup_name.zip";
            $this->createZip($temp_complete_dir, $zip_path);
            
            // Limpiar directorio temporal
            $this->removeDirectory($temp_complete_dir);
            
            $this->log("Backup completo finalizado: $backup_name.zip");
            
            return [
                'success' => true,
                'filename' => "$backup_name.zip",
                'path' => $zip_path,
                'size' => filesize($zip_path)
            ];
            
        } catch (Exception $e) {
            $this->log("Error en backup completo: " . $e->getMessage(), 'ERROR');
            
            // Limpiar en caso de error
            if (is_dir($temp_complete_dir)) {
                $this->removeDirectory($temp_complete_dir);
            }
            
            throw $e;
        }
    }
      /**
     * Copiar archivos del proyecto
     */
    private function copyProjectFiles($source_dir, $dest_dir, $include_patterns, $exclude_patterns) {
        // Normalizar rutas para Windows
        $source_dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $source_dir), DIRECTORY_SEPARATOR);
        $dest_dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dest_dir), DIRECTORY_SEPARATOR);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $source_path = $item->getPathname();
            
            // Calcular ruta relativa correctamente
            $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $source_path);
            $relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);
            
            // Convertir separadores para patrones
            $relative_path_for_patterns = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
            
            // Verificar si debe excluirse
            if ($this->shouldExclude($relative_path_for_patterns, $exclude_patterns)) {
                continue;
            }
            
            // Verificar si debe incluirse
            if (!$this->shouldInclude($relative_path_for_patterns, $include_patterns)) {
                continue;
            }
            
            $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $relative_path;
            
            if ($item->isDir()) {
                if (!is_dir($dest_path)) {
                    if (!mkdir($dest_path, 0755, true)) {
                        $this->log("Error creando directorio: $dest_path", 'ERROR');
                        continue;
                    }
                }
            } else {
                $dest_dir_path = dirname($dest_path);
                if (!is_dir($dest_dir_path)) {
                    if (!mkdir($dest_dir_path, 0755, true)) {
                        $this->log("Error creando directorio padre: $dest_dir_path", 'ERROR');
                        continue;
                    }
                }
                
                if (!copy($source_path, $dest_path)) {
                    $this->log("Error copiando archivo: $source_path -> $dest_path", 'ERROR');
                } else {
                    $this->log("Archivo copiado: $relative_path");
                }
            }
        }
    }
      /**
     * Verificar si un archivo debe excluirse
     */
    private function shouldExclude($path, $exclude_patterns) {
        foreach ($exclude_patterns as $pattern) {
            // Convertir patrón de directorio
            if (substr($pattern, -2) === '/*') {
                $dir_pattern = substr($pattern, 0, -2);
                if (strpos($path, $dir_pattern . '/') === 0 || $path === $dir_pattern) {
                    return true;
                }
            }
            
            // Patrón de archivo
            if (fnmatch($pattern, $path)) {
                return true;
            }
            
            // Verificar si está en un subdirectorio excluido
            $path_parts = explode('/', $path);
            foreach ($path_parts as $part) {
                if (fnmatch($pattern, $part)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Verificar si un archivo debe incluirse
     */
    private function shouldInclude($path, $include_patterns) {
        foreach ($include_patterns as $pattern) {
            // Convertir patrón de directorio
            if (substr($pattern, -2) === '/*') {
                $dir_pattern = substr($pattern, 0, -2);
                if (strpos($path, $dir_pattern . '/') === 0) {
                    return true;
                }
            }
            
            // Patrón de archivo
            if (fnmatch($pattern, $path)) {
                return true;
            }
            
            // Verificar si está en un subdirectorio incluido
            $path_parts = explode('/', $path);
            if (in_array($path_parts[0], array_map(function($p) { 
                return substr($p, -2) === '/*' ? substr($p, 0, -2) : $p; 
            }, $include_patterns))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Crear dump SQL de la base de datos
     */
    private function createSQLDump($sql_file) {
        $sql_content = "";
        
        // Cabecera del archivo SQL
        $sql_content .= "-- =====================================================\n";
        $sql_content .= "-- BACKUP DE BASE DE DATOS - SUITE AMBIENTAL\n";
        $sql_content .= "-- Generado el: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- Base de datos: " . $this->config['database']['database'] . "\n";
        $sql_content .= "-- Usuario: " . ($_SESSION['usuario'] ?? 'Sistema') . "\n";
        $sql_content .= "-- =====================================================\n\n";
        
        $sql_content .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql_content .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql_content .= "SET AUTOCOMMIT = 0;\n";
        $sql_content .= "START TRANSACTION;\n";
        $sql_content .= "SET time_zone = '+00:00';\n\n";
        
        // Obtener todas las tablas
        $tables_query = $this->db->query("SHOW TABLES");
        $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $sql_content .= "-- Estructura de tabla `$table`\n";
            $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
              // Obtener estructura de la tabla
            $create_query = $this->db->query("SHOW CREATE TABLE `$table`");
            $create_result = $create_query->fetch(PDO::FETCH_ASSOC);
            
            // La columna puede llamarse 'Create Table' o 'Create Table' según la configuración de MySQL
            $create_table_sql = null;
            foreach ($create_result as $key => $value) {
                if (stripos($key, 'create') !== false && stripos($key, 'table') !== false) {
                    $create_table_sql = $value;
                    break;
                }
            }
            
            if ($create_table_sql === null) {
                // Fallback: tomar el segundo valor (normalmente es el CREATE TABLE)
                $values = array_values($create_result);
                $create_table_sql = isset($values[1]) ? $values[1] : '';
            }
            
            $sql_content .= $create_table_sql . ";\n\n";
            
            // Obtener datos de la tabla
            $data_query = $this->db->query("SELECT * FROM `$table`");
            $rows = $data_query->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $sql_content .= "-- Datos de la tabla `$table`\n";
                
                // Obtener nombres de columnas
                $columns = array_keys($rows[0]);
                $columns_str = '`' . implode('`, `', $columns) . '`';
                
                $sql_content .= "INSERT INTO `$table` ($columns_str) VALUES\n";
                  $values_array = [];
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            // Escapar correctamente los valores
                            $escaped_value = $this->db->quote($value);
                            $values[] = $escaped_value;
                        }
                    }
                    $values_array[] = '(' . implode(', ', $values) . ')';
                }
                
                $sql_content .= implode(",\n", $values_array) . ";\n\n";
            }
        }
        
        $sql_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql_content .= "COMMIT;\n";
        
        // Guardar archivo SQL
        if (file_put_contents($sql_file, $sql_content) === false) {
            throw new Exception("No se pudo crear el archivo SQL");
        }
        
        $this->log("Dump SQL creado: " . basename($sql_file) . " (" . round(filesize($sql_file) / 1024, 2) . " KB)");
    }
    
    /**
     * Crear script de restauración
     */
    private function createRestoreScript($dest_dir) {
        $script_content = "#!/bin/bash\n";
        $script_content .= "# Script de restauración de base de datos\n";
        $script_content .= "# Suite Ambiental - Generado el " . date('Y-m-d H:i:s') . "\n\n";
        $script_content .= "echo \"Restaurando base de datos...\"\n";
        $script_content .= "mysql -u [USUARIO] -p [BASE_DE_DATOS] < database_dump.sql\n";
        $script_content .= "echo \"Restauración completada\"\n";
        
        file_put_contents($dest_dir . "/restore.sh", $script_content);
        
        // Script para Windows
        $bat_content = "@echo off\n";
        $bat_content .= "REM Script de restauración de base de datos\n";
        $bat_content .= "REM Suite Ambiental - Generado el " . date('Y-m-d H:i:s') . "\n\n";
        $bat_content .= "echo Restaurando base de datos...\n";
        $bat_content .= "mysql -u [USUARIO] -p [BASE_DE_DATOS] < database_dump.sql\n";
        $bat_content .= "echo Restauración completada\n";
        $bat_content .= "pause\n";
        
        file_put_contents($dest_dir . "/restore.bat", $bat_content);
    }
    
    /**
     * Crear archivo de información del backup
     */
    private function createBackupInfo($dest_dir, $type) {
        $info = [
            'tipo' => $type,
            'fecha_creacion' => date('Y-m-d H:i:s'),
            'usuario' => $_SESSION['usuario'] ?? 'Sistema',
            'version_sistema' => $this->config['sistema']['version'] ?? '2.0',
            'nombre_sistema' => $this->config['sistema']['nombre'] ?? 'Suite Ambiental'
        ];
        
        if ($type === 'database' || $type === 'complete') {
            $info['base_datos'] = [
                'host' => $this->config['database']['host'],
                'puerto' => $this->config['database']['port'],
                'base_datos' => $this->config['database']['database'],
                'charset' => $this->config['database']['charset']
            ];
        }
        
        $info_content = "INFORMACIÓN DEL BACKUP\n";
        $info_content .= "======================\n\n";
        $info_content .= "Tipo: " . ucfirst($type) . "\n";
        $info_content .= "Fecha de creación: " . $info['fecha_creacion'] . "\n";
        $info_content .= "Usuario: " . $info['usuario'] . "\n";
        $info_content .= "Sistema: " . $info['nombre_sistema'] . " v" . $info['version_sistema'] . "\n";
        
        if (isset($info['base_datos'])) {
            $info_content .= "\nCONFIGURACIÓN DE BASE DE DATOS:\n";
            $info_content .= "Host: " . $info['base_datos']['host'] . "\n";
            $info_content .= "Puerto: " . $info['base_datos']['puerto'] . "\n";
            $info_content .= "Base de datos: " . $info['base_datos']['base_datos'] . "\n";
            $info_content .= "Charset: " . $info['base_datos']['charset'] . "\n";
        }
        
        file_put_contents($dest_dir . "/backup_info.txt", $info_content);
        file_put_contents($dest_dir . "/backup_info.json", json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Crear archivo README
     */
    private function createReadme($dest_dir) {
        $readme_content = "# BACKUP COMPLETO - SUITE AMBIENTAL\n\n";
        $readme_content .= "Este archivo contiene un backup completo del sistema Suite Ambiental.\n\n";
        $readme_content .= "## CONTENIDO\n\n";
        $readme_content .= "- **proyecto/**: Todos los archivos del proyecto web\n";
        $readme_content .= "- **database/**: Backup de la base de datos\n";
        $readme_content .= "  - `database_dump.sql`: Dump completo de la base de datos\n";
        $readme_content .= "  - `restore.sh`: Script de restauración para Linux/Mac\n";
        $readme_content .= "  - `restore.bat`: Script de restauración para Windows\n\n";
        $readme_content .= "## INSTRUCCIONES DE RESTAURACIÓN\n\n";
        $readme_content .= "### 1. Restaurar archivos del proyecto\n";
        $readme_content .= "Copie todos los archivos de la carpeta `proyecto/` a su servidor web.\n\n";
        $readme_content .= "### 2. Restaurar base de datos\n";
        $readme_content .= "1. Cree una nueva base de datos en su servidor MySQL\n";
        $readme_content .= "2. Ejecute el comando:\n";
        $readme_content .= "   ```\n";
        $readme_content .= "   mysql -u [usuario] -p [nombre_base_datos] < database/database_dump.sql\n";
        $readme_content .= "   ```\n";
        $readme_content .= "3. O use los scripts incluidos modificando las credenciales\n\n";
        $readme_content .= "### 3. Configurar conexión\n";
        $readme_content .= "Edite el archivo `config.ini` con las credenciales de su nueva base de datos.\n\n";
        $readme_content .= "## INFORMACIÓN ADICIONAL\n\n";
        $readme_content .= "- Fecha de backup: " . date('Y-m-d H:i:s') . "\n";
        $readme_content .= "- Usuario: " . ($_SESSION['usuario'] ?? 'Sistema') . "\n";
        $readme_content .= "- Versión: " . ($this->config['sistema']['version'] ?? '2.0') . "\n";
        
        file_put_contents($dest_dir . "/README.md", $readme_content);
    }
      /**
     * Crear archivo ZIP
     */
    private function createZip($source_dir, $zip_path) {
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("No se pudo crear el archivo ZIP: $zip_path");
        }
        
        // Normalizar ruta de origen
        $source_dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $source_dir), DIRECTORY_SEPARATOR);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $file_path = $file->getPathname();
            
            // Calcular ruta relativa correctamente
            $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $file_path);
            $relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);
            
            // Convertir separadores a formato Unix para ZIP
            $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
            
            if ($file->isDir()) {
                // Agregar directorio al ZIP
                $zip->addEmptyDir($relative_path . '/');
            } else {
                // Agregar archivo al ZIP
                if (!$zip->addFile($file_path, $relative_path)) {
                    $this->log("Error agregando archivo al ZIP: $relative_path", 'ERROR');
                }
            }
        }
        
        if (!$zip->close()) {
            throw new Exception("Error al cerrar el archivo ZIP");
        }
        
        $this->log("Archivo ZIP creado: " . basename($zip_path) . " (" . round(filesize($zip_path) / 1024 / 1024, 2) . " MB)");
    }    /**
     * Eliminar directorio recursivamente
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        // Normalizar ruta
        $dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
        
        try {
            // Método mejorado para Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $this->removeDirectoryWindows($dir);
            } else {
                $this->removeDirectoryUnix($dir);
            }
        } catch (Exception $e) {
            $this->log("Error en removeDirectory: " . $e->getMessage(), 'ERROR');
            
            // Intentar con comando del sistema como último recurso
            $this->removeDirectoryWithSystemCommand($dir);
        }
    }
      /**
     * Eliminar directorio en Windows con reintentos
     */
    private function removeDirectoryWindows($dir) {
        $maxRetries = 5;
        $retryDelay = 200000; // 200ms en microsegundos
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Intentar cerrar cualquier handle abierto
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                // Esperar un poco para que Windows libere recursos
                if ($attempt > 1) {
                    usleep($retryDelay * $attempt);
                }
                
                if ($this->removeDirectoryWindowsMethod($dir)) {
                    return true;
                }
                
                $this->log("Intento $attempt/$maxRetries fallido para eliminar: $dir", 'WARNING');
                
            } catch (Exception $e) {
                $this->log("Error en intento $attempt: " . $e->getMessage(), 'WARNING');
                if ($attempt === $maxRetries) {
                    throw $e;
                }
            }
        }
        
        throw new Exception("No se pudo eliminar el directorio después de $maxRetries intentos");
    }
      /**
     * Método específico de Windows para eliminar directorios
     */
    private function removeDirectoryWindowsMethod($dir) {
        // Primero intentar cambiar permisos del directorio completo
        $this->changePermissionsRecursive($dir);
        
        // Usar SplFileInfo para mejor manejo de archivos en Windows
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            
            if ($fileInfo->isDir()) {
                // Asegurar que el directorio está vacío
                if (!$this->isDirectoryEmpty($path)) {
                    $this->log("Directorio no está vacío: $path", 'WARNING');
                    continue;
                }
                
                // Intentar eliminar directorio con reintentos
                $success = false;
                for ($i = 0; $i < 3; $i++) {
                    if (rmdir($path)) {
                        $success = true;
                        break;
                    }
                    usleep(50000); // 50ms
                }
                
                if (!$success) {
                    $this->log("No se pudo eliminar directorio: $path", 'ERROR');
                    return false;
                }
            } else {
                // Para archivos, intentar eliminar con reintentos
                $success = false;
                for ($i = 0; $i < 3; $i++) {
                    // Cambiar permisos del archivo
                    @chmod($path, 0777);
                    
                    if (unlink($path)) {
                        $success = true;
                        break;
                    }
                    usleep(50000); // 50ms
                }
                
                if (!$success) {
                    $this->log("No se pudo eliminar archivo: $path", 'ERROR');
                    return false;
                }
            }
        }
        
        // Eliminar directorio raíz
        for ($i = 0; $i < 3; $i++) {
            if (rmdir($dir)) {
                return true;
            }
            usleep(100000); // 100ms
        }
        
        $this->log("No se pudo eliminar directorio raíz: $dir", 'ERROR');
        return false;
    }
    
    /**
     * Cambiar permisos recursivamente en Windows
     */
    private function changePermissionsRecursive($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        try {
            @chmod($dir, 0777);
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $fileInfo) {
                @chmod($fileInfo->getPathname(), 0777);
            }
        } catch (Exception $e) {
            // Silenciar errores de permisos
        }
    }
      /**
     * Eliminar directorio en sistemas Unix
     */
    private function removeDirectoryUnix($dir) {
        return $this->removeDirectoryRecursive($dir);
    }
    
    /**
     * Eliminar directorio usando comandos del sistema
     */
    private function removeDirectoryWithSystemCommand($dir) {
        $this->log("Intentando eliminar con comando del sistema: $dir", 'WARNING');
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Usar attrib para quitar atributos de solo lectura
            exec("attrib -r \"$dir\\*.*\" /s /d 2>nul");
            
            // Usar rmdir con parámetros más agresivos
            exec("rmdir /s /q \"$dir\" 2>nul", $output, $return_var);
            
            if ($return_var === 0) {
                $this->log("Directorio eliminado exitosamente con rmdir del sistema", 'INFO');
            } else {
                // Último recurso: usar del y rmdir por separado
                exec("del /f /s /q \"$dir\\*.*\" 2>nul");
                exec("rmdir /s /q \"$dir\" 2>nul");
                
                if (!is_dir($dir)) {
                    $this->log("Directorio eliminado con comandos combinados del sistema", 'INFO');
                } else {
                    $this->log("Error: No se pudo eliminar el directorio con comandos del sistema", 'ERROR');
                }
            }
        } else {
            exec("rm -rf \"$dir\" 2>/dev/null", $output, $return_var);
            if ($return_var !== 0) {
                $this->log("Error usando rm del sistema: " . implode(' ', $output), 'ERROR');
            }
        }
    }
    private function removeDirectoryRecursive($dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            
            if ($file->isDir()) {
                // Verificar que el directorio esté vacío antes de eliminarlo
                if ($this->isDirectoryEmpty($filePath)) {
                    if (!rmdir($filePath)) {
                        $this->log("Error eliminando directorio: $filePath", 'ERROR');
                        return false;
                    }
                } else {
                    $this->log("Directorio no vacío: $filePath", 'WARNING');
                    return false;
                }
            } else {
                // Intentar cambiar permisos en Windows si es necesario
                if (PHP_OS_FAMILY === 'Windows') {
                    chmod($filePath, 0777);
                }
                
                if (!unlink($filePath)) {
                    $this->log("Error eliminando archivo: $filePath", 'ERROR');
                    return false;
                }
            }
        }
        
        // Eliminar directorio raíz
        if (!rmdir($dir)) {
            $this->log("Error eliminando directorio raíz: $dir", 'ERROR');
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar si un directorio está vacío
     */
    private function isDirectoryEmpty($dir) {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }
    
    /**
     * Obtener lista de backups disponibles
     */
    public function getAvailableBackups() {
        $backups = [];
        
        if (!is_dir($this->backup_dir)) {
            return $backups;
        }
        
        $files = scandir($this->backup_dir);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $file_path = $this->backup_dir . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'date' => filemtime($file_path),
                    'type' => $this->determineBackupType($file)
                ];
            }
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
        
        return $backups;
    }
      /**
     * Determinar tipo de backup por el nombre del archivo
     */
    private function determineBackupType($filename) {
        if (strpos($filename, 'complete_backup') === 0) {
            return 'complete';
        } elseif (strpos($filename, 'database_backup') === 0 || strpos($filename, 'db_backup') === 0) {
            return 'database';
        } elseif (strpos($filename, 'proyecto_backup') === 0) {
            return 'proyecto';
        }
        
        // Si no se puede determinar por nombre, tratar como completo por defecto
        return 'complete';
    }
    
    /**
     * Eliminar backup
     */
    public function deleteBackup($filename) {
        $file_path = $this->backup_dir . '/' . basename($filename);
        
        if (!file_exists($file_path)) {
            throw new Exception("El archivo de backup no existe");
        }
        
        if (!unlink($file_path)) {
            throw new Exception("No se pudo eliminar el archivo de backup");
        }
        
        $this->log("Backup eliminado: $filename");
        return true;
    }    /**
     * Restaurar backup
     * 
     * Esta función restaura un backup existente al sistema
     * El proceso:
     * 1. Extrae el contenido del backup al directorio temporal
     * 2. Verifica el tipo de backup (proyecto, database, completo)
     * 3. Dependiendo del tipo, restaura los archivos y/o la base de datos
     * 4. Limpia los archivos temporales
     * 
     * @param string $filename Nombre del archivo de backup a restaurar
     * @return array Resultado de la operación
     * @throws Exception Si ocurre algún error en el proceso
     */
    public function restoreBackup($filename) {
        // Limpiar el nombre del archivo por seguridad
        $filename = basename($filename);
        $file_path = $this->backup_dir . '/' . $filename;
        
        // Verificar que el archivo existe
        if (!file_exists($file_path)) {
            throw new Exception("El archivo de backup $filename no existe");
        }
        
        // Crear directorio temporal único para la restauración
        $restore_temp_dir = $this->temp_dir . '/restore_' . uniqid();
        if (!mkdir($restore_temp_dir, 0755, true)) {
            throw new Exception("No se pudo crear el directorio temporal para la restauración");
        }
        
        // Registrar inicio de restauración
        $this->log("Iniciando restauración de backup: $filename");
        
        try {
            // Extraer el archivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== true) {
                throw new Exception("No se pudo abrir el archivo ZIP");
            }
            
            // Extraer todos los archivos al directorio temporal
            $zip->extractTo($restore_temp_dir);
            $zip->close();
            
            // Determinar tipo de backup
            $backup_type = $this->determineBackupType($filename);
            $result = [
                'type' => $backup_type,
                'filename' => $filename,
                'restored' => []
            ];
            
            // Realizar restauración según el tipo
            switch ($backup_type) {
                case 'proyecto':
                    $this->log("Restaurando archivos del proyecto desde $filename");
                    $result['restored']['files'] = $this->restoreProjectFiles($restore_temp_dir);
                    break;
                    
                case 'database':
                    $this->log("Restaurando base de datos desde $filename");
                    $result['restored']['database'] = $this->restoreDatabase($restore_temp_dir);
                    break;
                    
                case 'complete':
                    $this->log("Restaurando backup completo desde $filename");
                    $result['restored']['files'] = $this->restoreProjectFiles($restore_temp_dir);
                    $result['restored']['database'] = $this->restoreDatabase($restore_temp_dir);
                    break;
                    
                default:
                    throw new Exception("Tipo de backup desconocido");
            }
            
            // Limpiar archivos temporales
            $this->rrmdir($restore_temp_dir);
            
            $this->log("Restauración completada con éxito: $filename");
            return $result;
            
        } catch (Exception $e) {
            // Limpiar archivos temporales en caso de error
            if (file_exists($restore_temp_dir)) {
                $this->rrmdir($restore_temp_dir);
            }
            
            $this->log("Error durante la restauración: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Restaurar archivos del proyecto
     */
    private function restoreProjectFiles($source_dir) {
        $project_dir = realpath(__DIR__ . '/../');
        $source_project_dir = $source_dir;
        
        // Verificar si hay un subdirectorio con el proyecto
        if (is_dir($source_dir . '/www') || is_dir($source_dir . '/proyecto')) {
            $source_project_dir = is_dir($source_dir . '/www') ? $source_dir . '/www' : $source_dir . '/proyecto';
        }
        
        // Verificar estructura antes de restaurar
        if (!is_dir($source_project_dir) || !file_exists($source_project_dir . '/index.php')) {
            throw new Exception("Estructura de backup inválida: no se encontró estructura de proyecto válida");
        }
        
        // Crear backup automático de seguridad antes de la restauración
        $safety_backup = $this->createProjectBackup(false, false, 'auto_pre_restore_' . date('Y-m-d_H-i-s'));
        $this->log("Backup de seguridad creado antes de restaurar: " . $safety_backup['filename']);
        
        // Copiar archivos preservando directorios sensibles
        $excludeDirs = ['backups', 'logs', 'configbackup', 'temp_backup'];
        $excludeFiles = ['.git', '.gitignore', 'config.ini'];
        $restoredFiles = $this->copyDirectory($source_project_dir, $project_dir, $excludeDirs, $excludeFiles);
        
        $this->log("Restauración de archivos completada: $restoredFiles archivos restaurados");
        
        // Regenerar archivo .htaccess si no existe
        if (!file_exists($project_dir . '/.htaccess')) {
            $this->createDefaultHtaccess($project_dir);
        }
        
        return [
            'count' => $restoredFiles,
            'safety_backup' => $safety_backup['filename']
        ];
    }
    
    /**
     * Restaurar base de datos
     */
    private function restoreDatabase($source_dir) {
        // Buscar archivo SQL en el directorio temporal
        $sql_files = glob($source_dir . '/*.sql');
        if (empty($sql_files)) {
            // Buscar en subdirectorio database
            $sql_files = glob($source_dir . '/database/*.sql');
            if (empty($sql_files)) {
                throw new Exception("No se encontró archivo SQL para restaurar la base de datos");
            }
        }
        
        // Usar el primer archivo SQL encontrado
        $sql_file = $sql_files[0];
        $this->log("Archivo SQL encontrado: " . basename($sql_file));
        
        // Conectar a la base de datos
        $dbManager = getDBManager($this->config);
        $pdo = $dbManager->getConnection();
        
        // Crear backup de seguridad de la base de datos actual
        $safety_backup = $this->createDatabaseBackup('auto_pre_restore_db_' . date('Y-m-d_H-i-s'));
        $this->log("Backup de seguridad de base de datos creado: " . $safety_backup['filename']);
        
        // Leer y ejecutar el archivo SQL
        $sql = file_get_contents($sql_file);
        $sqlQueries = $this->splitSqlQueries($sql);
        $executedQueries = 0;
        
        try {
            // Desactivar restricciones de claves foráneas temporalmente
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
            
            foreach ($sqlQueries as $query) {
                if (trim($query) !== '') {
                    $pdo->exec($query);
                    $executedQueries++;
                }
            }
            
            // Reactivar restricciones de claves foráneas
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            
        } catch (PDOException $e) {
            $this->log("Error al restaurar la base de datos: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error al restaurar la base de datos: " . $e->getMessage());
        }
        
        $this->log("Base de datos restaurada exitosamente: $executedQueries consultas ejecutadas");
        
        return [
            'queries' => $executedQueries,
            'safety_backup' => $safety_backup['filename']
        ];
    }
    
    /**
     * Divide un archivo SQL en consultas individuales
     */
    private function splitSqlQueries($sql) {
        // Eliminar comentarios
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
        $sql = preg_replace('/-- .*\n/', '', $sql);
        
        // Dividir por punto y coma, pero respetando delimitadores en triggers/procedures
        $queries = [];
        $currentQuery = '';
        $delimiter = ';';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            $nextChar = ($i < strlen($sql) - 1) ? $sql[$i + 1] : '';
            
            // Manejar strings para evitar detectar ; dentro de ellos
            if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i-1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } else if ($char === $stringChar) {
                    $inString = false;
                }
            }
            
            // Detectar cambio de delimitador
            if (!$inString && strtoupper(substr($sql, $i, 9)) === 'DELIMITER ') {
                $i += 9;
                $delimiter = '';
                while ($i < strlen($sql) && !in_array($sql[$i], ["\r", "\n"])) {
                    $delimiter .= $sql[$i];
                    $i++;
                }
                continue;
            }
            
            // Detectar fin de consulta
            if (!$inString && substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $currentQuery .= $char;
                $queries[] = $currentQuery;
                $currentQuery = '';
                $i += (strlen($delimiter) - 1);
                continue;
            }
            
            $currentQuery .= $char;
        }
        
        // Añadir última consulta si no está vacía
        if (trim($currentQuery) !== '') {
            $queries[] = $currentQuery;
        }
        
        return $queries;
    }
    
    /**
     * Copia un directorio completo preservando la estructura
     */
    private function copyDirectory($source, $dest, $excludeDirs = [], $excludeFiles = []) {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relPath = $iterator->getSubPathName();
            $targetPath = $dest . DIRECTORY_SEPARATOR . $relPath;
            
            // Verificar exclusiones
            $excluded = false;
            foreach ($excludeFiles as $exclude) {
                if (strpos($relPath, $exclude) !== false) {
                    $excluded = true;
                    break;
                }
            }
            
            foreach ($excludeDirs as $exclude) {
                if (strpos($relPath, $exclude . DIRECTORY_SEPARATOR) === 0) {
                    $excluded = true;
                    break;
                }
            }
            
            if ($excluded) {
                continue;
            }
            
            if ($item->isDir()) {
                if (!file_exists($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                if (copy($item, $targetPath)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
      // La función determineBackupType ya está definida anteriormente en el archivo
    
    /**
     * Crea un archivo .htaccess por defecto si no existe
     */    private function createDefaultHtaccess($dir) {
        // Evitar crear .htaccess en la raíz del servidor web para evitar conflictos con localhost
        if ($dir === realpath(__DIR__ . '/..')) {
            $this->log("Omitiendo creación de .htaccess en la raíz para evitar problemas con localhost", 'WARNING');
            return;
        }
        
        // Solo crear .htaccess en directorios específicos que requieran protección
        $allowedDirs = ['backups', 'logs', 'configbackup', 'temp_backup'];
        $createFile = false;
        
        foreach ($allowedDirs as $allowed) {
            if (strpos($dir, $allowed) !== false) {
                $createFile = true;
                break;
            }
        }
        
        if (!$createFile) {
            $this->log("Omitiendo creación de .htaccess en directorio no especificado: " . basename($dir), 'INFO');
            return;
        }
        
        $content = "# Archivo .htaccess generado automáticamente tras restauración\n";
        $content .= "# Fecha: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "# Prevenir listado de directorios\n";
        $content .= "Options -Indexes\n\n";
        $content .= "# Proteger archivos sensibles\n";
        $content .= "<FilesMatch \"^(config\.ini|.*\.log)$\">\n";
        $content .= "    Require all denied\n";
        $content .= "</FilesMatch>\n";
        
        file_put_contents($dir . '/.htaccess', $content);
        $this->log("Archivo .htaccess creado con configuración predeterminada en: " . basename($dir));
    }

    /**
     * Elimina un directorio recursivamente
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object)) {
                        $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    } else {
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Descargar backup
     */
    public function downloadBackup($filename) {
        // Limpiar el nombre del archivo por seguridad
        $filename = basename($filename);
        $file_path = $this->backup_dir . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($file_path)) {
            throw new Exception("El archivo de backup no existe: $filename");
        }
        
        if (!is_readable($file_path)) {
            throw new Exception("No se puede leer el archivo de backup: $filename");
        }
        
        $this->log("Descarga iniciada: $filename");
        
        // Limpiar cualquier salida previa
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Configurar headers para descarga
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        // Enviar archivo en chunks para evitar problemas de memoria con archivos grandes
        $file = fopen($file_path, 'rb');
        if ($file === false) {
            throw new Exception("No se pudo abrir el archivo para descarga: $filename");
        }
        
        while (!feof($file)) {
            echo fread($file, 8192);
            flush();
        }
        fclose($file);
        
        $this->log("Descarga completada: $filename");
        exit();
    }
}
?>
