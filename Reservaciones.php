<?php
class Reservaciones
{

    private $conexion;
    private $table = "reservaciones";

    public $id;
    public $nombre_cliente;
    public $telefono;
    public $email;
    public $tipo_vehiculo;
    public $placa;
    public $fecha_reservacion;
    public $hora_reservacion;
    public $servicio;
    public $precio;
    public $estado;
    public $notas;
    public $fecha_creacion;

    public function __construct($db)
    {
        $this->conexion = $db;
    }


    public function createReservacion()
    {
        $consulta = "INSERT INTO 
                    " . $this->table . "
                    SET 
                    nombre_cliente = :nombre_cliente,
                    telefono = :telefono,
                    email = :email,
                    tipo_vehiculo = :tipo_vehiculo,
                    placa = :placa,
                    fecha_reservacion = :fecha_reservacion,
                    hora_reservacion = :hora_reservacion,
                    servicio = :servicio,
                    precio = :precio,
                    estado = :estado,
                    notas = :notas";

        $comando = $this->conexion->prepare($consulta);

        $this->nombre_cliente = htmlspecialchars(strip_tags($this->nombre_cliente));
        $this->nombre_cliente = htmlspecialchars(strip_tags($this->nombre_cliente));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->tipo_vehiculo = htmlspecialchars(strip_tags($this->tipo_vehiculo));
        $this->placa = htmlspecialchars(strip_tags($this->placa));
        $this->fecha_reservacion = htmlspecialchars(strip_tags($this->fecha_reservacion));
        $this->hora_reservacion = htmlspecialchars(strip_tags($this->hora_reservacion));
        $this->servicio = htmlspecialchars(strip_tags($this->servicio));
        $this->precio = htmlspecialchars(strip_tags($this->precio));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->notas = htmlspecialchars(strip_tags($this->notas));

        $comando->bindParam(":nombre_cliente", $this->nombre_cliente);
        $comando->bindParam(":nombre_cliente", $this->nombre_cliente);
        $comando->bindParam(":telefono", $this->telefono);
        $comando->bindParam(":email", $this->email);
        $comando->bindParam(":tipo_vehiculo", $this->tipo_vehiculo);
        $comando->bindParam(":placa", $this->placa);
        $comando->bindParam(":fecha_reservacion", $this->fecha_reservacion);
        $comando->bindParam(":hora_reservacion", $this->hora_reservacion);
        $comando->bindParam(":servicio", $this->servicio);
        $comando->bindParam(":precio", $this->precio);
        $comando->bindParam(":estado", $this->estado);
        $comando->bindParam(":notas", $this->notas);

        if($comando->execute())
        {
            return true;
        }
        return false;
    }

    public function GetListReservaciones()
    {
        $consulta = "SELECT * FROM " . $this->table . "";
        $comando = $this->conexion->prepare($consulta);
        $comando->execute();

        return $comando;
    }

    public function updateReservacion()
    {
        $consulta = "UPDATE " . $this->table . " SET 
                        nombre_cliente = :nombre_cliente,
                        telefono = :telefono,
                        email = :email,
                        tipo_vehiculo = :tipo_vehiculo,
                        placa = :placa,
                        fecha_reservacion = :fecha_reservacion,
                        hora_reservacion = :hora_reservacion,
                        servicio = :servicio,
                        precio = :precio,
                        estado = :estado,
                        notas = :notas
                    WHERE id = :id";

        $comando = $this->conexion->prepare($consulta);

        // Sanitización
        $this->nombre_cliente = htmlspecialchars(strip_tags($this->nombre_cliente));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->tipo_vehiculo = htmlspecialchars(strip_tags($this->tipo_vehiculo));
        $this->placa = htmlspecialchars(strip_tags($this->placa));
        $this->fecha_reservacion = htmlspecialchars(strip_tags($this->fecha_reservacion));
        $this->hora_reservacion = htmlspecialchars(strip_tags($this->hora_reservacion));
        $this->servicio = htmlspecialchars(strip_tags($this->servicio));
        $this->precio = htmlspecialchars(strip_tags($this->precio));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->notas = htmlspecialchars(strip_tags($this->notas));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Binding de datos
        $comando->bindParam(':nombre_cliente', $this->nombre_cliente);
        $comando->bindParam(':telefono', $this->telefono);
        $comando->bindParam(':email', $this->email);
        $comando->bindParam(':tipo_vehiculo', $this->tipo_vehiculo);
        $comando->bindParam(':placa', $this->placa);
        $comando->bindParam(':fecha_reservacion', $this->fecha_reservacion);
        $comando->bindParam(':hora_reservacion', $this->hora_reservacion);
        $comando->bindParam(':servicio', $this->servicio);
        $comando->bindParam(':precio', $this->precio);
        $comando->bindParam(':estado', $this->estado);
        $comando->bindParam(':notas', $this->notas);
        $comando->bindParam(':id', $this->id);

        return $comando->execute();
    }


    // Delete

    public function deleteReservacion()
    {
        $consulta = "DELETE FROM " . $this->table . " WHERE id = :id";

        $comando = $this->conexion->prepare($consulta);

        // Sanitización
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Binding
        $comando->bindParam(':id', $this->id);

        return $comando->execute();
    }

}


?> 