<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'Auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->username) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(
            array(
                "message" => "Datos incompletos. Se requiere username y password."
            )
        );
        exit();
    }
    
    // Para simplificar, usamos credenciales fijas
    // En un entorno real, verificarías contra una base de datos
    if ($data->username == "admin" && $data->password == "admin123") {
        $auth = new Auth();
        $token = $auth->generateToken(1); // 1 es el ID de usuario
        
        http_response_code(200);
        echo json_encode(
            array(
                "message" => "Inicio de sesión exitoso",
                "token" => $token,
                "expires_in" => 3600
            )
        );
    } else {
        http_response_code(401);
        echo json_encode(
            array(
                "message" => "Credenciales inválidas"
            )
        );
    }
} else {
    http_response_code(405);
    echo json_encode(
        array(
            "message" => "Método no permitido"
        )
    );
}

?> 