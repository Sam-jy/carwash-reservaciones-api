-- ================================================================
-- MIGRACIÓN SEGURA DE RESERVACIONES A NUEVA ESTRUCTURA
-- Car Wash El Catracho - Migración de datos existentes
-- ================================================================

-- NOTA: Ejecutar este script DESPUÉS de hacer backup de la BD actual
-- Este script migra los datos existentes al nuevo esquema

USE carwash_db;

-- ================================================================
-- 1. CREAR TABLAS TEMPORALES PARA MIGRACIÓN
-- ================================================================

-- Crear tabla temporal para mapear usuarios
CREATE TEMPORARY TABLE temp_usuarios_mapping (
    old_id INT,
    new_id INT,
    email VARCHAR(100),
    nombre VARCHAR(100),
    telefono VARCHAR(20)
);

-- ================================================================
-- 2. MIGRAR DATOS DE RESERVACIONES A USUARIOS
-- ================================================================

-- Insertar usuarios únicos basados en las reservaciones existentes
INSERT INTO usuarios (nombre, apellido, email, telefono, password, tipo_usuario, email_verificado, activo)
SELECT DISTINCT
    SUBSTRING_INDEX(nombre_cliente, ' ', 1) as nombre,
    SUBSTRING_INDEX(nombre_cliente, ' ', -1) as apellido,
    COALESCE(email, CONCAT(REPLACE(LOWER(nombre_cliente), ' ', ''), '@pendiente.com')) as email,
    telefono,
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' as password, -- password por defecto: "password"
    'cliente' as tipo_usuario,
    FALSE as email_verificado,
    TRUE as activo
FROM reservaciones 
WHERE nombre_cliente IS NOT NULL 
AND telefono IS NOT NULL
GROUP BY nombre_cliente, telefono, email;

-- Mapear IDs antiguos con nuevos
INSERT INTO temp_usuarios_mapping (old_id, new_id, email, nombre, telefono)
SELECT 
    r.id as old_id,
    u.id as new_id,
    u.email,
    u.nombre,
    u.telefono
FROM reservaciones r
INNER JOIN usuarios u ON (
    u.telefono = r.telefono 
    AND u.nombre = SUBSTRING_INDEX(r.nombre_cliente, ' ', 1)
    AND (u.email = r.email OR r.email IS NULL)
);

-- ================================================================
-- 3. MIGRAR DATOS DE RESERVACIONES A VEHÍCULOS
-- ================================================================

-- Crear vehículos únicos basados en las reservaciones
INSERT INTO vehiculos (usuario_id, marca, modelo, año, placa, activo)
SELECT DISTINCT
    tum.new_id as usuario_id,
    CASE 
        WHEN r.tipo_vehiculo LIKE '%sedan%' OR r.tipo_vehiculo LIKE '%Sedan%' THEN 'Toyota'
        WHEN r.tipo_vehiculo LIKE '%suv%' OR r.tipo_vehiculo LIKE '%SUV%' THEN 'Honda'
        WHEN r.tipo_vehiculo LIKE '%pickup%' OR r.tipo_vehiculo LIKE '%Pickup%' THEN 'Ford'
        ELSE 'Toyota'
    END as marca,
    r.tipo_vehiculo as modelo,
    YEAR(CURDATE()) - 5 as año, -- Año estimado
    r.placa,
    TRUE as activo
FROM reservaciones r
INNER JOIN temp_usuarios_mapping tum ON r.id = tum.old_id
WHERE r.placa IS NOT NULL
GROUP BY tum.new_id, r.tipo_vehiculo, r.placa;

-- ================================================================
-- 4. INSERTAR SERVICIOS PREDEFINIDOS SI NO EXISTEN
-- ================================================================

INSERT IGNORE INTO servicios (nombre, descripcion, precio_base, precio_domicilio, disponible_domicilio, disponible_centro, tiempo_estimado, activo)
VALUES
('Lavado General', 'Lavado solo por fuera del vehículo', 100.00, 50.00, TRUE, TRUE, 30, TRUE),
('Lavado Completo', 'Lavado completo aplicando productos especializados', 150.00, 50.00, TRUE, TRUE, 60, TRUE),
('Cambio de Aceite', 'Cambio de aceite según modelo del vehículo', 800.00, NULL, FALSE, TRUE, 45, TRUE),
('Lavado de Motor', 'Lavado profesional del motor del vehículo', 400.00, NULL, FALSE, TRUE, 90, TRUE),
('Lavado Básico', 'Servicio de lavado básico', 80.00, 40.00, TRUE, TRUE, 25, TRUE),
('Lavado y Encerado', 'Lavado completo con encerado', 200.00, 60.00, TRUE, TRUE, 90, TRUE);

-- ================================================================
-- 5. CREAR TABLA TEMPORAL PARA MAPEAR SERVICIOS
-- ================================================================

CREATE TEMPORARY TABLE temp_servicios_mapping (
    servicio_nombre VARCHAR(100),
    servicio_id INT
);

INSERT INTO temp_servicios_mapping (servicio_nombre, servicio_id)
SELECT nombre, id FROM servicios;

-- ================================================================
-- 6. MIGRAR RESERVACIONES A COTIZACIONES
-- ================================================================

