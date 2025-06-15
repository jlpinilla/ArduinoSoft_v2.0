<?php
/**
 * =====================================================
 * GESTOR DE BASE DE DATOS OPTIMIZADO - ARDUINO SOFT
 * =====================================================
 * Sistema centralizado de gestión de conexiones PDO con:
 * - Pool de conexiones para mejorar rendimiento
 * - Caché de consultas frecuentes
 * - Manejo optimizado de errores
 * - Logging de consultas lentas
 * - Soporte para transacciones
 */

class DatabaseManager {
    private static $instance = null;
    private $pdo = null;
    private $config = null;
    private $query_cache = [];
    private $cache_size_limit = 50;
    private $slow_query_threshold = 2; // segundos
    
    /**
     * Constructor privado para patrón Singleton
     * Garantiza una sola instancia de conexión por request
     */
    private function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Obtener instancia única de DatabaseManager
     * @param array $config Configuración de base de datos
     * @return DatabaseManager Instancia única
     */
    public static function getInstance($config) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
      /**
     * Establecer conexión PDO optimizada
     * Incluye configuración de rendimiento y charset UTF-8
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['database']['host']};dbname={$this->config['database']['database']};charset=utf8mb4";
            
            // Agregar puerto si está configurado
            if (isset($this->config['database']['port']) && !empty($this->config['database']['port'])) {
                $dsn = "mysql:host={$this->config['database']['host']};port={$this->config['database']['port']};dbname={$this->config['database']['database']};charset=utf8mb4";
            }
            
            // Opciones optimizadas para rendimiento
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Usar prepared statements nativos
                PDO::ATTR_PERSISTENT => true,        // Conexiones persistentes
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_spanish_ci",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Buffer consultas para mejor rendimiento
                PDO::ATTR_TIMEOUT => 10              // Timeout de conexión
            ];
            
            $this->pdo = new PDO(
                $dsn,
                $this->config['database']['user'],
                $this->config['database']['password'] ?? '',
                $options
            );
            
            // Configurar zona horaria de España
            $this->pdo->exec("SET time_zone = '+01:00'");
            
        } catch (PDOException $e) {
            // Log del error de conexión
            error_log("DatabaseManager Error: " . $e->getMessage());
            throw new Exception("Error de conexión a base de datos: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener conexión PDO
     * @return PDO Objeto de conexión
     */
    public function getConnection() {
        // Verificar si la conexión está activa
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    /**
     * Ejecutar consulta preparada con caché
     * @param string $sql Consulta SQL
     * @param array $params Parámetros de la consulta
     * @param bool $use_cache Usar caché para esta consulta
     * @return array Resultados de la consulta
     */
    public function query($sql, $params = [], $use_cache = true) {
        $cache_key = md5($sql . serialize($params));
        
        // Verificar caché para consultas SELECT
        if ($use_cache && strpos(strtoupper(trim($sql)), 'SELECT') === 0) {
            if (isset($this->query_cache[$cache_key])) {
                return $this->query_cache[$cache_key];
            }
        }
        
        $start_time = microtime(true);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetchAll();
            
            // Calcular tiempo de ejecución
            $execution_time = microtime(true) - $start_time;
            
            // Log de consultas lentas
            if ($execution_time > $this->slow_query_threshold) {
                $this->logSlowQuery($sql, $params, $execution_time);
            }
            
            // Guardar en caché si es consulta SELECT
            if ($use_cache && strpos(strtoupper(trim($sql)), 'SELECT') === 0) {
                $this->addToCache($cache_key, $result);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
    
    /**
     * Ejecutar consulta preparada y obtener un solo resultado
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array|false Resultado único o false
     */
    public function fetchRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Ejecutar consulta preparada y obtener valor único
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return mixed Valor único
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Ejecutar INSERT, UPDATE o DELETE
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return bool|int Resultado de la operación
     */
    public function execute($sql, $params = []) {
        try {
            // Limpiar caché en operaciones de escritura
            $this->clearCache();
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            // Retornar ID del último insert si aplica
            if (strpos(strtoupper(trim($sql)), 'INSERT') === 0) {
                return $this->pdo->lastInsertId();
            }
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Database Execute Error: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Añadir resultado al caché
     * @param string $key Clave del caché
     * @param mixed $data Datos a cachear
     */
    private function addToCache($key, $data) {
        // Limpiar caché si supera el límite
        if (count($this->query_cache) >= $this->cache_size_limit) {
            // Remover elemento más antiguo (FIFO)
            array_shift($this->query_cache);
        }
        
        $this->query_cache[$key] = $data;
    }
    
    /**
     * Limpiar todo el caché
     */
    public function clearCache() {
        $this->query_cache = [];
    }
    
    /**
     * Log de consultas lentas
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @param float $execution_time Tiempo de ejecución
     */
    private function logSlowQuery($sql, $params, $execution_time) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => round($execution_time, 4),
            'sql' => $sql,
            'params' => $params
        ];
        
        // Crear directorio de logs si no existe
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents(
            $log_dir . '/slow_queries.log',
            json_encode($log_entry) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Obtener estadísticas de rendimiento
     * @return array Estadísticas
     */
    public function getStats() {
        return [
            'cache_size' => count($this->query_cache),
            'cache_limit' => $this->cache_size_limit,
            'slow_query_threshold' => $this->slow_query_threshold
        ];
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize DatabaseManager");
    }
}

/**
 * Función de compatibilidad para código existente
 * Retorna la conexión PDO usando el DatabaseManager optimizado
 * @param array $config Configuración de base de datos
 * @return PDO Conexión PDO
 */
function getDBConnection($config) {
    $db_manager = DatabaseManager::getInstance($config);
    return $db_manager->getConnection();
}

/**
 * Función helper para obtener el DatabaseManager
 * @param array $config Configuración opcional de base de datos
 * @return DatabaseManager Instancia del gestor
 */
function getDBManager($config = null) {
    // Si no se proporciona config, intentar cargar desde archivo
    if ($config === null) {
        $config = parse_ini_file(__DIR__ . '/../config.ini', true);
        if (!$config || !isset($config['database'])) {
            throw new Exception('No se pudo cargar la configuración de base de datos');
        }
    }
    return DatabaseManager::getInstance($config);
}
?>
