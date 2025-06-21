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

$id_pago = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pago <= 0) {
    header('Location: admin_pagos.php');
    exit;
}

// Obtener detalles completos del pago
$pago_query = "SELECT 
    p.*,
    r.fecha_entrada,
    r.fecha_salida,
    r.estado as estado_reserva,
    r.fecha_reserva,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    u.telefono as cliente_telefono,
    th.nombre as tipo_habitacion,
    th.precio_noche,
    h.numero as numero_habitacion
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    WHERE p.id_pago = $id_pago";

$result = $conn->query($pago_query);

if ($result->num_rows == 0) {
    header('Location: admin_pagos.php');
    exit;
}

$pago = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pago #<?= $pago['id_pago'] ?> - Hotel Rivo</title>
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
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
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-confirmado { background: #d4edda; color: #155724; }
        .estado-rechazado { background: #f8d7da; color: #721c24; }
        .monto-destacado {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
            display: inline-block;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .acciones {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Detalle de Pago</h1>
            <h2>Pago #<?= $pago['id_pago'] ?></h2>
        </div>
        
        <div class="content">
            <div class="monto-destacado">
                $<?= number_format($pago['monto'], 0, ',', '.') ?>
            </div>
            
            <div class="info-grid">
                <!-- Información del Pago -->
                <div class="info-card">
                    <h3>💰 Información del Pago</h3>
                    <div class="info-row">
                        <span class="label">ID de Pago:</span>
                        <span class="value">#<?= $pago['id_pago'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Estado:</span>
                        <span class="value">
                            <span class="estado-badge estado-<?= $pago['estado'] ?>">
                                <?= ucfirst($pago['estado']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Fecha de Pago:</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">ID de Reserva:</span>
                        <span class="value">#<?= $pago['id_reserva'] ?></span>
                    </div>
                </div>
                
                <!-- Información del Cliente -->
                <div class="info-card">
                    <h3>👤 Información del Cliente</h3>
                    <div class="info-row">
                        <span class="label">Nombre:</span>
                        <span class="value"><?= htmlspecialchars($pago['cliente_nombre']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value"><?= htmlspecialchars($pago['cliente_email']) ?></span>
                    </div>
                    <?php if ($pago['cliente_telefono']): ?>
                    <div class="info-row">
                        <span class="label">Teléfono:</span>
                        <span class="value"><?= htmlspecialchars($pago['cliente_telefono']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-grid">
                <!-- Información de la Reserva -->
                <div class="info-card">
                    <h3>📅 Información de la Reserva</h3>
                    <div class="info-row">
                        <span class="label">Fecha de Reserva:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($pago['fecha_reserva'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Check-in:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($pago['fecha_entrada'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Check-out:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($pago['fecha_salida'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Estado Reserva:</span>
                        <span class="value"><?= ucfirst($pago['estado_reserva']) ?></span>
                    </div>
                </div>
                
                <!-- Información de la Habitación -->
                <div class="info-card">
                    <h3>🏨 Información de la Habitación</h3>
                    <div class="info-row">
                        <span class="label">Habitación:</span>
                        <span class="value">#<?= $pago['numero_habitacion'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Tipo:</span>
                        <span class="value"><?= htmlspecialchars($pago['tipo_habitacion']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Precio por noche:</span>
                        <span class="value">$<?= number_format($pago['precio_noche'], 0, ',', '.') ?></span>
                    </div>
                    <?php
                    $noches = (strtotime($pago['fecha_salida']) - strtotime($pago['fecha_entrada'])) / (60*60*24);
                    ?>
                    <div class="info-row">
                        <span class="label">Número de noches:</span>
                        <span class="value"><?= $noches ?></span>
                    </div>
                </div>
            </div>
            
            <div class="acciones">
                <?php if ($pago['estado'] == 'pendiente'): ?>
                    <form action="admin_confirmar_pago.php" method="POST" style="display: inline-block;">
                        <input type="hidden" name="id_pago" value="<?= $pago['id_pago'] ?>">
                        <button type="submit" class="btn btn-success" 
                                onclick="return confirm('¿Confirmar el pago de $<?= number_format($pago['monto'], 0, ',', '.') ?>?')">
                            ✅ Confirmar Pago
                        </button>
                    </form>
                    
                    <form action="admin_rechazar_pago.php" method="POST" style="display: inline-block;">
                        <input type="hidden" name="id_pago" value="<?= $pago['id_pago'] ?>">
                        <button type="submit" class="btn btn-danger"
                                onclick="return confirm('¿Rechazar este pago?')">
                            ❌ Rechazar Pago
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="admin_reservas.php?id=<?= $pago['id_reserva'] ?>" class="btn btn-primary">
                    📅 Ver Reserva Completa
                </a>
                
                <a href="admin_pagos.php" class="btn btn-secondary">
                    ← Volver a Pagos
                </a>
            </div>
        </div>
    </div>
</body>
</html>