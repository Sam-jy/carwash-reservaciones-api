# Car Wash El Catracho - API v2.0

## Descripción del Proyecto

API robusta y escalable para el sistema de gestión de Car Wash El Catracho. Permite a los clientes solicitar cotizaciones de servicios, gestionar sus vehículos y ver su historial, mientras que los administradores pueden gestionar cotizaciones, usuarios y generar reportes.

## Estructura del Proyecto

```
carwash-reservaciones-api/
├── app/
│   ├── Models/                    # Modelos de datos (BD)
│   │   ├── BaseModel.php             # Modelo base con funcionalidad común
│   │   ├── Usuario.php               # Gestión de usuarios/clientes/admins
│   │   ├── Vehiculo.php              # Gestión de vehículos
│   │   ├── Servicio.php              # Servicios disponibles
│   │   ├── Cotizacion.php            # Sistema de cotizaciones
│   │   ├── Notificacion.php          # Notificaciones push/email
│   │   └── Historial.php             # Historial de servicios
│   │
│   ├── Http/
│   │   ├── Controllers/           # Controladores de la aplicación
│   │   │   ├── ClientController.php  # Endpoints para clientes
│   │   │   └── AdminController.php   # Endpoints para administradores
│   │   │
│   │   └── Requests/              # Validación de datos de entrada
│   │       ├── BaseRequest.php       # Validador base
│   │       ├── RegistroRequest.php   # Validación de registro
│   │       ├── LoginRequest.php      # Validación de login
│   │       ├── CotizacionRequest.php # Validación de cotizaciones
│   │       └── VehiculoRequest.php   # Validación de vehículos
│   │
│   ├── Services/                  # Lógica de negocio
│   │   ├── NotificacionService.php   # Gestión de notificaciones
│   │   └── CotizacionService.php     # Lógica de cotizaciones
│   │
│   └── Mail/                      # Clases para envío de emails
│       ├── CodigoVerificacionMail.php
│       └── NotificacionMail.php
│
├── config/                        # Archivos de configuración
│   ├── database.php                  # Configuración de BD
│   └── mail.php                      # Configuración de emails
│
├── routes/                        # Definición de rutas
│   └── api.php                       # Todas las rutas de la API
│
├── database/                      # Base de datos y migraciones
│   └── migrations/
│       ├── carwash_database_complete.sql     # Esquema completo nuevo
│       └── migrate_from_reservaciones.sql   # Migración desde estructura antigua
│
├── resources/                     # Recursos y vistas
│   └── views/
│       └── verificacion.blade.php   # Página de verificación de email
│
├── index.php                      # Punto de entrada principal
├── database.php                   # Clase de conexión a BD (actualizada)
├── Auth.php                       # Sistema de autenticación JWT
└── README_NEW_STRUCTURE.md        # Esta documentación
```

## Nuevas Características

### Para Clientes
- Registro y verificación por email con códigos de 6 dígitos
- Gestión completa de perfil (foto, datos personales)
- Registro de múltiples vehículos con validaciones
- Sistema de cotizaciones inteligente con precios automáticos
- Notificaciones push y email en tiempo real
- Historial detallado de servicios (lavados y cambios de aceite)
- Sistema de calificaciones para servicios recibidos

### Para Administradores
- Dashboard completo con estadísticas y métricas
- Gestión de cotizaciones pendientes con sistema de prioridades
- Administración de usuarios (activar/desactivar)
- Gestión de servicios (crear, editar, precios)
- Reportes avanzados por fechas y servicios
- Sistema de promociones masivas y personalizadas
- Historial general con filtros avanzados

## Servicios Disponibles

| Servicio | Precio Centro | Precio Domicilio | Disponibilidad |
|----------|---------------|------------------|----------------|
| Lavado General | L. 100 | L. 150 | Centro + Domicilio |
| Lavado Completo | L. 150 | L. 200 | Centro + Domicilio |
| Cambio de Aceite | Variable* | N/A | Solo Centro |
| Lavado de Motor | L. 400 | N/A | Solo Centro |

*El precio del cambio de aceite varía según el modelo del vehículo (L. 600 - L. 2000)

## Base de Datos

### Nuevas Tablas
- usuarios - Clientes y administradores
- vehiculos - Vehículos de los clientes
- servicios - Catálogo de servicios
- cotizaciones - Sistema de cotizaciones
- notificaciones - Sistema de notificaciones
- historial_servicios - Registro de servicios completados

### Migración Segura
El archivo migrate_from_reservaciones.sql permite migrar datos existentes:
- Convierte reservaciones en cotizaciones
- Crea usuarios basados en datos de reservaciones
- Mantiene la integridad de datos históricos

## API Endpoints

