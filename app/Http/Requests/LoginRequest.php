<?php

require_once __DIR__ . '/BaseRequest.php';

/**
 * Validación para el login de usuarios
 */
class LoginRequest extends BaseRequest
{
    protected function defineRules()
    {
        $this->rules = [
            'email' => 'required|email',
            'password' => 'required|min:1'
        ];
    }

    protected function defineMessages()
    {
        parent::defineMessages();
        
        $this->messages = array_merge($this->messages, [
            'email.required' => 'El email es requerido',
            'email.email' => 'Debe proporcionar un email válido',
            'password.required' => 'La contraseña es requerida'
        ]);
    }

    /**
     * Validaciones adicionales
     */
    public function validate()
    {
        $isValid = parent::validate();

        // Validación adicional: verificar que el email existe en la BD
        $email = $this->get('email');
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (!$this->emailExists($email)) {
                $this->addError('email', 'No existe una cuenta con este email');
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Verificar si el email existe en la base de datos
     */
    private function emailExists($email)
    {
        try {
            require_once __DIR__ . '/../../../database.php';
            $database = new Database();
            $db = $database->getConnection();

            $query = "SELECT COUNT(*) FROM usuarios WHERE email = :email AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            // En caso de error, permitir que continúe la validación
            return true;
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
