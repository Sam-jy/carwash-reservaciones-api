-- Script para corregir la tabla vehículos
-- Cambiar el nombre de la columna 'año' a 'anio' para evitar problemas de codificación

USE carwash_db;

-- Verificar la estructura actual
DESCRIBE vehiculos;

-- Cambiar el nombre de la columna
ALTER TABLE vehiculos CHANGE `año` `anio` INT(4) NOT NULL;

-- Verificar que el cambio se aplicó correctamente
DESCRIBE vehiculos;

-- Verificar que no hay datos que se pierdan
SELECT COUNT(*) as total_vehiculos FROM vehiculos;
