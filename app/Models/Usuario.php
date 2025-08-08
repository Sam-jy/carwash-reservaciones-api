<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Usuario
 * Maneja usuarios (clientes y administradores)
 */
class Usuario extends BaseModel
{
    protected $table = 'usuarios';
    protected $fillable = [
        'nombre', 'apellido', 'email', 'telefono', 'password', 
        'pais', 'foto_perfil', 'tipo_usuario', 'email_verificado',
        'codigo_verificacion', 'token_recuperacion', 'activo'
    ];
    protected $hidden = ['password', 'codigo_verificacion', 'token_recuperacion'];

    /**
     * Buscar usuario por email
     */
    public function findByEmail($email)
    {
        $query = "SELECT * FROM {$this->table} WHERE email = :email AND activo = 1 LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nuevo usuario con validaciones
     */
    public function createUser($data)
    {
        // Verificar si el email ya existe
        if ($this->findByEmail($data['email'])) {
            throw new Exception('El email ya está registrado');
        }

        // Hash de la contraseña
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Generar código de verificación
        $data['codigo_verificacion'] = $this->generateVerificationCode();
        $data['email_verificado'] = false;

        return $this->create($data);
    }

    /**
     * Verificar credenciales de login
     */
    public function verifyCredentials($email, $password)
    {
        $user = $this->findByEmail($email);
        
        if ($user && password_verify($password, $user['password'])) {
            return $this->hideAttributes($user);
        }
        
        return false;
    }

    /**
     * Verificar código de verificación de email
     */
    public function verifyEmail($email, $codigo)
    {
        $query = "UPDATE {$this->table} SET email_verificado = 1, codigo_verificacion = NULL 
                 WHERE email = :email AND codigo_verificacion = :codigo";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':codigo', $codigo);
        
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Generar token para recuperación de contraseña
     */
    public function generatePasswordResetToken($email)
    {
        $token = bin2hex(random_bytes(32));
        
        $query = "UPDATE {$this->table} SET token_recuperacion = :token 
                 WHERE email = :email AND activo = 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return $token;
        }
        
        return false;
    }

    /**
     * Restablecer contraseña con token
     */
    public function resetPassword($token, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE {$this->table} SET password = :password, token_recuperacion = NULL 
                 WHERE token_recuperacion = :token";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':token', $token);
        
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Actualizar perfil de usuario
     */
    public function updateProfile($id, $data)
    {
        // No permitir actualización de campos sensibles
        unset($data['password'], $data['email_verificado'], $data['tipo_usuario']);
        
        return $this->update($id, $data);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword($id, $currentPassword, $newPassword)
    {
        $user = $this->query("SELECT password FROM {$this->table} WHERE id = :id", [':id' => $id])->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->update($id, ['password' => $hashedPassword]);
    }

    /**
     * Obtener usuarios por tipo
     */
    public function getUsersByType($tipo)
    {
        return $this->all(['tipo_usuario' => $tipo, 'activo' => 1], 'fecha_creacion DESC');
    }

    /**
     * Generar código de verificación de 6 dígitos
     */
    private function generateVerificationCode()
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Reenviar código de verificación
     */
    public function resendVerificationCode($email)
    {
        $codigo = $this->generateVerificationCode();
        
        $query = "UPDATE {$this->table} SET codigo_verificacion = :codigo 
                 WHERE email = :email AND email_verificado = 0";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            return $codigo;
        }
        
        return false;
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getStats()
    {
        $query = "SELECT 
                    tipo_usuario,
                    COUNT(*) as total,
                    SUM(CASE WHEN email_verificado = 1 THEN 1 ELSE 0 END) as verificados
                  FROM {$this->table} 
                  WHERE activo = 1 
                  GROUP BY tipo_usuario";
        
        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
