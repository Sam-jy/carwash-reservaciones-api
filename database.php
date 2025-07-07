<?php
 
 class DataBase 
 {
    private $host = "localhost:3307";
    private $database = "carwash_db";
    private $username = "root";
    private $password = "";

    public $conexion;

    // Funcion de conexion a la base de datos

    public function getConnection()
    {
        $this->conexion = null;

        try
        {
            $this->conexion = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->database, $this->username, $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
            $this->conexion->exec("set names utf8");

            //echo " Conectado a la base de datos " ;
        }
        catch(PDOException $exception)
        {
            echo "No se pudo conectar al a base datos: " . $exception->getMessage();
        }
        return $this->conexion;

    }

 }

?>