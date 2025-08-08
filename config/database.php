<?php

/**
 * Configuración de la base de datos
 * Centraliza todos los parámetros de conexión
 */

return [
    'host' => 'localhost:3307',
    'database' => 'carwash_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
