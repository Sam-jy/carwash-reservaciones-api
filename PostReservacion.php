<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
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

$data = json_decode(file_get_contents("php://input"));


if(isset($data) && 
   !empty($data->nombre_cliente) &&
   !empty($data->telefono) &&
   !empty($data->tipo_vehiculo) &&
   !empty($data->placa) &&
   !empty($data->fecha_reservacion) &&
   !empty($data->hora_reservacion) &&
   !empty($data->servicio))
{

    $reserva->nombre_cliente = $data->nombre_cliente;
    $reserva->telefono = $data->telefono;
    $reserva->email = $data->email ?? null;
    $reserva->tipo_vehiculo = $data->tipo_vehiculo;
    $reserva->placa = $data->placa;
    $reserva->fecha_reservacion = $data->fecha_reservacion;
    $reserva->hora_reservacion = $data->hora_reservacion;
    $reserva->servicio = $data->servicio;
    $reserva->precio = $data->precio ?? null;
    $reserva->estado = $data->estado ?? 'pendiente';
    $reserva->notas = $data->notas ?? null;

    if($reserva->createReservacion())
    {
        http_response_code(201);
        echo json_encode( 
            array( "issuccess" => true,
            "message" => "Reservación creada con éxito"));
    }
    else
    {
        http_response_code(503); // Servicio no disponible
        echo json_encode( 
            array("issuccess" => false,
            "message" => "Error al crear la reservación"));
    }
}
else
{
    http_response_code(400);
    echo json_encode(array(
        "issuccess" => false,
        "message" => "Datos incompletos o inválidos. Se requieren todos los campos obligatorios."));

}

?> 