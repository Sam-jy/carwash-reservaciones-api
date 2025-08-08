<?php

require_once __DIR__ . '/BaseRequest.php';

/**
 * Validación para la creación de cotizaciones
 */
class CotizacionRequest extends BaseRequest
{
    protected function defineRules()
    {
        $this->rules = [
            'vehiculo_id' => 'required|integer|exists:vehiculos,id',
            'servicio_id' => 'required|integer|exists:servicios,id',
            'tipo_ubicacion' => 'required|in:centro,domicilio',
            'fecha_servicio' => 'required|date',
            'hora_servicio' => 'required',
            'direccion_servicio' => 'max:500',
            'latitud' => 'numeric',
            'longitud' => 'numeric',
            'notas_cliente' => 'max:1000'
        ];
    }

    protected function defineMessages()
    {
        parent::defineMessages();
        
        $this->messages = array_merge($this->messages, [
            'vehiculo_id.required' => 'Debe seleccionar un vehículo',
            'vehiculo_id.exists' => 'El vehículo seleccionado no es válido',
            'servicio_id.required' => 'Debe seleccionar un servicio',
            'servicio_id.exists' => 'El servicio seleccionado no es válido',
            'tipo_ubicacion.required' => 'Debe especificar el tipo de ubicación',
            'tipo_ubicacion.in' => 'El tipo de ubicación debe ser centro o domicilio',
            'fecha_servicio.required' => 'La fecha del servicio es requerida',
            'fecha_servicio.date' => 'La fecha del servicio debe ser válida',
            'hora_servicio.required' => 'La hora del servicio es requerida'
        ]);
    }

    /**
     * Validaciones adicionales específicas
     */
    public function validate()
    {
        $isValid = parent::validate();

        // Validar fecha futura
        $fechaServicio = $this->get('fecha_servicio');
        if ($fechaServicio && !$this->isFutureDate($fechaServicio)) {
            $this->addError('fecha_servicio', 'La fecha del servicio debe ser futura');
            $isValid = false;
        }

        // Validar hora
        $horaServicio = $this->get('hora_servicio');
        if ($horaServicio && !$this->isValidTime($horaServicio)) {
            $this->addError('hora_servicio', 'La hora del servicio no es válida');
            $isValid = false;
        }

        // Validar horario de trabajo
        if ($horaServicio && !$this->isWorkingTime($horaServicio)) {
            $this->addError('hora_servicio', 'El horario debe estar entre 7:00 AM y 6:00 PM');
            $isValid = false;
        }

        // Validar dirección para servicio a domicilio
        $tipoUbicacion = $this->get('tipo_ubicacion');
        $direccion = $this->get('direccion_servicio');
        
        if ($tipoUbicacion === 'domicilio' && empty($direccion)) {
            $this->addError('direccion_servicio', 'La dirección es requerida para servicios a domicilio');
            $isValid = false;
        }

        // Validar que el servicio esté disponible para la ubicación
        if ($tipoUbicacion && $this->get('servicio_id')) {
            if (!$this->isServiceAvailable($this->get('servicio_id'), $tipoUbicacion)) {
                $this->addError('servicio_id', 'El servicio no está disponible para la ubicación seleccionada');
                $isValid = false;
            }
        }

        // Validar que el vehículo pertenezca al usuario (se validará en el controlador)
        
        // Validar coordenadas para domicilio
        if ($tipoUbicacion === 'domicilio') {
            $latitud = $this->get('latitud');
            $longitud = $this->get('longitud');
            
            if ($latitud && !$this->isValidLatitude($latitud)) {
                $this->addError('latitud', 'La latitud no es válida');
                $isValid = false;
            }
            
            if ($longitud && !$this->isValidLongitude($longitud)) {
                $this->addError('longitud', 'La longitud no es válida');
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Verificar si la fecha es futura
     */
    private function isFutureDate($date)
    {
        $fecha = DateTime::createFromFormat('Y-m-d', $date);
        $hoy = new DateTime('today');
        
        return $fecha && $fecha >= $hoy;
    }

    /**
     * Validar formato de hora
     */
    private function isValidTime($time)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    /**
     * Verificar si está en horario de trabajo
     */
    private function isWorkingTime($time)
    {
        $hora = DateTime::createFromFormat('H:i', $time);
        if (!$hora) return false;
        
        $inicio = DateTime::createFromFormat('H:i', '07:00');
        $fin = DateTime::createFromFormat('H:i', '18:00');
        
        return $hora >= $inicio && $hora <= $fin;
    }

    /**
     * Verificar si el servicio está disponible para la ubicación
     */
    private function isServiceAvailable($servicioId, $tipoUbicacion)
    {
        try {
            require_once __DIR__ . '/../../../database.php';
            $database = new Database();
            $db = $database->getConnection();

            $campo = $tipoUbicacion === 'domicilio' ? 'disponible_domicilio' : 'disponible_centro';
            
            $query = "SELECT {$campo} FROM servicios WHERE id = :id AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $servicioId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result[$campo] == 1;
        } catch (Exception $e) {
            return true; // En caso de error, permitir
        }
    }

    /**
     * Validar latitud
     */
    private function isValidLatitude($lat)
    {
        return is_numeric($lat) && $lat >= -90 && $lat <= 90;
    }

    /**
     * Validar longitud
     */
    private function isValidLongitude($lng)
    {
        return is_numeric($lng) && $lng >= -180 && $lng <= 180;
    }

    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
