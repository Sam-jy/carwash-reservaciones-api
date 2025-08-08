-- ==============================================
-- Base de datos completa para Car Wash El Catracho
-- ==============================================

-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS carwash_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE carwash_db;

-- ==============================================
-- 1. TABLA USUARIOS (CLIENTES Y ADMINISTRADORES)
-- ==============================================
CREATE TABLE usuarios (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    pais VARCHAR(50) DEFAULT 'Honduras',
    foto_perfil VARCHAR(255) DEFAULT NULL,
    tipo_usuario ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    email_verificado BOOLEAN DEFAULT FALSE,
    codigo_verificacion VARCHAR(6) DEFAULT NULL,
    token_recuperacion VARCHAR(255) DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_tipo_usuario (tipo_usuario)
);

-- ==============================================
-- 2. TABLA VEHICULOS
-- ==============================================
CREATE TABLE vehiculos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario_id INT(11) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    año INT(4) NOT NULL,
    placa VARCHAR(20) NOT NULL,
    tipo_aceite VARCHAR(50) DEFAULT NULL COMMENT 'Para cambios de aceite',
    color VARCHAR(30) DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_placa (placa)
);

-- ==============================================
-- 3. TABLA SERVICIOS
-- ==============================================
CREATE TABLE servicios (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_base DECIMAL(10,2) NOT NULL,
    precio_domicilio DECIMAL(10,2) DEFAULT NULL COMMENT 'Precio adicional por servicio a domicilio',
    disponible_domicilio BOOLEAN DEFAULT TRUE,
    disponible_centro BOOLEAN DEFAULT TRUE,
    tiempo_estimado INT DEFAULT NULL COMMENT 'Tiempo estimado en minutos',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- ==============================================
-- 4. TABLA COTIZACIONES
-- ==============================================
CREATE TABLE cotizaciones (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario_id INT(11) NOT NULL,
    vehiculo_id INT(11) NOT NULL,
    servicio_id INT(11) NOT NULL,
    tipo_ubicacion ENUM('centro', 'domicilio') NOT NULL,
    direccion_servicio TEXT DEFAULT NULL COMMENT 'Solo para servicios a domicilio',
    latitud DECIMAL(10, 8) DEFAULT NULL,
    longitud DECIMAL(11, 8) DEFAULT NULL,
    fecha_servicio DATE NOT NULL,
    hora_servicio TIME NOT NULL,
    precio_cotizado DECIMAL(10,2) DEFAULT NULL,
    estado ENUM('pendiente', 'enviada', 'aceptada', 'rechazada', 'completada', 'cancelada') NOT NULL DEFAULT 'pendiente',
    notas_cliente TEXT DEFAULT NULL,
    notas_admin TEXT DEFAULT NULL,
    fecha_respuesta TIMESTAMP NULL DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_servicio (fecha_servicio)
);

-- ==============================================
-- 5. TABLA NOTIFICACIONES
-- ==============================================
CREATE TABLE notificaciones (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario_id INT(11) NOT NULL,
    cotizacion_id INT(11) DEFAULT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('cotizacion', 'recordatorio', 'promocion', 'sistema') NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    enviada_push BOOLEAN DEFAULT FALSE,
    enviada_email BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_leida (leida),
    INDEX idx_tipo (tipo)
);

-- ==============================================
-- 6. TABLA HISTORIAL DE SERVICIOS
-- ==============================================
CREATE TABLE historial_servicios (
    id INT(11) NOT NULL AUTO_INCREMENT,
    cotizacion_id INT(11) NOT NULL,
    usuario_id INT(11) NOT NULL,
    vehiculo_id INT(11) NOT NULL,
    servicio_id INT(11) NOT NULL,
    precio_final DECIMAL(10,2) NOT NULL,
    fecha_servicio DATE NOT NULL,
    hora_inicio TIME DEFAULT NULL,
    hora_fin TIME DEFAULT NULL,
    calificacion TINYINT DEFAULT NULL COMMENT 'Calificación del 1 al 5',
    comentario_cliente TEXT DEFAULT NULL,
    kilometraje INT DEFAULT NULL COMMENT 'Para cambios de aceite',
    observaciones TEXT DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_servicio_id (servicio_id),
    INDEX idx_fecha_servicio (fecha_servicio)
);

-- ==============================================
-- INSERCIÓN DE DATOS INICIALES
-- ==============================================

-- Servicios predefinidos
INSERT INTO servicios (nombre, descripcion, precio_base, precio_domicilio, disponible_domicilio, disponible_centro, tiempo_estimado) VALUES
('Lavado General', 'Lavado solo por fuera del vehículo', 100.00, 50.00, TRUE, TRUE, 30),
('Lavado Completo', 'Lavado completo aplicando productos especializados', 150.00, 50.00, TRUE, TRUE, 60),
('Cambio de Aceite', 'Cambio de aceite según modelo del vehículo', 0.00, NULL, FALSE, TRUE, 45),
('Lavado de Motor', 'Lavado profesional del motor del vehículo', 400.00, NULL, FALSE, TRUE, 90);

-- Usuario administrador por defecto
INSERT INTO usuarios (nombre, apellido, email, telefono, password, tipo_usuario, email_verificado) VALUES
('Admin', 'Sistema', 'admin@carwash.com', '504-0000-0000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- ==============================================
-- MIGRACIÓN DE DATOS EXISTENTES (si existen)
-- ==============================================

-- Nota: Si ya tienes datos en la tabla 'reservaciones', 
-- necesitarás migrar esos datos a las nuevas tablas.
-- Este script debe ejecutarse después de respaldar los datos existentes.

-- ==============================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ==============================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_cotizaciones_usuario_estado ON cotizaciones(usuario_id, estado);
CREATE INDEX idx_historial_usuario_fecha ON historial_servicios(usuario_id, fecha_servicio);
CREATE INDEX idx_vehiculos_usuario_activo ON vehiculos(usuario_id, activo);

-- ==============================================
-- TRIGGERS PARA AUDITORÍA Y LÓGICA DE NEGOCIO
-- ==============================================

-- Trigger para crear notificación cuando se actualiza una cotización
DELIMITER //
CREATE TRIGGER after_cotizacion_update 
AFTER UPDATE ON cotizaciones
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado AND NEW.estado = 'enviada' THEN
        INSERT INTO notificaciones (usuario_id, cotizacion_id, titulo, mensaje, tipo)
        VALUES (NEW.usuario_id, NEW.id, 'Cotización Enviada', 
                CONCAT('Tu cotización para el servicio ha sido procesada. Precio: L. ', NEW.precio_cotizado), 
                'cotizacion');
    END IF;
END//

-- Trigger para crear historial cuando se completa una cotización
CREATE TRIGGER after_cotizacion_completed
AFTER UPDATE ON cotizaciones
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado AND NEW.estado = 'completada' THEN
        INSERT INTO historial_servicios (cotizacion_id, usuario_id, vehiculo_id, servicio_id, precio_final, fecha_servicio)
        VALUES (NEW.id, NEW.usuario_id, NEW.vehiculo_id, NEW.servicio_id, NEW.precio_cotizado, NEW.fecha_servicio);
    END IF;
END//
DELIMITER ;
