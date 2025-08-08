<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

date_default_timezone_set('America/Tegucigalpa');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function autoloadClasses($className) {
function autoloadClasses($className) {
    $paths = [
        __DIR__ . '/app/Models/',
        __DIR__ . '/app/Http/Controllers/',
        __DIR__ . '/app/Http/Requests/',
        __DIR__ . '/app/Services/',
        __DIR__ . '/app/Mail/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
}

spl_autoload_register('autoloadClasses');

function handleError($errno, $errstr, $errfile, $errline) {
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'error' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'time' => date('Y-m-d H:i:s')
    ];
    
    error_log(json_encode($error));
    
    if (!in_array($errno, [E_NOTICE, E_WARNING])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

set_error_handler('handleError');

function handleException($exception) {
function handleException($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

set_exception_handler('handleException');

class MainRouter {
class MainRouter {
    private $basePath;
    private $requestUri;
    private $requestMethod;
    
    public function __construct() {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->requestUri = $this->sanitizeUri($_SERVER['REQUEST_URI']);
        $this->basePath = $this->getBasePath();
    }
    
    private function sanitizeUri($uri) {
        $uri = parse_url($uri, PHP_URL_PATH);
        return rtrim($uri, '/');
    }
    
    private function getBasePath() {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname($scriptName);
        return $basePath === '/' ? '' : $basePath;
    }
    
    public function route() {
        $path = substr($this->requestUri, strlen($this->basePath));
        
        if ($path === '' || $path === '/') {
            $this->showWelcome();
            return;
        }
        
        if ($path === '/verificar' || $path === '/verificacion') {
            $this->showVerification();
            return;
        }
        
        if ($path === '/docs' || $path === '/api-docs') {
            $this->showApiDocs();
            return;
        }
        
        if (strpos($path, '/api/') === 0) {
            $this->handleApiRoute($path);
            return;
        }
        
        $legacyFiles = [
            '/PostReservacion.php',
            '/GetReservaciones.php', 
            '/UpdateReservacion.php',
            '/DeleteReservacion.php',
            '/GenerateToken.php'
        ];
        
        if (in_array($path, $legacyFiles)) {
            $this->handleLegacyRoute($path);
            return;
        }
        
        $this->notFound();
    }
    
    private function showWelcome() {
        echo json_encode([
            'success' => true,
            'message' => 'Bienvenido a Car Wash El Catracho API',
            'version' => '2.0.0',
            'endpoints' => [
                'cliente' => '/api/cliente/*',
                'admin' => '/api/admin/*',
                'verificacion' => '/verificar',
                'documentacion' => '/docs'
            ],
            'features' => [
                'Autenticación JWT',
                'Gestión de usuarios y vehículos',
                'Sistema de cotizaciones',
                'Notificaciones push y email',
                'Historial de servicios',
                'Panel administrativo'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function showVerification() {
        readfile(__DIR__ . '/resources/views/verificacion.blade.php');
    }
    
    private function showApiDocs() {
        echo json_encode([
            'api_documentation' => 'Car Wash El Catracho API v2.0',
            'base_url' => $this->getBaseUrl(),
            'authentication' => 'Bearer Token (JWT)',
            'content_type' => 'application/json',
            'endpoints' => [
                'auth' => [
                    'POST /api/cliente/register' => 'Registro de cliente',
                    'POST /api/cliente/login' => 'Login de cliente',
                    'POST /api/cliente/verificar-email' => 'Verificar email',
                    'POST /api/admin/login' => 'Login de administrador'
                ],
                'cliente' => [
                    'GET /api/cliente/perfil' => 'Obtener perfil',
                    'PUT /api/cliente/perfil' => 'Actualizar perfil',
                    'GET /api/cliente/vehiculos' => 'Listar vehículos',
                    'POST /api/cliente/vehiculos' => 'Crear vehículo',
                    'GET /api/cliente/servicios' => 'Listar servicios',
                    'POST /api/cliente/cotizaciones' => 'Crear cotización',
                    'GET /api/cliente/historial' => 'Ver historial'
                ],
                'admin' => [
                    'GET /api/admin/dashboard' => 'Dashboard administrativo',
                    'GET /api/admin/cotizaciones/pendientes' => 'Cotizaciones pendientes',
                    'POST /api/admin/cotizaciones/{id}/responder' => 'Responder cotización',
                    'GET /api/admin/usuarios' => 'Gestionar usuarios',
                    'GET /api/admin/reportes' => 'Generar reportes'
                ]
            ],
            'example_request' => [
                'url' => $this->getBaseUrl() . '/api/cliente/login',
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => [
                    'email' => 'cliente@ejemplo.com',
                    'password' => 'mi_password'
                ]
            ],
            'example_response' => [
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'token' => 'eyJ0eXAiOiJKV1QiLCJhbGc...',
                    'usuario' => [
                        'id' => 1,
                        'nombre' => 'Juan',
                        'apellido' => 'Pérez',
                        'email' => 'cliente@ejemplo.com'
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT);
    }
    
    private function handleApiRoute($path) {
        $_SERVER['REQUEST_URI'] = $this->basePath . $path;
        require_once __DIR__ . '/routes/api.php';
    }
    
    private function handleLegacyRoute($path) {
        $file = __DIR__ . $path;
        if (file_exists($file)) {
            require_once $file;
        } else {
            $this->notFound();
        }
    }
    
    private function notFound() {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint no encontrado',
            'requested_path' => $this->requestUri,
            'method' => $this->requestMethod,
            'available_endpoints' => [
                '/' => 'Información de la API',
                '/api/cliente/*' => 'Endpoints para clientes', 
                '/api/admin/*' => 'Endpoints para administradores',
                '/verificar' => 'Página de verificación de email',
                '/docs' => 'Documentación de la API'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = $this->basePath;
        
        return $protocol . $host . $basePath;
    }
}


try {
    $router = new MainRouter();
    $router->route();
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
