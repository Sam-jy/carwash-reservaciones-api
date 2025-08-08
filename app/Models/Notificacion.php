<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Notificacion
 * Maneja las notificaciones push y por email
 */
class Notificacion extends BaseModel
{
    protected $table = 'notificaciones';
    protected $fillable = [
        'usuario_id', 'cotizacion_id', 'titulo', 'mensaje', 'tipo',
        'leida', 'enviada_push', 'enviada_email'
    ];

    const TIPO_COTIZACION = 'cotizacion';
    const TIPO_RECORDATORIO = 'recordatorio';
    const TIPO_PROMOCION = 'promocion';
    const TIPO_SISTEMA = 'sistema';

    /**
     * Obtener notificaciones de un usuario
     */
    public function getByUsuario($usuarioId, $soloNoLeidas = false)
    {
        $conditions = ['usuario_id' => $usuarioId];
        
        if ($soloNoLeidas) {
            $conditions['leida'] = 0;
        }

        return $this->all($conditions, 'fecha_creacion DESC');
    }

    /**
     * Crear notificación completa
     */
    public function createNotificacion($data)
    {
        $notificacion = $this->create($data);
        
        if ($notificacion) {
            // Enviar notificación push si está configurado
            $this->enviarPush($notificacion['id']);
            
            // Enviar email si es necesario
            if (in_array($data['tipo'], [self::TIPO_COTIZACION, self::TIPO_SISTEMA])) {
                $this->enviarEmail($notificacion['id']);
            }
        }
        
        return $notificacion;
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida($id, $usuarioId)
    {
        $notificacion = $this->find($id);
        
        if (!$notificacion || $notificacion['usuario_id'] != $usuarioId) {
            throw new Exception('Notificación no encontrada');
        }

        return $this->update($id, ['leida' => 1]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasLeidas($usuarioId)
    {
        $query = "UPDATE {$this->table} SET leida = 1 WHERE usuario_id = :usuario_id";
        $stmt = $this->query($query, [':usuario_id' => $usuarioId]);
        return $stmt->rowCount();
    }

    /**
     * Obtener contador de notificaciones no leídas
     */
    public function getContadorNoLeidas($usuarioId)
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table} 
                 WHERE usuario_id = :usuario_id AND leida = 0";
        
        $stmt = $this->query($query, [':usuario_id' => $usuarioId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }

    /**
     * Enviar notificación push
     */
    private function enviarPush($notificacionId)
    {
        $notificacion = $this->find($notificacionId);
        
        if (!$notificacion) {
            return false;
        }

        // Obtener token FCM del usuario (si existe)
        $query = "SELECT fcm_token FROM usuarios WHERE id = :usuario_id AND fcm_token IS NOT NULL";
        $stmt = $this->query($query, [':usuario_id' => $notificacion['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !$usuario['fcm_token']) {
            return false;
        }

        try {
            // Aquí iría la lógica para enviar push notification
            // Por ejemplo, usando Firebase Cloud Messaging
            $result = $this->sendFCMNotification(
                $usuario['fcm_token'],
                $notificacion['titulo'],
                $notificacion['mensaje']
            );

            if ($result) {
                $this->update($notificacionId, ['enviada_push' => 1]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error enviando push notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación por email
     */
    private function enviarEmail($notificacionId)
    {
        $notificacion = $this->find($notificacionId);
        
        if (!$notificacion) {
            return false;
        }

        // Obtener datos del usuario
        $query = "SELECT email, nombre, apellido FROM usuarios WHERE id = :usuario_id";
        $stmt = $this->query($query, [':usuario_id' => $notificacion['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !$usuario['email']) {
            return false;
        }

        try {
            require_once __DIR__ . '/../Mail/NotificacionMail.php';
            $mailer = new NotificacionMail();
            
            $result = $mailer->enviar(
                $usuario['email'],
                $usuario['nombre'] . ' ' . $usuario['apellido'],
                $notificacion['titulo'],
                $notificacion['mensaje'],
                $notificacion['tipo']
            );

            if ($result) {
                $this->update($notificacionId, ['enviada_email' => 1]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear notificación de cotización
     */
    public function notificarCotizacion($usuarioId, $cotizacionId, $precio)
    {
        return $this->createNotificacion([
            'usuario_id' => $usuarioId,
            'cotizacion_id' => $cotizacionId,
            'titulo' => 'Cotización Enviada',
            'mensaje' => "Tu cotización ha sido procesada. Precio: L. {$precio}",
            'tipo' => self::TIPO_COTIZACION
        ]);
    }

    /**
     * Crear notificación de recordatorio
     */
    public function notificarRecordatorio($usuarioId, $mensaje, $cotizacionId = null)
    {
        return $this->createNotificacion([
            'usuario_id' => $usuarioId,
            'cotizacion_id' => $cotizacionId,
            'titulo' => 'Recordatorio de Servicio',
            'mensaje' => $mensaje,
            'tipo' => self::TIPO_RECORDATORIO
        ]);
    }

    /**
     * Enviar notificaciones promocionales
     */
    public function enviarPromocion($titulo, $mensaje, $usuarioIds = null)
    {
        if ($usuarioIds === null) {
            // Enviar a todos los usuarios activos
            $query = "SELECT id FROM usuarios WHERE activo = 1 AND tipo_usuario = 'cliente'";
            $stmt = $this->query($query);
            $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $usuarios = $usuarioIds;
        }

        $contador = 0;
        foreach ($usuarios as $usuarioId) {
            try {
                $this->createNotificacion([
                    'usuario_id' => $usuarioId,
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'tipo' => self::TIPO_PROMOCION
                ]);
                $contador++;
            } catch (Exception $e) {
                error_log("Error enviando promoción a usuario {$usuarioId}: " . $e->getMessage());
            }
        }

        return $contador;
    }

    /**
     * Limpiar notificaciones antiguas
     */
    public function limpiarAntiguas($diasAntiguedad = 30)
    {
        $fechaLimite = date('Y-m-d H:i:s', strtotime("-{$diasAntiguedad} days"));
        
        $query = "DELETE FROM {$this->table} 
                 WHERE fecha_creacion < :fecha_limite AND leida = 1";
        
        $stmt = $this->query($query, [':fecha_limite' => $fechaLimite]);
        return $stmt->rowCount();
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function getStats($fechaInicio = null, $fechaFin = null)
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($fechaInicio) {
            $whereClause .= " AND fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        if ($fechaFin) {
            $whereClause .= " AND fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN leida = 1 THEN 1 ELSE 0 END) as leidas,
                    SUM(CASE WHEN enviada_push = 1 THEN 1 ELSE 0 END) as enviadas_push,
                    SUM(CASE WHEN enviada_email = 1 THEN 1 ELSE 0 END) as enviadas_email,
                    COUNT(DISTINCT usuario_id) as usuarios_notificados
                 FROM {$this->table}
                 {$whereClause}";

        $stmt = $this->query($query, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Enviar FCM notification (Firebase Cloud Messaging)
     */
    private function sendFCMNotification($token, $titulo, $mensaje)
    {
        // Esta función requiere configuración de Firebase
        // Por ahora retornamos true como placeholder
        
        // $serverKey = 'TU_SERVER_KEY_DE_FIREBASE';
        // $url = 'https://fcm.googleapis.com/fcm/send';
        
        // $data = [
        //     'to' => $token,
        //     'notification' => [
        //         'title' => $titulo,
        //         'body' => $mensaje,
        //         'icon' => 'ic_notification',
        //         'sound' => 'default'
        //     ]
        // ];
        
        // $headers = [
        //     'Authorization: key=' . $serverKey,
        //     'Content-Type: application/json',
        // ];
        
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        // $result = curl_exec($ch);
        // curl_close($ch);
        
        // return $result !== false;
        
        return true; // Placeholder
    }

    /**
     * Programar recordatorios automáticos
     */
    public function programarRecordatorios()
    {
        // Recordatorios para servicios del día siguiente
        $mañana = date('Y-m-d', strtotime('+1 day'));
        
        $query = "SELECT c.*, u.nombre, u.apellido, s.nombre as servicio_nombre
                 FROM cotizaciones c
                 INNER JOIN usuarios u ON c.usuario_id = u.id
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 WHERE c.fecha_servicio = :fecha AND c.estado = 'aceptada'";
        
        $stmt = $this->query($query, [':fecha' => $mañana]);
        $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $contador = 0;
        foreach ($cotizaciones as $cotizacion) {
            $mensaje = "Recordatorio: Tienes un servicio de {$cotizacion['servicio_nombre']} programado mañana a las {$cotizacion['hora_servicio']}.";
            
            $this->notificarRecordatorio(
                $cotizacion['usuario_id'],
                $mensaje,
                $cotizacion['id']
            );
            
            $contador++;
        }
        
        return $contador;
    }
}
