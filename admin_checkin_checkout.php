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

// Función auxiliar para manejo seguro de consultas
function fetch_or_default($query, $default) {
    global $conn;
    try {
        $result = $conn->query($query);
        return $result ? $result->fetch_assoc() : $default;
    } catch (Exception $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return $default;
    }
}

function fetch_all_or_default($query) {
    global $conn;
    try {
        $result = $conn->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Exception $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return [];
    }
}

// Procesar acciones de check-in y check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id_reserva']);
    $accion = $_POST['accion'];

    try {
        $conn->begin_transaction();

        // Obtener la reserva con información completa
        $reserva = fetch_or_default("SELECT r.*, u.nombre as cliente_nombre, h.numero as habitacion_numero 
                                    FROM reservas r
                                    JOIN usuarios u ON r.id_usuario = u.id_usuario
                                    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
                                    WHERE r.id_reserva = $id_reserva", null);

        if ($reserva) {
            $id_habitacion = $reserva['id_habitacion'];
            $fecha_actual = date('Y-m-d H:i:s');
            $fecha_hoy = date('Y-m-d');

            if ($accion === 'checkin' && $reserva['estado'] === 'pendiente') {
                // Validar que la fecha actual sea igual o posterior a la fecha de entrada
                if ($fecha_hoy >= $reserva['fecha_entrada']) {
                    $stmt = $conn->prepare("UPDATE reservas SET estado = 'checkin', fecha_checkin = ? WHERE id_reserva = ?");
                    $stmt->bind_param("si", $fecha_actual, $id_reserva);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE habitaciones SET estado = 'ocupada' WHERE id_habitacion = ?");
                    $stmt->bind_param("i", $id_habitacion);
                    $stmt->execute();

                    // Log de la acción
                    $log_stmt = $conn->prepare("INSERT INTO log_admin (user_id, accion, detalle, fecha) VALUES (?, 'CHECK_IN', ?, NOW())");
                    $detalle = "Check-in: {$reserva['cliente_nombre']} - Habitación {$reserva['habitacion_numero']}";
                    $log_stmt->bind_param("is", $_SESSION['user_id'], $detalle);
                    $log_stmt->execute();

                    $mensaje = "✅ Check-in realizado exitosamente para {$reserva['cliente_nombre']}";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "❌ No se puede hacer check-in antes de la fecha de entrada programada";
                    $tipo_mensaje = "error";
                }

            } elseif ($accion === 'checkout' && $reserva['estado'] === 'checkin') {
                // Permitir check-out en cualquier momento después del check-in
                $stmt = $conn->prepare("UPDATE reservas SET estado = 'completada', fecha_checkout = ? WHERE id_reserva = ?");
                $stmt->bind_param("si", $fecha_actual, $id_reserva);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE habitaciones SET estado = 'disponible' WHERE id_habitacion = ?");
                $stmt->bind_param("i", $id_habitacion);
                $stmt->execute();

                // Log de la acción
                $log_stmt = $conn->prepare("INSERT INTO log_admin (user_id, accion, detalle, fecha) VALUES (?, 'CHECK_OUT', ?, NOW())");
                $detalle = "Check-out: {$reserva['cliente_nombre']} - Habitación {$reserva['habitacion_numero']}";
                $log_stmt->bind_param("is", $_SESSION['user_id'], $detalle);
                $log_stmt->execute();

                $mensaje = "✅ Check-out realizado exitosamente para {$reserva['cliente_nombre']}";
                $tipo_mensaje = "success";

            } else {
                $mensaje = "❌ Acción no válida para el estado actual de la reserva";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "❌ Reserva no encontrada";
            $tipo_mensaje = "error";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "❌ Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener reservas activas (pendientes y en check-in)
$reservas_activas = fetch_all_or_default("
    SELECT 
        r.id_reserva, 
        u.nombre AS cliente,
        u.email AS cliente_email,
        u.telefono AS cliente_telefono,
        h.numero AS habitacion,
        th.nombre AS tipo_habitacion,
        r.fecha_entrada, 
        r.fecha_salida, 
        r.estado,
        r.fecha_checkin,
        r.fecha_checkout,
        r.total as monto_reserva,
        p.estado as estado_pago,
        p.monto as monto_pago
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.estado IN ('pendiente', 'checkin', 'confirmada')
    ORDER BY 
        CASE 
            WHEN r.estado = 'checkin' THEN 1
            WHEN r.estado = 'pendiente' AND r.fecha_entrada = CURDATE() THEN 2
            WHEN r.estado = 'confirmada' AND r.fecha_entrada = CURDATE() THEN 3
            ELSE 4
        END,
        r.fecha_entrada ASC");

// Estadísticas del día
$stats_hoy = fetch_or_default("SELECT 
    COUNT(CASE WHEN r.estado = 'checkin' THEN 1 END) as huespedes_actuales,
    COUNT(CASE WHEN r.fecha_entrada = CURDATE() AND r.estado IN ('pendiente', 'confirmada') THEN 1 END) as checkins_pendientes,
    COUNT(CASE WHEN r.estado = 'checkin' AND r.fecha_salida = CURDATE() THEN 1 END) as checkouts_pendientes,
    COUNT(CASE WHEN DATE(r.fecha_checkin) = CURDATE() THEN 1 END) as checkins_hoy,
    COUNT(CASE WHEN DATE(r.fecha_checkout) = CURDATE() THEN 1 END) as checkouts_hoy
    FROM reservas r", 
    ['huespedes_actuales' => 0, 'checkins_pendientes' => 0, 'checkouts_pendientes' => 0, 'checkins_hoy' => 0, 'checkouts_hoy' => 0]);

// Próximas llegadas (siguientes 3 días)
$proximas_llegadas = fetch_all_or_default("
    SELECT 
        r.fecha_entrada,
        COUNT(*) as cantidad_llegadas,
        GROUP_CONCAT(CONCAT(u.nombre, ' (Hab. ', h.numero, ')') SEPARATOR ', ') as detalles
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE r.fecha_entrada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND r.estado IN ('pendiente', 'confirmada')
    GROUP BY r.fecha_entrada
    ORDER BY r.fecha_entrada ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In y Check-Out - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #e17055 0%, #fdcb6e 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .header-subtitle {
            opacity: 0.9;
            font-size: 1.1em;
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
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
        .btn-secondary { background: rgba(97, 4, 91, 0.71); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-small { padding: 8px 16px; font-size: 12px; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #e17055, #fdcb6e);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #e17055, #fdcb6e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.1em;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .section-header {
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            color: white;
            padding: 20px;
            font-weight: bold;
            font-size: 1.2em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-content {
            padding: 25px;
        }
        .reserva-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
        }
        .reserva-card:hover {
            border-color: #e17055;
            box-shadow: 0 8px 25px rgba(225, 112, 85, 0.15);
            transform: translateY(-2px);
        }
        .reserva-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .cliente-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.3em;
        }
        .cliente-detalles {
            color: #666;
            font-size: 0.9em;
        }
        .reserva-detalles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .detalle-item {
            text-align: center;
        }
        .detalle-label {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .detalle-valor {
            font-weight: bold;
            color: #333;
        }
        .estado-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-checkin { background: #d4edda; color: #155724; }
        .estado-confirmada { background: #cce5ff; color: #004085; }
        .pago-confirmado { background: #d4edda; color: #155724; }
        .pago-pendiente { background: #f8d7da; color: #721c24; }
        .acciones-reserva {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
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
        .proximas-llegadas {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .llegada-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .llegada-item:last-child {
            border-bottom: none;
        }
        .llegada-fecha {
            font-weight: bold;
            color: #e17055;
            margin-bottom: 5px;
        }
        .llegada-cantidad {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .llegada-detalles {
            font-size: 0.9em;
            color: #666;
        }
        .urgente {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        .hoy {
            border-left: 4px solid #ffc107;
            background: #fffdf5;
        }
        .no-reservas {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .reserva-detalles {
                grid-template-columns: 1fr;
            }
            .actions-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header principal -->
        <div class="header">
            <h1>🏨 Check-In y Check-Out</h1>
            <div class="header-subtitle">
                Gestión de llegadas y salidas - <?= date('l, d \d\e F \d\e Y') ?>
            </div>
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Panel Principal</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Reservas</a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">🛏️ Habitaciones</a>
                </div>
                <div>
                    <button onclick="actualizarDatos()" class="btn btn-primary">🔄 Actualizar</button>
                    <button onclick="exportarReporte()" class="btn btn-success">📊 Reporte Diario</button>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas del día -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats_hoy['huespedes_actuales'] ?></div>
                <div class="stat-label">Huéspedes Actuales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_hoy['checkins_pendientes'] ?></div>
                <div class="stat-label">Check-ins Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_hoy['checkouts_pendientes'] ?></div>
                <div class="stat-label">Check-outs Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_hoy['checkins_hoy'] ?></div>
                <div class="stat-label">Check-ins Realizados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_hoy['checkouts_hoy'] ?></div>
                <div class="stat-label">Check-outs Realizados</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Lista de reservas activas -->
            <div class="main-content">
                <div class="section">
                    <div class="section-header">
                        <span>📋 Reservas Activas</span>
                        <span><?= count($reservas_activas) ?> reservas</span>
                    </div>
                    <div class="section-content">
                        <?php if (empty($reservas_activas)): ?>
                            <div class="no-reservas">
                                <h3>🏨 No hay reservas activas</h3>
                                <p>No hay check-ins o check-outs pendientes para hoy.</p>
                                <a href="admin_reservas.php" class="btn btn-primary">Ver todas las reservas</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reservas_activas as $reserva): ?>
                                <?php 
                                $es_hoy_entrada = $reserva['fecha_entrada'] === date('Y-m-d');
                                $es_hoy_salida = $reserva['fecha_salida'] === date('Y-m-d');
                                $clase_urgencia = '';
                                if ($es_hoy_entrada || $es_hoy_salida) {
                                    $clase_urgencia = 'hoy';
                                } elseif ($reserva['fecha_salida'] < date('Y-m-d') && $reserva['estado'] === 'checkin') {
                                    $clase_urgencia = 'urgente';
                                }
                                ?>
                                <div class="reserva-card <?= $clase_urgencia ?>">
                                    <div class="reserva-header">
                                        <div class="cliente-info">
                                            <h3><?= htmlspecialchars($reserva['cliente']) ?></h3>
                                            <div class="cliente-detalles">
                                                📧 <?= htmlspecialchars($reserva['cliente_email']) ?><br>
                                                📞 <?= htmlspecialchars($reserva['cliente_telefono'] ?? 'No disponible') ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="estado-badge estado-<?= $reserva['estado'] ?>">
                                                <?= ucfirst($reserva['estado']) ?>
                                            </span>
                                            <br><br>
                                            <span class="estado-badge <?= $reserva['estado_pago'] === 'confirmado' ? 'pago-confirmado' : 'pago-pendiente' ?>">
                                                Pago: <?= ucfirst($reserva['estado_pago'] ?? 'Pendiente') ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="reserva-detalles">
                                        <div class="detalle-item">
                                            <div class="detalle-label">Habitación</div>
                                            <div class="detalle-valor">#<?= $reserva['habitacion'] ?></div>
                                            <div style="font-size: 0.8em; color: #666; margin-top: 2px;">
                                                <?= htmlspecialchars($reserva['tipo_habitacion']) ?>
                                            </div>
                                        </div>
                                        <div class="detalle-item">
                                            <div class="detalle-label">Fecha Entrada</div>
                                            <div class="detalle-valor"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
                                            <div style="font-size: 0.8em; color: #666; margin-top: 2px;">
                                                <?= $es_hoy_entrada ? '🔥 HOY' : '' ?>
                                            </div>
                                        </div>
                                        <div class="detalle-item">
                                            <div class="detalle-label">Fecha Salida</div>
                                            <div class="detalle-valor"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
                                            <div style="font-size: 0.8em; color: #666; margin-top: 2px;">
                                                <?= $es_hoy_salida ? '🔥 HOY' : '' ?>
                                            </div>
                                        </div>
                                        <div class="detalle-item">
                                            <div class="detalle-label">Monto</div>
                                            <div class="detalle-valor">$<?= number_format($reserva['monto_pago'] ?? $reserva['monto_reserva'] ?? 0, 0, ',', '.') ?></div>
                                        </div>
                                    </div>

                                    <div class="acciones-reserva">
                                        <?php if ($reserva['estado'] === 'pendiente' || $reserva['estado'] === 'confirmada'): ?>
                                            <?php if (date('Y-m-d') >= $reserva['fecha_entrada']): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('¿Realizar check-in para <?= htmlspecialchars($reserva['cliente']) ?>?')">
                                                    <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                                    <input type="hidden" name="accion" value="checkin">
                                                    <button type="submit" class="btn btn-success btn-small">
                                                        ✅ Check-In
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn btn-secondary btn-small" style="cursor: not-allowed; opacity: 0.6;">
                                                    ⏰ Check-in el <?= date('d/m', strtotime($reserva['fecha_entrada'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($reserva['estado'] === 'checkin'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Realizar check-out para <?= htmlspecialchars($reserva['cliente']) ?>?')">
                                                <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                                <input type="hidden" name="accion" value="checkout">
                                                <button type="submit" class="btn btn-warning btn-small">
                                                    🚪 Check-Out
                                                </button>
                                            </form>
                                            
                                            <?php if ($reserva['fecha_checkin']): ?>
                                                <span class="btn btn-info btn-small" style="cursor: default;">
                                                    🕐 Ingresó: <?= date('d/m H:i', strtotime($reserva['fecha_checkin'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="admin_reserva_ver.php?id=<?= $reserva['id_reserva'] ?>" 
                                           class="btn btn-info btn-small">
                                            👁️ Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel lateral -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Próximas llegadas -->
                <div class="proximas-llegadas">
                    <div class="section-header">
                        📅 Próximas Llegadas
                    </div>
                    <div style="padding: 0;">
                        <?php if (empty($proximas_llegadas)): ?>
                            <div style="padding: 20px; text-align: center; color: #666;">
                                <p>No hay llegadas programadas para los próximos 3 días.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($proximas_llegadas as $llegada): ?>
                                <div class="llegada-item">
                                    <div class="llegada-fecha">
                                        <?= date('l, d/m/Y', strtotime($llegada['fecha_entrada'])) ?>
                                        <?= $llegada['fecha_entrada'] === date('Y-m-d') ? '(HOY)' : '' ?>
                                    </div>
                                    <div class="llegada-cantidad">
                                        <?= $llegada['cantidad_llegadas'] ?> llegada<?= $llegada['cantidad_llegadas'] != 1 ? 's' : '' ?>
                                    </div>
                                    <div class="llegada-detalles">
                                        <?= htmlspecialchars($llegada['detalles']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones rápidas -->
                <div class="section">
                    <div class="section-header">
                        ⚡ Acciones Rápidas
                    </div>
                    <div class="section-content">
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <a href="admin_nueva_reserva.php" class="btn btn-primary" style="text-align: center;">
                                ➕ Nueva Reserva
                            </a>
                            <a href="admin_habitaciones.php" class="btn btn-secondary" style="text-align: center;">
                                🛏️ Estado Habitaciones
                            </a>
                            <a href="admin_pagos.php" class="btn btn-info" style="text-align: center;">
                                💳 Gestión de Pagos
                            </a>
                            <button onclick="mostrarReporteRapido()" class="btn btn-warning" style="text-align: center;">
                                📊 Reporte Rápido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para actualizar datos
        function actualizarDatos() {
            location.reload();
        }

        // Función para exportar reporte diario
        function exportarReporte() {
            const fechaActual = new Date().toLocaleDateString('es-CO');
            const nombreArchivo = `checkin_checkout_${fechaActual.replace(/\//g, '_')}.txt`;
            
            let contenido = `REPORTE DIARIO CHECK-IN/CHECK-OUT - HOTEL RIVO\n`;
            contenido += `Fecha: ${fechaActual}\n`;
            contenido += `${'='.repeat(60)}\n\n`;
            
            contenido += `RESUMEN DEL DÍA:\n`;
            contenido += `- Huéspedes actuales: <?= $stats_hoy['huespedes_actuales'] ?>\n`;
            contenido += `- Check-ins pendientes hoy: <?= $stats_hoy['checkins_pendientes'] ?>\n`;
            contenido += `- Check-outs pendientes hoy: <?= $stats_hoy['checkouts_pendientes'] ?>\n`;
            contenido += `- Check-ins realizados: <?= $stats_hoy['checkins_hoy'] ?>\n`;
            contenido += `- Check-outs realizados: <?= $stats_hoy['checkouts_hoy'] ?>\n\n`;
            
            contenido += `RESERVAS ACTIVAS:\n`;
            contenido += `${'='.repeat(40)}\n`;
            
            <?php if (!empty($reservas_activas)): ?>
                <?php foreach ($reservas_activas as $index => $reserva): ?>
                    contenido += `\n${<?= $index + 1 ?>}. <?= addslashes($reserva['cliente']) ?>\n`;
                    contenido += `   Habitación: #<?= $reserva['habitacion'] ?> (<?= addslashes($reserva['tipo_habitacion']) ?>)\n`;
                    contenido += `   Entrada: <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?>\n`;
                    contenido += `   Salida: <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>\n`;
                    contenido += `   Estado: <?= ucfirst($reserva['estado']) ?>\n`;
                    contenido += `   Pago: <?= ucfirst($reserva['estado_pago'] ?? 'Pendiente') ?>\n`;
                    contenido += `   Email: <?= addslashes($reserva['cliente_email']) ?>\n`;
                    <?php if ($reserva['fecha_checkin']): ?>
                        contenido += `   Check-in: <?= date('d/m/Y H:i', strtotime($reserva['fecha_checkin'])) ?>\n`;
                    <?php endif; ?>
                    <?php if ($reserva['fecha_checkout']): ?>
                        contenido += `   Check-out: <?= date('d/m/Y H:i', strtotime($reserva['fecha_checkout'])) ?>\n`;
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                contenido += `\nNo hay reservas activas.\n`;
            <?php endif; ?>
            
            contenido += `\nPRÓXIMAS LLEGADAS:\n`;
            contenido += `${'='.repeat(40)}\n`;
            
            <?php if (!empty($proximas_llegadas)): ?>
                <?php foreach ($proximas_llegadas as $llegada): ?>
                    contenido += `\n<?= date('d/m/Y', strtotime($llegada['fecha_entrada'])) ?>: <?= $llegada['cantidad_llegadas'] ?> llegada(s)\n`;
                    contenido += `   Detalles: <?= addslashes($llegada['detalles']) ?>\n`;
                <?php endforeach; ?>
            <?php else: ?>
                contenido += `\nNo hay llegadas programadas para los próximos 3 días.\n`;
            <?php endif; ?>
            
            contenido += `\n${'='.repeat(60)}\n`;
            contenido += `Reporte generado automáticamente por Sistema Hotel Rivo\n`;
            contenido += `Fecha y hora: ${new Date().toLocaleString('es-CO')}\n`;
            
            // Crear y descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain; charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombreArchivo;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            mostrarNotificacion('📊 Reporte diario exportado exitosamente', 'success');
        }

        // Función para mostrar reporte rápido
        function mostrarReporteRapido() {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
            `;
            
            modal.innerHTML = `
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                ">
                    <h3 style="margin: 0 0 20px 0; color: #333; text-align: center;">📊 Reporte Rápido</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2em; font-weight: bold; color: #e17055;"><?= $stats_hoy['huespedes_actuales'] ?></div>
                            <div style="font-size: 0.9em; color: #666;">Huéspedes Actuales</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2em; font-weight: bold; color: #fdcb6e;"><?= $stats_hoy['checkins_pendientes'] ?></div>
                            <div style="font-size: 0.9em; color: #666;">Check-ins Hoy</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2em; font-weight: bold; color: #6c5ce7;"><?= $stats_hoy['checkouts_pendientes'] ?></div>
                            <div style="font-size: 0.9em; color: #666;">Check-outs Hoy</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-size: 2em; font-weight: bold; color: #00b894;"><?= count($reservas_activas) ?></div>
                            <div style="font-size: 0.9em; color: #666;">Reservas Activas</div>
                        </div>
                    </div>
                    
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #1565c0;">Estado del Hotel</h4>
                        <div style="font-size: 0.9em; color: #666;">
                            Ocupación actual: <strong><?= $stats_hoy['huespedes_actuales'] ?> habitaciones ocupadas</strong><br>
                            Actividad del día: <strong><?= $stats_hoy['checkins_hoy'] + $stats_hoy['checkouts_hoy'] ?> movimientos</strong><br>
                            Pendientes: <strong><?= $stats_hoy['checkins_pendientes'] + $stats_hoy['checkouts_pendientes'] ?> operaciones</strong>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button onclick="exportarReporte(); cerrarModal();" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer;">
                            📥 Exportar Completo
                        </button>
                        <button onclick="cerrarModal();" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">
                            Cerrar
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            window.cerrarModal = function() {
                document.body.removeChild(modal);
                delete window.cerrarModal;
            };
            
            // Cerrar con clic fuera del modal
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cerrarModal();
                }
            });
        }

        // Sistema de notificaciones
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const existentes = document.querySelectorAll('.notificacion-checkin');
            if (existentes.length >= 3) existentes[0].remove();
            
            const notif = document.createElement('div');
            notif.className = 'notificacion-checkin';
            
            const colores = {
                'success': '#28a745',
                'info': '#007bff',
                'warning': '#ffc107',
                'error': '#dc3545'
            };
            
            const iconos = {
                'success': '✅',
                'info': 'ℹ️',
                'warning': '⚠️',
                'error': '❌'
            };
            
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${colores[tipo] || colores.info};
                color: ${tipo === 'warning' ? '#212529' : 'white'};
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideInRight 0.3s ease;
                max-width: 350px;
                word-wrap: break-word;
                font-weight: 500;
            `;
            
            notif.innerHTML = `${iconos[tipo] || iconos.info} ${mensaje}`;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                if (notif.parentNode) {
                    notif.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        if (notif.parentNode) notif.remove();
                    }, 300);
                }
            }, tipo === 'error' ? 5000 : 3000);
        }

        // Auto-actualización cada 2 minutos
        setInterval(function() {
            console.log('🔄 Verificando actualizaciones automáticas...');
            // En un entorno real, podrías hacer una petición AJAX aquí
        }, 120000);

        // Función para resaltar reservas urgentes
        function resaltarReservasUrgentes() {
            const reservasHoy = document.querySelectorAll('.hoy');
            const reservasUrgentes = document.querySelectorAll('.urgente');
            
            reservasHoy.forEach(reserva => {
                reserva.style.animation = 'pulseWarning 2s infinite';
            });
            
            reservasUrgentes.forEach(reserva => {
                reserva.style.animation = 'pulseError 2s infinite';
            });
        }

        // Sonido de notificación (opcional)
        function reproducirSonidoNotificacion() {
            // Crear un contexto de audio simple
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elementos = document.querySelectorAll('.stat-card, .section, .proximas-llegadas');
            elementos.forEach((elemento, index) => {
                elemento.style.opacity = '0';
                elemento.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    elemento.style.transition = 'all 0.6s ease';
                    elemento.style.opacity = '1';
                    elemento.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animar tarjetas de reservas
            setTimeout(() => {
                const tarjetas = document.querySelectorAll('.reserva-card');
                tarjetas.forEach((tarjeta, index) => {
                    tarjeta.style.opacity = '0';
                    tarjeta.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        tarjeta.style.transition = 'all 0.6s ease';
                        tarjeta.style.opacity = '1';
                        tarjeta.style.transform = 'translateX(0)';
                    }, index * 100);
                });
            }, 500);

            // Resaltar reservas urgentes después de cargar
            setTimeout(resaltarReservasUrgentes, 1000);
        });

        // CSS para animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            @keyframes pulseWarning {
                0%, 100% { box-shadow: 0 0 10px rgba(255, 193, 7, 0.5); }
                50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.8); }
            }
            @keyframes pulseError {
                0%, 100% { box-shadow: 0 0 10px rgba(220, 53, 69, 0.5); }
                50% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8); }
            }
            @media print {
                .btn, .actions-header { display: none !important; }
                .reserva-card { page-break-inside: avoid; }
                body { background: white !important; }
                .header { background: #e17055 !important; -webkit-print-color-adjust: exact; }
            }
        `;
        document.head.appendChild(style);

        // Ocultar mensajes automáticamente
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if (mensaje) {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (mensaje.parentNode) {
                        mensaje.remove();
                    }
                }, 500);
            }
        }, 5000);

        console.log('🏨 Sistema de Check-in/Check-out inicializado correctamente');
    </script>
</body>
</html>