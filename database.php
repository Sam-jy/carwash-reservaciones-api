<?php

/**
 * Clase de configuración y conexión a la base de datos
 * Maneja la conexión PDO con configuración centralizada
 */
class Database 
{
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;

    public $conexion;

    public function __construct() {
        // Cargar configuración desde archivo de configuración
        $configPath = __DIR__ . '/config/database.php';
        if (!file_exists($configPath)) {
            // Fallback a configuración por defecto
            $config = [
                'host' => 'localhost:3307',
                'database' => 'carwash_db',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4'
            ];
        } else {
            $config = require_once $configPath;
        }
        
        $this->host = $config['host'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->charset = $config['charset'];
    }

    /**
     * Establece y retorna la conexión a la base de datos
     * @return PDO|null
     */
    public function getConnection()
    {
        $this->conexion = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=" . $this->charset;
            
            $this->conexion = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->charset
            ]);

        } catch(PDOException $exception) {
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            throw new Exception("Error al conectar con la base de datos");
        }
        
        return $this->conexion;
    }

    /**
     * Método estático para obtener una instancia de conexión rápidamente
     * @return PDO
     */
    public static function connect() {
        $db = new self();
        return $db->getConnection();
    }
}

?>