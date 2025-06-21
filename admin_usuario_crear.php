<?php 
session_start(); 
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {     
    header('Location: login.php');     
    exit; 
}  

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo'); 

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$mensaje = '';
$tipo_mensaje = '';
$usuario_creado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $rol = $_POST['rol'];
    $estado = $_POST['estado'];
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $enviar_email = isset($_POST['enviar_email']);
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    } elseif (strlen($nombre) < 2) {
        $errores[] = "El nombre debe tener al menos 2 caracteres";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido";
    }
    
    // Verificar si el email ya existe
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $errores[] = "Ya existe un usuario con este email";
    }
    
    if (empty($password)) {
        $errores[] = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres";
    }
    
    if ($password !== $confirm_password) {
        $errores[] = "Las contraseñas no coinciden";
    }
    
    if (!in_array($rol, ['cliente', 'empleado', 'manager', 'admin'])) {
        $errores[] = "Rol no válido";
    }
    
    if (!in_array($estado, ['activo', 'inactivo', 'suspendido'])) {
        $errores[] = "Estado no válido";
    }
    
    if ($telefono && !preg_match('/^[+]?[0-9\s\-\(\)]{7,20}$/', $telefono)) {
        $errores[] = "El formato del teléfono no es válido";
    }
    
    if (empty($errores)) {
        try {
            // Hashear la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar usuario
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado, telefono, direccion, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssssss", $nombre, $email, $password_hash, $rol, $estado, $telefono, $direccion);
            
            if ($stmt->execute()) {
                $nuevo_id = $conn->insert_id;
                $usuario_creado = true;
                $mensaje = "Usuario '{$nombre}' creado exitosamente con ID: {$nuevo_id}";
                $tipo_mensaje = "success";
                
                // Aquí podrías agregar lógica para enviar email de bienvenida
                if ($enviar_email) {
                    // Simular envío de email
                    $mensaje .= " (Email de bienvenida enviado)";
                }
                
            } else {
                $mensaje = "Error al crear el usuario";
                $tipo_mensaje = "error";
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = implode(", ", $errores);
        $tipo_mensaje = "error";
    }
}

// Obtener estadísticas de usuarios por rol
$stats = $conn->query("SELECT 
    rol, 
    COUNT(*) as cantidad 
    FROM usuarios 
    GROUP BY rol 
    ORDER BY cantidad DESC")->fetch_all(MYSQLI_ASSOC);
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Crear Usuario - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .form-card, .info-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-group.required label::after {
            content: " *";
            color: #dc3545;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9em;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }
        .requirement.valid {
            color: #28a745;
        }
        .requirement.invalid {
            color: #dc3545;
        }
        .rol-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .rol-option {
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .rol-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .rol-option.selected {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .rol-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }
        .rol-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .rol-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .rol-description {
            font-size: 0.8em;
            color: #666;
        }
        .estado-selector {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .estado-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .estado-option.activo { border-color: #28a745; background: #f8fff9; }
        .estado-option.inactivo { border-color: #dc3545; background: #fff8f8; }
        .estado-option.suspendido { border-color: #ffc107; background: #fffdf0; }
        .estado-option.selected {
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }
        .btn {
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        .mensaje {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin: 20px 0;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .checkbox-group {
            margin-top: 15px;
        }
        .checkbox-option {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 5px 0;
        }
        .checkbox-option input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }
        .success-card {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        .user-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .user-avatar-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            margin: 0 auto 15px;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .rol-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>➕ Crear Nuevo Usuario</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a usuarios</a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($usuario_creado): ?>
            <div class="success-card">
                <h3>✅ Usuario Creado Exitosamente</h3>
                <p>El nuevo usuario ha sido agregado al sistema correctamente.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_usuarios.php" class="btn btn-success">👥 Ver todos los usuarios</a>
                    <a href="admin_usuario_crear.php" class="btn btn-primary">➕ Crear otro usuario</a>
                    <a href="admin_usuario_editar.php?id=<?= $nuevo_id ?>" class="btn btn-secondary">✏️ Editar usuario creado</a>
                </div>
            </div>
        <?php else: ?>

            <div class="content-grid">
                <!-- Formulario de creación -->
                <div class="form-card">
                    <h3>📝 Información del Nuevo Usuario</h3>
                    
                    <form method="POST" action="" id="form-crear-usuario">
                        <!-- Información básica -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="nombre">👤 Nombre completo</label>
                                <input type="text" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                                       required maxlength="100" 
                                       placeholder="Ingrese el nombre completo">
                            </div>
                            
                            <div class="form-group required">
                                <label for="email">📧 Email</label>
                                <input type="email" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       required maxlength="100" 
                                       placeholder="usuario@ejemplo.com">
                            </div>
                        </div>
                        
                        <!-- Contraseñas -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="password">🔐 Contraseña</label>
                                <input type="password" id="password" name="password" 
                                       required minlength="6" 
                                       placeholder="Mínimo 6 caracteres">
                                <div class="password-requirements">
                                    <div class="requirement" id="req-length">
                                        <span id="icon-length">❌</span> Al menos 6 caracteres
                                    </div>
                                    <div class="requirement" id="req-letter">
                                        <span id="icon-letter">❌</span> Al menos una letra
                                    </div>
                                    <div class="requirement" id="req-number">
                                        <span id="icon-number">❌</span> Al menos un número (recomendado)
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group required">
                                <label for="confirm_password">🔐 Confirmar contraseña</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       required 
                                       placeholder="Repita la contraseña">
                                <div id="password-match" style="margin-top: 5px; font-size: 0.9em;"></div>
                            </div>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="telefono">📱 Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" 
                                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" 
                                       maxlength="20" 
                                       placeholder="+1234567890">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="direccion">🏠 Dirección</label>
                            <textarea id="direccion" name="direccion" rows="3" 
                                      placeholder="Dirección completa (opcional)"><?= htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Selección de rol -->
                        <div class="form-group required">
                            <label>👔 Rol del usuario</label>
                            <div class="rol-selector">
                                <div class="rol-option" data-rol="cliente">
                                    <input type="radio" name="rol" value="cliente" 
                                           <?= ($_POST['rol'] ?? 'cliente') === 'cliente' ? 'checked' : '' ?>>
                                    <div class="rol-icon">🧑‍💼</div>
                                    <div class="rol-name">Cliente</div>
                                    <div class="rol-description">Puede hacer reservas</div>
                                </div>
                                
                                <div class="rol-option" data-rol="empleado">
                                    <input type="radio" name="rol" value="empleado" 
                                           <?= ($_POST['rol'] ?? '') === 'empleado' ? 'checked' : '' ?>>
                                    <div class="rol-icon">👷</div>
                                    <div class="rol-name">Empleado</div>
                                    <div class="rol-description">Acceso básico</div>
                                </div>
                                
                                <div class="rol-option" data-rol="manager">
                                    <input type="radio" name="rol" value="manager" 
                                           <?= ($_POST['rol'] ?? '') === 'manager' ? 'checked' : '' ?>>
                                    <div class="rol-icon">📊</div>
                                    <div class="rol-name">Manager</div>
                                    <div class="rol-description">Gestión avanzada</div>
                                </div>
                                
                                <div class="rol-option" data-rol="admin">
                                    <input type="radio" name="rol" value="admin" 
                                           <?= ($_POST['rol'] ?? '') === 'admin' ? 'checked' : '' ?>>
                                    <div class="rol-icon">👑</div>
                                    <div class="rol-name">Admin</div>
                                    <div class="rol-description">Control total</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado inicial -->
                        <div class="form-group required">
                            <label>⚡ Estado inicial</label>
                            <div class="estado-selector">
                                <div class="estado-option activo" data-estado="activo">
                                    <input type="radio" name="estado" value="activo" 
                                           <?= ($_POST['estado'] ?? 'activo') === 'activo' ? 'checked' : '' ?>>
                                    <div><strong>✅ Activo</strong></div>
                                    <div style="font-size: 0.9em;">Acceso inmediato</div>
                                </div>
                                
                                <div class="estado-option inactivo" data-estado="inactivo">
                                    <input type="radio" name="estado" value="inactivo" 
                                           <?= ($_POST['estado'] ?? '') === 'inactivo' ? 'checked' : '' ?>>
                                    <div><strong>❌ Inactivo</strong></div>
                                    <div style="font-size: 0.9em;">Sin acceso</div>
                                </div>
                                
                                <div class="estado-option suspendido" data-estado="suspendido">
                                    <input type="radio" name="estado" value="suspendido" 
                                           <?= ($_POST['estado'] ?? '') === 'suspendido' ? 'checked' : '' ?>>
                                    <div><strong>⏸️ Suspendido</strong></div>
                                    <div style="font-size: 0.9em;">Temporalmente bloqueado</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Opciones adicionales -->
                        <div class="checkbox-group">
                            <label class="checkbox-option">
                                <input type="checkbox" name="enviar_email" value="1" 
                                       <?= isset($_POST['enviar_email']) ? 'checked' : '' ?>>
                                <div>
                                    <strong>📧 Enviar email de bienvenida</strong><br>
                                    <small>Envía las credenciales de acceso al usuario</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="actions">
                            <div>
                                <a href="admin_usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-success" id="btn-crear">
                                    ➕ Crear Usuario
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Panel de información -->
                <div class="info-card">
                    <h4>📊 Estadísticas Actuales</h4>
                    <div class="stats-grid">
                        <?php foreach ($stats as $stat): ?>
                            <div class="stat-item">
                                <span>
                                    <?php 
                                    $icons = ['cliente' => '🧑‍💼', 'empleado' => '👷', 'manager' => '📊', 'admin' => '👑'];
                                    echo ($icons[$stat['rol']] ?? '👤') . ' ' . ucfirst($stat['rol']);
                                    ?>
                                </span>
                                <strong><?= $stat['cantidad'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h4>💡 Consejos</h4>
                    <ul style="color: #666; font-size: 0.9em;">
                        <li><strong>Contraseñas seguras:</strong> Combina letras, números y símbolos</li>
                        <li><strong>Emails únicos:</strong> Cada usuario debe tener un email diferente</li>
                        <li><strong>Roles apropiados:</strong> Asigna el rol mínimo necesario</li>
                        <li><strong>Estado activo:</strong> Para acceso inmediato al sistema</li>
                    </ul>
                    
                    <!-- Vista previa del usuario -->
                    <div class="user-preview" id="user-preview" style="display: none;">
                        <h4>👁️ Vista Previa</h4>
                        <div class="user-avatar-preview" id="preview-avatar">?</div>
                        <div style="text-align: center;">
                            <div id="preview-name" style="font-weight: bold; margin-bottom: 5px;">Nombre</div>
                            <div id="preview-email" style="color: #666; margin-bottom: 5px;">email@ejemplo.com</div>
                            <div id="preview-rol" style="font-size: 0.9em;">Rol</div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Validación de contraseña en tiempo real
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Validar longitud
            const lengthValid = password.length >= 6;
            updateRequirement('req-length', 'icon-length', lengthValid);
            
            // Validar letra
            const letterValid = /[a-zA-Z]/.test(password);
            updateRequirement('req-letter', 'icon-letter', letterValid);
            
            // Validar número
            const numberValid = /[0-9]/.test(password);
            updateRequirement('req-number', 'icon-number', numberValid);
            
            checkPasswordMatch();
        });
        
        function updateRequirement(reqId, iconId, isValid) {
            const req = document.getElementById(reqId);
            const icon = document.getElementById(iconId);
            
            if (isValid) {
                req.classList.add('valid');
                req.classList.remove('invalid');
                icon.textContent = '✅';
            } else {
                req.classList.add('invalid');
                req.classList.remove('valid');
                icon.textContent = '❌';
            }
        }
        
        // Validar coincidencia de contraseñas
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword === '') {
                matchDiv.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span style="color: #28a745;">✅ Las contraseñas coinciden</span>';
            } else {
                matchDiv.innerHTML = '<span style="color: #dc3545;">❌ Las contraseñas no coinciden</span>';
            }
        }
        
        // Selección visual de roles
        document.querySelectorAll('.rol-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.rol-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                updatePreview();
            });
        });
        
        // Selección visual de estados
        document.querySelectorAll('.estado-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.estado-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Vista previa del usuario
        function updatePreview() {
            const nombre = document.getElementById('nombre').value;
            const email = document.getElementById('email').value;
            const rolSeleccionado = document.querySelector('input[name="rol"]:checked');
            
            if (nombre || email) {
                document.getElementById('user-preview').style.display = 'block';
                document.getElementById('preview-avatar').textContent = nombre ? nombre.charAt(0).toUpperCase() : '?';
                document.getElementById('preview-name').textContent = nombre || 'Nombre';
                document.getElementById('preview-email').textContent = email || 'email@ejemplo.com';
                document.getElementById('preview-rol').textContent = rolSeleccionado ? 
                    rolSeleccionado.value.charAt(0).toUpperCase() + rolSeleccionado.value.slice(1) : 'Rol';
            }
        }
        
        // Actualizar vista previa cuando cambian los campos
        document.getElementById('nombre').addEventListener('input', updatePreview);
        document.getElementById('email').addEventListener('input', updatePreview);
        
        // Inicializar selecciones visuales
        document.addEventListener('DOMContentLoaded', function() {
            // Marcar rol seleccionado
            const rolChecked = document.querySelector('input[name="rol"]:checked');
            if (rolChecked) {
                rolChecked.closest('.rol-option').classList.add('selected');
            }
            
            // Marcar estado seleccionado
            const estadoChecked = document.querySelector('input[name="estado"]:checked');
            if (estadoChecked) {
                estadoChecked.closest('.estado-option').classList.add('selected');
            }
            
            updatePreview();
        });
        
        // Validación final del formulario
        document.getElementById('form-crear-usuario').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden. Por favor verifique.');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres.');
                return;
            }
        });
        
        // Ocultar mensaje después de unos segundos
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if (mensaje) {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s';
                setTimeout(() => mensaje.remove(), 500);
            }
        }, 5000);
        
        // Generar contraseña automática
        function generarPassword() {
            const caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += caracteres.charAt(Math.floor(Math.random() * caracteres.length));
            }
            
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
            
            // Disparar eventos para actualizar validaciones
            document.getElementById('password').dispatchEvent(new Event('input'));
            document.getElementById('confirm_password').dispatchEvent(new Event('input'));
        }
        
        // Agregar botón de generar contraseña
        document.addEventListener('DOMContentLoaded', function() {
            const passwordGroup = document.getElementById('password').closest('.form-group');
            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = 'btn btn-secondary';
            generateBtn.style.marginTop = '10px';
            generateBtn.innerHTML = '🎲 Generar contraseña';
            generateBtn.onclick = generarPassword;
            passwordGroup.appendChild(generateBtn);
        });
    </script>
</body>
</html>