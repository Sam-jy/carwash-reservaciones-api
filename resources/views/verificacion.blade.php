<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email - Car Wash El Catracho</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .logo {
            background: linear-gradient(45deg, #3b82f6, #1d4ed8);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        h2 {
            color: #3b82f6;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .message {
            background-color: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .success {
            background-color: #f0fdf4;
            border-color: #22c55e;
            color: #166534;
        }

        .error {
            background-color: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .verification-form {
            margin: 2rem 0;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus, input[type="email"]:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 3px;
        }

        .btn {
            background: linear-gradient(45deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .instructions {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

        .instructions ul {
            text-align: left;
            margin-left: 1rem;
        }

        .instructions li {
            margin-bottom: 0.5rem;
        }

        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.8rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚗</div>
        
        <h1>Car Wash El Catracho</h1>
        <h2>Verificación de Email</h2>

        <!-- Mensaje de estado -->
        <div id="message" class="message" style="display: none;"></div>

        <!-- Formulario de verificación -->
        <div class="verification-form">
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" placeholder="tu@email.com" required>
            </div>

            <div class="input-group">
                <label for="codigo">Código de Verificación:</label>
                <input type="text" id="codigo" class="code-input" placeholder="000000" maxlength="6" required>
            </div>

            <button type="button" class="btn" onclick="verificarCodigo()">
                Verificar Cuenta
            </button>

            <button type="button" class="btn btn-secondary" onclick="reenviarCodigo()">
                Reenviar Código
            </button>
        </div>

        <!-- Instrucciones -->
        <div class="instructions">
            <strong>Instrucciones:</strong>
            <ul>
                <li>Revisa tu bandeja de entrada y spam</li>
                <li>El código tiene 6 dígitos numéricos</li>
                <li>Es válido por 24 horas</li>
                <li>Si no lo recibes, puedes solicitar uno nuevo</li>
            </ul>
        </div>

        <div class="footer">
            <p>&copy; 2025 Car Wash El Catracho - Todos los derechos reservados</p>
            <p>¿Problemas? Contáctanos: 504-0000-0000</p>
        </div>
    </div>

    <script>
        const API_BASE = '/carwash-reservaciones-api/routes/api.php';

        document.getElementById('email').focus();

        document.getElementById('codigo').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
        });

        async function verificarCodigo() {
            const email = document.getElementById('email').value.trim();
            const codigo = document.getElementById('codigo').value.trim();

            if (!email || !codigo) {
                mostrarMensaje('Por favor completa todos los campos', 'error');
                return;
            }

            if (codigo.length !== 6) {
                mostrarMensaje('El código debe tener 6 dígitos', 'error');
                return;
            }

            try {
                mostrarMensaje('Verificando código...', 'info');

                const response = await fetch(API_BASE + '/api/cliente/verificar-email', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        codigo: codigo
                    })
                });

                const data = await response.json();

                if (data.success) {
                    mostrarMensaje('¡Email verificado exitosamente! Ya puedes iniciar sesión en la aplicación.', 'success');
                    
                    setTimeout(() => {
                        document.querySelector('.verification-form').style.display = 'none';
                    }, 3000);
                } else {
                    mostrarMensaje(data.message || 'Código de verificación inválido', 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Inténtalo de nuevo.', 'error');
                console.error('Error:', error);
            }
        }

        async function reenviarCodigo() {
            const email = document.getElementById('email').value.trim();

            if (!email) {
                mostrarMensaje('Por favor ingresa tu email', 'error');
                return;
            }

            try {
                mostrarMensaje('Reenviando código...', 'info');

                const response = await fetch(API_BASE + '/api/cliente/reenviar-codigo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });

                const data = await response.json();

                if (data.success) {
                    mostrarMensaje('Código reenviado. Revisa tu email.', 'success');
                    document.getElementById('codigo').value = '';
                    document.getElementById('codigo').focus();
                } else {
                    mostrarMensaje(data.message || 'No se pudo reenviar el código', 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Inténtalo de nuevo.', 'error');
                console.error('Error:', error);
            }
        }

        // Mostrar mensaje
        function mostrarMensaje(texto, tipo) {
            const messageDiv = document.getElementById('message');
            
            messageDiv.textContent = texto;
            messageDiv.className = 'message';
            
            if (tipo === 'success') {
                messageDiv.classList.add('success');
            } else if (tipo === 'error') {
                messageDiv.classList.add('error');
            }
            
            messageDiv.style.display = 'block';

            // Auto-ocultar después de 5 segundos si es success
            if (tipo === 'success') {
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        }

        // Permitir verificar con Enter
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                verificarCodigo();
            }
        });

        // Cargar email desde URL si está presente
        const urlParams = new URLSearchParams(window.location.search);
        const emailParam = urlParams.get('email');
        if (emailParam) {
            document.getElementById('email').value = emailParam;
            document.getElementById('codigo').focus();
        }
    </script>
</body>
</html>
