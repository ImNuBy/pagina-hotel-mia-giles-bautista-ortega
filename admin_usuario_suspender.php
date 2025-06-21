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

// Prevenir que un admin se suspenda a sí mismo
if ($id == $_SESSION['user_id']) {
    header('Location: admin_usuarios.php?error=auto_suspension');
    exit;
}

// Obtener información del usuario
$stmt = $conn->prepare("SELECT 
    u.*,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN r.estado = 'confirmada' AND r.fecha_entrada >= CURDATE() THEN 1 ELSE 0 END) as reservas_futuras,
    SUM(CASE WHEN r.estado = 'confirmada' AND r.fecha_entrada <= CURDATE() AND r.fecha_salida >= CURDATE() THEN 1 ELSE 0 END) as reservas_activas
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
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

// Prevenir suspensión de otros administradores
if ($usuario['rol'] === 'admin') {
    header('Location: admin_usuarios.php?error=suspender_admin');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$accion_completada = false;

// Procesar la suspensión/reactivación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $afectar_reservas = $_POST['afectar_reservas'] ?? 'mantener';
    $duracion = $_POST['duracion'] ?? '';
    
    if ($accion === 'suspender') {
        if (empty($motivo)) {
            $mensaje = "El motivo de suspensión es obligatorio";
            $tipo_mensaje = "error";
        } else {
            try {
                $conn->begin_transaction();
                
                // Actualizar estado del usuario
                $stmt_update = $conn->prepare("UPDATE usuarios SET estado = 'suspendido' WHERE id_usuario = ?");
                $stmt_update->bind_param("i", $id);
                $stmt_update->execute();
                
                // Registrar la suspensión (crear tabla de logs si no existe)
                $log_query = "INSERT INTO usuarios_logs (id_usuario, accion, motivo, admin_id, fecha) 
                             VALUES (?, 'suspendido', ?, ?, NOW())
                             ON DUPLICATE KEY UPDATE 
                             accion = VALUES(accion), 
                             motivo = VALUES(motivo), 
                             admin_id = VALUES(admin_id), 
                             fecha = VALUES(fecha)";
                
                // Crear tabla de logs si no existe
                $conn->query("CREATE TABLE IF NOT EXISTS usuarios_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    id_usuario INT,
                    accion VARCHAR(50),
                    motivo TEXT,
                    admin_id INT,
                    fecha DATETIME,
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
                    FOREIGN KEY (admin_id) REFERENCES usuarios(id_usuario)
                )");
                
                $stmt_log = $conn->prepare($log_query);
                $stmt_log->bind_param("isi", $id, $motivo, $_SESSION['user_id']);
                $stmt_log->execute();
                
                // Manejar reservas futuras si se especifica
                if ($afectar_reservas === 'cancelar' && $usuario['reservas_futuras'] > 0) {
                    $stmt_reservas = $conn->prepare("UPDATE reservas SET estado = 'cancelada' 
                                                   WHERE id_usuario = ? AND estado = 'confirmada' AND fecha_entrada >= CURDATE()");
                    $stmt_reservas->bind_param("i", $id);
                    $stmt_reservas->execute();
                    $reservas_canceladas = $stmt_reservas->affected_rows;
                } else {
                    $reservas_canceladas = 0;
                }
                
                $conn->commit();
                $accion_completada = true;
                $tipo_mensaje = "success";
                $mensaje = "Usuario suspendido exitosamente.";
                if ($reservas_canceladas > 0) {
                    $mensaje .= " Se cancelaron {$reservas_canceladas} reserva(s) futura(s).";
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "Error al suspender el usuario: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
        
    } elseif ($accion === 'reactivar') {
        try {
            $conn->begin_transaction();
            
            // Reactivar usuario
            $stmt_update = $conn->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?");
            $stmt_update->bind_param("i", $id);
            $stmt_update->execute();
            
            // Registrar la reactivación
            $stmt_log = $conn->prepare("INSERT INTO usuarios_logs (id_usuario, accion, motivo, admin_id, fecha) 
                                      VALUES (?, 'reactivado', ?, ?, NOW())");
            $motivo_reactivacion = $motivo ?: 'Reactivación administrativa';
            $stmt_log->bind_param("isi", $id, $motivo_reactivacion, $_SESSION['user_id']);
            $stmt_log->execute();
            
            $conn->commit();
            $accion_completada = true;
            $tipo_mensaje = "success";
            $mensaje = "Usuario reactivado exitosamente.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al reactivar el usuario: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    // Actualizar información del usuario después de la acción
    if ($accion_completada) {
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
    }
}

// Obtener historial de suspensiones
$historial = $conn->query("SELECT 
    ul.*,
    u.nombre as admin_nombre
    FROM usuarios_logs ul
    LEFT JOIN usuarios u ON ul.admin_id = u.id_usuario
    WHERE ul.id_usuario = $id
    ORDER BY ul.fecha DESC
    LIMIT 10");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title><?= $usuario['estado'] === 'suspendido' ? 'Reactivar' : 'Suspender' ?> Usuario - Hotel Rivo</title>
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
        .info-card-content {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 6px solid #2196f3;
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
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            margin-right: 20px;
        }
        .usuario-avatar.suspendido {
            background: #ffc107;
            color: #212529;
        }
        .usuario-detalles h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .usuario-detalles p {
            margin: 0;
            color: #666;
        }
        .estado-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 10px;
        }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-suspendido { background: #fff3cd; color: #856404; }
        .estado-inactivo { background: #f8d7da; color: #721c24; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
            font-size: 1.8em;
            font-weight: bold;
            color: #007bff;
        }
        .stat-number.warning {
            color: #ffc107;
        }
        .stat-number.danger {
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
        .form-group textarea, .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
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
        .historial-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .historial-table th,
        .historial-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .historial-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .accion-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .accion-suspendido { background: #fff3cd; color: #856404; }
        .accion-reactivado { background: #d4edda; color: #155724; }
        .impact-warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            border-bottom-color: #007bff;
            background: #f8f9fa;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1><?= $usuario['estado'] === 'suspendido' ? '✅ Reactivar Usuario' : '⏸️ Suspender Usuario' ?></h1>
            <div style="margin-top: 20px;">
                <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a usuarios</a>
                <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">👁️ Ver perfil</a>
                <a href="admin_usuario_editar.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">✏️ Editar</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($accion_completada): ?>
            <div class="success-card">
                <h3>✅ Acción Completada</h3>
                <p>La operación se ha realizado exitosamente.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_usuarios.php" class="btn btn-success">👥 Volver a la lista de usuarios</a>
                    <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">👁️ Ver perfil actualizado</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulario principal -->
            <div class="form-card">
                <!-- Información del usuario -->
                <div class="info-card-content">
                    <h3>👤 Información del Usuario</h3>
                    <div class="usuario-info">
                        <div class="usuario-avatar <?= $usuario['estado'] ?>">
                            <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                        </div>
                        <div class="usuario-detalles">
                            <h3><?= htmlspecialchars($usuario['nombre']) ?></h3>
                            <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>
                            <p><strong>Rol:</strong> <?= ucfirst($usuario['rol']) ?></p>
                            <p><strong>Registrado:</strong> <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
                            <span class="estado-badge estado-<?= $usuario['estado'] ?>">
                                <?= ucfirst($usuario['estado']) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($usuario['total_reservas'] > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= $usuario['total_reservas'] ?></div>
                                <div class="stat-label">Total Reservas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number <?= $usuario['reservas_futuras'] > 0 ? 'warning' : '' ?>">
                                    <?= $usuario['reservas_futuras'] ?>
                                </div>
                                <div class="stat-label">Reservas Futuras</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number <?= $usuario['reservas_activas'] > 0 ? 'danger' : '' ?>">
                                    <?= $usuario['reservas_activas'] ?>
                                </div>
                                <div class="stat-label">Reservas Activas</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pestañas -->
                <div class="tabs">
                    <div class="tab active" onclick="showTab('tab-accion')">
                        <?= $usuario['estado'] === 'suspendido' ? '✅ Reactivar' : '⏸️ Suspender' ?>
                    </div>
                    <?php if ($historial && $historial->num_rows > 0): ?>
                        <div class="tab" onclick="showTab('tab-historial')">📋 Historial</div>
                    <?php endif; ?>
                </div>

                <!-- Contenido de suspensión/reactivación -->
                <div class="tab-content active" id="tab-accion">
                    <?php if ($usuario['estado'] === 'suspendido'): ?>
                        <!-- Formulario de reactivación -->
                        <div class="info-card-content">
                            <h4>✅ Reactivar Usuario</h4>
                            <p>Este usuario está actualmente suspendido. ¿Desea reactivarlo?</p>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="reactivar">
                            
                            <div class="form-group">
                                <label for="motivo">📝 Motivo de reactivación (opcional)</label>
                                <textarea id="motivo" name="motivo" 
                                          placeholder="Describa el motivo de la reactivación..."></textarea>
                            </div>

                            <div class="actions">
                                <div>
                                    <a href="admin_usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-success" 
                                            onclick="return confirm('¿Está seguro de reactivar a este usuario?')">
                                        ✅ Reactivar Usuario
                                    </button>
                                </div>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- Formulario de suspensión -->
                        <?php if ($usuario['reservas_activas'] > 0): ?>
                            <div class="danger-card">
                                <h4>⚠️ Usuario con Reservas Activas</h4>
                                <p><strong>Este usuario tiene <?= $usuario['reservas_activas'] ?> reserva(s) activa(s) en este momento.</strong></p>
                                <p>Suspender este usuario podría afectar reservas en curso. Considere contactar al usuario antes de proceder.</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($usuario['reservas_futuras'] > 0): ?>
                            <div class="warning-card">
                                <h4>📅 Reservas Futuras Detectadas</h4>
                                <p>Este usuario tiene <strong><?= $usuario['reservas_futuras'] ?> reserva(s) futura(s)</strong> confirmada(s).</p>
                                <p>Puede elegir qué hacer con estas reservas al suspender el usuario.</p>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="suspender">
                            
                            <div class="form-group">
                                <label for="motivo">📝 Motivo de suspensión *</label>
                                <textarea id="motivo" name="motivo" required
                                          placeholder="Describa detalladamente el motivo de la suspensión..."></textarea>
                            </div>

                            <?php if ($usuario['reservas_futuras'] > 0): ?>
                                <div class="form-group">
                                    <label>📅 ¿Qué hacer con las reservas futuras?</label>
                                    <div class="radio-group">
                                        <label class="radio-option">
                                            <input type="radio" name="afectar_reservas" value="mantener" checked>
                                            <div>
                                                <strong>✅ Mantener reservas</strong><br>
                                                <small>Las reservas futuras se mantienen confirmadas</small>
                                            </div>
                                        </label>
                                        
                                        <label class="radio-option danger">
                                            <input type="radio" name="afectar_reservas" value="cancelar">
                                            <div>
                                                <strong>❌ Cancelar reservas futuras</strong><br>
                                                <small>Cancela automáticamente todas las reservas futuras</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="duracion">⏰ Duración estimada (opcional)</label>
                                <select id="duracion" name="duracion">
                                    <option value="">No especificada</option>
                                    <option value="1_dia">1 día</option>
                                    <option value="3_dias">3 días</option>
                                    <option value="1_semana">1 semana</option>
                                    <option value="1_mes">1 mes</option>
                                    <option value="indefinida">Indefinida</option>
                                </select>
                            </div>

                            <?php if ($usuario['reservas_futuras'] > 0 || $usuario['reservas_activas'] > 0): ?>
                                <div class="impact-warning">
                                    <h5>⚠️ Impacto de la Suspensión</h5>
                                    <ul>
                                        <?php if ($usuario['reservas_activas'] > 0): ?>
                                            <li><strong>Reservas activas:</strong> El usuario no podrá acceder a su cuenta durante su estadía actual</li>
                                        <?php endif; ?>
                                        <?php if ($usuario['reservas_futuras'] > 0): ?>
                                            <li><strong>Reservas futuras:</strong> Dependiendo de su elección, se mantendrán o cancelarán</li>
                                        <?php endif; ?>
                                        <li><strong>Acceso al sistema:</strong> El usuario no podrá iniciar sesión</li>
                                        <li><strong>Notificaciones:</strong> No recibirá emails del sistema</li>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="actions">
                                <div>
                                    <a href="admin_usuarios.php" class="btn btn-secondary">❌ Cancelar</a>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-warning" 
                                            onclick="return confirmarSuspension()">
                                        ⏸️ Suspender Usuario
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Historial -->
                <?php if ($historial && $historial->num_rows > 0): ?>
                    <div class="tab-content" id="tab-historial">
                        <h4>📋 Historial de Acciones</h4>
                        <table class="historial-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Acción</th>
                                    <th>Motivo</th>
                                    <th>Administrador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $historial->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($log['fecha'])) ?></td>
                                        <td>
                                            <span class="accion-badge accion-<?= $log['accion'] ?>">
                                                <?= $log['accion'] === 'suspendido' ? '⏸️ Suspendido' : '✅ Reactivado' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($log['motivo']) ?></td>
                                        <td><?= htmlspecialchars($log['admin_nombre'] ?? 'Sistema') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Panel de información lateral -->
            <div class="info-card">
                <h4>ℹ️ Información sobre Suspensiones</h4>
                
                <div style="margin-bottom: 20px;">
                    <h5>🔒 ¿Qué hace la suspensión?</h5>
                    <ul style="font-size: 0.9em; color: #666;">
                        <li>Impide el acceso del usuario al sistema</li>
                        <li>Mantiene todos los datos del usuario</li>
                        <li>Las reservas existentes no se ven afectadas automáticamente</li>
                        <li>Se puede revertir en cualquier momento</li>
                    </ul>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5>⚖️ Motivos comunes de suspensión:</h5>
                    <ul style="font-size: 0.9em; color: #666;">
                        <li>Violación de términos de servicio</li>
                        <li>Actividad sospechosa</li>
                        <li>Solicitud del propio usuario</li>
                        <li>Problemas de pago recurrentes</li>
                        <li>Mantenimiento de cuenta</li>
                    </ul>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5>🔄 Reactivación:</h5>
                    <ul style="font-size: 0.9em; color: #666;">
                        <li>Restaura completamente el acceso</li>
                        <li>El usuario puede volver a usar el sistema</li>
                        <li>Todas las funcionalidades quedan habilitadas</li>
                        <li>Se registra en el historial</li>
                    </ul>
                </div>
                
                <?php if ($usuario['estado'] === 'activo'): ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <h5 style="margin-top: 0;">⚠️ Recomendaciones</h5>
                        <ul style="font-size: 0.9em; margin-bottom: 0;">
                            <li>Contacte al usuario antes de suspender</li>
                            <li>Documente claramente el motivo</li>
                            <li>Considere el impacto en reservas activas</li>
                            <li>Establezca un plan de seguimiento</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <h5 style="margin-top: 0;">✅ Usuario Suspendido</h5>
                        <p style="font-size: 0.9em; margin-bottom: 0;">
                            Este usuario está actualmente suspendido. Puede reactivarlo cuando considere que es apropiado.
                        </p>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <h5>🔗 Acciones relacionadas:</h5>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">
                            👁️ Ver perfil completo
                        </a>
                        <a href="admin_reservas.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">
                            📅 Ver reservas
                        </a>
                        <?php if ($usuario['total_reservas'] > 0): ?>
                            <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-secondary">
                                📋 Historial completo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostrar la pestaña seleccionada
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        function confirmarSuspension() {
            const motivo = document.getElementById('motivo').value.trim();
            if (motivo === '') {
                alert('Debe proporcionar un motivo para la suspensión.');
                return false;
            }
            
            const reservasFuturas = <?= $usuario['reservas_futuras'] ?>;
            const reservasActivas = <?= $usuario['reservas_activas'] ?>;
            
            let mensaje = '¿Está seguro de suspender a "<?= addslashes($usuario['nombre']) ?>"?';
            
            if (reservasActivas > 0) {
                mensaje += '\n\n⚠️ ATENCIÓN: Este usuario tiene ' + reservasActivas + ' reserva(s) activa(s) en este momento.';
            }
            
            if (reservasFuturas > 0) {
                const afectarReservas = document.querySelector('input[name="afectar_reservas"]:checked').value;
                if (afectarReservas === 'cancelar') {
                    mensaje += '\n\n❌ Se cancelarán ' + reservasFuturas + ' reserva(s) futura(s).';
                } else {
                    mensaje += '\n\n✅ Las ' + reservasFuturas + ' reserva(s) futura(s) se mantendrán.';
                }
            }
            
            mensaje += '\n\nMotivo: ' + motivo;
            mensaje += '\n\nEsta acción se puede revertir posteriormente.';
            
            return confirm(mensaje);
        }
        
        // Validación en tiempo real del motivo
        document.getElementById('motivo').addEventListener('input', function() {
            const btnSuspender = document.querySelector('button[type="submit"]');
            if (this.value.trim().length > 0) {
                btnSuspender.disabled = false;
                btnSuspender.style.opacity = '1';
            } else {
                btnSuspender.disabled = true;
                btnSuspender.style.opacity = '0.6';
            }
        });
        
        // Inicializar validación
        document.addEventListener('DOMContentLoaded', function() {
            const motivoField = document.getElementById('motivo');
            if (motivoField) {
                const btnSuspender = document.querySelector('button[type="submit"]');
                if (btnSuspender && motivoField.value.trim() === '') {
                    btnSuspender.disabled = true;
                    btnSuspender.style.opacity = '0.6';
                }
            }
        });
        
        // Advertencia sobre reservas activas
        document.querySelectorAll('input[name="afectar_reservas"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const reservasFuturas = <?= $usuario['reservas_futuras'] ?>;
                if (this.value === 'cancelar' && reservasFuturas > 0) {
                    if (!confirm('⚠️ ADVERTENCIA: Esto cancelará ' + reservasFuturas + ' reserva(s) futura(s).\n\n¿Está seguro de que desea proceder?')) {
                        document.querySelector('input[name="afectar_reservas"][value="mantener"]').checked = true;
                    }
                }
            });
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