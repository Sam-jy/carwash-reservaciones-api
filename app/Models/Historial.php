<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Historial
 * Maneja el historial de servicios completados
 */
class Historial extends BaseModel
{
    protected $table = 'historial_servicios';
    protected $fillable = [
        'cotizacion_id', 'usuario_id', 'vehiculo_id', 'servicio_id',
        'precio_final', 'fecha_servicio', 'hora_inicio', 'hora_fin',
        'calificacion', 'comentario_cliente', 'kilometraje', 'observaciones'
    ];

    /**
     * Obtener historial de un usuario
     */
    public function getByUsuario($usuarioId, $limit = null)
    {
        $query = "SELECT h.*, s.nombre as servicio_nombre, s.descripcion as servicio_descripcion,
                        v.marca, v.modelo, v.placa, v.anio
                 FROM {$this->table} h
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 INNER JOIN vehiculos v ON h.vehiculo_id = v.id
                 WHERE h.usuario_id = :usuario_id
                 ORDER BY h.fecha_servicio DESC, h.fecha_creacion DESC";
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }

        $stmt = $this->query($query, [':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener historial de cambios de aceite de un vehículo
     */
    public function getCambiosAceite($vehiculoId)
    {
        $query = "SELECT h.*, s.nombre as servicio_nombre
                 FROM {$this->table} h
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 WHERE h.vehiculo_id = :vehiculo_id 
                 AND LOWER(s.nombre) LIKE '%aceite%'
                 ORDER BY h.fecha_servicio DESC";

        $stmt = $this->query($query, [':vehiculo_id' => $vehiculoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener historial de lavados de un vehículo
     */
    public function getLavados($vehiculoId)
    {
        $query = "SELECT h.*, s.nombre as servicio_nombre
                 FROM {$this->table} h
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 WHERE h.vehiculo_id = :vehiculo_id 
                 AND LOWER(s.nombre) LIKE '%lavado%'
                 ORDER BY h.fecha_servicio DESC";

        $stmt = $this->query($query, [':vehiculo_id' => $vehiculoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear registro de historial
     */
    public function createHistorial($data)
    {
        // Validar que la cotización existe y está completada
        require_once __DIR__ . '/Cotizacion.php';
        $cotizacionModel = new Cotizacion($this->conexion);
        $cotizacion = $cotizacionModel->find($data['cotizacion_id']);

        if (!$cotizacion || $cotizacion['estado'] !== 'completada') {
            throw new Exception('La cotización debe estar completada para crear el historial');
        }

        return $this->create($data);
    }

    /**
     * Calificar servicio
     */
    public function calificarServicio($id, $usuarioId, $calificacion, $comentario = null)
    {
        $historial = $this->find($id);
        
        if (!$historial || $historial['usuario_id'] != $usuarioId) {
            throw new Exception('Registro de historial no encontrado');
        }

        if ($calificacion < 1 || $calificacion > 5) {
            throw new Exception('La calificación debe estar entre 1 y 5');
        }

        $data = ['calificacion' => $calificacion];
        if ($comentario) {
            $data['comentario_cliente'] = $comentario;
        }

        return $this->update($id, $data);
    }

    /**
     * Obtener estadísticas de servicios por usuario
     */
    public function getStatsUsuario($usuarioId)
    {
        $query = "SELECT 
                    COUNT(*) as total_servicios,
                    SUM(precio_final) as total_gastado,
                    AVG(precio_final) as precio_promedio,
                    COUNT(DISTINCT servicio_id) as servicios_diferentes,
                    COUNT(DISTINCT vehiculo_id) as vehiculos_atendidos,
                    AVG(calificacion) as calificacion_promedio
                 FROM {$this->table}
                 WHERE usuario_id = :usuario_id";

        $stmt = $this->query($query, [':usuario_id' => $usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener servicios más solicitados
     */
    public function getServiciosMasPopulares($limit = 10)
    {
        $query = "SELECT s.nombre, s.descripcion, COUNT(*) as total_servicios,
                        AVG(h.precio_final) as precio_promedio,
                        AVG(h.calificacion) as calificacion_promedio
                 FROM {$this->table} h
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 GROUP BY h.servicio_id, s.nombre, s.descripcion
                 ORDER BY total_servicios DESC
                 LIMIT {$limit}";

        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener ingresos por período
     */
    public function getIngresosPorPeriodo($fechaInicio, $fechaFin)
    {
        $query = "SELECT 
                    DATE(fecha_servicio) as fecha,
                    COUNT(*) as servicios,
                    SUM(precio_final) as ingresos_dia
                 FROM {$this->table}
                 WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                 GROUP BY DATE(fecha_servicio)
                 ORDER BY fecha ASC";

        $stmt = $this->query($query, [
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener clientes frecuentes
     */
    public function getClientesFrecuentes($limit = 20)
    {
        $query = "SELECT u.nombre, u.apellido, u.email, u.telefono,
                        COUNT(*) as total_servicios,
                        SUM(h.precio_final) as total_gastado,
                        MAX(h.fecha_servicio) as ultimo_servicio
                 FROM {$this->table} h
                 INNER JOIN usuarios u ON h.usuario_id = u.id
                 GROUP BY h.usuario_id, u.nombre, u.apellido, u.email, u.telefono
                 ORDER BY total_servicios DESC, total_gastado DESC
                 LIMIT {$limit}";

        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener historial general para admin
     */
    public function getHistorialGeneral($filtros = [], $limit = 50)
    {
        $whereConditions = ["1=1"];
        $params = [];

        if (!empty($filtros['fecha_inicio'])) {
            $whereConditions[] = "h.fecha_servicio >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $whereConditions[] = "h.fecha_servicio <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['servicio_id'])) {
            $whereConditions[] = "h.servicio_id = :servicio_id";
            $params[':servicio_id'] = $filtros['servicio_id'];
        }

        if (!empty($filtros['usuario_id'])) {
            $whereConditions[] = "h.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filtros['usuario_id'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "SELECT h.*, u.nombre, u.apellido, u.telefono,
                        s.nombre as servicio_nombre,
                        v.marca, v.modelo, v.placa
                 FROM {$this->table} h
                 INNER JOIN usuarios u ON h.usuario_id = u.id
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 INNER JOIN vehiculos v ON h.vehiculo_id = v.id
                 WHERE {$whereClause}
                 ORDER BY h.fecha_servicio DESC, h.fecha_creacion DESC
                 LIMIT {$limit}";

        $stmt = $this->query($query, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si se necesita cambio de aceite
     */
    public function verificarCambioAceite($vehiculoId, $kilometrajeActual = null)
    {
        $ultimoCambio = $this->query(
            "SELECT * FROM {$this->table} h
             INNER JOIN servicios s ON h.servicio_id = s.id
             WHERE h.vehiculo_id = :vehiculo_id 
             AND LOWER(s.nombre) LIKE '%aceite%'
             ORDER BY h.fecha_servicio DESC
             LIMIT 1",
            [':vehiculo_id' => $vehiculoId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$ultimoCambio) {
            return [
                'necesita_cambio' => true,
                'razon' => 'No hay registro de cambios de aceite anteriores',
                'ultimo_cambio' => null
            ];
        }

        $fechaUltimoCambio = new DateTime($ultimoCambio['fecha_servicio']);
        $fechaActual = new DateTime();
        $diasTranscurridos = $fechaActual->diff($fechaUltimoCambio)->days;

        // Recomendación cada 6 meses o 5000 km
        $necesitaPorTiempo = $diasTranscurridos > 180;
        $necesitaPorKilometraje = false;

        if ($kilometrajeActual && $ultimoCambio['kilometraje']) {
            $kmRecorridos = $kilometrajeActual - $ultimoCambio['kilometraje'];
            $necesitaPorKilometraje = $kmRecorridos >= 5000;
        }

        return [
            'necesita_cambio' => $necesitaPorTiempo || $necesitaPorKilometraje,
            'razon' => $necesitaPorTiempo ? 'Han pasado más de 6 meses' : 
                      ($necesitaPorKilometraje ? 'Ha recorrido más de 5000 km' : 'Aún no es necesario'),
            'ultimo_cambio' => $ultimoCambio,
            'dias_transcurridos' => $diasTranscurridos,
            'km_recorridos' => $kilometrajeActual && $ultimoCambio['kilometraje'] ? 
                              $kilometrajeActual - $ultimoCambio['kilometraje'] : null
        ];
    }

    /**
     * Obtener reporte de calificaciones
     */
    public function getReporteCalificaciones()
    {
        $query = "SELECT 
                    s.nombre as servicio,
                    COUNT(h.calificacion) as total_calificaciones,
                    AVG(h.calificacion) as calificacion_promedio,
                    SUM(CASE WHEN h.calificacion = 5 THEN 1 ELSE 0 END) as cinco_estrellas,
                    SUM(CASE WHEN h.calificacion = 4 THEN 1 ELSE 0 END) as cuatro_estrellas,
                    SUM(CASE WHEN h.calificacion = 3 THEN 1 ELSE 0 END) as tres_estrellas,
                    SUM(CASE WHEN h.calificacion = 2 THEN 1 ELSE 0 END) as dos_estrellas,
                    SUM(CASE WHEN h.calificacion = 1 THEN 1 ELSE 0 END) as una_estrella
                 FROM {$this->table} h
                 INNER JOIN servicios s ON h.servicio_id = s.id
                 WHERE h.calificacion IS NOT NULL
                 GROUP BY h.servicio_id, s.nombre
                 ORDER BY calificacion_promedio DESC";

        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