### Autenticación
```http
POST /api/cliente/register          # Registro de cliente
POST /api/cliente/login             # Login de cliente  
POST /api/cliente/verificar-email   # Verificar código de email
POST /api/admin/login               # Login de administrador
```

### Gestión de Perfil (Cliente)
```http
GET    /api/cliente/perfil          # Obtener perfil
PUT    /api/cliente/perfil          # Actualizar perfil
POST   /api/cliente/cambiar-password # Cambiar contraseña
```

### Gestión de Vehículos (Cliente)
```http
GET    /api/cliente/vehiculos       # Listar vehículos
POST   /api/cliente/vehiculos       # Registrar vehículo
PUT    /api/cliente/vehiculos/{id}  # Actualizar vehículo
DELETE /api/cliente/vehiculos/{id}  # Eliminar vehículo
```

### Sistema de Cotizaciones (Cliente)
```http
GET  /api/cliente/servicios         # Ver servicios disponibles
POST /api/cliente/cotizaciones      # Solicitar cotización
GET  /api/cliente/cotizaciones      # Ver mis cotizaciones
POST /api/cliente/cotizaciones/{id}/aceptar   # Aceptar cotización
POST /api/cliente/cotizaciones/{id}/rechazar  # Rechazar cotización
```

### Notificaciones (Cliente)
```http
GET  /api/cliente/notificaciones    # Ver notificaciones
POST /api/cliente/notificaciones/{id}/leer    # Marcar como leída
POST /api/cliente/notificaciones/leer-todas   # Marcar todas como leídas
```

### Panel Administrativo
```http
GET  /api/admin/dashboard           # Dashboard con estadísticas
GET  /api/admin/cotizaciones/pendientes # Cotizaciones pendientes
POST /api/admin/cotizaciones/{id}/responder # Enviar cotización
POST /api/admin/cotizaciones/{id}/completar # Completar servicio
GET  /api/admin/usuarios            # Gestionar usuarios
GET  /api/admin/reportes            # Generar reportes
```

## Ejemplo de Uso

### Registro de Cliente
```bash
curl -X POST http://localhost/carwash-api/api/cliente/register \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Juan",
    "apellido": "Pérez", 
    "email": "juan@example.com",
    "telefono": "504-1234-5678",
    "password": "mi_password_seguro"
  }'
```

### Solicitar Cotización
```bash
curl -X POST http://localhost/carwash-api/api/cliente/cotizaciones \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "vehiculo_id": 1,
    "servicio_id": 2,
    "tipo_ubicacion": "domicilio",
    "direccion_servicio": "Col. Palmira, Casa #123",
    "fecha_servicio": "2025-01-20",
    "hora_servicio": "14:00"
  }'
```

## Configuración

### 1. Base de Datos
```php
// config/database.php
return [
    'host' => 'localhost:3307',
    'database' => 'carwash_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];
```

### 2. Email
```php
// config/mail.php
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'tu_email@gmail.com',
        'password' => 'tu_app_password'
    ]
];
```

## Instalación

### 1. Clonar y Configurar
```bash
git clone [tu-repositorio]
cd carwash-reservaciones-api
```

### 2. Configurar Base de Datos
```sql
-- Ejecutar en MySQL
SOURCE database/migrations/carwash_database_complete.sql;

-- Si tienes datos existentes, ejecutar también:
SOURCE database/migrations/migrate_from_reservaciones.sql;
```

### 3. Configurar Servidor Web
- Apuntar DocumentRoot a la carpeta del proyecto
- Asegurarse de que index.php sea el archivo principal
- Habilitar mod_rewrite para URLs amigables

### 4. Probar Instalación
```bash
curl http://localhost/tu-proyecto/
```

## Seguridad

- Autenticación JWT con tokens seguros
- Validación robusta de todos los inputs
- Sanitización automática de datos
- Rate limiting para prevenir abuso
- Logs de seguridad para auditoría

## Funcionalidades Avanzadas

### Sistema de Notificaciones
- Push notifications via FCM
- Emails HTML responsivos
- Recordatorios automáticos
- Promociones personalizadas

### Reportes y Analytics
- Dashboard en tiempo real
- Reportes de ingresos
- Métricas de satisfacción
- Análisis de servicios populares

### Sistema Inteligente de Precios
- Precios automáticos por tipo de vehículo
- Recargos por ubicación
- Descuentos para clientes frecuentes

## Próximas Mejoras

- Sistema de pagos en línea
- Integración con mapas para rutas
- App móvil nativa
- Sistema de inventario
- Chat en tiempo real
- API de terceros (WhatsApp Business)

## Soporte

Para soporte técnico o reportar bugs:
- Email: soporte@carwashelcatracho.com
- Teléfono: 504-0000-0000

---

**Car Wash El Catracho** - Manteniendo tu vehículo impecable desde 2025
