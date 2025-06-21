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

// Validar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_usuarios.php');
    exit;
}

$id = intval($_GET['id']);

// Obtener información detallada del usuario
$stmt = $conn->prepare("SELECT 
    u.*,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
    MAX(r.fecha_reserva) as ultima_reserva
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
    WHERE u.id_usuario = ? AND u.rol = 'cliente'
    GROUP BY u.id_usuario");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    header('Location: admin_usuarios.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $estado = $_POST['estado'];
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido";
    }
    
    // Verificar si el email ya existe para otro usuario
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
    $stmt_check->bind_param("si", $email, $id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $errores[] = "Ya existe otro usuario con este email";
    }
    
    if (empty($errores)) {
        $stmt_update = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, estado = ?, telefono = ?, direccion = ? WHERE id_usuario = ?");
        $stmt_update->bind_param("sssssi", $nombre, $email, $estado, $telefono, $direccion, $id);
        
        if ($stmt_update->execute()) {
            $mensaje = "Usuario actualizado correctamente";
            $tipo_mensaje = "success";
            
            // Actualizar los datos mostrados
            $usuario['nombre'] = $nombre;
            $usuario['email'] = $email;
            $usuario['estado'] = $estado;
            $usuario['telefono'] = $telefono;
            $usuario['direccion'] = $direccion;
        } else {
            $mensaje = "Error al actualizar el usuario";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = implode(", ", $errores);
        $tipo_mensaje = "error";
    }
}
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Editar Usuario - Hotel Rivo</title>
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
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .estado-select {
            background: white;
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
        .btn-danger { background: #dc3545; color: white; }
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
        .usuario-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 32px;
            margin: 0 auto 20px;
        }
        .estado-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 10px;
        }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-inactivo { background: #f8d7da; color: #721c24; }
        .estado-suspendido { background: #fff3cd; color: #856404; }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #333;
            text-align: right;
        }
        .reservas-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .stat-mini {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-mini-number {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-mini-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        .danger-zone {
            margin-top: 30px;
            padding: 20px;
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 8px;
        }
        .danger-zone h4 {
            color: #c53030;
            margin-top: 0;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Usuario</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a usuarios</a>
                    <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">👁️ Ver perfil</a>
                </div>
                <div>
                    <span style="color: #666;">ID: <?= $usuario['id_usuario'] ?></span>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulario de edición -->
            <div class="form-card">
                <h3>📝 Información del Usuario</h3>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">👤 Nombre completo *</label>
                            <input type="text" id="nombre" name="nombre" 
                                   value="<?= htmlspecialchars($usuario['nombre']) ?>" 
                                   required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">📧 Email *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($usuario['email']) ?>" 
                                   required maxlength="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">📱 Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" 
                                   value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" 
                                   maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">⚡ Estado del usuario *</label>
                            <select id="estado" name="estado" class="estado-select" required>
                                <option value="activo" <?= $usuario['estado'] == 'activo' ? 'selected' : '' ?>>
                                    ✅ Activo
                                </option>
                                <option value="inactivo" <?= $usuario['estado'] == 'inactivo' ? 'selected' : '' ?>>
                                    ❌ Inactivo
                                </option>
                                <option value="suspendido" <?= $usuario['estado'] == 'suspendido' ? 'selected' : '' ?>>
                                    ⏸️ Suspendido
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="direccion">🏠 Dirección</label>
                        <textarea id="direccion" name="direccion" 
                                  placeholder="Ingrese la dirección completa..."><?= htmlspecialchars($usuario['direccion'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="actions">
                        <div>
                            <button type="submit" class="btn btn-success">💾 Guardar Cambios</button>
                            <a href="admin_usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                        </div>
                        <div>
                            <small style="color: #666;">* Campos obligatorios</small>
                        </div>
                    </div>
                </form>
                
                <!-- Zona de peligro -->
                <div class="danger-zone">
                    <h4>🚨 Zona de Peligro</h4>
                    <p>Las siguientes acciones son irreversibles y pueden afectar las reservas del usuario.</p>
                    <div style="margin-top: 15px;">
                        <?php if ($usuario['estado'] === 'activo'): ?>
                            <a href="admin_usuario_suspender.php?id=<?= $usuario['id_usuario'] ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('¿Está seguro de suspender a <?= htmlspecialchars($usuario['nombre']) ?>?')">
                                ⏸️ Suspender Usuario
                            </a>
                        <?php endif; ?>
                        
                        <a href="admin_usuario_eliminar.php?id=<?= $usuario['id_usuario'] ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('⚠️ ATENCIÓN: Esta acción eliminará permanentemente al usuario y todas sus reservas.\n\n¿Está absolutamente seguro?')">
                            🗑️ Eliminar Usuario
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Panel de información -->
            <div class="info-card">
                <div class="usuario-avatar">
                    <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                </div>
                
                <h3 style="text-align: center; color: #007bff; margin-bottom: 5px;">
                    <?= htmlspecialchars($usuario['nombre']) ?>
                </h3>
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <span class="estado-badge estado-<?= $usuario['estado'] ?>">
                        <?= ucfirst($usuario['estado']) ?>
                    </span>
                </div>
                
                <!-- Estadísticas de reservas -->
                <h4>📊 Estadísticas</h4>
                <div class="reservas-stats">
                    <div class="stat-mini">
                        <div class="stat-mini-number"><?= $usuario['total_reservas'] ?></div>
                        <div class="stat-mini-label">Total Reservas</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-number"><?= $usuario['reservas_confirmadas'] ?></div>
                        <div class="stat-mini-label">Confirmadas</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-number"><?= $usuario['reservas_canceladas'] ?></div>
                        <div class="stat-mini-label">Canceladas</div>
                    </div>
                </div>
                
                <!-- Información detallada -->
                <h4>ℹ️ Información</h4>
                <div class="info-item">
                    <span class="info-label">ID Usuario:</span>
                    <span class="info-value">#<?= $usuario['id_usuario'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($usuario['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value"><?= $usuario['telefono'] ? htmlspecialchars($usuario['telefono']) : 'No registrado' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Registro:</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($usuario['fecha_registro'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Última Reserva:</span>
                    <span class="info-value">
                        <?= $usuario['ultima_reserva'] ? date('d/m/Y', strtotime($usuario['ultima_reserva'])) : 'Nunca' ?>
                    </span>
                </div>
                
                <!-- Acciones rápidas -->
                <h4>🔧 Acciones Rápidas</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-primary">
                        👁️ Ver Perfil Completo
                    </a>
                    <a href="admin_reservas.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-primary">
                        📅 Ver Reservas
                    </a>
                    <?php if ($usuario['total_reservas'] > 0): ?>
                        <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">
                            📋 Historial Completo
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validación en tiempo real
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
                if (!document.getElementById('email-error')) {
                    const error = document.createElement('small');
                    error.id = 'email-error';
                    error.style.color = '#dc3545';
                    error.textContent = 'Email no válido';
                    this.parentNode.appendChild(error);
                }
            } else {
                this.style.borderColor = '#e1e5e9';
                const error = document.getElementById('email-error');
                if (error) error.remove();
            }
        });
        
        // Confirmación antes de salir si hay cambios
        let datosOriginales = new FormData(document.querySelector('form'));
        
        window.addEventListener('beforeunload', function(e) {
            const datosActuales = new FormData(document.querySelector('form'));
            let haycambios = false;
            
            for (let [key, value] of datosActuales.entries()) {
                if (datosOriginales.get(key) !== value) {
                    hayChangios = true;
                    break;
                }
            }
            
            if (hayChangios) {
                e.preventDefault();
                e.returnValue = '';
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
    </script>
</body> 
</html>