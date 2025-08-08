<?php

/**
 * Clase para envío de notificaciones por email
 */
class NotificacionMail
{
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/mail.php';
    }

    /**
     * Enviar notificación por email
     */
    public function enviar($email, $nombre, $titulo, $mensaje, $tipo = 'sistema')
    {
        try {
            $asunto = $this->generarAsunto($titulo, $tipo);
            $contenido = $this->generarMensaje($nombre, $titulo, $mensaje, $tipo);

            return $this->enviarEmail($email, $asunto, $contenido);
        } catch (Exception $e) {
            error_log("Error enviando notificación por email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generar asunto del email según el tipo
     */
    private function generarAsunto($titulo, $tipo)
    {
        $prefix = '';
        
        switch ($tipo) {
            case 'cotizacion':
                $prefix = '[Cotización] ';
                break;
            case 'recordatorio':
                $prefix = '[Recordatorio] ';
                break;
            case 'promocion':
                $prefix = '[Oferta] ';
                break;
            default:
                $prefix = '[Notificación] ';
        }

        return $prefix . $titulo . ' - Car Wash El Catracho';
    }

    /**
     * Generar mensaje HTML según el tipo de notificación
     */
    private function generarMensaje($nombre, $titulo, $mensaje, $tipo)
    {
        $color = $this->getColorPorTipo($tipo);
        $icono = $this->getIconoPorTipo($tipo);
        
        return "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .header { background-color: {$color}; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .message-box { background-color: #f8fafc; border-left: 4px solid {$color}; padding: 20px; margin: 20px 0; }
                .cta-button { display: inline-block; background-color: {$color}; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
                .icon { font-size: 48px; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='icon'>{$icono}</div>
                    <h1>Car Wash El Catracho</h1>
                    <p>{$titulo}</p>
                </div>
                
                <div class='content'>
                    <p>Hola {$nombre},</p>
                    
                    <div class='message-box'>
                        {$mensaje}
                    </div>
                    
                    {$this->getAccionesPorTipo($tipo)}
                    
                    <p>Gracias por confiar en Car Wash El Catracho.</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>Equipo Car Wash El Catracho</strong></p>
                </div>
                
                <div class='footer'>
                    <p><strong>Car Wash El Catracho</strong></p>
                    <p>📞 Teléfono: 504-0000-0000</p>
                    <p>📧 Email: info@carwashelcatracho.com</p>
                    <p>📍 Dirección: [Tu dirección aquí]</p>
                    <hr style='margin: 15px 0; border: none; border-top: 1px solid #d1d5db;'>
                    <p>&copy; 2025 Car Wash El Catracho - Todos los derechos reservados</p>
                    <p style='font-size: 10px; color: #9ca3af;'>
                        Si no deseas recibir estas notificaciones, puedes desactivarlas en la configuración de tu cuenta.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Obtener color según el tipo de notificación
     */
    private function getColorPorTipo($tipo)
    {
        switch ($tipo) {
            case 'cotizacion':
                return '#3b82f6'; // Azul
            case 'recordatorio':
                return '#f59e0b'; // Amarillo/Naranja
            case 'promocion':
                return '#10b981'; // Verde
            case 'sistema':
            default:
                return '#6366f1'; // Índigo
        }
    }

    /**
     * Obtener icono según el tipo de notificación
     */
    private function getIconoPorTipo($tipo)
    {
        switch ($tipo) {
            case 'cotizacion':
                return '💰';
            case 'recordatorio':
                return '⏰';
            case 'promocion':
                return '🎉';
            case 'sistema':
            default:
                return '🔔';
        }
    }

    /**
     * Obtener acciones recomendadas por tipo
     */
    private function getAccionesPorTipo($tipo)
    {
        switch ($tipo) {
            case 'cotizacion':
                return "
                <p><strong>¿Qué sigue?</strong></p>
                <ul>
                    <li>Revisa los detalles en la aplicación</li>
                    <li>Acepta o rechaza la cotización</li>
                    <li>Programa tu cita si aceptas</li>
                </ul>
                <a href='#' class='cta-button'>Ver Cotización</a>";
                
            case 'recordatorio':
                return "
                <p><strong>¡No olvides!</strong></p>
                <ul>
                    <li>Mantén tu vehículo en perfecto estado</li>
                    <li>Programa tu cita con anticipación</li>
                    <li>Aprovecha nuestros descuentos</li>
                </ul>
                <a href='#' class='cta-button'>Programar Cita</a>";
                
            case 'promocion':
                return "
                <p><strong>¡Aprovecha esta oferta!</strong></p>
                <ul>
                    <li>Oferta válida por tiempo limitado</li>
                    <li>No requiere código promocional</li>
                    <li>Aplicable a todos nuestros servicios</li>
                </ul>
                <a href='#' class='cta-button'>Solicitar Servicio</a>";
                
            default:
                return "
                <a href='#' class='cta-button'>Abrir Aplicación</a>";
        }
    }

    /**
     * Enviar email
     */
    private function enviarEmail($destinatario, $asunto, $mensaje)
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['email'] . '>',
            'Reply-To: ' . $this->config['from']['email'],
            'X-Mailer: PHP/' . phpversion()
        ];

        $enviado = mail($destinatario, $asunto, $mensaje, implode("\r\n", $headers));

        if ($enviado) {
            error_log("Notificación enviada por email a: {$destinatario}");
        } else {
            error_log("Error enviando notificación por email a: {$destinatario}");
        }

        return $enviado;
    }
}
