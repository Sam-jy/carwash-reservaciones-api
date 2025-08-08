<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Cotizacion
 * Maneja las cotizaciones de servicios
 */
class Cotizacion extends BaseModel
{
    protected $table = 'cotizaciones';
    protected $fillable = [
        'usuario_id', 'vehiculo_id', 'servicio_id', 'tipo_ubicacion',
        'direccion_servicio', 'latitud', 'longitud', 'fecha_servicio',
        'hora_servicio', 'precio_cotizado', 'estado', 'notas_cliente',
        'notas_admin', 'fecha_respuesta'
    ];

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_ENVIADA = 'enviada';
    const ESTADO_ACEPTADA = 'aceptada';
    const ESTADO_RECHAZADA = 'rechazada';
    const ESTADO_COMPLETADA = 'completada';
    const ESTADO_CANCELADA = 'cancelada';

    /**
     * Crear nueva cotización
     */
    public function createCotizacion($data)
    {
        // Validar fecha del servicio
        $fechaServicio = new DateTime($data['fecha_servicio']);
        $fechaActual = new DateTime();
        
        if ($fechaServicio <= $fechaActual) {
            throw new Exception('La fecha del servicio debe ser futura');
        }

        // Validar que el vehículo pertenece al usuario
        require_once __DIR__ . '/Vehiculo.php';
        $vehiculoModel = new Vehiculo($this->conexion);
        $vehiculo = $vehiculoModel->find($data['vehiculo_id']);
        
        if (!$vehiculo || $vehiculo['usuario_id'] != $data['usuario_id']) {
            throw new Exception('Vehículo no válido');
        }

        // Calcular precio automáticamente
        require_once __DIR__ . '/Servicio.php';
        $servicioModel = new Servicio($this->conexion);
        $precio = $servicioModel->calcularPrecio(
            $data['servicio_id'], 
            $data['tipo_ubicacion'], 
            $vehiculo
        );
        
        $data['precio_cotizado'] = $precio;
        $data['estado'] = self::ESTADO_PENDIENTE;

        return $this->create($data);
    }

