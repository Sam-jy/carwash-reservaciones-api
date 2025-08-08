<?php

require_once __DIR__ . '/../../Models/Usuario.php';
require_once __DIR__ . '/../../Models/Vehiculo.php';
require_once __DIR__ . '/../../Models/Servicio.php';
require_once __DIR__ . '/../../Models/Cotizacion.php';
require_once __DIR__ . '/../../Models/Notificacion.php';
require_once __DIR__ . '/../../Models/Historial.php';
require_once __DIR__ . '/../Middleware/Auth.php';

/**
 * Controlador para administradores
 * Maneja todas las operaciones administrativas
 */
class AdminController
{
    private $db;
    private $auth;

    public function __construct()
    {
        require_once __DIR__ . '/../../../database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth();
        
        $this->setHeaders();
    }

    private function setHeaders()
    {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    private function validateAdminAuth()
    {
        $tokenData = $this->auth->validateToken();
        if (!$tokenData) {
            $this->respondError('Token no válido o expirado', 401);
        }

        // Verificar que el usuario es admin
        $usuarioModel = new Usuario($this->db);
        $usuario = $usuarioModel->find($tokenData['user_id']);
        
        if (!$usuario || $usuario['tipo_usuario'] !== 'admin') {
            $this->respondError('Acceso denegado. Solo administradores', 403);
        }

        return $tokenData;
    }

    private function respondSuccess($data, $message = null, $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }

    private function respondError($message, $code = 400, $errors = null)
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
        exit();
    }

    private function getRequestData()
    {
        return json_decode(file_get_contents("php://input"), true);
    }

    // =============================================
    // DASHBOARD Y ESTADÍSTICAS
    // =============================================

