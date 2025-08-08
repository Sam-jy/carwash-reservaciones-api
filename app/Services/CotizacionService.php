<?php

require_once __DIR__ . '/../Models/Cotizacion.php';
require_once __DIR__ . '/../Models/Servicio.php';
require_once __DIR__ . '/../Models/Vehiculo.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/NotificacionService.php';

/**
 * Servicio de Cotizaciones
 * Maneja la lógica de negocio para cotizaciones y servicios
 */
class CotizacionService
{
    private $db;
    private $cotizacionModel;
    private $servicioModel;
    private $vehiculoModel;
    private $usuarioModel;
    private $notificacionService;

    public function __construct($db = null)
    {
        if ($db === null) {
            require_once __DIR__ . '/../../database.php';
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }

        $this->cotizacionModel = new Cotizacion($this->db);
        $this->servicioModel = new Servicio($this->db);
        $this->vehiculoModel = new Vehiculo($this->db);
        $this->usuarioModel = new Usuario($this->db);
        $this->notificacionService = new NotificacionService($this->db);
    }

    /**
     * Crear una nueva cotización con validaciones completas
     */
    public function crearCotizacion($data, $usuarioId)
    {
        try {
            // Validar que el vehículo pertenece al usuario
            $vehiculo = $this->vehiculoModel->find($data['vehiculo_id']);
            if (!$vehiculo || $vehiculo['usuario_id'] != $usuarioId) {
                throw new Exception('El vehículo seleccionado no es válido');
            }

            // Validar que el servicio existe y está activo
            $servicio = $this->servicioModel->find($data['servicio_id']);
            if (!$servicio || !$servicio['activo']) {
                throw new Exception('El servicio seleccionado no está disponible');
            }

            // Validar disponibilidad del servicio para la ubicación
            if (!$this->servicioModel->isDisponible($data['servicio_id'], $data['tipo_ubicacion'])) {
                throw new Exception('El servicio no está disponible para la ubicación seleccionada');
            }

            // Verificar disponibilidad de fecha/hora
            if (!$this->cotizacionModel->verificarDisponibilidad($data['fecha_servicio'], $data['hora_servicio'])) {
                throw new Exception('La fecha y hora seleccionadas no están disponibles');
            }

            // Validar horarios especiales para cambio de aceite
            if (strtolower($servicio['nombre']) === 'cambio de aceite' && $data['tipo_ubicacion'] === 'domicilio') {
                throw new Exception('El cambio de aceite solo está disponible en centro de servicio');
            }

            // Agregar usuario_id a los datos
            $data['usuario_id'] = $usuarioId;

            // Crear la cotización
            $cotizacion = $this->cotizacionModel->createCotizacion($data);

            if ($cotizacion) {
                // Enviar notificación de confirmación
                $this->notificarCotizacionCreada($cotizacion['id'], $usuarioId);
                
                return $cotizacion;
            }

            throw new Exception('Error al crear la cotización');

        } catch (Exception $e) {
            error_log("Error en CotizacionService::crearCotizacion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar respuesta de cotización por parte del admin
     */
    public function procesarRespuestaCotizacion($cotizacionId, $precio, $notasAdmin = null)
    {
        try {
            // Obtener cotización
            $cotizacion = $this->cotizacionModel->find($cotizacionId);
            if (!$cotizacion) {
                throw new Exception('Cotización no encontrada');
            }

            if ($cotizacion['estado'] !== Cotizacion::ESTADO_PENDIENTE) {
                throw new Exception('La cotización ya ha sido procesada');
            }

            // Validar precio
            if ($precio <= 0) {
                throw new Exception('El precio debe ser mayor a 0');
            }

            // Responder la cotización
            $respondida = $this->cotizacionModel->responderCotizacion($cotizacionId, $precio, $notasAdmin);

            if ($respondida) {
                // Enviar notificación al cliente
                $this->notificacionService->notificarCotizacionEnviada(
                    $cotizacionId,
                    $cotizacion['usuario_id'],
                    $precio
                );

                return true;
            }

            throw new Exception('Error al responder la cotización');

        } catch (Exception $e) {
            error_log("Error en CotizacionService::procesarRespuestaCotizacion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar aceptación de cotización por parte del cliente
     */
    public function procesarAceptacionCotizacion($cotizacionId, $usuarioId)
    {
        try {
            $cotizacion = $this->cotizacionModel->find($cotizacionId);
            if (!$cotizacion) {
                throw new Exception('Cotización no encontrada');
            }

            if ($cotizacion['usuario_id'] != $usuarioId) {
                throw new Exception('No tienes permisos para esta cotización');
            }

            if ($cotizacion['estado'] !== Cotizacion::ESTADO_ENVIADA) {
                throw new Exception('La cotización no puede ser aceptada en su estado actual');
            }

            // Verificar que la fecha aún es válida
            $fechaServicio = new DateTime($cotizacion['fecha_servicio']);
            $fechaActual = new DateTime();
            
            if ($fechaServicio <= $fechaActual) {
                throw new Exception('La fecha del servicio ya ha pasado. Solicita una nueva cotización');
            }

            $aceptada = $this->cotizacionModel->aceptarCotizacion($cotizacionId, $usuarioId);

            if ($aceptada) {
                // Obtener datos completos de la cotización
                $cotizacionCompleta = $this->obtenerCotizacionCompleta($cotizacionId);
                
                // Notificar aceptación
                $this->notificacionService->notificarCotizacionAceptada($cotizacionId, $cotizacionCompleta);

                return true;
            }

            throw new Exception('Error al aceptar la cotización');

        } catch (Exception $e) {
            error_log("Error en CotizacionService::procesarAceptacionCotizacion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Completar un servicio
     */
    public function completarServicio($cotizacionId, $observaciones = null, $horaInicio = null, $horaFin = null)
    {
        try {
            $cotizacion = $this->cotizacionModel->find($cotizacionId);
            if (!$cotizacion) {
                throw new Exception('Cotización no encontrada');
            }

            if ($cotizacion['estado'] !== Cotizacion::ESTADO_ACEPTADA) {
                throw new Exception('Solo se pueden completar servicios aceptados');
            }

            // Completar el servicio
            $completado = $this->cotizacionModel->completarServicio($cotizacionId, $observaciones);

            if ($completado) {
                // Actualizar horarios en el historial si se proporcionaron
                if ($horaInicio || $horaFin) {
                    $this->actualizarHorariosHistorial($cotizacionId, $horaInicio, $horaFin);
                }

                // Obtener datos completos
                $cotizacionCompleta = $this->obtenerCotizacionCompleta($cotizacionId);

                // Notificar completado
                $this->notificacionService->notificarServicioCompletado($cotizacionId, $cotizacionCompleta);

                // Verificar si necesita recordatorio de cambio de aceite
                $this->verificarRecordatorioAceite($cotizacion['vehiculo_id'], $cotizacion['usuario_id']);

                return true;
            }

            throw new Exception('Error al completar el servicio');

        } catch (Exception $e) {
            error_log("Error en CotizacionService::completarServicio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcular precio estimado de cotización
     */
    public function calcularPrecioEstimado($servicioId, $tipoUbicacion, $vehiculoId)
    {
        try {
            $servicio = $this->servicioModel->find($servicioId);
            if (!$servicio) {
                throw new Exception('Servicio no encontrado');
            }

            $vehiculo = $this->vehiculoModel->find($vehiculoId);
            if (!$vehiculo) {
                throw new Exception('Vehículo no encontrado');
            }

            return $this->servicioModel->calcularPrecio($servicioId, $tipoUbicacion, $vehiculo);

        } catch (Exception $e) {
            error_log("Error calculando precio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener cotizaciones pendientes con información adicional
     */
    public function getCotizacionesPendientesDetalladas()
    {
        try {
            $cotizaciones = $this->cotizacionModel->getPendientes();
            
            // Agregar información adicional a cada cotización
            foreach ($cotizaciones as &$cotizacion) {
                $cotizacion['tiempo_espera'] = $this->calcularTiempoEspera($cotizacion['fecha_creacion']);
                $cotizacion['prioridad'] = $this->calcularPrioridad($cotizacion);
            }

            // Ordenar por prioridad
            usort($cotizaciones, function($a, $b) {
                return $b['prioridad'] - $a['prioridad'];
            });

            return $cotizaciones;

        } catch (Exception $e) {
            error_log("Error obteniendo cotizaciones pendientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generar reporte de cotizaciones
     */
    public function generarReporte($fechaInicio, $fechaFin, $filtros = [])
    {
        try {
            $stats = $this->cotizacionModel->getStats($fechaInicio, $fechaFin);
            
            $reporte = [
                'periodo' => [
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin
                ],
                'estadisticas' => $stats,
                'tasa_conversion' => $this->calcularTasaConversion($stats),
                'tiempo_respuesta_promedio' => $this->calcularTiempoRespuestaPromedio($fechaInicio, $fechaFin),
                'servicios_populares' => $this->getServiciosPopulares($fechaInicio, $fechaFin)
            ];

            return $reporte;

        } catch (Exception $e) {
            error_log("Error generando reporte: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar y enviar recordatorios automáticos
     */
    public function procesarRecordatoriosAutomaticos()
    {
        try {
            // Recordatorios para servicios del día siguiente
            $recordatoriosDiarios = $this->notificacionService->programarRecordatoriosDiarios();

            // Recordatorios de cambio de aceite
            $recordatoriosAceite = $this->procesarRecordatoriosCambioAceite();

            return [
                'recordatorios_diarios' => $recordatoriosDiarios,
                'recordatorios_aceite' => $recordatoriosAceite
            ];

        } catch (Exception $e) {
            error_log("Error procesando recordatorios: " . $e->getMessage());
            return ['recordatorios_diarios' => 0, 'recordatorios_aceite' => 0];
        }
    }

    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    /**
     * Obtener cotización con información completa
     */
    private function obtenerCotizacionCompleta($cotizacionId)
    {
        $query = "SELECT c.*, u.nombre, u.apellido, s.nombre as servicio_nombre, v.marca, v.modelo, v.placa
                 FROM cotizaciones c
                 INNER JOIN usuarios u ON c.usuario_id = u.id
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 INNER JOIN vehiculos v ON c.vehiculo_id = v.id
                 WHERE c.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $cotizacionId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Notificar creación de cotización
     */
    private function notificarCotizacionCreada($cotizacionId, $usuarioId)
    {
        try {
            $mensaje = "Tu solicitud de cotización ha sido recibida. Nuestro equipo la revisará y te enviará una respuesta pronto.";
            
            $this->notificacionService->programarNotificacion(
                $usuarioId,
                'Cotización Recibida',
                $mensaje,
                'sistema',
                date('Y-m-d H:i:s'),
                $cotizacionId
            );
        } catch (Exception $e) {
            error_log("Error notificando cotización creada: " . $e->getMessage());
        }
    }

    /**
     * Calcular tiempo de espera en horas
     */
    private function calcularTiempoEspera($fechaCreacion)
    {
        $creacion = new DateTime($fechaCreacion);
        $ahora = new DateTime();
        $diff = $ahora->diff($creacion);
        
        return $diff->h + ($diff->days * 24);
    }

    /**
     * Calcular prioridad de cotización
     */
    private function calcularPrioridad($cotizacion)
    {
        $prioridad = 1;
        
        // Mayor prioridad por tiempo de espera
        $tiempoEspera = $this->calcularTiempoEspera($cotizacion['fecha_creacion']);
        if ($tiempoEspera > 24) $prioridad += 3;
        elseif ($tiempoEspera > 12) $prioridad += 2;
        elseif ($tiempoEspera > 6) $prioridad += 1;

        // Mayor prioridad para servicios a domicilio
        if ($cotizacion['tipo_ubicacion'] === 'domicilio') {
            $prioridad += 1;
        }

        // Mayor prioridad para clientes frecuentes
        if ($this->esClienteFrecuente($cotizacion['usuario_id'])) {
            $prioridad += 2;
        }

        return $prioridad;
    }

    /**
     * Verificar si es cliente frecuente
     */
    private function esClienteFrecuente($usuarioId)
    {
        $query = "SELECT COUNT(*) FROM historial_servicios WHERE usuario_id = :usuario_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
        
        return $stmt->fetchColumn() >= 3;
    }

    /**
     * Calcular tasa de conversión
     */
    private function calcularTasaConversion($stats)
    {
        if ($stats['total'] == 0) return 0;
        
        $conversiones = $stats['aceptadas'] + $stats['completadas'];
        return round(($conversiones / $stats['total']) * 100, 2);
    }

    /**
     * Calcular tiempo promedio de respuesta
     */
    private function calcularTiempoRespuestaPromedio($fechaInicio, $fechaFin)
    {
        $query = "SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_respuesta)) as promedio
                 FROM cotizaciones 
                 WHERE fecha_respuesta IS NOT NULL 
                 AND fecha_creacion BETWEEN :inicio AND :fin";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':inicio', $fechaInicio);
        $stmt->bindParam(':fin', $fechaFin);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return round($result['promedio'] ?? 0, 2);
    }

    /**
     * Obtener servicios más populares en un período
     */
    private function getServiciosPopulares($fechaInicio, $fechaFin)
    {
        $query = "SELECT s.nombre, COUNT(*) as total
                 FROM cotizaciones c
                 INNER JOIN servicios s ON c.servicio_id = s.id
                 WHERE c.fecha_creacion BETWEEN :inicio AND :fin
                 GROUP BY c.servicio_id, s.nombre
                 ORDER BY total DESC
                 LIMIT 5";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':inicio', $fechaInicio);
        $stmt->bindParam(':fin', $fechaFin);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Procesar recordatorios de cambio de aceite
     */
    private function procesarRecordatoriosCambioAceite()
    {
        require_once __DIR__ . '/../Models/Historial.php';
        $historialModel = new Historial($this->db);

        $query = "SELECT DISTINCT v.id, v.usuario_id, v.marca, v.modelo, v.placa
                 FROM vehiculos v
                 INNER JOIN usuarios u ON v.usuario_id = u.id
                 WHERE v.activo = 1 AND u.activo = 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $recordatorios = 0;
        foreach ($vehiculos as $vehiculo) {
            $verificacion = $historialModel->verificarCambioAceite($vehiculo['id']);
            
            if ($verificacion['necesita_cambio']) {
                $this->notificacionService->recordarCambioAceite(
                    $vehiculo['usuario_id'],
                    $vehiculo,
                    $verificacion['dias_transcurridos'] ?? 0,
                    $verificacion['km_recorridos'] ?? null
                );
                $recordatorios++;
            }
        }

        return $recordatorios;
    }

    /**
     * Verificar si el vehículo necesita recordatorio de aceite
     */
    private function verificarRecordatorioAceite($vehiculoId, $usuarioId)
    {
        try {
            require_once __DIR__ . '/../Models/Historial.php';
            $historialModel = new Historial($this->db);

            $verificacion = $historialModel->verificarCambioAceite($vehiculoId);
            
            if ($verificacion['necesita_cambio']) {
                $vehiculo = $this->vehiculoModel->find($vehiculoId);
                
                $this->notificacionService->recordarCambioAceite(
                    $usuarioId,
                    $vehiculo,
                    $verificacion['dias_transcurridos'] ?? 0,
                    $verificacion['km_recorridos'] ?? null
                );
            }
        } catch (Exception $e) {
            error_log("Error verificando recordatorio de aceite: " . $e->getMessage());
        }
    }

    /**
     * Actualizar horarios en el historial
     */
    private function actualizarHorariosHistorial($cotizacionId, $horaInicio, $horaFin)
    {
        try {
            require_once __DIR__ . '/../Models/Historial.php';
            $historialModel = new Historial($this->db);

            $query = "UPDATE historial_servicios SET ";
            $updates = [];
            $params = [':cotizacion_id' => $cotizacionId];

            if ($horaInicio) {
                $updates[] = "hora_inicio = :hora_inicio";
                $params[':hora_inicio'] = $horaInicio;
            }

            if ($horaFin) {
                $updates[] = "hora_fin = :hora_fin";
                $params[':hora_fin'] = $horaFin;
            }

            if (!empty($updates)) {
                $query .= implode(', ', $updates) . " WHERE cotizacion_id = :cotizacion_id";
                
                $stmt = $this->db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error actualizando horarios: " . $e->getMessage());
        }
    }
}