    /**
     * Obtener cotizaciones de un usuario
     */
    public function getByUsuario($usuarioId, $estado = null)
    {
        $conditions = ['usuario_id' => $usuarioId];
        
        if ($estado) {
            $conditions['estado'] = $estado;
        }

        $query = "SELECT c.*, s.nombre as servicio_nombre, v.marca, v.modelo, v.placa
                 FROM {$this->table} c
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 INNER JOIN vehiculos v ON c.vehiculo_id = v.id
                 WHERE " . implode(' AND ', array_map(fn($k) => "c.$k = :$k", array_keys($conditions))) . "
                 ORDER BY c.fecha_creacion DESC";

        $stmt = $this->query($query, array_combine(
            array_map(fn($k) => ":$k", array_keys($conditions)),
            array_values($conditions)
        ));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener cotizaciones pendientes para admin
     */
    public function getPendientes()
    {
        $query = "SELECT c.*, u.nombre, u.apellido, u.telefono, u.email,
                        s.nombre as servicio_nombre, v.marca, v.modelo, v.placa
                 FROM {$this->table} c
                 INNER JOIN usuarios u ON c.usuario_id = u.id
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 INNER JOIN vehiculos v ON c.vehiculo_id = v.id
                 WHERE c.estado = :estado
                 ORDER BY c.fecha_creacion ASC";

        $stmt = $this->query($query, [':estado' => self::ESTADO_PENDIENTE]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Responder a una cotización (Admin)
     */
    public function responderCotizacion($id, $precio, $notasAdmin = null)
    {
        $data = [
            'precio_cotizado' => $precio,
            'estado' => self::ESTADO_ENVIADA,
            'fecha_respuesta' => date('Y-m-d H:i:s')
        ];

        if ($notasAdmin) {
            $data['notas_admin'] = $notasAdmin;
        }

        $result = $this->update($id, $data);

        if ($result) {
            // Crear notificación para el cliente
            $this->createNotificacion($id);
        }

        return $result;
    }

    /**
     * Aceptar cotización (Cliente)
     */
    public function aceptarCotizacion($id, $usuarioId)
    {
        $cotizacion = $this->find($id);
        
        if (!$cotizacion || $cotizacion['usuario_id'] != $usuarioId) {
            throw new Exception('Cotización no encontrada');
        }

        if ($cotizacion['estado'] !== self::ESTADO_ENVIADA) {
            throw new Exception('No se puede aceptar esta cotización');
        }

        return $this->update($id, ['estado' => self::ESTADO_ACEPTADA]);
    }

    /**
     * Rechazar cotización (Cliente)
     */
    public function rechazarCotizacion($id, $usuarioId)
    {
        $cotizacion = $this->find($id);
        
        if (!$cotizacion || $cotizacion['usuario_id'] != $usuarioId) {
            throw new Exception('Cotización no encontrada');
        }

        if ($cotizacion['estado'] !== self::ESTADO_ENVIADA) {
            throw new Exception('No se puede rechazar esta cotización');
        }

        return $this->update($id, ['estado' => self::ESTADO_RECHAZADA]);
    }

    /**
     * Completar servicio (Admin)
     */
    public function completarServicio($id, $observaciones = null)
    {
        $cotizacion = $this->find($id);
        
        if (!$cotizacion || $cotizacion['estado'] !== self::ESTADO_ACEPTADA) {
            throw new Exception('No se puede completar este servicio');
        }

        $result = $this->update($id, ['estado' => self::ESTADO_COMPLETADA]);

        if ($result) {
            // Crear registro en historial
            $this->createHistorialRecord($cotizacion, $observaciones);
        }

        return $result;
    }

    /**
     * Cancelar cotización
     */
    public function cancelarCotizacion($id, $usuarioId = null)
    {
        $cotizacion = $this->find($id);
        
        if (!$cotizacion) {
            throw new Exception('Cotización no encontrada');
        }

        // Si se especifica usuarioId, verificar que sea el propietario
        if ($usuarioId && $cotizacion['usuario_id'] != $usuarioId) {
            throw new Exception('No tienes permisos para cancelar esta cotización');
        }

        return $this->update($id, ['estado' => self::ESTADO_CANCELADA]);
    }

    /**
     * Obtener cotizaciones por estado
     */
    public function getByEstado($estado, $limit = null)
    {
        $query = "SELECT c.*, u.nombre, u.apellido, s.nombre as servicio_nombre, 
                        v.marca, v.modelo, v.placa
                 FROM {$this->table} c
                 INNER JOIN usuarios u ON c.usuario_id = u.id
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 INNER JOIN vehiculos v ON c.vehiculo_id = v.id
                 WHERE c.estado = :estado
                 ORDER BY c.fecha_creacion DESC";

        if ($limit) {
            $query .= " LIMIT {$limit}";
        }

        $stmt = $this->query($query, [':estado' => $estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener estadísticas de cotizaciones
     */
    public function getStats($fechaInicio = null, $fechaFin = null)
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($fechaInicio) {
            $whereClause .= " AND c.fecha_creacion >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }

        if ($fechaFin) {
            $whereClause .= " AND c.fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
                    SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
                    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                    AVG(precio_cotizado) as precio_promedio,
                    SUM(CASE WHEN estado = 'completada' THEN precio_cotizado ELSE 0 END) as ingresos_totales
                 FROM {$this->table} c
                 {$whereClause}";

        $stmt = $this->query($query, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear notificación para el cliente
     */
    private function createNotificacion($cotizacionId)
    {
        require_once __DIR__ . '/Notificacion.php';
        $notificacionModel = new Notificacion($this->conexion);
        
        $cotizacion = $this->find($cotizacionId);
        
        $notificacionModel->create([
            'usuario_id' => $cotizacion['usuario_id'],
            'cotizacion_id' => $cotizacionId,
            'titulo' => 'Cotización Enviada',
            'mensaje' => "Tu cotización ha sido procesada. Precio: L. {$cotizacion['precio_cotizado']}",
            'tipo' => 'cotizacion'
        ]);
    }

    /**
     * Crear registro en historial
     */
    private function createHistorialRecord($cotizacion, $observaciones = null)
    {
        require_once __DIR__ . '/Historial.php';
        $historialModel = new Historial($this->conexion);
        
        $historialModel->create([
            'cotizacion_id' => $cotizacion['id'],
            'usuario_id' => $cotizacion['usuario_id'],
            'vehiculo_id' => $cotizacion['vehiculo_id'],
            'servicio_id' => $cotizacion['servicio_id'],
            'precio_final' => $cotizacion['precio_cotizado'],
            'fecha_servicio' => $cotizacion['fecha_servicio'],
            'observaciones' => $observaciones
        ]);
    }

    /**
     * Verificar disponibilidad de fecha/hora
     */
    public function verificarDisponibilidad($fechaServicio, $horaServicio, $excludeId = null)
    {
        $query = "SELECT COUNT(*) as total
                 FROM {$this->table}
                 WHERE fecha_servicio = :fecha AND hora_servicio = :hora
                 AND estado IN ('enviada', 'aceptada')";
        
        $params = [':fecha' => $fechaServicio, ':hora' => $horaServicio];
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->query($query, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Máximo 5 servicios por hora (configurable)
        return $result['total'] < 5;
    }
}
