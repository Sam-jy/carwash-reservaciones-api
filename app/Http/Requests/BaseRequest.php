<?php

/**
 * Clase base para validación de requests
 * Proporciona funcionalidad común para validar datos de entrada
 */
abstract class BaseRequest
{
    protected $data;
    protected $errors = [];
    protected $rules = [];
    protected $messages = [];

    public function __construct($data = null)
    {
        $this->data = $data ?? json_decode(file_get_contents("php://input"), true);
        $this->defineRules();
        $this->defineMessages();
    }

    /**
     * Definir reglas de validación (debe ser implementado por cada request)
     */
    abstract protected function defineRules();

    /**
     * Definir mensajes personalizados (opcional)
     */
    protected function defineMessages()
    {
        $this->messages = [
            'required' => 'El campo :field es requerido',
            'email' => 'El campo :field debe ser un email válido',
            'min' => 'El campo :field debe tener al menos :value caracteres',
            'max' => 'El campo :field no puede tener más de :value caracteres',
            'numeric' => 'El campo :field debe ser numérico',
            'integer' => 'El campo :field debe ser un número entero',
            'in' => 'El campo :field debe ser uno de: :values',
            'date' => 'El campo :field debe ser una fecha válida',
            'boolean' => 'El campo :field debe ser verdadero o falso',
            'unique' => 'El valor del campo :field ya existe',
            'exists' => 'El valor del campo :field no existe'
        ];
    }

    /**
     * Validar los datos según las reglas definidas
     */
    public function validate()
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->getValue($field);
            $rules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($rules as $rule) {
                $this->validateRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Obtener errores de validación
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Obtener datos validados
     */
    public function getValidatedData()
    {
        $validated = [];
        foreach ($this->rules as $field => $rules) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }

    /**
     * Obtener valor de un campo
     */
    private function getValue($field)
    {
        return isset($this->data[$field]) ? $this->data[$field] : null;
    }

    /**
     * Validar una regla específica
     */
    private function validateRule($field, $value, $rule)
    {
        if (strpos($rule, ':') !== false) {
            list($ruleName, $parameter) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, 'required');
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'email');
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$parameter) {
                    $this->addError($field, 'min', ['value' => $parameter]);
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$parameter) {
                    $this->addError($field, 'max', ['value' => $parameter]);
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'numeric');
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'integer');
                }
                break;

            case 'in':
                $values = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $values)) {
                    $this->addError($field, 'in', ['values' => implode(', ', $values)]);
                }
                break;

            case 'date':
                if (!empty($value)) {
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $this->addError($field, 'date');
                    }
                }
                break;

            case 'datetime':
                if (!empty($value)) {
                    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    if (!$datetime || $datetime->format('Y-m-d H:i:s') !== $value) {
                        $this->addError($field, 'date');
                    }
                }
                break;

            case 'boolean':
                if (!empty($value) && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    $this->addError($field, 'boolean');
                }
                break;

            case 'unique':
                if (!empty($value)) {
                    $this->validateUnique($field, $value, $parameter);
                }
                break;

            case 'exists':
                if (!empty($value)) {
                    $this->validateExists($field, $value, $parameter);
                }
                break;
        }
    }

    /**
     * Agregar error de validación
     */
    private function addError($field, $rule, $parameters = [])
    {
        $message = $this->messages[$rule] ?? "El campo {$field} no es válido";
        $message = str_replace(':field', $field, $message);
        
        foreach ($parameters as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Validar unicidad en base de datos
     */
    private function validateUnique($field, $value, $parameter)
    {
        // Formato: table,column o solo table (asume column = field)
        $parts = explode(',', $parameter);
        $table = $parts[0];
        $column = $parts[1] ?? $field;

        try {
            require_once __DIR__ . '/../../../database.php';
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $this->addError($field, 'unique');
            }
        } catch (Exception $e) {
            // En caso de error de BD, no validar
        }
    }

    /**
     * Validar existencia en base de datos
     */
    private function validateExists($field, $value, $parameter)
    {
        // Formato: table,column o solo table (asume column = id)
        $parts = explode(',', $parameter);
        $table = $parts[0];
        $column = $parts[1] ?? 'id';

        try {
            require_once __DIR__ . '/../../../database.php';
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            if ($count == 0) {
                $this->addError($field, 'exists');
            }
        } catch (Exception $e) {
            // En caso de error de BD, no validar
        }
    }

    /**
     * Verificar si los datos son válidos y lanzar excepción si no lo son
     */
    public function validateOrFail()
    {
        if (!$this->validate()) {
            $errors = [];
            foreach ($this->errors as $field => $fieldErrors) {
                $errors[] = implode(', ', $fieldErrors);
            }
            throw new Exception('Errores de validación: ' . implode('; ', $errors));
        }
        
        return $this->getValidatedData();
    }

    /**
     * Obtener un campo específico
     */
    public function get($field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }

    /**
     * Verificar si existe un campo
     */
    public function has($field)
    {
        return isset($this->data[$field]);
    }

    /**
     * Obtener todos los datos
     */
    public function all()
    {
        return $this->data;
    }
}
