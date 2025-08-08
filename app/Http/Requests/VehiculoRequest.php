<?php

require_once __DIR__ . '/BaseRequest.php';

/**
 * Validación para la gestión de vehículos
 */
class VehiculoRequest extends BaseRequest
{
    protected function defineRules()
    {
        $this->rules = [
            'marca' => 'required|min:2|max:50',
            'modelo' => 'required|min:2|max:50',
            'anio' => 'required|integer|min:1900',
            'placa' => 'required|min:6|max:20',
            'tipo_aceite' => 'max:50',
            'color' => 'max:30'
        ];
    }

    protected function defineMessages()
    {
        parent::defineMessages();
        
        $this->messages = array_merge($this->messages, [
            'marca.required' => 'La marca del vehículo es requerida',
            'marca.min' => 'La marca debe tener al menos 2 caracteres',
            'modelo.required' => 'El modelo del vehículo es requerido',
            'modelo.min' => 'El modelo debe tener al menos 2 caracteres',
            'anio.required' => 'El año del vehículo es requerido',
            'anio.integer' => 'El año debe ser un número válido',
            'anio.min' => 'El año no puede ser anterior a 1900',
            'placa.required' => 'La placa del vehículo es requerida',
            'placa.min' => 'La placa debe tener al menos 6 caracteres'
        ]);
    }

    /**
     * Validaciones adicionales específicas
     */
    public function validate()
    {
        $isValid = parent::validate();

        // Validar año máximo (año actual + 1)
        $anio = $this->get('anio');
        $maxYear = date('Y') + 1;
        
        if ($anio && $anio > $maxYear) {
            $this->addError('anio', 'El año no puede ser superior a ' . $maxYear);
            $isValid = false;
        }

        // Validar formato de placa hondureña
        $placa = $this->get('placa');
        if ($placa && !$this->isValidPlate($placa)) {
            $this->addError('placa', 'El formato de la placa no es válido para Honduras');
            $isValid = false;
        }

        // Validar que la marca y modelo sean realistas
        $marca = $this->get('marca');
        $modelo = $this->get('modelo');
        
        if ($marca && !$this->isValidMarca($marca)) {
            $this->addError('marca', 'La marca ingresada no parece válida');
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Validar formato de placa hondureña
     */
    private function isValidPlate($placa)
    {
        // Eliminar espacios y guiones
        $cleanPlate = preg_replace('/[\s\-]/', '', strtoupper($placa));
        
        // Patrones para placas hondureñas
        $patterns = [
            '/^[A-Z]{3}[0-9]{4}$/',     // Formato ABC1234
            '/^[A-Z]{2}[0-9]{5}$/',     // Formato AB12345
            '/^[0-9]{3}[A-Z]{3}$/',     // Formato 123ABC
            '/^[A-Z]{1}[0-9]{6}$/',     // Formato A123456
            '/^[A-Z]{4}[0-9]{3}$/'      // Formato ABCD123
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanPlate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validar marcas conocidas de vehículos
     */
    private function isValidMarca($marca)
    {
        $marcasConocidas = [
            'toyota', 'honda', 'nissan', 'mazda', 'ford', 'chevrolet', 'hyundai',
            'kia', 'volkswagen', 'bmw', 'mercedes', 'audi', 'lexus', 'infiniti',
            'acura', 'subaru', 'mitsubishi', 'suzuki', 'isuzu', 'jeep', 'dodge',
            'chrysler', 'buick', 'cadillac', 'gmc', 'lincoln', 'volvo', 'saab',
            'peugeot', 'renault', 'citroen', 'fiat', 'alfa romeo', 'seat',
            'skoda', 'dacia', 'lada', 'geely', 'chery', 'byd', 'great wall',
            'haval', 'mg', 'mini', 'smart', 'tesla', 'rivian', 'lucid'
        ];

        $marcaLower = strtolower(trim($marca));
        
        // Verificar coincidencia exacta
        if (in_array($marcaLower, $marcasConocidas)) {
            return true;
        }

        // Verificar coincidencia parcial (para casos como "TOYOTA HILUX" -> "toyota")
        foreach ($marcasConocidas as $marcaConocida) {
            if (strpos($marcaLower, $marcaConocida) !== false || 
                strpos($marcaConocida, $marcaLower) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validar unicidad de placa para el usuario
     */
    public function validateUniqueForUser($usuarioId, $vehiculoId = null)
    {
        $placa = $this->get('placa');
        if (!$placa) return true;

        try {
            require_once __DIR__ . '/../../../database.php';
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT COUNT(*) FROM vehiculos 
                     WHERE placa = :placa AND usuario_id = :usuario_id AND activo = 1";
            
            $params = [':placa' => $placa, ':usuario_id' => $usuarioId];
            
            // Si es una actualización, excluir el vehículo actual
            if ($vehiculoId) {
                $query .= " AND id != :vehiculo_id";
                $params[':vehiculo_id'] = $vehiculoId;
            }

            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $this->addError('placa', 'Ya tienes un vehículo registrado con esta placa');
                return false;
            }

            return true;
        } catch (Exception $e) {
            return true; // En caso de error, permitir
        }
    }

    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
