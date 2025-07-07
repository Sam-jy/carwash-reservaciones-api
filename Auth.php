<?php

class Auth 
{
    private $secretKey = "clave_secreta_proyecto_crud_php";
    private $tokenExpiration = 3600; // 1 hora en segundos
    
    public function generateToken($user_id) 
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->tokenExpiration;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user_id' => $user_id
        ];
        
        $token = base64_encode(json_encode($payload)) . "." . $this->generateSignature($payload);
        
        return $token;
    }
    
    private function generateSignature($payload) 
    {
        return hash_hmac('sha256', base64_encode(json_encode($payload)), $this->secretKey);
    }
    
    public function validateToken() 
    {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return false;
        }
        
        $authHeader = $headers['Authorization'];
        
        if (strpos($authHeader, 'Bearer ') !== 0) {
            return false;
        }
        
        $token = substr($authHeader, 7);
        
        $parts = explode('.', $token);
        if (count($parts) != 2) {
            return false;
        }
        
        $payload = json_decode(base64_decode($parts[0]), true);
        $signature = $parts[1];
        
        if ($payload['exp'] < time()) {
            return false;
        }
        
        $expectedSignature = $this->generateSignature($payload);
        
        if ($signature !== $expectedSignature) {
            return false;
        }
        
        return $payload;
    }
}

?> 