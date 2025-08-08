<?php

require_once __DIR__ . '/../Models/Notificacion.php';
require_once __DIR__ . '/../Models/Usuario.php';

/**
 * Servicio de Notificaciones
 * Maneja la lógica de negocio para el envío y gestión de notificaciones
 */
class NotificacionService
{
    private $db;
    private $notificacionModel;
    private $usuarioModel;

    public function __construct($db = null)
    {
        if ($db === null) {
            require_once __DIR__ . '/../../database.php';
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }

        $this->notificacionModel = new Notificacion($this->db);
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Enviar notificación de cotización enviada
     */
    public function notificarCotizacionEnviada($cotizacionId, $usuarioId, $precio)
    {
        try {
            return $this->notificacionModel->notificarCotizacion($usuarioId, $cotizacionId, $precio);
        } catch (Exception $e) {
            error_log("Error enviando notificación de cotización: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de cotización aceptada
     */
    public function notificarCotizacionAceptada($cotizacionId, $cotizacionData)
    {
        try {
            $mensaje = "Tu cotización para {$cotizacionData['servicio_nombre']} ha sido aceptada. El servicio está programado para el {$cotizacionData['fecha_servicio']} a las {$cotizacionData['hora_servicio']}.";

            return $this->notificacionModel->createNotificacion([
                'usuario_id' => $cotizacionData['usuario_id'],
                'cotizacion_id' => $cotizacionId,
                'titulo' => 'Cotización Aceptada',
                'mensaje' => $mensaje,
                'tipo' => Notificacion::TIPO_SISTEMA
            ]);
        } catch (Exception $e) {
            error_log("Error enviando notificación de aceptación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de servicio completado
     */
    public function notificarServicioCompletado($cotizacionId, $cotizacionData)
    {
        try {
            $mensaje = "Tu servicio de {$cotizacionData['servicio_nombre']} ha sido completado exitosamente. ¡Gracias por confiar en nosotros!";

            return $this->notificacionModel->createNotificacion([
                'usuario_id' => $cotizacionData['usuario_id'],
                'cotizacion_id' => $cotizacionId,
                'titulo' => 'Servicio Completado',
                'mensaje' => $mensaje,
                'tipo' => Notificacion::TIPO_SISTEMA
            ]);
        } catch (Exception $e) {
            error_log("Error enviando notificación de completado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Programar recordatorios automáticos para servicios del día siguiente
     */
    public function programarRecordatoriosDiarios()
    {
        try {
            return $this->notificacionModel->programarRecordatorios();
        } catch (Exception $e) {
            error_log("Error programando recordatorios: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Enviar recordatorio de cambio de aceite
     */
    public function recordarCambioAceite($usuarioId, $vehiculoData, $diasTranscurridos, $kmRecorridos = null)
    {
        try {
            $mensaje = "Es hora de cambiar el aceite de tu {$vehiculoData['marca']} {$vehiculoData['modelo']} (Placa: {$vehiculoData['placa']}). ";
            
            if ($kmRecorridos) {
                $mensaje .= "Has recorrido {$kmRecorridos} km desde el último cambio.";
            } else {
                $mensaje .= "Han pasado {$diasTranscurridos} días desde el último cambio.";
            }
            
            $mensaje .= " ¡Programa tu cita con nosotros!";

            return $this->notificacionModel->notificarRecordatorio($usuarioId, $mensaje);
        } catch (Exception $e) {
            error_log("Error enviando recordatorio de aceite: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar promoción a usuarios específicos o todos
     */
    public function enviarPromocion($titulo, $mensaje, $criterios = [])
    {
        try {
            $usuarioIds = $this->obtenerUsuariosParaPromocion($criterios);
            return $this->notificacionModel->enviarPromocion($titulo, $mensaje, $usuarioIds);
        } catch (Exception $e) {
            error_log("Error enviando promoción: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener usuarios que cumplen criterios para promociones
     */
    private function obtenerUsuariosParaPromocion($criterios)
    {
        $whereConditions = ["tipo_usuario = 'cliente'", "activo = 1"];
        $params = [];

        // Filtrar por usuarios sin servicios en X días
        if (isset($criterios['dias_sin_servicio'])) {
            $fechaLimite = date('Y-m-d', strtotime("-{$criterios['dias_sin_servicio']} days"));
            $whereConditions[] = "id NOT IN (
                SELECT DISTINCT usuario_id FROM historial_servicios 
                WHERE fecha_servicio >= :fecha_limite
            )";
            $params[':fecha_limite'] = $fechaLimite;
        }

        // Filtrar por usuarios frecuentes
        if (isset($criterios['solo_frecuentes']) && $criterios['solo_frecuentes']) {
            $whereConditions[] = "id IN (
                SELECT usuario_id FROM historial_servicios 
                GROUP BY usuario_id 
                HAVING COUNT(*) >= 3
            )";
        }

        // Filtrar por usuarios nuevos
        if (isset($criterios['solo_nuevos']) && $criterios['solo_nuevos']) {
            $fechaLimite = date('Y-m-d', strtotime('-30 days'));
            $whereConditions[] = "fecha_creacion >= :fecha_nuevos";
            $params[':fecha_nuevos'] = $fechaLimite;
        }

        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "SELECT id FROM usuarios WHERE {$whereClause}";
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Limpiar notificaciones antiguas
     */
    public function limpiarNotificacionesAntiguas($dias = 30)
    {
        try {
            return $this->notificacionModel->limpiarAntiguas($dias);
        } catch (Exception $e) {
            error_log("Error limpiando notificaciones: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function getEstadisticas($fechaInicio = null, $fechaFin = null)
    {
        try {
            return $this->notificacionModel->getStats($fechaInicio, $fechaFin);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marcar notificaciones como leídas masivamente
     */
    public function marcarLeidasMasivo($usuarioId, $notificacionIds = null)
    {
        try {
            if ($notificacionIds) {
                $count = 0;
                foreach ($notificacionIds as $id) {
                    if ($this->notificacionModel->marcarLeida($id, $usuarioId)) {
                        $count++;
                    }
                }
                return $count;
            } else {
                return $this->notificacionModel->marcarTodasLeidas($usuarioId);
            }
        } catch (Exception $e) {
            error_log("Error marcando notificaciones: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verificar límite de notificaciones por usuario
     */
    public function verificarLimiteNotificaciones($usuarioId, $limiteDiario = 10)
    {
        try {
            $fechaHoy = date('Y-m-d');
            
            $query = "SELECT COUNT(*) FROM notificaciones 
                     WHERE usuario_id = :usuario_id 
                     AND DATE(fecha_creacion) = :fecha";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuarioId);
            $stmt->bindParam(':fecha', $fechaHoy);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            return $count < $limiteDiario;
        } catch (Exception $e) {
            error_log("Error verificando límite: " . $e->getMessage());
            return true; // En caso de error, permitir
        }
    }

    /**
     * Programar notificaciones diferidas
     */
    public function programarNotificacion($usuarioId, $titulo, $mensaje, $tipo, $fechaEnvio, $cotizacionId = null)
    {
        try {
            // Por ahora crear la notificación inmediatamente
            // En una implementación más robusta, se usaría un sistema de colas
            return $this->notificacionModel->createNotificacion([
                'usuario_id' => $usuarioId,
                'cotizacion_id' => $cotizacionId,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => $tipo
            ]);
        } catch (Exception $e) {
            error_log("Error programando notificación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de bienvenida a nuevo usuario
     */
    public function enviarBienvenida($usuarioId, $nombreUsuario)
    {
        try {
            $titulo = "¡Bienvenido a Car Wash El Catracho!";
            $mensaje = "Hola {$nombreUsuario}, gracias por registrarte en nuestra plataforma. Estamos aquí para mantener tu vehículo siempre limpio y en perfectas condiciones. ¡Solicita tu primera cotización ahora!";

            return $this->notificacionModel->createNotificacion([
                'usuario_id' => $usuarioId,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => Notificacion::TIPO_SISTEMA
            ]);
        } catch (Exception $e) {
            error_log("Error enviando bienvenida: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notificar sobre promociones especiales basadas en historial
     */
    public function notificarPromocionPersonalizada($usuarioId)
    {
        try {
            // Obtener historial del usuario
            require_once __DIR__ . '/../Models/Historial.php';
            $historialModel = new Historial($this->db);
            $stats = $historialModel->getStatsUsuario($usuarioId);

            if ($stats['total_servicios'] >= 5) {
                $titulo = "¡Oferta Especial para Cliente Frecuente!";
                $mensaje = "Como cliente frecuente con {$stats['total_servicios']} servicios realizados, tienes 20% de descuento en tu próximo lavado completo. ¡Válido por 7 días!";
            } elseif ($stats['total_servicios'] >= 3) {
                $titulo = "Descuento por Fidelidad";
                $mensaje = "¡Felicidades! Por ser un cliente leal, tienes 15% de descuento en cualquier servicio. ¡Aprovecha esta oferta especial!";
            } else {
                $titulo = "Oferta de Bienvenida";
                $mensaje = "Como nuevo cliente, disfruta de 10% de descuento en tu próximo servicio. ¡Conócenos mejor!";
            }

            return $this->notificacionModel->createNotificacion([
                'usuario_id' => $usuarioId,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => Notificacion::TIPO_PROMOCION
            ]);
        } catch (Exception $e) {
            error_log("Error enviando promoción personalizada: " . $e->getMessage());
            return false;
        }
    }
}
