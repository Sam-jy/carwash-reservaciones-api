<?php
class Database 
{
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;

    public $conexion;

    public function __construct() {
        $configPath = __DIR__ . '/config/database.php';
        if (!file_exists($configPath)) {
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


    public static function connect() {
        $db = new self();
        return $db->getConnection();
    }
}

?>