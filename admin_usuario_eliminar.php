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
    header('Location: admin_usuarios.php?error=id_invalido');
    exit;
}

$id = intval($_GET['id']);

// Prevenir que un admin se elimine a sí mismo
if ($id == $_SESSION['user_id']) {
    header('Location: admin_usuarios.php?error=auto_eliminacion');
    exit;
}

// Obtener información del usuario a eliminar
$stmt = $conn->prepare("SELECT 
    u.*,
    COUNT(r.id_reserva) as total_reservas,
    COUNT(p.id_pago) as total_pagos,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_activas
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE u.id_usuario = ?
    GROUP BY u.id_usuario");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    header('Location: admin_usuarios.php?error=usuario_no_encontrado');
    exit;
}

// Prevenir eliminación de otros administradores
if ($usuario['rol'] === 'admin') {
    header('Location: admin_usuarios.php?error=eliminar_admin');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$eliminacion_exitosa = false;

// Procesar la eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmar = $_POST['confirmar'] ?? '';
    $eliminar_datos = $_POST['eliminar_datos'] ?? 'no';
    
    if ($confirmar === 'SI_ELIMINAR') {
        try {
            // Iniciar transacción
            $conn->begin_transaction();
            
            if ($eliminar_datos === 'si' && $usuario['total_reservas'] > 0) {
                // Opción 1: Eliminar reservas y pagos asociados
                
                // Primero eliminar pagos asociados a las reservas del usuario
                $stmt_pagos = $conn->prepare("DELETE p FROM pagos p 
                                            INNER JOIN reservas r ON p.id_reserva = r.id_reserva 
                                            WHERE r.id_usuario = ?");
                $stmt_pagos->bind_param("i", $id);
                $stmt_pagos->execute();
                
                // Luego eliminar las reservas
                $stmt_reservas = $conn->prepare("DELETE FROM reservas WHERE id_usuario = ?");
                $stmt_reservas->bind_param("i", $id);
                $stmt_reservas->execute();
                
                $mensaje = "Se eliminaron {$usuario['total_reservas']} reserva(s) y {$usuario['total_pagos']} pago(s) asociados.";
                
            } elseif ($eliminar_datos === 'anonimizar') {
                // Opción 2: Anonimizar - mantener reservas pero desasociar del usuario
                
                // Crear un usuario "eliminado" genérico si no existe
                $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = 'usuario.eliminado@hotel.com'");
                $stmt_check->execute();
                $usuario_eliminado = $stmt_check->get_result()->fetch_assoc();
                
                if (!$usuario_eliminado) {
                    $stmt_crear = $conn->prepare("INSERT INTO usuarios (nombre, email, rol, estado, fecha_registro) 
                                                VALUES ('Usuario Eliminado', 'usuario.eliminado@hotel.com', 'cliente', 'inactivo', NOW())");
                    $stmt_crear->execute();
                    $id_usuario_eliminado = $conn->insert_id;
                } else {
                    $id_usuario_eliminado = $usuario_eliminado['id_usuario'];
                }
                
                // Transferir reservas al usuario "eliminado"
                $stmt_transferir = $conn->prepare("UPDATE reservas SET id_usuario = ? WHERE id_usuario = ?");
                $stmt_transferir->bind_param("ii", $id_usuario_eliminado, $id);
                $stmt_transferir->execute();
                
                $mensaje = "Las reservas fueron transferidas a un usuario anónimo para mantener el historial.";
            }
            
            // Finalmente eliminar el usuario
            $stmt_usuario = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt_usuario->bind_param("i", $id);
            $stmt_usuario->execute();
            
            // Confirmar transacción
            $conn->commit();
            
            $eliminacion_exitosa = true;
            $tipo_mensaje = 'success';
            $mensaje = "Usuario '{$usuario['nombre']}' eliminado exitosamente. " . $mensaje;
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            $tipo_mensaje = 'error';
            $mensaje = "Error al eliminar el usuario: " . $e->getMessage();
        }
    } else {
        $tipo_mensaje = 'error';
        $mensaje = 'Debe confirmar la eliminación escribiendo "SI_ELIMINAR"';
    }
}
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Eliminar Usuario - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .warning-card {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 6px solid #ff6b35;
        }
        .danger-card {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 6px solid #dc3545;
        }
        .success-card {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 6px solid #28a745;
        }
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .usuario-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .usuario-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            margin-right: 20px;
        }
        .usuario-detalles h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .usuario-detalles p {
            margin: 0;
            color: #666;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #dc3545;
        }
        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
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
        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input[type="text"] {
            font-family: monospace;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .radio-option {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .radio-option:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }
        .radio-option input[type="radio"] {
            margin-right: 10px;
            margin-top: 2px;
        }
        .radio-option.danger {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .radio-option.warning {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .option-details {
            flex: 1;
        }
        .option-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .option-description {
            font-size: 0.9em;
            color: #666;
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
        .btn-danger { background: #dc3545; color: white; }
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
        .confirmacion-requerida {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        .impacto-list {
            list-style: none;
            padding: 0;
        }
        .impacto-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        .impacto-list li:last-child {
            border-bottom: none;
        }
        .countdown {
            font-size: 2em;
            font-weight: bold;
            color: #dc3545;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>🗑️ Eliminar Usuario</h1>
            <div style="margin-top: 20px;">
                <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a usuarios</a>
                <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">👁️ Ver perfil</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($eliminacion_exitosa): ?>
            <div class="success-card">
                <h3>✅ Eliminación Completada</h3>
                <p>El usuario ha sido eliminado exitosamente del sistema.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_usuarios.php" class="btn btn-success">👥 Volver a la lista de usuarios</a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Ir al dashboard</a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Información del usuario -->
            <div class="info-card">
                <h3>👤 Información del Usuario a Eliminar</h3>
                <div class="usuario-info">
                    <div class="usuario-avatar">
                        <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                    </div>
                    <div class="usuario-detalles">
                        <h3><?= htmlspecialchars($usuario['nombre']) ?></h3>
                        <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>
                        <p><strong>Rol:</strong> <?= ucfirst($usuario['rol']) ?></p>
                        <p><strong>Estado:</strong> <?= ucfirst($usuario['estado']) ?></p>
                        <p><strong>Registrado:</strong> <?= date('d/m/Y H:i', strtotime($usuario['fecha_registro'])) ?></p>
                    </div>
                </div>

                <?php if ($usuario['total_reservas'] > 0): ?>
                    <h4>📊 Datos Asociados</h4>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?= $usuario['total_reservas'] ?></div>
                            <div class="stat-label">Reservas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $usuario['total_pagos'] ?></div>
                            <div class="stat-label">Pagos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $usuario['reservas_activas'] ?></div>
                            <div class="stat-label">Reservas Activas</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Advertencia -->
            <div class="danger-card">
                <h3>⚠️ ADVERTENCIA: Acción Irreversible</h3>
                <p><strong>Esta acción NO se puede deshacer.</strong> Una vez eliminado el usuario, no será posible recuperar su información.</p>
                
                <?php if ($usuario['total_reservas'] > 0): ?>
                    <div style="margin-top: 15px;">
                        <h4>🔗 Datos Relacionados Encontrados:</h4>
                        <ul class="impacto-list">
                            <li>
                                <span>📅 Reservas en el sistema:</span>
                                <strong><?= $usuario['total_reservas'] ?></strong>
                            </li>
                            <li>
                                <span>💳 Pagos registrados:</span>
                                <strong><?= $usuario['total_pagos'] ?></strong>
                            </li>
                            <?php if ($usuario['reservas_activas'] > 0): ?>
                                <li style="color: #dc3545;">
                                    <span>⚠️ Reservas confirmadas:</span>
                                    <strong><?= $usuario['reservas_activas'] ?></strong>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formulario de eliminación -->
            <div class="info-card">
                <h3>🔧 Opciones de Eliminación</h3>
                <form method="POST" action="">
                    
                    <?php if ($usuario['total_reservas'] > 0): ?>
                        <div class="form-group">
                            <label>¿Qué hacer con los datos relacionados?</label>
                            <div class="radio-group">
                                <label class="radio-option danger">
                                    <input type="radio" name="eliminar_datos" value="si" required>
                                    <div class="option-details">
                                        <div class="option-title">🗑️ Eliminar todo (Destructivo)</div>
                                        <div class="option-description">
                                            Elimina permanentemente el usuario y TODAS sus reservas y pagos asociados.
                                            <strong>Se perderá completamente el historial.</strong>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="radio-option warning">
                                    <input type="radio" name="eliminar_datos" value="anonimizar">
                                    <div class="option-details">
                                        <div class="option-title">🔄 Anonimizar (Recomendado)</div>
                                        <div class="option-description">
                                            Elimina el usuario pero transfiere sus reservas a un usuario anónimo.
                                            <strong>Se mantiene el historial para reportes.</strong>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="eliminar_datos" value="no">
                        <div class="success-card">
                            <h4>✅ Eliminación Segura</h4>
                            <p>Este usuario no tiene reservas ni pagos asociados, por lo que se puede eliminar de forma segura sin afectar otros datos.</p>
                        </div>
                    <?php endif; ?>

                    <div class="confirmacion-requerida">
                        <h4>🔐 Confirmación Requerida</h4>
                        <p>Para confirmar la eliminación, escriba exactamente: <strong>SI_ELIMINAR</strong></p>
                    </div>

                    <div class="form-group">
                        <label for="confirmar">Escriba "SI_ELIMINAR" para confirmar:</label>
                        <input type="text" id="confirmar" name="confirmar" 
                               placeholder="SI_ELIMINAR" 
                               pattern="SI_ELIMINAR" 
                               title="Debe escribir exactamente: SI_ELIMINAR"
                               required>
                    </div>

                    <div class="actions">
                        <div>
                            <a href="admin_usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-danger" id="btn-eliminar" disabled>
                                🗑️ Eliminar Usuario Permanentemente
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Habilitar botón solo cuando se confirme correctamente
        document.getElementById('confirmar').addEventListener('input', function() {
            const btnEliminar = document.getElementById('btn-eliminar');
            if (this.value === 'SI_ELIMINAR') {
                btnEliminar.disabled = false;
                btnEliminar.style.background = '#dc3545';
            } else {
                btnEliminar.disabled = true;
                btnEliminar.style.background = '#6c757d';
            }
        });

        // Confirmación adicional antes del envío
        document.querySelector('form').addEventListener('submit', function(e) {
            const confirmText = document.getElementById('confirmar').value;
            if (confirmText !== 'SI_ELIMINAR') {
                e.preventDefault();
                alert('Debe escribir exactamente "SI_ELIMINAR" para confirmar la eliminación.');
                return;
            }

            const usuarioNombre = '<?= addslashes($usuario['nombre']) ?>';
            const totalReservas = <?= $usuario['total_reservas'] ?>;
            
            let mensaje = `¿Está absolutamente seguro de eliminar al usuario "${usuarioNombre}"?`;
            
            if (totalReservas > 0) {
                mensaje += `\n\nEsto afectará a ${totalReservas} reserva(s) asociada(s).`;
            }
            
            mensaje += '\n\nEsta acción NO se puede deshacer.';
            
            if (!confirm(mensaje)) {
                e.preventDefault();
            }
        });

        // Advertencia al salir de la página
        window.addEventListener('beforeunload', function(e) {
            const confirmText = document.getElementById('confirmar').value;
            if (confirmText.length > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Auto-enfocar en el campo de confirmación
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmar').focus();
        });
    </script>
</body> 
</html>