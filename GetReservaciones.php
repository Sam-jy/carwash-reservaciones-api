<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'database.php';
include_once 'Reservaciones.php';
include_once 'Auth.php';

// Verificar autenticación
$auth = new Auth();
$tokenData = $auth->validateToken();

if (!$tokenData) {
    http_response_code(401);
    echo json_encode(
        array(
            "message" => "Acceso no autorizado. Se requiere un token válido."
        )
    );
    exit();
}

$db = new DataBase();
$instant = $db->getConnection();

$reserva = new Reservaciones($instant);
$cmd = $reserva->GetListReservaciones();
$count = $cmd->rowCount();

if($count > 0)
{
    $reservacionesArray = array();

    while($row = $cmd->fetch(PDO::FETCH_ASSOC))
    {
        extract($row);
        $e = array(
            "id" => $id,
            "nombre_cliente" => $nombre_cliente,
            "telefono" => $telefono,
            "email" => $email,
            "tipo_vehiculo" => $tipo_vehiculo,
            "placa" => $placa,
            "fecha_reservacion" => $fecha_reservacion,
            "hora_reservacion" => $hora_reservacion,
            "servicio" => $servicio,
            "precio" => $precio,
            "estado" => $estado,
            "notas" => $notas,
            "fecha_creacion" => $fecha_creacion
        );

        array_push($reservacionesArray, $e);
    }

    http_response_code(200);
    echo json_encode($reservacionesArray);
}
else
{
    http_response_code(404);
    echo json_encode( 
        array( "issuccess" => false,
               "message" => "No hay reservaciones registradas")
    );
}

?> 