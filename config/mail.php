<?php

/**
 * Configuración para el envío de correos electrónicos
 */

return [
    'smtp' => [
        'host' => 'smtp.gmail.com', // Cambia según tu proveedor
        'port' => 587,
        'username' => '', // Tu email
        'password' => '', // Tu contraseña de aplicación
        'encryption' => 'tls',
        'auth' => true
    ],
    
    'from' => [
        'email' => 'noreply@carwashelcatracho.com',
        'name' => 'Car Wash El Catracho'
    ],
    
    'templates' => [
        'verification' => 'verification',
        'password_reset' => 'password_reset',
        'cotizacion' => 'cotizacion'
    ]
];
