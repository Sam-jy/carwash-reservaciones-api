<?php

require_once __DIR__ . '/BaseRequest.php';

/**
 * Validación para el registro de usuarios
 */
class RegistroRequest extends BaseRequest
{
    protected function defineRules()
    {
        $this->rules = [
            'nombre' => 'required|min:2|max:100',
            'apellido' => 'required|min:2|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'telefono' => 'required|min:8|max:20',
            'password' => 'required|min:6',
            'pais' => 'max:50'
        ];
    }

    protected function defineMessages()
    {
        parent::defineMessages();
        
        $this->messages = array_merge($this->messages, [
            'nombre.required' => 'El nombre es requerido',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'apellido.required' => 'El apellido es requerido',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres',
            'email.unique' => 'Este email ya está registrado',
            'telefono.min' => 'El teléfono debe tener al menos 8 dígitos',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres'
        ]);
    }

    /**
     * Validaciones adicionales específicas
     */
    public function validate()
    {
        $isValid = parent::validate();

        // Validación personalizada del teléfono
        $telefono = $this->get('telefono');
        if ($telefono && !$this->isValidPhone($telefono)) {
            $this->addError('telefono', 'El formato del teléfono no es válido');
            $isValid = false;
        }

        // Validar confirmación de contraseña si existe
        $password = $this->get('password');
        $passwordConfirm = $this->get('password_confirmation');
        
        if ($passwordConfirm && $password !== $passwordConfirm) {
            $this->addError('password_confirmation', 'Las contraseñas no coinciden');
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Validar formato de teléfono hondureño
     */
    private function isValidPhone($phone)
    {
        // Eliminar espacios, guiones y paréntesis
        $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // Patrones para Honduras
        $patterns = [
            '/^504[0-9]{8}$/',     // Con código de país
            '/^[0-9]{8}$/',        // Sin código de país
            '/^\+504[0-9]{8}$/'    // Con + y código de país
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanPhone)) {
                return true;
            }
        }

        return false;
    }

    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
