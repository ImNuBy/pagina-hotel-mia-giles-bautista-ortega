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
    echo "<p>Usuario no encontrado.</p>";
    exit;
}

$usuario = $usuario_result->fetch_assoc();

// Obtener reservas con información completa
$reservas = $conn->query("
    SELECT r.*, 
           h.numero AS numero_habitacion,
           th.nombre AS tipo_habitacion,
           th.precio_noche,
           p.monto as monto_pagado,
           p.estado as estado_pago,
           p.fecha_pago
    FROM reservas r 
    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion 
    JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_usuario = $id_usuario
    ORDER BY r.fecha_reserva DESC
");

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

// Obtener total gastado (solo pagos confirmados)
$gastos = $conn->query("SELECT 
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as total_gastado,
    COUNT(p.id_pago) as total_pagos,
    SUM(CASE WHEN p.estado = 'pendiente' THEN p.monto ELSE 0 END) as monto_pendiente
    FROM reservas r 
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_usuario = $id_usuario")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de <?= htmlspecialchars($usuario['nombre']) ?> - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
            gap: 20px;
        }
        .usuario-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .usuario-datos h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        .usuario-datos p {
            margin: 5px 0;
            font-size: 1.1em;
            opacity: 0.9;
        }
        .estado-usuario {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            margin-top: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
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
        .stat-number.gastado { color: #17a2b8; }
        .stat-money {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #28a745;
        }
        .reservas-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: #007bff;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .reserva-id {
            font-weight: bold;
            color: #007bff;
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
        .habitacion-info {
            font-size: 0.9em;
            color: #666;
        }
        .fechas-info {
            font-size: 0.9em;
        }
        .fecha-entrada {
            color: #28a745;
            font-weight: bold;
        }
        .fecha-salida {
            color: #dc3545;
            font-weight: bold;
        }
        .pago-info {
            font-size: 0.9em;
        }
        .pago-confirmado { color: #28a745; }
        .pago-pendiente { color: #ffc107; }
        .pago-rechazado { color: #dc3545; }
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            margin: 2px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
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
            margin-bottom: 20px;
        }
        .no-reservas {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .timeline-item {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
        }
        .monto-cell {
            color: #28a745;
            font-weight: bold;
        }
        .acciones {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .resumen-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="actions-header">
                <div>
                    <a href="admin_usuarios.php" class="btn btn-secondary">← Volver a Usuarios</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Ver todas las reservas</a>
                </div>
                <div>
                    <a href="admin_usuario_editar.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-primary">✏️ Editar Usuario</a>
                    <a href="admin_nueva_reserva.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-success">➕ Nueva Reserva</a>
                </div>
            </div>
        </div>

        <!-- Información del Usuario -->
        <div class="usuario-card">
            <div class="usuario-info">
                <div class="usuario-avatar">
                    <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                </div>
                <div class="usuario-datos">
                    <h1><?= htmlspecialchars($usuario['nombre']) ?></h1>
                    <p>📧 <?= htmlspecialchars($usuario['email']) ?></p>
                    <p>🆔 ID: <?= $usuario['id_usuario'] ?></p>
                    <p>📅 Registrado: <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
                    <span class="estado-usuario">
                        <?= $usuario['estado'] === 'activo' ? '✅ Usuario Activo' : '❌ Usuario Inactivo' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Estadísticas del Usuario -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_reservas'] ?></div>
                <div>Total Reservas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmadas"><?= $stats['reservas_confirmadas'] ?></div>
                <div>Confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pendientes"><?= $stats['reservas_pendientes'] ?></div>
                <div>Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number canceladas"><?= $stats['reservas_canceladas'] ?></div>
                <div>Canceladas</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($gastos['total_gastado'] ?? 0, 0, ',', '.') ?></div>
                <div>Total Gastado</div>
            </div>
            <?php if ($gastos['monto_pendiente'] > 0): ?>
            <div class="stat-card">
                <div class="stat-money" style="color: #ffc107;">$<?= number_format($gastos['monto_pendiente'], 0, ',', '.') ?></div>
                <div>Monto Pendiente</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Resumen de Actividad -->
        <?php if ($stats['total_reservas'] > 0): ?>
        <div class="resumen-card">
            <h3>📊 Resumen de Actividad</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>Primera reserva:</strong><br>
                    <?= date('d/m/Y', strtotime($stats['primera_reserva'])) ?>
                </div>
                <div>
                    <strong>Última reserva:</strong><br>
                    <?= date('d/m/Y', strtotime($stats['ultima_reserva'])) ?>
                </div>
                <div>
                    <strong>Promedio por reserva:</strong><br>
                    $<?= $gastos['total_pagos'] > 0 ? number_format($gastos['total_gastado'] / $gastos['total_pagos'], 0, ',', '.') : '0' ?>
                </div>
                <div>
                    <strong>Estado actual:</strong><br>
                    <?= $usuario['estado'] === 'activo' ? 'Cliente Activo' : 'Cliente Inactivo' ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historial de Reservas -->
        <div class="reservas-table">
            <div class="table-header">
                <h3>📋 Historial de Reservas</h3>
                <span><?= $reservas->num_rows ?> reservas encontradas</span>
            </div>

            <?php if ($reservas->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Reserva</th>
                            <th>Habitación</th>
                            <th>Fechas de Estadía</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Fecha Reserva</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reserva = $reservas->fetch_assoc()): ?>
                            <tr>
                                <td class="reserva-id">#<?= $reserva['id_reserva'] ?></td>
                                <td>
                                    <div class="habitacion-info">
                                        <strong>Hab. #<?= $reserva['numero_habitacion'] ?></strong><br>
                                        <?= htmlspecialchars($reserva['tipo_habitacion']) ?><br>
                                        <span class="monto-cell">$<?= number_format($reserva['precio_noche'], 0, ',', '.') ?>/noche</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fechas-info">
                                        <div class="fecha-entrada">📅 <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
                                        <div class="fecha-salida">📤 <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
                                        <?php
                                        $dias = (strtotime($reserva['fecha_salida']) - strtotime($reserva['fecha_entrada'])) / (60*60*24);
                                        ?>
                                        <small><?= $dias ?> noche(s)</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?= $reserva['estado'] ?>">
                                        <?= ucfirst($reserva['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reserva['monto_pagado']): ?>
                                        <div class="pago-info pago-<?= $reserva['estado_pago'] ?>">
                                            <strong>$<?= number_format($reserva['monto_pagado'], 0, ',', '.') ?></strong><br>
                                            <small><?= ucfirst($reserva['estado_pago']) ?></small>
                                            <?php if ($reserva['fecha_pago']): ?>
                                                <br><small><?= date('d/m/Y', strtotime($reserva['fecha_pago'])) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #dc3545;">Sin pago</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.9em; color: #666;">
                                        <?= date('d/m/Y', strtotime($reserva['fecha_reserva'])) ?><br>
                                        <small><?= date('H:i', strtotime($reserva['fecha_reserva'])) ?></small>
                                    </div>
                                </td>
                                <td class="acciones">
                                    <a href="admin_reserva_detalle.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-info" title="Ver detalles">👁️</a>
                                    <a href="admin_reserva_editar.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-primary" title="Editar">✏️</a>
                                    <?php if ($reserva['monto_pagado'] && $reserva['estado_pago'] == 'confirmado'): ?>
                                        <a href="admin_generar_factura.php?reserva=<?= $reserva['id_reserva'] ?>" class="btn btn-success" title="Factura">📄</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-reservas">
                    <h3>📅 Sin Reservas</h3>
                    <p>Este usuario no tiene reservas registradas aún.</p>
                    <a href="admin_nueva_reserva.php?usuario=<?= $usuario['id_usuario'] ?>" class="btn btn-success">➕ Crear Primera Reserva</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>