    /**
     * Obtener dashboard con estadísticas generales
     */
    public function getDashboard()
    {
        $this->validateAdminAuth();
        
        try {
            $usuarioModel = new Usuario($this->db);
            $cotizacionModel = new Cotizacion($this->db);
            $historialModel = new Historial($this->db);
            $servicioModel = new Servicio($this->db);

            $dashboard = [
                'usuarios' => $usuarioModel->getStats(),
                'cotizaciones' => $cotizacionModel->getStats(),
                'servicios' => $servicioModel->getStats(),
                'ingresos_mes' => $this->getIngresosMes(),
                'servicios_populares' => $historialModel->getServiciosMasPopulares(5),
                'clientes_frecuentes' => $historialModel->getClientesFrecuentes(10),
                'cotizaciones_pendientes' => count($cotizacionModel->getPendientes())
            ];

            $this->respondSuccess($dashboard);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener reportes por fechas
     */
    public function getReportes()
    {
        $this->validateAdminAuth();
        
        try {
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-t');

            $historialModel = new Historial($this->db);
            $cotizacionModel = new Cotizacion($this->db);

            $reportes = [
                'ingresos_por_dia' => $historialModel->getIngresosPorPeriodo($fechaInicio, $fechaFin),
                'estadisticas_cotizaciones' => $cotizacionModel->getStats($fechaInicio, $fechaFin),
                'calificaciones' => $historialModel->getReporteCalificaciones()
            ];

            $this->respondSuccess($reportes);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // GESTIÓN DE USUARIOS
    // =============================================

    /**
     * Obtener todos los usuarios registrados
     */
    public function getUsuarios()
    {
        $this->validateAdminAuth();
        
        try {
            $tipo = $_GET['tipo'] ?? null;
            
            $usuarioModel = new Usuario($this->db);
            
            if ($tipo) {
                $usuarios = $usuarioModel->getUsersByType($tipo);
            } else {
                $usuarios = $usuarioModel->all(['activo' => 1], 'fecha_creacion DESC');
            }

            $this->respondSuccess($usuarios);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener detalles de un usuario específico
     */
    public function getUsuario($id)
    {
        $this->validateAdminAuth();
        
        try {
            $usuarioModel = new Usuario($this->db);
            $vehiculoModel = new Vehiculo($this->db);
            $historialModel = new Historial($this->db);

            $usuario = $usuarioModel->find($id);
            if (!$usuario) {
                $this->respondError('Usuario no encontrado', 404);
            }

            $vehiculos = $vehiculoModel->getByUsuario($id);
            $historial = $historialModel->getByUsuario($id, 10);
            $stats = $historialModel->getStatsUsuario($id);

            $detalle = [
                'usuario' => $usuario,
                'vehiculos' => $vehiculos,
                'historial_reciente' => $historial,
                'estadisticas' => $stats
            ];

            $this->respondSuccess($detalle);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Activar/Desactivar usuario
     */
    public function toggleUsuario($id)
    {
        $this->validateAdminAuth();
        
        try {
            $usuarioModel = new Usuario($this->db);
            $usuario = $usuarioModel->find($id);
            
            if (!$usuario) {
                $this->respondError('Usuario no encontrado', 404);
            }

            $nuevoEstado = $usuario['activo'] ? 0 : 1;
            $actualizado = $usuarioModel->update($id, ['activo' => $nuevoEstado]);

            if ($actualizado) {
                $mensaje = $nuevoEstado ? 'Usuario activado' : 'Usuario desactivado';
                $this->respondSuccess(['activo' => $nuevoEstado], $mensaje);
            } else {
                $this->respondError('Error al actualizar usuario');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // GESTIÓN DE COTIZACIONES
    // =============================================

    /**
     * Obtener cotizaciones pendientes
     */
    public function getCotizacionesPendientes()
    {
        $this->validateAdminAuth();
        
        try {
            $cotizacionModel = new Cotizacion($this->db);
            $cotizaciones = $cotizacionModel->getPendientes();

            $this->respondSuccess($cotizaciones);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener todas las cotizaciones con filtros
     */
    public function getCotizaciones()
    {
        $this->validateAdminAuth();
        
        try {
            $estado = $_GET['estado'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $cotizacionModel = new Cotizacion($this->db);
            
            if ($estado) {
                $cotizaciones = $cotizacionModel->getByEstado($estado, $limit);
            } else {
                $cotizaciones = $cotizacionModel->all([], 'fecha_creacion DESC', $limit);
            }

            $this->respondSuccess($cotizaciones);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Responder a una cotización
     */
    public function responderCotizacion($id)
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            if (!isset($data['precio']) || $data['precio'] <= 0) {
                $this->respondError('Precio debe ser mayor a 0');
            }

            $cotizacionModel = new Cotizacion($this->db);
            $respondida = $cotizacionModel->responderCotizacion(
                $id,
                $data['precio'],
                $data['notas_admin'] ?? null
            );

            if ($respondida) {
                $this->respondSuccess(null, 'Cotización enviada al cliente');
            } else {
                $this->respondError('Error al responder cotización');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Completar un servicio
     */
    public function completarServicio($id)
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            $cotizacionModel = new Cotizacion($this->db);
            $completado = $cotizacionModel->completarServicio(
                $id,
                $data['observaciones'] ?? null
            );

            if ($completado) {
                $this->respondSuccess(null, 'Servicio completado exitosamente');
            } else {
                $this->respondError('Error al completar servicio');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Cancelar cotización
     */
    public function cancelarCotizacion($id)
    {
        $this->validateAdminAuth();
        
        try {
            $cotizacionModel = new Cotizacion($this->db);
            $cancelada = $cotizacionModel->cancelarCotizacion($id);

            if ($cancelada) {
                $this->respondSuccess(null, 'Cotización cancelada');
            } else {
                $this->respondError('Error al cancelar cotización');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // GESTIÓN DE SERVICIOS
    // =============================================

    /**
     * Obtener todos los servicios
     */
    public function getServicios()
    {
        $this->validateAdminAuth();
        
        try {
            $servicioModel = new Servicio($this->db);
            $servicios = $servicioModel->all([], 'nombre ASC');

            $this->respondSuccess($servicios);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Crear nuevo servicio
     */
    public function createServicio()
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            $required = ['nombre', 'descripcion', 'precio_base'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->respondError("El campo {$field} es requerido");
                }
            }

            $servicioModel = new Servicio($this->db);
            $servicio = $servicioModel->createServicio($data);

            if ($servicio) {
                $this->respondSuccess($servicio, 'Servicio creado exitosamente', 201);
            } else {
                $this->respondError('Error al crear servicio');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Actualizar servicio
     */
    public function updateServicio($id)
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            $servicioModel = new Servicio($this->db);
            $actualizado = $servicioModel->updateServicio($id, $data);

            if ($actualizado) {
                $servicio = $servicioModel->find($id);
                $this->respondSuccess($servicio, 'Servicio actualizado exitosamente');
            } else {
                $this->respondError('Error al actualizar servicio');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Eliminar/Desactivar servicio
     */
    public function deleteServicio($id)
    {
        $this->validateAdminAuth();
        
        try {
            $servicioModel = new Servicio($this->db);
            
            // Soft delete - marcar como inactivo
            $eliminado = $servicioModel->update($id, ['activo' => 0]);

            if ($eliminado) {
                $this->respondSuccess(null, 'Servicio desactivado exitosamente');
            } else {
                $this->respondError('Error al desactivar servicio');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // HISTORIAL Y REPORTES
    // =============================================

    /**
     * Obtener historial general de servicios
     */
    public function getHistorialGeneral()
    {
        $this->validateAdminAuth();
        
        try {
            $filtros = [
                'fecha_inicio' => $_GET['fecha_inicio'] ?? null,
                'fecha_fin' => $_GET['fecha_fin'] ?? null,
                'servicio_id' => $_GET['servicio_id'] ?? null,
                'usuario_id' => $_GET['usuario_id'] ?? null
            ];

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $historialModel = new Historial($this->db);
            $historial = $historialModel->getHistorialGeneral($filtros, $limit);

            $this->respondSuccess($historial);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener clientes frecuentes
     */
    public function getClientesFrecuentes()
    {
        $this->validateAdminAuth();
        
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

            $historialModel = new Historial($this->db);
            $clientes = $historialModel->getClientesFrecuentes($limit);

            $this->respondSuccess($clientes);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // NOTIFICACIONES
    // =============================================

    /**
     * Enviar notificación promocional
     */
    public function enviarPromocion()
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            if (empty($data['titulo']) || empty($data['mensaje'])) {
                $this->respondError('Título y mensaje son requeridos');
            }

            $notificacionModel = new Notificacion($this->db);
            $enviadas = $notificacionModel->enviarPromocion(
                $data['titulo'],
                $data['mensaje'],
                $data['usuario_ids'] ?? null
            );

            $this->respondSuccess(
                ['enviadas' => $enviadas], 
                "Promoción enviada a {$enviadas} usuarios"
            );

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Programar recordatorios automáticos
     */
    public function programarRecordatorios()
    {
        $this->validateAdminAuth();
        
        try {
            $notificacionModel = new Notificacion($this->db);
            $enviados = $notificacionModel->programarRecordatorios();

            $this->respondSuccess(
                ['enviados' => $enviados], 
                "Se enviaron {$enviados} recordatorios"
            );

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Limpiar notificaciones antiguas
     */
    public function limpiarNotificaciones()
    {
        $this->validateAdminAuth();
        
        try {
            $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 30;

            $notificacionModel = new Notificacion($this->db);
            $eliminadas = $notificacionModel->limpiarAntiguas($dias);

            $this->respondSuccess(
                ['eliminadas' => $eliminadas], 
                "Se eliminaron {$eliminadas} notificaciones antiguas"
            );

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS AUXILIARES
    // =============================================

    /**
     * Obtener ingresos del mes actual
     */
    private function getIngresosMes()
    {
        $fechaInicio = date('Y-m-01');
        $fechaFin = date('Y-m-t');

        $query = "SELECT 
                    SUM(precio_final) as total,
                    COUNT(*) as servicios
                 FROM historial_servicios 
                 WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fechaInicio);
        $stmt->bindParam(':fecha_fin', $fechaFin);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Login específico para administradores
     */
    public function loginAdmin()
    {
        try {
            $data = $this->getRequestData();
            
            if (empty($data['email']) || empty($data['password'])) {
                $this->respondError('Email y contraseña son requeridos');
            }

            $usuarioModel = new Usuario($this->db);
            $usuario = $usuarioModel->verifyCredentials($data['email'], $data['password']);

            if (!$usuario) {
                $this->respondError('Credenciales incorrectas', 401);
            }

            if ($usuario['tipo_usuario'] !== 'admin') {
                $this->respondError('Acceso denegado. Solo administradores', 403);
            }

            $token = $this->auth->generateToken($usuario['id']);

            $this->respondSuccess([
                'token' => $token,
                'usuario' => $usuario
            ], 'Login administrativo exitoso');

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Crear usuario administrador
     */
    public function createAdmin()
    {
        $this->validateAdminAuth();
        
        try {
            $data = $this->getRequestData();
            
            $required = ['nombre', 'apellido', 'email', 'telefono', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->respondError("El campo {$field} es requerido");
                }
            }

            $data['tipo_usuario'] = 'admin';
            $data['email_verificado'] = true; // Admins se crean verificados

            $usuarioModel = new Usuario($this->db);
            $admin = $usuarioModel->createUser($data);

            if ($admin) {
                $this->respondSuccess($admin, 'Administrador creado exitosamente', 201);
            } else {
                $this->respondError('Error al crear administrador');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }
}
