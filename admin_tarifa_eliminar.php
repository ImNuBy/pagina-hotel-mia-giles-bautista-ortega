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
        WHEN CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN CURDATE() < t.fecha_inicio THEN 'futura'
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

// Verificar si hay reservas asociadas
$reservas_asociadas = $conn->query("SELECT COUNT(*) as total FROM reservas r
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE h.id_tipo = {$tarifa['id_tipo']}
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'
    AND r.estado IN ('confirmada', 'pendiente')")->fetch_assoc();

$mensaje = '';
$tipo_mensaje = '';

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_eliminacion'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $mensaje = "Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        try {
            $conn->begin_transaction();
            
            // Eliminar la tarifa
            $stmt_delete = $conn->prepare("DELETE FROM tarifas WHERE id_tarifa = ?");
            $stmt_delete->bind_param("i", $id);
            
            if ($stmt_delete->execute()) {
                $conn->commit();
                
                // Log de la eliminación (opcional)
                $log_stmt = $conn->prepare("INSERT INTO log_admin (user_id, accion, detalle, fecha) VALUES (?, 'ELIMINAR_TARIFA', ?, NOW())");
                $detalle = "Eliminó tarifa ID: {$id} - {$tarifa['tipo_habitacion']} - {$tarifa['temporada']}";
                $log_stmt->bind_param("is", $_SESSION['user_id'], $detalle);
                $log_stmt->execute();
                
                header('Location: admin_tarifas.php?msg=tarifa_eliminada');
                exit;
            } else {
                throw new Exception("Error al eliminar la tarifa");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

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
    <title>Eliminar Tarifa - Hotel Rivo</title>
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
            padding: 30px;
            margin-bottom: 30px;
        }
        .danger-card {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
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
        .precio-final {
            font-size: 1.5em;
            font-weight: bold;
            color: #dc3545;
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
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .checkbox-confirmation {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .checkbox-confirmation label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        .checkbox-confirmation input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        #btn-eliminar:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>🗑️ Eliminar Tarifa</h1>
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
            </div>
        <?php endif; ?>

        <!-- Información de la tarifa a eliminar -->
        <div class="tarifa-info">
            <h3>📋 Información de la Tarifa a Eliminar</h3>
            
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
                    <div class="info-label">Estado</div>
                    <div class="info-value"><?= ucfirst($tarifa['estado_tarifa']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Precio Final</div>
                    <div class="precio-final">$<?= number_format($tarifa['precio_final'], 0, ',', '.') ?></div>
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

        <!-- Advertencias -->
        <?php if ($reservas_asociadas['total'] > 0): ?>
            <div class="danger-card">
                <h3>🚨 ADVERTENCIA CRÍTICA</h3>
                <p><strong>Esta tarifa tiene <?= $reservas_asociadas['total'] ?> reserva(s) asociada(s).</strong></p>
                <p>Eliminar esta tarifa puede afectar:</p>
                <ul>
                    <li>Cálculos de precios de reservas existentes</li>
                    <li>Reportes históricos</li>
                    <li>Referencias en el sistema de pagos</li>
                </ul>
                <p><strong>🔧 Recomendación:</strong> En lugar de eliminar, considera <strong>desactivar</strong> la tarifa para mantener la integridad de los datos.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_tarifa_desactivar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                        ⏸️ Desactivar en lugar de eliminar
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="warning-card">
                <h3>⚠️ Confirmación de Eliminación</h3>
                <p>Esta acción eliminará permanentemente la tarifa del sistema.</p>
                <p><strong>No se puede deshacer esta operación.</strong></p>
                <p>La tarifa no tiene reservas asociadas, por lo que es seguro eliminarla.</p>
            </div>
        <?php endif; ?>

        <!-- Formulario de confirmación -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h3>🔐 Confirmación Requerida</h3>
            
            <form method="POST" id="form-eliminar">
                <input type="hidden" name="confirmar_eliminacion" value="1">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="checkbox-confirmation">
                    <label>
                        <input type="checkbox" id="confirmar" required onchange="toggleBotonEliminar()">
                        Entiendo que esta acción es <strong>IRREVERSIBLE</strong> y eliminará permanentemente la tarifa
                    </label>
                </div>
                
                <div class="checkbox-confirmation">
                    <label>
                        <input type="checkbox" id="confirmar2" required onchange="toggleBotonEliminar()">
                        He revisado que <?php if ($reservas_asociadas['total'] > 0): ?>esta tarifa tiene reservas asociadas y aún así<?php else: ?>no hay reservas asociadas y<?php endif; ?> deseo continuar
                    </label>
                </div>
                
                <div class="checkbox-confirmation">
                    <label>
                        <input type="checkbox" id="confirmar3" required onchange="toggleBotonEliminar()">
                        Confirmo que soy administrador autorizado para realizar esta acción
                    </label>
                </div>
                
                <div class="actions">
                    <a href="admin_tarifas.php" class="btn btn-secondary">❌ Cancelar</a>
                    <button type="submit" id="btn-eliminar" class="btn btn-danger" disabled>
                        🗑️ ELIMINAR TARIFA PERMANENTEMENTE
                    </button>
                </div>
            </form>
        </div>

        <!-- Información adicional -->
        <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin-top: 30px; border-left: 4px solid #007bff;">
            <h4>💡 Alternativas a la Eliminación</h4>
            <ul>
                <li><strong>Desactivar:</strong> Mantiene la tarifa en el sistema pero la marca como inactiva</li>
                <li><strong>Modificar fechas:</strong> Cambia el período de validez para que no afecte reservas futuras</li>
                <li><strong>Duplicar y modificar:</strong> Crea una nueva versión de la tarifa con cambios</li>
            </ul>
            
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="admin_tarifa_desactivar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                    ⏸️ Desactivar Tarifa
                </a>
                <a href="admin_tarifa_editar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                    ✏️ Modificar Tarifa
                </a>
                <a href="admin_tarifa_duplicar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">
                    📋 Duplicar Tarifa
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleBotonEliminar() {
            const confirmar1 = document.getElementById('confirmar').checked;
            const confirmar2 = document.getElementById('confirmar2').checked;
            const confirmar3 = document.getElementById('confirmar3').checked;
            const btnEliminar = document.getElementById('btn-eliminar');
            
            btnEliminar.disabled = !(confirmar1 && confirmar2 && confirmar3);
        }
        
        // Validación adicional antes del envío
        document.getElementById('form-eliminar').addEventListener('submit', function(e) {
            const tipoHabitacion = '<?= htmlspecialchars($tarifa['tipo_habitacion']) ?>';
            const temporada = '<?= htmlspecialchars($tarifa['temporada']) ?>';
            const reservasAsociadas = <?= $reservas_asociadas['total'] ?>;
            
            let mensaje = `¿CONFIRMAR ELIMINACIÓN PERMANENTE?\n\n`;
            mensaje += `Tarifa: ${tipoHabitacion} - ${temporada}\n`;
            mensaje += `ID: <?= $tarifa['id_tarifa'] ?>\n`;
            
            if (reservasAsociadas > 0) {
                mensaje += `\n⚠️ ATENCIÓN: ${reservasAsociadas} reserva(s) asociada(s)\n`;
            }
            
            mensaje += `\n🚨 ESTA ACCIÓN NO SE PUEDE DESHACER\n\n`;
            mensaje += `Escribe "ELIMINAR" para confirmar:`;
            
            const confirmacion = prompt(mensaje);
            if (confirmacion !== 'ELIMINAR') {
                e.preventDefault();
                alert('Eliminación cancelada. Debe escribir exactamente "ELIMINAR" para confirmar.');
                return false;
            }
            
            // Confirmación final
            if (!confirm('ÚLTIMA CONFIRMACIÓN:\n\n¿Eliminar la tarifa PERMANENTEMENTE?\n\nEsta es tu última oportunidad para cancelar.')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Prevenir acciones accidentales
        document.addEventListener('keydown', function(e) {
            // Bloquear F5 para evitar recargas accidentales
            if (e.key === 'F5') {
                e.preventDefault();
                alert('Recarga de página bloqueada para evitar acciones accidentales.');
            }
        });
        
        // Advertencia al salir
        window.addEventListener('beforeunload', function(e) {
            const formModificado = document.querySelector('input[type="checkbox"]:checked');
            if (formModificado) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>