INSERT INTO cotizaciones (
    usuario_id, 
    vehiculo_id, 
    servicio_id, 
    tipo_ubicacion, 
    fecha_servicio, 
    hora_servicio, 
    precio_cotizado, 
    estado, 
    notas_cliente,
    fecha_creacion
)
SELECT 
    tum.new_id as usuario_id,
    v.id as vehiculo_id,
    COALESCE(
        (SELECT servicio_id FROM temp_servicios_mapping WHERE servicio_nombre LIKE CONCAT('%', SUBSTRING_INDEX(r.servicio, ' ', 1), '%') LIMIT 1),
        1 -- Default al primer servicio si no encuentra coincidencia
    ) as servicio_id,
    'centro' as tipo_ubicacion, -- Asumir centro por defecto
    r.fecha_reservacion,
    r.hora_reservacion,
    COALESCE(r.precio, 150.00) as precio_cotizado,
    CASE 
        WHEN r.estado = 'pendiente' THEN 'pendiente'
        WHEN r.estado = 'en_proceso' THEN 'aceptada'
        WHEN r.estado = 'completada' THEN 'completada'
        WHEN r.estado = 'cancelada' THEN 'cancelada'
        ELSE 'pendiente'
    END as estado,
    r.notas as notas_cliente,
    r.fecha_creacion
FROM reservaciones r
INNER JOIN temp_usuarios_mapping tum ON r.id = tum.old_id
INNER JOIN vehiculos v ON (v.usuario_id = tum.new_id AND v.placa = r.placa)
WHERE r.fecha_reservacion IS NOT NULL;

-- ================================================================
-- 7. MIGRAR RESERVACIONES COMPLETADAS A HISTORIAL
-- ================================================================

INSERT INTO historial_servicios (
    cotizacion_id,
    usuario_id,
    vehiculo_id,
    servicio_id,
    precio_final,
    fecha_servicio,
    fecha_creacion
)
SELECT 
    c.id as cotizacion_id,
    c.usuario_id,
    c.vehiculo_id,
    c.servicio_id,
    c.precio_cotizado as precio_final,
    c.fecha_servicio,
    c.fecha_creacion
FROM cotizaciones c
WHERE c.estado = 'completada';

-- ================================================================
-- 8. CREAR NOTIFICACIONES DE BIENVENIDA PARA USUARIOS MIGRADOS
-- ================================================================

INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leida)
SELECT 
    id as usuario_id,
    'Bienvenido a la nueva plataforma' as titulo,
    CONCAT('Hola ', nombre, ', hemos migrado tu cuenta a nuestra nueva plataforma. Ahora puedes gestionar mejor tus servicios y vehículos.') as mensaje,
    'sistema' as tipo,
    FALSE as leida
FROM usuarios 
WHERE tipo_usuario = 'cliente';

-- ================================================================
-- 9. ACTUALIZAR ESTADÍSTICAS Y VERIFICAR MIGRACIÓN
-- ================================================================

-- Mostrar resumen de migración
SELECT 
    'RESUMEN DE MIGRACIÓN' as descripcion,
    (SELECT COUNT(*) FROM reservaciones) as reservaciones_originales,
    (SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'cliente') as usuarios_migrados,
    (SELECT COUNT(*) FROM vehiculos) as vehiculos_migrados,
    (SELECT COUNT(*) FROM cotizaciones) as cotizaciones_migradas,
    (SELECT COUNT(*) FROM historial_servicios) as servicios_completados;

-- Verificar integridad de datos
SELECT 
    'VERIFICACIÓN DE INTEGRIDAD' as descripcion,
    (SELECT COUNT(*) FROM cotizaciones c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE u.id IS NULL) as cotizaciones_sin_usuario,
    (SELECT COUNT(*) FROM cotizaciones c LEFT JOIN vehiculos v ON c.vehiculo_id = v.id WHERE v.id IS NULL) as cotizaciones_sin_vehiculo,
    (SELECT COUNT(*) FROM vehiculos v LEFT JOIN usuarios u ON v.usuario_id = u.id WHERE u.id IS NULL) as vehiculos_sin_usuario;

-- ================================================================
-- 10. LIMPIAR TABLAS TEMPORALES
-- ================================================================

DROP TEMPORARY TABLE temp_usuarios_mapping;
DROP TEMPORARY TABLE temp_servicios_mapping;

-- ================================================================
-- 11. COMENTARIOS FINALES
-- ================================================================

/*
PASOS POST-MIGRACIÓN RECOMENDADOS:

1. Verificar que todos los datos se migraron correctamente
2. Ejecutar: SELECT * FROM reservaciones LIMIT 5; -- Para comparar
3. Ejecutar: SELECT * FROM usuarios LIMIT 5; -- Para verificar
4. Probar login con usuarios migrados (password por defecto: "password")
5. Solicitar a usuarios que actualicen sus contraseñas
6. Configurar emails reales para usuarios con emails temporales
7. Activar verificación de email para nuevos usuarios

NOTAS IMPORTANTES:
- Los usuarios migrados tienen password por defecto: "password"
- Los emails que faltaban se crearon como: nombre@pendiente.com
- Todos los usuarios están marcados como no verificados
- Los años de vehículos son estimados (año actual - 5)
- Las marcas de vehículos se asignaron basándose en el tipo

SEGURIDAD:
- Cambiar todas las contraseñas por defecto
- Implementar flujo de recuperación de contraseña
- Validar emails temporales y solicitar actualización
*/
