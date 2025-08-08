<?php

require_once __DIR__ . '/../../Models/Usuario.php';
require_once __DIR__ . '/../../Models/Vehiculo.php';
require_once __DIR__ . '/../../Models/Servicio.php';
require_once __DIR__ . '/../../Models/Cotizacion.php';
require_once __DIR__ . '/../../Models/Notificacion.php';
require_once __DIR__ . '/../../Models/Historial.php';
require_once __DIR__ . '/../Middleware/Auth.php';

/**
 * Controlador para clientes
 * Maneja todas las operaciones relacionadas con los clientes
 */
class ClientController
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

    private function validateAuth()
    {
        $tokenData = $this->auth->validateToken();
        if (!$tokenData) {
            $this->respondError('Token no válido o expirado', 401);
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
    // MÉTODOS DE AUTENTICACIÓN
    // =============================================

    /**
     * Registro de nuevo cliente
     */
    public function register()
    {
        try {
            $data = $this->getRequestData();
            
            // Validar datos requeridos
            $required = ['nombre', 'apellido', 'email', 'telefono', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->respondError("El campo {$field} es requerido");
                }
            }

            // Validar email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->respondError('Email no válido');
            }

            // Validar longitud de contraseña
            if (strlen($data['password']) < 6) {
                $this->respondError('La contraseña debe tener al menos 6 caracteres');
            }

            $usuarioModel = new Usuario($this->db);
            $data['tipo_usuario'] = 'cliente';
            
            $usuario = $usuarioModel->createUser($data);

            if ($usuario) {
                // Enviar código de verificación por email
                $this->enviarCodigoVerificacion($data['email'], $usuario['codigo_verificacion']);
                
                $this->respondSuccess(
                    ['usuario_id' => $usuario['id']],
                    'Usuario registrado exitosamente. Revisa tu email para verificar tu cuenta.',
                    201
                );
            } else {
                $this->respondError('Error al registrar usuario');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Login de cliente
     */
    public function login()
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

            if (!$usuario['email_verificado']) {
                $this->respondError('Debes verificar tu email antes de iniciar sesión', 403);
            }

            $token = $this->auth->generateToken($usuario['id']);

            $this->respondSuccess([
                'token' => $token,
                'usuario' => $usuario
            ], 'Login exitoso');

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Verificar email con código
     */
    public function verificarEmail()
    {
        try {
            $data = $this->getRequestData();
            
            if (empty($data['email']) || empty($data['codigo'])) {
                $this->respondError('Email y código son requeridos');
            }

            $usuarioModel = new Usuario($this->db);
            $verificado = $usuarioModel->verifyEmail($data['email'], $data['codigo']);

            if ($verificado) {
                $this->respondSuccess(null, 'Email verificado exitosamente');
            } else {
                $this->respondError('Código de verificación inválido');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Reenviar código de verificación
     */
    public function reenviarCodigo()
    {
        try {
            $data = $this->getRequestData();
            
            if (empty($data['email'])) {
                $this->respondError('Email es requerido');
            }

            $usuarioModel = new Usuario($this->db);
            $codigo = $usuarioModel->resendVerificationCode($data['email']);

            if ($codigo) {
                $this->enviarCodigoVerificacion($data['email'], $codigo);
                $this->respondSuccess(null, 'Código reenviado exitosamente');
            } else {
                $this->respondError('No se pudo reenviar el código');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE PERFIL
    // =============================================

    /**
     * Obtener perfil del usuario
     */
    public function getPerfil()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $usuarioModel = new Usuario($this->db);
            $usuario = $usuarioModel->find($tokenData['user_id']);

            if ($usuario) {
                $this->respondSuccess($usuario);
            } else {
                $this->respondError('Usuario no encontrado', 404);
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Actualizar perfil
     */
    public function updatePerfil()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            $usuarioModel = new Usuario($this->db);
            $actualizado = $usuarioModel->updateProfile($tokenData['user_id'], $data);

            if ($actualizado) {
                $usuario = $usuarioModel->find($tokenData['user_id']);
                $this->respondSuccess($usuario, 'Perfil actualizado exitosamente');
            } else {
                $this->respondError('Error al actualizar perfil');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            if (empty($data['password_actual']) || empty($data['password_nuevo'])) {
                $this->respondError('Contraseña actual y nueva son requeridas');
            }

            if (strlen($data['password_nuevo']) < 6) {
                $this->respondError('La nueva contraseña debe tener al menos 6 caracteres');
            }

            $usuarioModel = new Usuario($this->db);
            $cambiado = $usuarioModel->changePassword(
                $tokenData['user_id'],
                $data['password_actual'],
                $data['password_nuevo']
            );

            if ($cambiado) {
                $this->respondSuccess(null, 'Contraseña cambiada exitosamente');
            } else {
                $this->respondError('Contraseña actual incorrecta');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE VEHÍCULOS
    // =============================================

    /**
     * Obtener vehículos del usuario
     */
    public function getVehiculos()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $vehiculoModel = new Vehiculo($this->db);
            $vehiculos = $vehiculoModel->getByUsuario($tokenData['user_id']);
            
            $this->respondSuccess($vehiculos);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Crear nuevo vehículo
     */
    public function createVehiculo()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            // Validar campos requeridos
            $required = ['marca', 'modelo', 'anio', 'placa'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->respondError("El campo {$field} es requerido");
                }
            }

            $data['usuario_id'] = $tokenData['user_id'];
            
            $vehiculoModel = new Vehiculo($this->db);
            
            // Verificar límite de vehículos
            if (!$vehiculoModel->canAddMore($tokenData['user_id'])) {
                $this->respondError('Has alcanzado el límite máximo de vehículos');
            }

            $vehiculo = $vehiculoModel->createVehiculo($data);

            if ($vehiculo) {
                $this->respondSuccess($vehiculo, 'Vehículo registrado exitosamente', 201);
            } else {
                $this->respondError('Error al registrar vehículo');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Actualizar vehículo
     */
    public function updateVehiculo($id)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            $vehiculoModel = new Vehiculo($this->db);
            $vehiculo = $vehiculoModel->find($id);

            if (!$vehiculo || $vehiculo['usuario_id'] != $tokenData['user_id']) {
                $this->respondError('Vehículo no encontrado', 404);
            }

            $actualizado = $vehiculoModel->updateVehiculo($id, $data);

            if ($actualizado) {
                $vehiculo = $vehiculoModel->find($id);
                $this->respondSuccess($vehiculo, 'Vehículo actualizado exitosamente');
            } else {
                $this->respondError('Error al actualizar vehículo');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Eliminar vehículo
     */
    public function deleteVehiculo($id)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $vehiculoModel = new Vehiculo($this->db);
            $eliminado = $vehiculoModel->deleteVehiculo($id, $tokenData['user_id']);

            if ($eliminado) {
                $this->respondSuccess(null, 'Vehículo eliminado exitosamente');
            } else {
                $this->respondError('Error al eliminar vehículo');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE SERVICIOS
    // =============================================

    /**
     * Obtener servicios disponibles
     */
    public function getServicios()
    {
        try {
            $servicioModel = new Servicio($this->db);
            $servicios = $servicioModel->getActivos();
            
            $this->respondSuccess($servicios);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener servicios por ubicación
     */
    public function getServiciosByUbicacion($ubicacion)
    {
        try {
            $servicioModel = new Servicio($this->db);
            $servicios = $servicioModel->getByUbicacion($ubicacion);
            
            $this->respondSuccess($servicios);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE COTIZACIONES
    // =============================================

    /**
     * Crear nueva cotización
     */
    public function createCotizacion()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            // Validar campos requeridos
            $required = ['vehiculo_id', 'servicio_id', 'tipo_ubicacion', 'fecha_servicio', 'hora_servicio'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $this->respondError("El campo {$field} es requerido");
                }
            }

            $data['usuario_id'] = $tokenData['user_id'];
            
            $cotizacionModel = new Cotizacion($this->db);
            $cotizacion = $cotizacionModel->createCotizacion($data);

            if ($cotizacion) {
                $this->respondSuccess($cotizacion, 'Cotización creada exitosamente', 201);
            } else {
                $this->respondError('Error al crear cotización');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener cotizaciones del usuario
     */
    public function getCotizaciones()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $estado = $_GET['estado'] ?? null;
            
            $cotizacionModel = new Cotizacion($this->db);
            $cotizaciones = $cotizacionModel->getByUsuario($tokenData['user_id'], $estado);
            
            $this->respondSuccess($cotizaciones);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Aceptar cotización
     */
    public function aceptarCotizacion($id)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $cotizacionModel = new Cotizacion($this->db);
            $aceptada = $cotizacionModel->aceptarCotizacion($id, $tokenData['user_id']);

            if ($aceptada) {
                $this->respondSuccess(null, 'Cotización aceptada exitosamente');
            } else {
                $this->respondError('Error al aceptar cotización');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Rechazar cotización
     */
    public function rechazarCotizacion($id)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $cotizacionModel = new Cotizacion($this->db);
            $rechazada = $cotizacionModel->rechazarCotizacion($id, $tokenData['user_id']);

            if ($rechazada) {
                $this->respondSuccess(null, 'Cotización rechazada');
            } else {
                $this->respondError('Error al rechazar cotización');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE NOTIFICACIONES
    // =============================================

    /**
     * Obtener notificaciones del usuario
     */
    public function getNotificaciones()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $soloNoLeidas = isset($_GET['no_leidas']) && $_GET['no_leidas'] === 'true';
            
            $notificacionModel = new Notificacion($this->db);
            $notificaciones = $notificacionModel->getByUsuario($tokenData['user_id'], $soloNoLeidas);
            
            $this->respondSuccess($notificaciones);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarNotificacionLeida($id)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $notificacionModel = new Notificacion($this->db);
            $marcada = $notificacionModel->marcarLeida($id, $tokenData['user_id']);

            if ($marcada) {
                $this->respondSuccess(null, 'Notificación marcada como leída');
            } else {
                $this->respondError('Error al marcar notificación');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasNotificacionesLeidas()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $notificacionModel = new Notificacion($this->db);
            $count = $notificacionModel->marcarTodasLeidas($tokenData['user_id']);

            $this->respondSuccess(['marcadas' => $count], 'Todas las notificaciones han sido marcadas como leídas');

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS DE HISTORIAL
    // =============================================

    /**
     * Obtener historial de servicios
     */
    public function getHistorial()
    {
        $tokenData = $this->validateAuth();
        
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
            
            $historialModel = new Historial($this->db);
            $historial = $historialModel->getByUsuario($tokenData['user_id'], $limit);
            
            $this->respondSuccess($historial);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener historial de cambios de aceite
     */
    public function getHistorialAceite($vehiculoId)
    {
        $tokenData = $this->validateAuth();
        
        try {
            // Verificar que el vehículo pertenece al usuario
            $vehiculoModel = new Vehiculo($this->db);
            $vehiculo = $vehiculoModel->find($vehiculoId);

            if (!$vehiculo || $vehiculo['usuario_id'] != $tokenData['user_id']) {
                $this->respondError('Vehículo no encontrado', 404);
            }

            $historialModel = new Historial($this->db);
            $historial = $historialModel->getCambiosAceite($vehiculoId);
            
            $this->respondSuccess($historial);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Obtener historial de lavados
     */
    public function getHistorialLavados($vehiculoId)
    {
        $tokenData = $this->validateAuth();
        
        try {
            // Verificar que el vehículo pertenece al usuario
            $vehiculoModel = new Vehiculo($this->db);
            $vehiculo = $vehiculoModel->find($vehiculoId);

            if (!$vehiculo || $vehiculo['usuario_id'] != $tokenData['user_id']) {
                $this->respondError('Vehículo no encontrado', 404);
            }

            $historialModel = new Historial($this->db);
            $historial = $historialModel->getLavados($vehiculoId);
            
            $this->respondSuccess($historial);

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    /**
     * Calificar servicio
     */
    public function calificarServicio($historialId)
    {
        $tokenData = $this->validateAuth();
        
        try {
            $data = $this->getRequestData();
            
            if (!isset($data['calificacion']) || $data['calificacion'] < 1 || $data['calificacion'] > 5) {
                $this->respondError('Calificación debe estar entre 1 y 5');
            }

            $historialModel = new Historial($this->db);
            $calificado = $historialModel->calificarServicio(
                $historialId,
                $tokenData['user_id'],
                $data['calificacion'],
                $data['comentario'] ?? null
            );

            if ($calificado) {
                $this->respondSuccess(null, 'Servicio calificado exitosamente');
            } else {
                $this->respondError('Error al calificar servicio');
            }

        } catch (Exception $e) {
            $this->respondError($e->getMessage());
        }
    }

    // =============================================
    // MÉTODOS AUXILIARES
    // =============================================

    /**
     * Enviar código de verificación por email
     */
    private function enviarCodigoVerificacion($email, $codigo)
    {
        try {
            require_once __DIR__ . '/../../Mail/CodigoVerificacionMail.php';
            $mailer = new CodigoVerificacionMail();
            return $mailer->enviar($email, $codigo);
        } catch (Exception $e) {
            error_log("Error enviando código de verificación: " . $e->getMessage());
            return false;
        }
    }
}
