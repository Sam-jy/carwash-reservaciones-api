-- Creación de la base de datos
CREATE DATABASE carwash_db
CHARACTER SET utf8
COLLATE utf8_general_ci;

-- Selección de la base de datos
USE carwash_db;

-- Creación de la tabla de reservaciones
CREATE TABLE reservaciones (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre_cliente VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    tipo_vehiculo VARCHAR(50) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    fecha_reservacion DATE NOT NULL,
    hora_reservacion TIME NOT NULL,
    servicio VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) DEFAULT NULL,
    estado ENUM('pendiente', 'en_proceso', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    notas TEXT DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Datos de ejemplo para pruebas
INSERT INTO reservaciones (nombre_cliente, telefono, email, tipo_vehiculo, placa, fecha_reservacion, hora_reservacion, servicio, precio, estado) 
VALUES 
('Juan Pérez', '555-1234', 'juan@example.com', 'Sedan', 'ABC123', '2023-08-15', '10:00:00', 'Lavado completo', 250.00, 'pendiente'),
('María García', '555-5678', 'maria@example.com', 'SUV', 'XYZ789', '2023-08-15', '11:30:00', 'Lavado y encerado', 350.00, 'pendiente'),
('Carlos López', '555-9012', 'carlos@example.com', 'Pickup', 'DEF456', '2023-08-16', '09:00:00', 'Lavado básico', 180.00, 'pendiente'); 