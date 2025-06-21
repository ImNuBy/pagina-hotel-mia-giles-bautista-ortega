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

$id_usuario = intval($_GET['id'] ?? 0);

if ($id_usuario <= 0) {
    header('Location: admin_usuarios.php');
    exit;
}

// Obtener información completa del usuario
$usuario_query = "SELECT * FROM usuarios WHERE id_usuario = $id_usuario";
$usuario_result = $conn->query($usuario_query);

if ($usuario_result->num_rows == 0) {
    header('Location: admin_usuarios.php');
    exit;
}

$usuario = $usuario_result->fetch_assoc();

// Obtener estadísticas del usuario
$stats = $conn->query("SELECT 
    COUNT(*) as total_reservas,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as reservas_pendientes,
    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as reservas_completadas,
    MIN(fecha_reserva) as primera_reserva,
    MAX(fecha_reserva) as ultima_reserva
    FROM reservas WHERE id_usuario = $id_usuario")->fetch_assoc();

// Obtener información financiera
$gastos = $conn->query("SELECT 
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as total_gastado,
    COUNT(p.id_pago) as total_pagos,
    SUM(CASE WHEN p.estado = 'pendiente' THEN p.monto ELSE 0 END) as monto_pendiente
    FROM reservas r 
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_usuario = $id_usuario")->fetch_assoc();

// Obtener últimas 5 reservas
$ultimas_reservas = $conn->query("
    SELECT r.*, 
           h.numero AS numero_habitacion,
           th.nombre AS tipo_habitacion,
           p.monto as monto_pagado,
           p.estado as estado_pago
    FROM reservas r 
    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion 
    JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_usuario = $id_usuario
    ORDER BY r.fecha_reserva DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?= htmlspecialchars($usuario['nombre']) ?> - Hotel Rivo</title>
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
        .usuario-card {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .usuario-info {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        .usuario-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            font-weight: bold;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .usuario-datos h1 {
            margin: 0 0 15px 0;
            font-size: 2.5em;
        }
        .usuario-datos p {
            margin: 8px 0;
            font-size: 1.2em;
            opacity: 0.9;
        }
        .estado-usuario {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 1em;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            margin-top: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-number.total { color: #007bff; }
        .stat-number.confirmadas { color: #28a745; }
        .stat-number.pendientes { color: #ffc107; }
        .stat-number.canceladas { color: #dc3545; }
        .stat-money {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #28a745;
        }
        .info-cards {
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
        .reservas-recientes {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .reservas-header {
            background: #007bff;
            color: white;
            padding: 20px;
        }
        .reserva-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .reserva-item:last-child {
            border-bottom: none;
        }
        .reserva-info {
            flex: 1;
        }
        .reserva-id {
            font-weight: bold;
            color: #007bff;
            font-size: 1.1em;
        }
        .reserva-detalles {
            color: #666;
            margin: 5px 0;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-confirmada { background: #d4edda; color: #155724; }
        .estado-cancelada { background: #f8d7da; color: #721c24; }
        .estado-completada { background: #e2e3e5; color: #383d41; }
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
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .no-reservas {
            text-align: center;
            padding: 40px 20px;
            color: #666;
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
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="actions-header">
                <div>
                    <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a Usuarios</a>
                </div>
                <div>
                    <a href="admin_usuario_editar.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-primary">✏️ Editar Usuario</a>
                    <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-info">📋 Historial Completo</a>
                    <a href="admin_nueva_reserva.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-success">➕ Nueva Reserva</a>
                </div>
            </div>
        </div>

        <!-- Perfil del Usuario -->
        <div class="usuario-card">
            <div class="usuario-info">
                <div class="usuario-avatar">
                    <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                </div>
                <div class="usuario-datos">
                    <h1><?= htmlspecialchars($usuario['nombre']) ?></h1>
                    <p>📧 <?= htmlspecialchars($usuario['email']) ?></p>
                    <p>🆔 ID de Usuario: <?= $usuario['id_usuario'] ?></p>
                    <p>📅 Miembro desde: <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
                    <span class="estado-usuario">
                        <?= $usuario['estado'] === 'activo' ? '✅ Usuario Activo' : '❌ Usuario Inactivo' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_reservas'] ?></div>
                <div>Total de Reservas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmadas"><?= $stats['reservas_confirmadas'] ?></div>
                <div>Reservas Confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($gastos['total_gastado'] ?? 0, 0, ',', '.') ?></div>
                <div>Total Gastado</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pendientes"><?= $stats['reservas_pendientes'] ?></div>
                <div>Reservas Pendientes</div>
            </div>
        </div>

        <!-- Información Detallada -->
        <div class="info-cards">
            <div class="info-card">
                <h3>👤 Información Personal</h3>
                <div class="info-row">
                    <span class="label">Nombre Completo:</span>
                    <span class="value"><?= htmlspecialchars($usuario['nombre']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span class="value"><?= htmlspecialchars($usuario['email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Estado de Cuenta:</span>
                    <span class="value"><?= ucfirst($usuario['estado']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Rol en el Sistema:</span>
                    <span class="value"><?= ucfirst($usuario['rol']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Fecha de Registro:</span>
                    <span class="value"><?= date('d/m/Y H:i', strtotime($usuario['fecha_registro'])) ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>📊 Estadísticas de Actividad</h3>
                <?php if ($stats['total_reservas'] > 0): ?>
                    <div class="info-row">
                        <span class="label">Primera Reserva:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($stats['primera_reserva'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Última Reserva:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($stats['ultima_reserva'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Promedio por Reserva:</span>
                        <span class="value">$<?= $gastos['total_pagos'] > 0 ? number_format($gastos['total_gastado'] / $gastos['total_pagos'], 0, ',', '.') : '0' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Tasa de Cancelación:</span>
                        <span class="value"><?= $stats['total_reservas'] > 0 ? round(($stats['reservas_canceladas'] / $stats['total_reservas']) * 100, 1) : 0 ?>%</span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Este usuario aún no ha realizado ninguna reserva.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($gastos['monto_pendiente'] > 0): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Atención:</strong> Este usuario tiene $<?= number_format($gastos['monto_pendiente'], 0, ',', '.') ?> en pagos pendientes.
        </div>
        <?php endif; ?>

        <?php if ($usuario['estado'] === 'inactivo'): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Usuario Inactivo:</strong> Esta cuenta está desactivada y no puede realizar nuevas reservas.
        </div>
        <?php endif; ?>

        <!-- Reservas Recientes -->
        <div class="reservas-recientes">
            <div class="reservas-header">
                <h3>📅 Últimas Reservas</h3>
            </div>
            
            <?php if ($ultimas_reservas->num_rows > 0): ?>
                <?php while ($reserva = $ultimas_reservas->fetch_assoc()): ?>
                    <div class="reserva-item">
                        <div class="reserva-info">
                            <div class="reserva-id">Reserva #<?= $reserva['id_reserva'] ?></div>
                            <div class="reserva-detalles">
                                🏨 Habitación #<?= $reserva['numero_habitacion'] ?> - <?= htmlspecialchars($reserva['tipo_habitacion']) ?>
                            </div>
                            <div class="reserva-detalles">
                                📅 <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?> - <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
                            </div>
                            <?php if ($reserva['monto_pagado']): ?>
                                <div class="reserva-detalles">
                                    💰 $<?= number_format($reserva['monto_pagado'], 0, ',', '.') ?> - <?= ucfirst($reserva['estado_pago']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="estado-badge estado-<?= $reserva['estado'] ?>">
                                <?= ucfirst($reserva['estado']) ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <div style="text-align: center; padding: 20px;">
                    <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-primary">Ver Historial Completo</a>
                </div>
            <?php else: ?>
                <div class="no-reservas">
                    <h3>📅 Sin Reservas</h3>
                    <p>Este usuario no ha realizado reservas aún.</p>
                    <a href="admin_nueva_reserva.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-success">➕ Crear Primera Reserva</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>