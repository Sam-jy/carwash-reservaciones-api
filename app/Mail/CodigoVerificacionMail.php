<?php

/**
 * Clase para envío de códigos de verificación por email
 */
class CodigoVerificacionMail
{
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/mail.php';
    }

    /**
     * Enviar código de verificación
     */
    public function enviar($email, $codigo, $nombre = null)
    {
        try {
            $asunto = "Verificación de cuenta - Car Wash El Catracho";
            $mensaje = $this->generarMensaje($codigo, $nombre);

            return $this->enviarEmail($email, $asunto, $mensaje);
        } catch (Exception $e) {
            error_log("Error enviando código de verificación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generar mensaje HTML del código de verificación
     */
    private function generarMensaje($codigo, $nombre = null)
    {
        $saludo = $nombre ? "Hola {$nombre}," : "Hola,";
        
        return "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8fafc; }
                .code { background-color: #3b82f6; color: white; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; border-radius: 5px; margin: 20px 0; letter-spacing: 3px; }
                .footer { background-color: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Car Wash El Catracho</h1>
                    <p>Verificación de Cuenta</p>
                </div>
                
                <div class='content'>
                    <p>{$saludo}</p>
                    
                    <p>Gracias por registrarte en Car Wash El Catracho. Para completar tu registro, por favor ingresa el siguiente código de verificación en la aplicación:</p>
                    
                    <div class='code'>{$codigo}</div>
                    
                    <p><strong>Importante:</strong></p>
                    <ul>
                        <li>Este código es válido por 24 horas</li>
                        <li>No compartas este código con nadie</li>
                        <li>Si no solicitaste este código, ignora este mensaje</li>
                    </ul>
                    
                    <p>Una vez verificada tu cuenta, podrás:</p>
                    <ul>
                        <li>Registrar tus vehículos</li>
                        <li>Solicitar cotizaciones de servicios</li>
                        <li>Programar citas de lavado</li>
                        <li>Ver tu historial de servicios</li>
                    </ul>
                    
                    <p>¡Esperamos poder atender tus vehículos pronto!</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; 2025 Car Wash El Catracho - Todos los derechos reservados</p>
                    <p>Si tienes problemas, contáctanos al: 504-0000-0000</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Enviar email usando configuración SMTP
     */
    private function enviarEmail($destinatario, $asunto, $mensaje)
    {
        // Headers para email HTML
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['email'] . '>',
            'Reply-To: ' . $this->config['from']['email'],
            'X-Mailer: PHP/' . phpversion()
        ];

        // Intentar enviar con mail() por ahora
        // En producción se debería usar una librería como PHPMailer o SwiftMailer
        $enviado = mail($destinatario, $asunto, $mensaje, implode("\r\n", $headers));

        if ($enviado) {
            error_log("Código de verificación enviado a: {$destinatario}");
        } else {
            error_log("Error enviando email a: {$destinatario}");
        }

        return $enviado;
    }

    /**
     * Enviar con PHPMailer (si está disponible)
     */
    private function enviarConPHPMailer($destinatario, $asunto, $mensaje)
    {
        // Esta función se puede implementar si se instala PHPMailer
        // require_once 'PHPMailer/PHPMailer.php';
        // require_once 'PHPMailer/SMTP.php';
        
        // $mail = new PHPMailer\PHPMailer\PHPMailer();
        // $mail->isSMTP();
        // $mail->Host = $this->config['smtp']['host'];
        // $mail->SMTPAuth = true;
        // $mail->Username = $this->config['smtp']['username'];
        // $mail->Password = $this->config['smtp']['password'];
        // $mail->SMTPSecure = $this->config['smtp']['encryption'];
        // $mail->Port = $this->config['smtp']['port'];
        
        // $mail->setFrom($this->config['from']['email'], $this->config['from']['name']);
        // $mail->addAddress($destinatario);
        // $mail->isHTML(true);
        // $mail->Subject = $asunto;
        // $mail->Body = $mensaje;
        
        // return $mail->send();
        
        return false;
    }
}
