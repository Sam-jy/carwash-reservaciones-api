# Car Wash El Catracho - API

API para el sistema de gestión de Car Wash El Catracho. Permite a los clientes solicitar servicios y a los administradores gestionar el negocio.

## Características Principales

### Para Clientes
- Registro con verificación por email
- Gestión de perfil y vehículos
- Solicitud de cotizaciones
- Notificaciones en tiempo real
- Historial de servicios

### Para Administradores
- Dashboard con estadísticas
- Gestión de cotizaciones y usuarios
- Sistema de notificaciones

## Estructura del Proyecto

```
carwash-reservaciones-api/
├── app/
│   ├── Models/          # Modelos de datos
│   ├── Http/            # Controladores y validaciones
│   ├── Services/        # Lógica de negocio
│   └── Mail/            # Envío de emails
├── config/              # Configuraciones
├── routes/              # Definición de rutas
├── database/            # Migraciones y esquemas
└── resources/           # Vistas y recursos
```

## Instalación

1. **Clonar el proyecto**
```bash
git clone [https://github.com/Sam-jy/carwash-reservaciones-api]
cd carwash-reservaciones-api
```

2. **Configurar base de datos**
```sql
SOURCE database/migrations/carwash_database_complete.sql;
```

3. **Configurar variables**
- Editar `config/database.php` 
- Configurar email en `config/mail.php`

4. **Probar**
```bash
curl http://localhost/carwash-reservaciones-api/
```

## 🔌 Endpoints Principales

### Autenticación
```http
POST /api/cliente/register          # Registro
POST /api/cliente/login             # Login cliente
POST /api/admin/login               # Login admin
```

### Clientes
```http
GET    /api/cliente/perfil          # Ver perfil
PUT    /api/cliente/perfil          # Actualizar perfil
GET    /api/cliente/vehiculos       # Mis vehículos
POST   /api/cliente/cotizaciones    # Solicitar cotización
GET    /api/cliente/cotizaciones    # Ver cotizaciones
```

### Administradores
```http
GET  /api/admin/dashboard           # Dashboard
GET  /api/admin/cotizaciones        # Gestionar cotizaciones
GET  /api/admin/usuarios            # Gestionar usuarios
GET  /api/admin/reportes            # Reportes
```

## Servicios Disponibles

| Servicio | Centro | Domicilio |
|----------|--------|-----------|
| Lavado General | L. 100 | L. 150 |
| Lavado Completo | L. 150 | L. 200 |
| Cambio de Aceite | Variable* | N/A |
| Lavado de Motor | L. 400 | N/A |

*Precio según modelo del vehículo

## Seguridad

- Autenticación JWT
- Validación de datos
- Rate limiting
- Logs de seguridad


**Car Wash El Catracho** - Manteniendo tu vehículo impecable
