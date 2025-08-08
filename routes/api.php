<?php

require_once __DIR__ . '/../app/Http/Controllers/ClientController.php';
require_once __DIR__ . '/../app/Http/Controllers/AdminController.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


class ApiRouter
{
    private $routes = [];
    private $method;
    private $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        $basePath = '/carwash-reservaciones-api';
        if (strpos($this->uri, $basePath) === 0) {
            $this->uri = substr($this->uri, strlen($basePath));
        }
        
        $this->uri = str_replace('/routes/api.php', '', $this->uri);
        
        if (empty($this->uri)) {
            $this->uri = '/';
        }
    }

    public function get($pattern, $callback)
    {
        $this->addRoute('GET', $pattern, $callback);
    }

    public function post($pattern, $callback)
    {
        $this->addRoute('POST', $pattern, $callback);
    }

    public function put($pattern, $callback)
    {
        $this->addRoute('PUT', $pattern, $callback);
    }

    public function delete($pattern, $callback)
    {
        $this->addRoute('DELETE', $pattern, $callback);
    }

    private function addRoute($method, $pattern, $callback)
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }

    public function run()
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $this->method) {
                if ($this->matchRoute($route['pattern'])) {
                    $this->executeCallback($route['callback'], $route['pattern']);
                    return;
                }
            }
        }

        $this->notFound();
    }

    private function matchRoute($pattern)
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        return preg_match($pattern, $this->uri);
    }

    /**
     * Obtener parámetros de la URL
     */
    private function getParams($pattern)
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        preg_match($pattern, $this->uri, $matches);
        array_shift($matches);
        
        return $matches;
    }

    private function executeCallback($callback, $pattern)
    private function executeCallback($callback, $pattern)
    {
        $params = $this->getParams($pattern);
        call_user_func_array($callback, $params);
    }


    private function notFound()
    {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint no encontrado',
            'requested_uri' => $this->uri,
            'original_uri' => $_SERVER['REQUEST_URI'],
            'method' => $this->method,
            'available_routes' => array_map(function($route) {
                return $route['method'] . ' ' . $route['pattern'];
            }, $this->routes)
        ]);
    }
}

$router = new ApiRouter();

