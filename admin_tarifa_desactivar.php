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
    header('Location: admin_tarifas.php?error=id_invalido');
    exit;
}

$id = intval($_GET['id']);

// Obtener información de la tarifa
$stmt = $conn->prepare("SELECT 
    t.*,
    th.nombre as tipo_habitacion,
    CASE 
        WHEN t.activa = 1 AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN t.activa = 1 AND CURDATE() < t.fecha_inicio THEN 'futura'
        WHEN t.activa = 0 THEN 'desactivada'
        ELSE 'vencida'
    END as estado_tarifa,
    (t.precio - (t.precio * t.descuento / 100)) as precio_final
    FROM tarifas t
    INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
    WHERE t.id_tarifa = ?");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$tarifa = $result->fetch_assoc();

if (!$tarifa) {
    header('Location: admin_tarifas.php?error=tarifa_no_encontrada');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar activación/desactivación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '')) {
        $nueva_accion = $_POST['accion']; // 'activar' o 'desactivar'
        $nuevo_estado = ($nueva_accion === 'activar') ? 1 : 0;
        
        try {
            $stmt_update = $conn->prepare("UPDATE tarifas SET activa = ? WHERE id_tarifa = ?");
            $stmt_update->bind_param("ii", $nuevo_estado, $id);
            
            if ($stmt_update->execute()) {
                $accion_texto = $nueva_accion === 'activar' ? 'activada' : 'desactivada';
                
                // Log de la acción
                $log_stmt = $conn->prepare("INSERT INTO log_admin (user_id, accion, detalle, fecha) VALUES (?, ?, ?, NOW())");
                $detalle = "Tarifa {$accion_texto}: ID {$id} - {$tarifa['tipo_habitacion']} - {$tarifa['temporada']}";
                $log_stmt->bind_param("iss", $_SESSION['user_id'], strtoupper($nueva_accion) . '_TARIFA', $detalle);
                $log_stmt->execute();
                
                // Actualizar estado en la variable local
                $tarifa['activa'] = $nuevo_estado;
                $mensaje = "Tarifa {$accion_texto} correctamente";
                $tipo_mensaje = "success";
                
                // Opcional: redirigir después de unos segundos
                header("refresh:3;url=admin_tarifas.php");
            } else {
                throw new Exception("Error al cambiar el estado de la tarifa");
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Token de seguridad inválido";
        $tipo_mensaje = "error";
    }
}

// Obtener reservas que podrían verse afectadas
$reservas_afectadas = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
    SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
    FROM reservas r
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE h.id_tipo = {$tarifa['id_tipo']}
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'
    AND r.estado IN ('confirmada', 'pendiente')")->fetch_assoc();

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title><?= $tarifa['activa'] ? 'Desactivar' : 'Activar' ?> Tarifa - Hotel Rivo</title>
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
        .status-card {
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .status-card.activa {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        .status-card.inactiva {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }
        .tarifa-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .info-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .info-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
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
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
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
        .impacto-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .toggle-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1><?= $tarifa['activa'] ? '⏸️ Desactivar' : '▶️ Activar' ?> Tarifa</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_tarifas.php" class="btn btn-secondary">← Volver a tarifas</a>
                    <a href="admin_tarifa_ver.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">👁️ Ver detalles</a>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
                <?php if ($tipo_mensaje === 'success'): ?>
                    <br><small>Redirigiendo a la lista de tarifas en 3 segundos...</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Estado actual -->
        <div class="status-card <?= $tarifa['activa'] ? 'activa' : 'inactiva' ?>">
            <h3>📊 Estado Actual de la Tarifa</h3>
            <?php if ($tarifa['activa']): ?>
                <p><strong>✅ TARIFA ACTIVA</strong></p>
                <p>Esta tarifa está actualmente activa y afecta los precios efectivos del sistema.</p>
            <?php else: ?>
                <p><strong>⏸️ TARIFA DESACTIVADA</strong></p>
                <p>Esta tarifa está desactivada y NO afecta los precios efectivos del sistema.</p>
            <?php endif; ?>
        </div>

        <!-- Información de la tarifa -->
        <div class="tarifa-info">
            <h3>📋 Información de la Tarifa</h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ID Tarifa</div>
                    <div class="info-value">#<?= $tarifa['id_tarifa'] ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Tipo de Habitación</div>
                    <div class="info-value"><?= htmlspecialchars($tarifa['tipo_habitacion']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Temporada</div>
                    <div class="info-value"><?= htmlspecialchars($tarifa['temporada']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Estado Temporal</div>
                    <div class="info-value"><?= ucfirst($tarifa['estado_tarifa']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Precio Final</div>
                    <div class="info-value" style="color: #28a745;">$<?= number_format($tarifa['precio_final'], 0, ',', '.') ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Período</div>
                    <div class="info-value">
                        <?= date('d/m/Y', strtotime($tarifa['fecha_inicio'])) ?><br>
                        <small>al</small><br>
                        <?= date('d/m/Y', strtotime($tarifa['fecha_fin'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Impacto de la acción -->
        <?php if ($reservas_afectadas['total'] > 0): ?>
            <div class="impacto-info">
                <h4>📊 Impacto de <?= $tarifa['activa'] ? 'Desactivar' : 'Activar' ?> esta Tarifa</h4>
                <p>Esta tarifa tiene <strong><?= $reservas_afectadas['total'] ?> reserva(s) asociada(s)</strong>:</p>
                <ul>
                    <li><?= $reservas_afectadas['confirmadas'] ?> reservas confirmadas</li>
                    <li><?= $reservas_afectadas['pendientes'] ?> reservas pendientes</li>
                </ul>
                
                <?php if ($tarifa['activa']): ?>
                    <p><strong>Al desactivar:</strong></p>
                    <ul>
                        <li>✅ Las reservas existentes NO se verán afectadas</li>
                        <li>✅ Los precios de reservas confirmadas se mantienen</li>
                        <li>⚠️ Los nuevos huéspedes verán el precio base en lugar de esta tarifa</li>
                        <li>📊 La tarifa permanece en el sistema para consultas históricas</li>
                    </ul>
                <?php else: ?>
                    <p><strong>Al activar:</strong></p>
                    <ul>
                        <li>✅ Los nuevos huéspedes verán esta tarifa especial</li>
                        <li>✅ Se aplicará automáticamente a las fechas del período</li>
                        <li>⚠️ Sobrescribirá el precio base del tipo de habitación</li>
                        <li>📊 Aparecerá en reportes de tarifas activas</li>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Vista previa del cambio -->
        <div class="toggle-preview">
            <h4>🔄 Vista Previa del Cambio</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h5>Estado Actual</h5>
                    <div style="padding: 15px; background: <?= $tarifa['activa'] ? '#d4edda' : '#f8d7da' ?>; border-radius: 6px;">
                        <strong><?= $tarifa['activa'] ? '✅ ACTIVA' : '⏸️ DESACTIVADA' ?></strong><br>
                        <?php if ($tarifa['activa']): ?>
                            Afecta precios efectivos
                        <?php else: ?>
                            NO afecta precios efectivos
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <h5>Después del Cambio</h5>
                    <div style="padding: 15px; background: <?= $tarifa['activa'] ? '#f8d7da' : '#d4edda' ?>; border-radius: 6px;">
                        <strong><?= $tarifa['activa'] ? '⏸️ DESACTIVADA' : '✅ ACTIVA' ?></strong><br>
                        <?php if ($tarifa['activa']): ?>
                            NO afectará precios efectivos
                        <?php else: ?>
                            Afectará precios efectivos
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de confirmación -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h3>🔐 Confirmar Acción</h3>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="<?= $tarifa['activa'] ? 'desactivar' : 'activar' ?>">
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <label style="display: flex; align-items: center; gap: 10px; font-weight: bold; cursor: pointer;">
                        <input type="checkbox" required style="width: 20px; height: 20px;">
                        Confirmo que deseo <?= $tarifa['activa'] ? 'desactivar' : 'activar' ?> esta tarifa
                    </label>
                </div>
                
                <div class="actions">
                    <a href="admin_tarifas.php" class="btn btn-secondary">❌ Cancelar</a>
                    <button type="submit" class="btn <?= $tarifa['activa'] ? 'btn-warning' : 'btn-success' ?>">
                        <?= $tarifa['activa'] ? '⏸️ DESACTIVAR TARIFA' : '▶️ ACTIVAR TARIFA' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Información adicional -->
        <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin-top: 30px; border-left: 4px solid #007bff;">
            <h4>💡 Información Importante</h4>
            <ul>
                <li><strong>Desactivar vs Eliminar:</strong> Desactivar mantiene la tarifa en el sistema para consultas históricas</li>
                <li><strong>Reversible:</strong> Puedes activar/desactivar tarifas en cualquier momento</li>
                <li><strong>Reservas existentes:</strong> No se ven afectadas por cambios de estado</li>
                <li><strong>Precios dinámicos:</strong> Solo las tarifas activas afectan los precios efectivos</li>
            </ul>
            
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="admin_tarifa_editar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                    ✏️ Editar Tarifa
                </a>
                <a href="admin_tarifa_duplicar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                    📋 Duplicar Tarifa
                </a>
                <?php if (!$tarifa['activa']): ?>
                    <a href="admin_tarifa_eliminar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-danger">
                        🗑️ Eliminar Definitivamente
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Confirmación adicional para la acción
        document.querySelector('form').addEventListener('submit', function(e) {
            const accion = '<?= $tarifa['activa'] ? 'desactivar' : 'activar' ?>';
            const tipoHabitacion = '<?= htmlspecialchars($tarifa['tipo_habitacion']) ?>';
            const temporada = '<?= htmlspecialchars($tarifa['temporada']) ?>';
            const reservasAfectadas = <?= $reservas_afectadas['total'] ?>;
            
            let mensaje = `¿Confirmar ${accion} la tarifa?\n\n`;
            mensaje += `Tarifa: ${tipoHabitacion} - ${temporada}\n`;
            mensaje += `ID: <?= $tarifa['id_tarifa'] ?>\n`;
            
            if (reservasAfectadas > 0) {
                mensaje += `\nReservas asociadas: ${reservasAfectadas}\n`;
            }
            
            if (accion === 'desactivar') {
                mensaje += `\n✅ Los nuevos huéspedes verán el precio base\n`;
                mensaje += `✅ Las reservas existentes no se afectan\n`;
            } else {
                mensaje += `\n✅ Los nuevos huéspedes verán esta tarifa especial\n`;
                mensaje += `✅ Se aplicará automáticamente\n`;
            }
            
            if (!confirm(mensaje)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-redirect si fue exitoso
        <?php if ($tipo_mensaje === 'success'): ?>
            setTimeout(function() {
                window.location.href = 'admin_tarifas.php';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
$conn->close();
?>