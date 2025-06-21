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

$id_reserva = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_reserva <= 0) {
    header('Location: admin_reservas.php');
    exit;
}

// Obtener detalles completos de la reserva
$reserva_query = "SELECT 
    r.*,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    u.estado as cliente_estado,
    h.numero as numero_habitacion,
    th.nombre as tipo_habitacion,
    th.precio_noche,
    th.capacidad,
    p.id_pago,
    p.monto as monto_pagado,
    p.estado as estado_pago,
    p.fecha_pago
    FROM reservas r
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_reserva = $id_reserva";

$result = $conn->query($reserva_query);

if ($result->num_rows == 0) {
    header('Location: admin_reservas.php');
    exit;
}

$reserva = $result->fetch_assoc();

// Calcular información adicional
$fecha_entrada = new DateTime($reserva['fecha_entrada']);
$fecha_salida = new DateTime($reserva['fecha_salida']);
$noches = $fecha_entrada->diff($fecha_salida)->days;
$total_estimado = $reserva['precio_noche'] * $noches;
$hoy = new DateTime();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva #<?= $reserva['id_reserva'] ?> - Hotel Rivo</title>
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
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .reserva-card {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .reserva-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .estado-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: bold;
            text-transform: uppercase;
            background: rgba(255,255,255,0.2);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .info-card h3 {
            margin: 0 0 20px 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        .value {
            color: #333;
        }
        .monto-destacado {
            font-size: 2.5em;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        .timeline {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 20px 0;
        }
        .timeline-header {
            background: #007bff;
            color: white;
            padding: 20px;
        }
        .timeline-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 50px;
        }
        .timeline-item:last-child {
            border-bottom: none;
        }
        .timeline-icon {
            position: absolute;
            left: 20px;
            top: 25px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
        }
        .timeline-icon.completed {
            background: #28a745;
        }
        .timeline-icon.current {
            background: #ffc107;
        }
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin: 5px;
            display: inline-block;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .cliente-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 1.5em;
        }
        .cliente-info {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="actions-header">
                <div>
                    <a href="admin_reservas.php" class="btn btn-secondary">← Volver a Reservas</a>
                </div>
                <div>
                    <a href="admin_reserva_editar.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-primary">✏️ Editar Reserva</a>
                    <?php if ($reserva['monto_pagado'] && $reserva['estado_pago'] == 'confirmado'): ?>
                        <a href="admin_generar_factura.php?reserva=<?= $reserva['id_reserva'] ?>" class="btn btn-success">📄 Generar Factura</a>
                    <?php endif; ?>
                    <a href="admin_usuario_ver.php?id=<?= $reserva['id_usuario'] ?>" class="btn btn-info">👤 Ver Cliente</a>
                </div>
            </div>
        </div>

        <!-- Encabezado de la Reserva -->
        <div class="reserva-card">
            <div class="reserva-header">
                <div>
                    <h1>📅 Reserva #<?= $reserva['id_reserva'] ?></h1>
                    <p>Realizada el <?= date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])) ?></p>
                </div>
                <div class="estado-badge">
                    <?= ucfirst($reserva['estado']) ?>
                </div>
            </div>
        </div>

        <!-- Alertas según estado -->
        <?php if ($reserva['estado'] == 'pendiente' && $fecha_entrada <= $hoy->modify('+2 days')): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Atención:</strong> Esta reserva tiene check-in próximo y aún está pendiente de confirmación.
        </div>
        <?php endif; ?>

        <?php if ($reserva['estado_pago'] == 'pendiente'): ?>
        <div class="alert alert-warning">
            <strong>💳 Pago Pendiente:</strong> El pago de esta reserva aún no ha sido confirmado.
        </div>
        <?php endif; ?>

        <?php if ($reserva['cliente_estado'] == 'inactivo'): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Cliente Inactivo:</strong> La cuenta del cliente está desactivada.
        </div>
        <?php endif; ?>

        <!-- Monto Total -->
        <div class="monto-destacado">
            $<?= number_format($total_estimado, 0, ',', '.') ?>
            <div style="font-size: 0.4em; color: #666; margin-top: 10px;">
                Total estimado (<?= $noches ?> noche<?= $noches != 1 ? 's' : '' ?>)
            </div>
        </div>

        <!-- Información Detallada -->
        <div class="info-grid">
            <!-- Cliente -->
            <div class="info-card">
                <h3>👤 Información del Cliente</h3>
                <div class="cliente-info">
                    <div class="cliente-avatar">
                        <?= strtoupper(substr($reserva['cliente_nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight: bold; font-size: 1.2em;"><?= htmlspecialchars($reserva['cliente_nombre']) ?></div>
                        <div style="color: #666;"><?= htmlspecialchars($reserva['cliente_email']) ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <span class="label">ID Cliente:</span>
                    <span class="value"><?= $reserva['id_usuario'] ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Estado de Cuenta:</span>
                    <span class="value"><?= ucfirst($reserva['cliente_estado']) ?></span>
                </div>
            </div>

            <!-- Habitación -->
            <div class="info-card">
                <h3>🏨 Información de la Habitación</h3>
                <div class="info-row">
                    <span class="label">Habitación:</span>
                    <span class="value">#<?= $reserva['numero_habitacion'] ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Tipo:</span>
                    <span class="value"><?= htmlspecialchars($reserva['tipo_habitacion']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Capacidad:</span>
                    <span class="value"><?= $reserva['capacidad'] ?> personas</span>
                </div>
                <div class="info-row">
                    <span class="label">Precio por noche:</span>
                    <span class="value">$<?= number_format($reserva['precio_noche'], 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Fechas -->
            <div class="info-card">
                <h3>📅 Fechas de Estadía</h3>
                <div class="info-row">
                    <span class="label">Check-in:</span>
                    <span class="value"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Check-out:</span>
                    <span class="value"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Número de noches:</span>
                    <span class="value"><?= $noches ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Días hasta check-in:</span>
                    <span class="value">
                        <?php 
                        $dias_hasta = $hoy->diff($fecha_entrada)->days;
                        if ($fecha_entrada < $hoy) {
                            echo "Ya pasó";
                        } elseif ($fecha_entrada == $hoy) {
                            echo "Hoy";
                        } else {
                            echo $dias_hasta . " días";
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- Pago -->
            <div class="info-card">
                <h3>💳 Información de Pago</h3>
                <?php if ($reserva['monto_pagado']): ?>
                    <div class="info-row">
                        <span class="label">ID de Pago:</span>
                        <span class="value">#<?= $reserva['id_pago'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Monto Pagado:</span>
                        <span class="value">$<?= number_format($reserva['monto_pagado'], 0, ',', '.') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Estado del Pago:</span>
                        <span class="value"><?= ucfirst($reserva['estado_pago']) ?></span>
                    </div>
                    <?php if ($reserva['fecha_pago']): ?>
                    <div class="info-row">
                        <span class="label">Fecha de Pago:</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($reserva['fecha_pago'])) ?></span>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Sin pago registrado</strong><br>
                        Esta reserva no tiene pagos asociados.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline de la Reserva -->
        <div class="timeline">
            <div class="timeline-header">
                <h3>📊 Timeline de la Reserva</h3>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon completed"></div>
                <strong>Reserva Creada</strong><br>
                <?= date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])) ?>
            </div>
            
            <?php if ($reserva['fecha_pago']): ?>
            <div class="timeline-item">
                <div class="timeline-icon completed"></div>
                <strong>Pago <?= ucfirst($reserva['estado_pago']) ?></strong><br>
                <?= date('d/m/Y H:i', strtotime($reserva['fecha_pago'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="timeline-item">
                <div class="timeline-icon <?= $fecha_entrada <= $hoy ? 'completed' : 'current' ?>"></div>
                <strong>Check-in Programado</strong><br>
                <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?>
            </div>
            
            <div class="timeline-item">
                <div class="timeline-icon <?= $fecha_salida <= $hoy ? 'completed' : '' ?>"></div>
                <strong>Check-out Programado</strong><br>
                <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
            </div>
        </div>

        <!-- Acciones -->
        <div style="text-align: center; padding: 30px;">
            <?php if ($reserva['estado'] == 'pendiente'): ?>
                <form action="admin_confirmar_reserva.php" method="POST" style="display: inline-block;">
                    <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Confirmar esta reserva?')">
                        ✅ Confirmar Reserva
                    </button>
                </form>
                
                <form action="admin_cancelar_reserva.php" method="POST" style="display: inline-block;">
                    <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('¿Cancelar esta reserva?')">
                        ❌ Cancelar Reserva
                    </button>
                </form>
            <?php elseif ($reserva['estado'] == 'confirmada' && $fecha_salida <= $hoy): ?>
                <form action="admin_completar_reserva.php" method="POST" style="display: inline-block;">
                    <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Marcar como completada?')">
                        ✅ Marcar como Completada
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>