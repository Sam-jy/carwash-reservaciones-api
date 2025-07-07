# API de Reservaciones para Carwash

API REST desarrollada en PHP para gestionar reservaciones de un autolavado (carwash).

## Características

- Sistema completo CRUD para gestionar reservaciones
- Autenticación mediante OAuth 2.0 con tokens JWT
- Endpoints protegidos para mayor seguridad
- Gestión de datos de clientes, vehículos y servicios

## Tecnologías

- PHP puro
- MySQL/MariaDB
- PDO para conexiones seguras a base de datos
- JWT para autenticación

## Endpoints

- `GenerateToken.php`: Obtener token de autenticación
- `GetReservaciones.php`: Listar todas las reservaciones
- `PostReservacion.php`: Crear nueva reservación
- `UpdateReservacion.php`: Actualizar reservación existente
- `DeleteReservacion.php`: Eliminar reservación

## Instalación

1. Clonar el repositorio
2. Crear base de datos 'carwash_db'
3. Importar estructura de la tabla desde el script SQL proporcionado
4. Configurar conexión en database.php si es necesario

## Uso

Todas las solicitudes (excepto la generación de token) requieren autenticación mediante token Bearer en el header:

```
Authorization: Bearer {tu_token}
```

### Autenticación

POST a `/GenerateToken.php`
```json
{
  "username": "admin",
  "password": "admin123"
}
``` 