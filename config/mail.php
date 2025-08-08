<?php

return [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => '',
        'password' => '',
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