$router->get('/', function() {
$router->get('/', function() {
    echo json_encode([
        'success' => true,
        'message' => 'API Car Wash El Catracho funcionando correctamente',
        'version' => '1.0.0',
        'endpoints' => [
            'cliente' => '/api/cliente/*',
            'admin' => '/api/admin/*'
        ]
    ]);
});

$router->post('/api/cliente/register', function() {
$router->post('/api/cliente/register', function() {
    $controller = new ClientController();
    $controller->register();
});

$router->post('/api/cliente/login', function() {
$router->post('/api/cliente/login', function() {
    $controller = new ClientController();
    $controller->login();
});

$router->post('/api/cliente/verificar-email', function() {
$router->post('/api/cliente/verificar-email', function() {
    $controller = new ClientController();
    $controller->verificarEmail();
});

$router->post('/api/cliente/reenviar-codigo', function() {
$router->post('/api/cliente/reenviar-codigo', function() {
    $controller = new ClientController();
    $controller->reenviarCodigo();
});

$router->get('/api/cliente/perfil', function() {
$router->get('/api/cliente/perfil', function() {
    $controller = new ClientController();
    $controller->getPerfil();
});

$router->put('/api/cliente/perfil', function() {
    $controller = new ClientController();
    $controller->updatePerfil();
});

$router->post('/api/cliente/cambiar-password', function() {
    $controller = new ClientController();
    $controller->cambiarPassword();
});

// Gestión de vehículos
$router->get('/api/cliente/vehiculos', function() {
    $controller = new ClientController();
    $controller->getVehiculos();
});

$router->post('/api/cliente/vehiculos', function() {
    $controller = new ClientController();
    $controller->createVehiculo();
});

$router->put('/api/cliente/vehiculos/{id}', function($id) {
    $controller = new ClientController();
    $controller->updateVehiculo($id);
});

$router->delete('/api/cliente/vehiculos/{id}', function($id) {
    $controller = new ClientController();
    $controller->deleteVehiculo($id);
});

// Servicios disponibles
$router->get('/api/cliente/servicios', function() {
    $controller = new ClientController();
    $controller->getServicios();
});

$router->get('/api/cliente/servicios/{ubicacion}', function($ubicacion) {
    $controller = new ClientController();
    $controller->getServiciosByUbicacion($ubicacion);
});

// Gestión de cotizaciones
$router->get('/api/cliente/cotizaciones', function() {
    $controller = new ClientController();
    $controller->getCotizaciones();
});

$router->post('/api/cliente/cotizaciones', function() {
    $controller = new ClientController();
    $controller->createCotizacion();
});

$router->post('/api/cliente/cotizaciones/{id}/aceptar', function($id) {
    $controller = new ClientController();
    $controller->aceptarCotizacion($id);
});

$router->post('/api/cliente/cotizaciones/{id}/rechazar', function($id) {
    $controller = new ClientController();
    $controller->rechazarCotizacion($id);
});

// Notificaciones
$router->get('/api/cliente/notificaciones', function() {
    $controller = new ClientController();
    $controller->getNotificaciones();
});

$router->post('/api/cliente/notificaciones/{id}/leer', function($id) {
    $controller = new ClientController();
    $controller->marcarNotificacionLeida($id);
});

$router->post('/api/cliente/notificaciones/leer-todas', function() {
    $controller = new ClientController();
    $controller->marcarTodasNotificacionesLeidas();
});

// Historial de servicios
$router->get('/api/cliente/historial', function() {
    $controller = new ClientController();
    $controller->getHistorial();
});

$router->get('/api/cliente/historial/aceite/{vehiculoId}', function($vehiculoId) {
    $controller = new ClientController();
    $controller->getHistorialAceite($vehiculoId);
});

$router->get('/api/cliente/historial/lavados/{vehiculoId}', function($vehiculoId) {
    $controller = new ClientController();
    $controller->getHistorialLavados($vehiculoId);
});

$router->post('/api/cliente/historial/{historialId}/calificar', function($historialId) {
    $controller = new ClientController();
    $controller->calificarServicio($historialId);
});

$router->post('/api/admin/login', function() {
$router->post('/api/admin/login', function() {
    $controller = new AdminController();
    $controller->loginAdmin();
});

$router->get('/api/admin/dashboard', function() {
$router->get('/api/admin/dashboard', function() {
    $controller = new AdminController();
    $controller->getDashboard();
});

$router->get('/api/admin/reportes', function() {
    $controller = new AdminController();
    $controller->getReportes();
});

$router->get('/api/admin/usuarios', function() {
$router->get('/api/admin/usuarios', function() {
    $controller = new AdminController();
    $controller->getUsuarios();
});

$router->get('/api/admin/usuarios/{id}', function($id) {
    $controller = new AdminController();
    $controller->getUsuario($id);
});

$router->post('/api/admin/usuarios/{id}/toggle', function($id) {
    $controller = new AdminController();
    $controller->toggleUsuario($id);
});

$router->post('/api/admin/crear-admin', function() {
    $controller = new AdminController();
    $controller->createAdmin();
});

$router->get('/api/admin/cotizaciones', function() {
$router->get('/api/admin/cotizaciones', function() {
    $controller = new AdminController();
    $controller->getCotizaciones();
});

$router->get('/api/admin/cotizaciones/pendientes', function() {
    $controller = new AdminController();
    $controller->getCotizacionesPendientes();
});

$router->post('/api/admin/cotizaciones/{id}/responder', function($id) {
    $controller = new AdminController();
    $controller->responderCotizacion($id);
});

$router->post('/api/admin/cotizaciones/{id}/completar', function($id) {
    $controller = new AdminController();
    $controller->completarServicio($id);
});

$router->post('/api/admin/cotizaciones/{id}/cancelar', function($id) {
    $controller = new AdminController();
    $controller->cancelarCotizacion($id);
});

$router->get('/api/admin/servicios', function() {
$router->get('/api/admin/servicios', function() {
    $controller = new AdminController();
    $controller->getServicios();
});

$router->post('/api/admin/servicios', function() {
    $controller = new AdminController();
    $controller->createServicio();
});

$router->put('/api/admin/servicios/{id}', function($id) {
    $controller = new AdminController();
    $controller->updateServicio($id);
});

$router->delete('/api/admin/servicios/{id}', function($id) {
    $controller = new AdminController();
    $controller->deleteServicio($id);
});

$router->get('/api/admin/historial', function() {
$router->get('/api/admin/historial', function() {
    $controller = new AdminController();
    $controller->getHistorialGeneral();
});

$router->get('/api/admin/clientes-frecuentes', function() {
    $controller = new AdminController();
    $controller->getClientesFrecuentes();
});

// Notificaciones y promociones
$router->post('/api/admin/promociones/enviar', function() {
    $controller = new AdminController();
    $controller->enviarPromocion();
});

$router->post('/api/admin/recordatorios/programar', function() {
    $controller = new AdminController();
    $controller->programarRecordatorios();
});

$router->post('/api/admin/notificaciones/limpiar', function() {
    $controller = new AdminController();
    $controller->limpiarNotificaciones();
});

// =============================================
// RUTAS PARA COMPATIBILIDAD CON CÓDIGO EXISTENTE
// =============================================

// Mantener compatibilidad con endpoints existentes
$router->post('/PostReservacion.php', function() {
    require_once __DIR__ . '/../PostReservacion.php';
});

$router->get('/GetReservaciones.php', function() {
    require_once __DIR__ . '/../GetReservaciones.php';
});

$router->put('/UpdateReservacion.php', function() {
    require_once __DIR__ . '/../UpdateReservacion.php';
});

$router->delete('/DeleteReservacion.php', function() {
    require_once __DIR__ . '/../DeleteReservacion.php';
});

$router->post('/GenerateToken.php', function() {
    require_once __DIR__ . '/../GenerateToken.php';
});

// =============================================
// EJECUTAR EL ROUTER
// =============================================

try {
    $router->run();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
