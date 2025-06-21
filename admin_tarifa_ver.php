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

// Obtener información completa de la tarifa
$stmt = $conn->prepare("SELECT 
    t.*,
    th.nombre as tipo_habitacion,
    th.descripcion as tipo_descripcion,
    th.capacidad,
    th.amenidades,
    COUNT(h.id_habitacion) as habitaciones_disponibles,
    CASE 
        WHEN CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN CURDATE() < t.fecha_inicio THEN 'futura'
        ELSE 'vencida'
    END as estado_tarifa,
    DATEDIFF(t.fecha_fin, t.fecha_inicio) as duracion_dias,
    (t.precio - (t.precio * t.descuento / 100)) as precio_final
    FROM tarifas t
    INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
    LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
    WHERE t.id_tarifa = ?
    GROUP BY t.id_tarifa");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$tarifa = $result->fetch_assoc();

if (!$tarifa) {
    header('Location: admin_tarifas.php?error=tarifa_no_encontrada');
    exit;
}

// Obtener reservas asociadas a esta tarifa
$reservas = $conn->query("SELECT 
    r.*,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    u.telefono as cliente_telefono,
    h.numero as habitacion_numero,
    p.id_pago,
    p.monto as monto_pago,
    p.estado as estado_pago,
    p.fecha_pago
    FROM reservas r
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE h.id_tipo = {$tarifa['id_tipo']}
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'
    ORDER BY r.fecha_entrada ASC");

// Estadísticas detalladas
$stats = $conn->query("SELECT 
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
    SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) as reservas_pendientes,
    MIN(r.fecha_entrada) as primera_reserva,
    MAX(r.fecha_entrada) as ultima_reserva,
    AVG(DATEDIFF(r.fecha_salida, r.fecha_entrada)) as estancia_promedio,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_confirmados,
    SUM(CASE WHEN p.estado = 'pendiente' THEN p.monto ELSE 0 END) as ingresos_pendientes
    FROM reservas r
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE h.id_tipo = {$tarifa['id_tipo']}
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'")->fetch_assoc();

// Comparación con otras tarifas del mismo tipo
$tarifas_comparacion = $conn->query("SELECT 
    t.*,
    CASE 
        WHEN CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN CURDATE() < t.fecha_inicio THEN 'futura'
        ELSE 'vencida'
    END as estado_tarifa,
    (t.precio - (t.precio * t.descuento / 100)) as precio_final
    FROM tarifas t
    WHERE t.id_tipo = {$tarifa['id_tipo']} AND t.id_tarifa != $id
    ORDER BY t.fecha_inicio DESC
    LIMIT 5");

// Análisis de ocupación por día
$ocupacion_diaria = $conn->query("SELECT 
    DATE(r.fecha_entrada) as fecha,
    COUNT(r.id_reserva) as reservas_dia,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas_dia
    FROM reservas r
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE h.id_tipo = {$tarifa['id_tipo']}
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'
    GROUP BY DATE(r.fecha_entrada)
    ORDER BY fecha ASC");

// Días restantes o transcurridos
$hoy = new DateTime();
$inicio = new DateTime($tarifa['fecha_inicio']);
$fin = new DateTime($tarifa['fecha_fin']);
$estado_temporal = '';

if ($hoy < $inicio) {
    $dias_para_inicio = $hoy->diff($inicio)->days;
    $estado_temporal = "Inicia en {$dias_para_inicio} día(s)";
} elseif ($hoy > $fin) {
    $dias_desde_fin = $fin->diff($hoy)->days;
    $estado_temporal = "Venció hace {$dias_desde_fin} día(s)";
} else {
    $dias_restantes = $hoy->diff($fin)->days;
    $estado_temporal = "Quedan {$dias_restantes} día(s)";
}
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Detalles de Tarifa - Hotel Rivo</title>
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
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .tarifa-card, .stats-card, .reservas-card, .sidebar {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .tarifa-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .tarifa-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        .tarifa-hero.activa {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .tarifa-hero.vencida {
            background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);
        }
        .tarifa-hero.futura {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .hero-content {
            position: relative;
            z-index: 1;
        }
        .tipo-habitacion {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .temporada-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 1.1em;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
        }
        .precio-hero {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
        }
        .precio-principal {
            font-size: 3em;
            font-weight: bold;
        }
        .precio-original {
            font-size: 1.5em;
            text-decoration: line-through;
            opacity: 0.7;
        }
        .descuento-hero {
            background: rgba(255,255,255,0.9);
            color: #dc3545;
            padding: 10px 15px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.2em;
        }
        .estado-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            background: rgba(255,255,255,0.9);
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #007bff;
        }
        .info-item.activa { border-left-color: #28a745; }
        .info-item.vencida { border-left-color: #dc3545; }
        .info-item.futura { border-left-color: #ffc107; }
        .info-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .info-value {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
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
            color: #007bff;
        }
        .stat-number.confirmadas { color: #28a745; }
        .stat-number.canceladas { color: #dc3545; }
        .stat-number.pendientes { color: #ffc107; }
        .stat-number.ingresos { color: #17a2b8; }
        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .reservas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .reservas-table th,
        .reservas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .reservas-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .reservas-table tr:hover {
            background: #f8f9fa;
        }
        .estado-reserva {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-confirmada { background: #d4edda; color: #155724; }
        .estado-cancelada { background: #f8d7da; color: #721c24; }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
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
            margin-bottom: 20px;
        }
        .timeline {
            position: relative;
            margin: 20px 0;
        }
        .timeline-item {
            padding: 15px 20px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            position: relative;
        }
        .timeline-date {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .timeline-content {
            color: #666;
        }
        .comparacion-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .comparacion-table th,
        .comparacion-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .comparacion-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .precio-comparacion {
            font-weight: bold;
        }
        .precio-mejor {
            color: #28a745;
        }
        .precio-peor {
            color: #dc3545;
        }
        .ocupacion-chart {
            height: 200px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tipo-habitacion {
                font-size: 2em;
            }
            .precio-principal {
                font-size: 2em;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>👁️ Detalles de Tarifa</h1>
            <div class="actions-header">
                <div>
                    <a href="admin_tarifas.php" class="btn btn-secondary">← Volver a tarifas</a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                </div>
                <div>
                    <a href="admin_tarifa_editar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-primary">✏️ Editar</a>
                    <a href="admin_tarifa_crear.php" class="btn btn-success">➕ Nueva Tarifa</a>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="tarifa-hero <?= $tarifa['estado_tarifa'] ?>">
            <div class="estado-badge">
                <?= ucfirst($tarifa['estado_tarifa']) ?>
            </div>
            
            <div class="hero-content">
                <div class="tipo-habitacion"><?= htmlspecialchars($tarifa['tipo_habitacion']) ?></div>
                
                <div class="temporada-badge">
                    🌤️ Temporada <?= htmlspecialchars($tarifa['temporada']) ?>
                </div>
                
                <div><?= $estado_temporal ?></div>
                
                <div class="precio-hero">
                    <?php if ($tarifa['descuento'] > 0): ?>
                        <div class="precio-original">$<?= number_format($tarifa['precio'], 0, ',', '.') ?></div>
                        <div class="precio-principal">$<?= number_format($tarifa['precio_final'], 0, ',', '.') ?></div>
                        <div class="descuento-hero"><?= $tarifa['descuento'] ?>% OFF</div>
                    <?php else: ?>
                        <div class="precio-principal">$<?= number_format($tarifa['precio'], 0, ',', '.') ?></div>
                        <div style="opacity: 0.7;">por noche</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <!-- Información General -->
                <div class="tarifa-card">
                    <h3>📋 Información General</h3>
                    
                    <div class="info-grid">
                        <div class="info-item <?= $tarifa['estado_tarifa'] ?>">
                            <div class="info-label">ID Tarifa</div>
                            <div class="info-value">#<?= $tarifa['id_tarifa'] ?></div>
                        </div>
                        
                        <div class="info-item <?= $tarifa['estado_tarifa'] ?>">
                            <div class="info-label">Duración</div>
                            <div class="info-value"><?= $tarifa['duracion_dias'] ?> días</div>
                        </div>
                        
                        <div class="info-item <?= $tarifa['estado_tarifa'] ?>">
                            <div class="info-label">Habitaciones Disponibles</div>
                            <div class="info-value"><?= $tarifa['habitaciones_disponibles'] ?></div>
                        </div>
                        
                        <div class="info-item <?= $tarifa['estado_tarifa'] ?>">
                            <div class="info-label">Capacidad</div>
                            <div class="info-value"><?= $tarifa['capacidad'] ?> personas</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <h4>📅 Período de Validez</h4>
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <div>
                                <strong>Inicio:</strong> <?= date('d/m/Y', strtotime($tarifa['fecha_inicio'])) ?><br>
                                <small><?= date('l', strtotime($tarifa['fecha_inicio'])) ?></small>
                            </div>
                            <div style="text-align: center; color: #007bff;">
                                <strong><?= $tarifa['duracion_dias'] ?></strong><br>
                                <small>días</small>
                            </div>
                            <div style="text-align: right;">
                                <strong>Fin:</strong> <?= date('d/m/Y', strtotime($tarifa['fecha_fin'])) ?><br>
                                <small><?= date('l', strtotime($tarifa['fecha_fin'])) ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($tarifa['descripcion']): ?>
                        <div style="margin-top: 20px;">
                            <h4>📝 Descripción</h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;">
                                <?= nl2br(htmlspecialchars($tarifa['descripcion'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Estadísticas -->
                <div class="stats-card">
                    <h3>📊 Estadísticas de Rendimiento</h3>
                    
                    <?php if ($stats['total_reservas'] > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= $stats['total_reservas'] ?></div>
                                <div class="stat-label">Total Reservas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number confirmadas"><?= $stats['reservas_confirmadas'] ?></div>
                                <div class="stat-label">Confirmadas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number canceladas"><?= $stats['reservas_canceladas'] ?></div>
                                <div class="stat-label">Canceladas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number pendientes"><?= $stats['reservas_pendientes'] ?></div>
                                <div class="stat-label">Pendientes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number ingresos">$<?= number_format($stats['ingresos_confirmados'], 0, ',', '.') ?></div>
                                <div class="stat-label">Ingresos Confirmados</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">$<?= number_format($stats['ingresos_pendientes'], 0, ',', '.') ?></div>
                                <div class="stat-label">Ingresos Pendientes</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <h4>📈 Métricas Adicionales</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Estancia Promedio</div>
                                    <div class="info-value"><?= number_format($stats['estancia_promedio'], 1) ?> días</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Tasa de Confirmación</div>
                                    <div class="info-value"><?= $stats['total_reservas'] > 0 ? round(($stats['reservas_confirmadas'] / $stats['total_reservas']) * 100, 1) : 0 ?>%</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Primera Reserva</div>
                                    <div class="info-value"><?= $stats['primera_reserva'] ? date('d/m/Y', strtotime($stats['primera_reserva'])) : 'N/A' ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Última Reserva</div>
                                    <div class="info-value"><?= $stats['ultima_reserva'] ? date('d/m/Y', strtotime($stats['ultima_reserva'])) : 'N/A' ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <h4>📊 Sin Datos de Reservas</h4>
                            <p>Esta tarifa aún no tiene reservas asociadas.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reservas Asociadas -->
                <div class="reservas-card">
                    <h3>📅 Reservas Asociadas</h3>
                    
                    <?php if ($reservas->num_rows > 0): ?>
                        <table class="reservas-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Habitación</th>
                                    <th>Fechas</th>
                                    <th>Estado</th>
                                    <th>Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($reserva = $reservas->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?= htmlspecialchars($reserva['cliente_nombre']) ?></div>
                                            <div style="font-size: 0.9em; color: #666;"><?= htmlspecialchars($reserva['cliente_email']) ?></div>
                                        </td>
                                        <td>Hab. <?= $reserva['habitacion_numero'] ?></td>
                                        <td>
                                            <div><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
                                            <div style="font-size: 0.9em; color: #666;">al <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="estado-reserva estado-<?= $reserva['estado'] ?>">
                                                <?= ucfirst($reserva['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($reserva['monto_pago']): ?>
                                                <div>$<?= number_format($reserva['monto_pago'], 0, ',', '.') ?></div>
                                                <div style="font-size: 0.8em; color: #666;"><?= ucfirst($reserva['estado_pago']) ?></div>
                                            <?php else: ?>
                                                <span style="color: #666;">Sin pago</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="admin_reserva_ver.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.8em;">Ver</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <h4>📅 Sin Reservas</h4>
                            <p>No hay reservas asociadas a esta tarifa en el período especificado.</p>
                        </div>
                    <?php endif;?>
                </div>
            </div>

            <!--Sidebar-->

            <div class="sidebar">
                <!-- Estado de la Tarifa -->
                <div style="margin-bottom: 30px;">
                    <h4>🔄 Estado de la Tarifa</h4>
                    
                    <?php if ($tarifa['estado_tarifa'] == 'activa'): ?>
                        <div class="success-box">
                            <strong>✅ Tarifa Activa</strong><br>
                            Esta tarifa está disponible para reservas.
                        </div>
                    <?php elseif ($tarifa['estado_tarifa'] == 'futura'): ?>
                        <div class="warning-box">
                            <strong>⏰ Tarifa Futura</strong><br>
                            Esta tarifa entrará en vigor próximamente.
                        </div>
                    <?php else: ?>
                        <div class="danger-box">
                            <strong>❌ Tarifa Vencida</strong><br>
                            Esta tarifa ya no está disponible.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Información del Tipo de Habitación -->
                <div style="margin-bottom: 30px;">
                    <h4>🏨 Tipo de Habitación</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h5><?= htmlspecialchars($tarifa['tipo_habitacion']) ?></h5>
                        
                        <?php if ($tarifa['tipo_descripcion']): ?>
                            <p style="margin: 10px 0; color: #666; font-size: 0.9em;">
                                <?= nl2br(htmlspecialchars($tarifa['tipo_descripcion'])) ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 10px;">
                            <strong>👥 Capacidad:</strong> <?= $tarifa['capacidad'] ?> personas<br>
                            <strong>🏨 Habitaciones:</strong> <?= $tarifa['habitaciones_disponibles'] ?>
                        </div>
                        
                        <?php if ($tarifa['amenidades']): ?>
                            <div style="margin-top: 10px;">
                                <strong>🎯 Amenidades:</strong><br>
                                <small style="color: #666;"><?= htmlspecialchars($tarifa['amenidades']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comparación con Otras Tarifas -->
                <div style="margin-bottom: 30px;">
                    <h4>📊 Comparación de Tarifas</h4>
                    
                    <?php if ($tarifas_comparacion->num_rows > 0): ?>
                        <table class="comparacion-table">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Precio Final</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $precio_actual = $tarifa['precio_final'];
                                while ($comp = $tarifas_comparacion->fetch_assoc()): 
                                    $clase_precio = '';
                                    if ($comp['precio_final'] > $precio_actual) {
                                        $clase_precio = 'precio-peor';
                                    } elseif ($comp['precio_final'] < $precio_actual) {
                                        $clase_precio = 'precio-mejor';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-size: 0.8em;">
                                                <?= date('M Y', strtotime($comp['fecha_inicio'])) ?>
                                            </div>
                                            <div style="font-size: 0.7em; color: #666;">
                                                <?= $comp['temporada'] ?>
                                            </div>
                                        </td>
                                        <td class="precio-comparacion <?= $clase_precio ?>">
                                            $<?= number_format($comp['precio_final'], 0, ',', '.') ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.8em; padding: 2px 6px; border-radius: 10px; 
                                                background: <?= $comp['estado_tarifa'] == 'activa' ? '#d4edda' : ($comp['estado_tarifa'] == 'futura' ? '#fff3cd' : '#f8d7da') ?>;">
                                                <?= ucfirst($comp['estado_tarifa']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 10px; font-size: 0.8em; color: #666;">
                            <span style="color: #28a745;">●</span> Precio menor &nbsp;
                            <span style="color: #dc3545;">●</span> Precio mayor
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color: #666; padding: 20px;">
                            No hay otras tarifas para comparar
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ocupación Diaria -->
                <div style="margin-bottom: 30px;">
                    <h4>📈 Ocupación por Día</h4>
                    
                    <?php if ($ocupacion_diaria->num_rows > 0): ?>
                        <div class="ocupacion-chart">
                            <div style="width: 100%;">
                                <?php 
                                $max_reservas = 0;
                                $datos_ocupacion = [];
                                
                                // Obtener datos y calcular máximo
                                while ($ocu = $ocupacion_diaria->fetch_assoc()) {
                                    $datos_ocupacion[] = $ocu;
                                    if ($ocu['reservas_dia'] > $max_reservas) {
                                        $max_reservas = $ocu['reservas_dia'];
                                    }
                                }
                                
                                // Mostrar gráfico simple
                                foreach ($datos_ocupacion as $dia): 
                                    $porcentaje = $max_reservas > 0 ? ($dia['reservas_dia'] / $max_reservas) * 100 : 0;
                                ?>
                                    <div style="display: flex; align-items: center; margin: 5px 0;">
                                        <div style="width: 60px; font-size: 0.8em; color: #666;">
                                            <?= date('d/m', strtotime($dia['fecha'])) ?>
                                        </div>
                                        <div style="flex: 1; background: #e9ecef; height: 20px; border-radius: 10px; margin: 0 10px; overflow: hidden;">
                                            <div style="background: #007bff; height: 100%; width: <?= $porcentaje ?>%; border-radius: 10px;"></div>
                                        </div>
                                        <div style="width: 30px; font-size: 0.8em; text-align: right; color: #333;">
                                            <?= $dia['reservas_dia'] ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color: #666; padding: 20px;">
                            Sin datos de ocupación
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Acciones Rápidas -->
                <div>
                    <h4>⚡ Acciones Rápidas</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="admin_tarifa_editar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-primary">
                            ✏️ Editar Tarifa
                        </a>
                        <a href="admin_tarifa_duplicar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-success">
                            📋 Duplicar Tarifa
                        </a>
                        <a href="admin_reservas.php?tipo=<?= $tarifa['id_tipo'] ?>" class="btn btn-secondary">
                            📅 Ver Reservas del Tipo
                        </a>
                        <a href="admin_habitaciones.php?tipo=<?= $tarifa['id_tipo'] ?>" class="btn btn-secondary">
                            🏨 Ver Habitaciones
                        </a>
                        
                        <?php if ($tarifa['estado_tarifa'] == 'activa'): ?>
                            <a href="admin_tarifa_desactivar.php?id=<?= $tarifa['id_tarifa'] ?>" 
                               class="btn btn-warning"
                               onclick="return confirm('¿Desactivar esta tarifa?')">
                                ⏸️ Desactivar
                            </a>
                        <?php endif; ?>
                        
                        <a href="admin_tarifa_eliminar.php?id=<?= $tarifa['id_tarifa'] ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('¿Eliminar esta tarifa? Esta acción no se puede deshacer.')">
                            🗑️ Eliminar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline de Eventos -->
        <?php if ($stats['total_reservas'] > 0): ?>
            <div class="tarifa-card" style="margin-top: 30px;">
                <h3>📅 Cronología de Eventos</h3>
                
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?= date('d/m/Y', strtotime($tarifa['fecha_inicio'])) ?>
                        </div>
                        <div class="timeline-content">
                            🎯 Inicio de la tarifa "<?= htmlspecialchars($tarifa['temporada']) ?>" 
                            - Precio: $<?= number_format($tarifa['precio_final'], 0, ',', '.') ?>
                        </div>
                    </div>
                    
                    <?php if ($stats['primera_reserva']): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?= date('d/m/Y', strtotime($stats['primera_reserva'])) ?>
                            </div>
                            <div class="timeline-content">
                                🎉 Primera reserva registrada
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['ultima_reserva'] && $stats['ultima_reserva'] != $stats['primera_reserva']): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?= date('d/m/Y', strtotime($stats['ultima_reserva'])) ?>
                            </div>
                            <div class="timeline-content">
                                📈 Última reserva registrada
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?= date('d/m/Y', strtotime($tarifa['fecha_fin'])) ?>
                        </div>
                        <div class="timeline-content">
                            🏁 Fin de la tarifa - Total: <?= $stats['total_reservas'] ?> reservas, 
                            $<?= number_format($stats['ingresos_confirmados'], 0, ',', '.') ?> en ingresos
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alertas y Recomendaciones -->
        <div class="tarifa-card" style="margin-top: 30px;">
            <h3>💡 Alertas y Recomendaciones</h3>
            
            <?php
            $alertas = [];
            
            // Verificar estado de la tarifa
            if ($tarifa['estado_tarifa'] == 'vencida' && $stats['total_reservas'] == 0) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'mensaje' => 'Esta tarifa vencida no generó ninguna reserva. Considera revisar la estrategia de precios.'
                ];
            }
            
            if ($tarifa['estado_tarifa'] == 'activa' && $stats['total_reservas'] == 0) {
                $dias_activa = (new DateTime())->diff(new DateTime($tarifa['fecha_inicio']))->days;
                if ($dias_activa > 7) {
                    $alertas[] = [
                        'tipo' => 'warning',
                        'mensaje' => "Esta tarifa lleva {$dias_activa} días activa sin reservas. Podría necesitar ajustes de precio o promoción."
                    ];
                }
            }
            
            // Verificar tasa de cancelación
            if ($stats['total_reservas'] > 0) {
                $tasa_cancelacion = ($stats['reservas_canceladas'] / $stats['total_reservas']) * 100;
                if ($tasa_cancelacion > 20) {
                    $alertas[] = [
                        'tipo' => 'danger',
                        'mensaje' => "Tasa de cancelación alta ({$tasa_cancelacion}%). Revisa las políticas de cancelación."
                    ];
                }
            }
            
            // Verificar precio competitivo
            if ($tarifas_comparacion->num_rows > 0) {
                $tarifas_comparacion->data_seek(0); // Reset pointer
                $precios_comparacion = [];
                while ($comp = $tarifas_comparacion->fetch_assoc()) {
                    if ($comp['estado_tarifa'] == 'activa') {
                        $precios_comparacion[] = $comp['precio_final'];
                    }
                }
                
                if (!empty($precios_comparacion)) {
                    $precio_promedio = array_sum($precios_comparacion) / count($precios_comparacion);
                    $diferencia = (($tarifa['precio_final'] - $precio_promedio) / $precio_promedio) * 100;
                    
                    if ($diferencia > 25) {
                        $alertas[] = [
                            'tipo' => 'warning',
                            'mensaje' => "Precio " . number_format($diferencia, 1) . "% superior al promedio de tarifas similares."
                        ];
                    } elseif ($diferencia < -25) {
                        $alertas[] = [
                            'tipo' => 'success',
                            'mensaje' => "Precio competitivo, " . number_format(abs($diferencia), 1) . "% inferior al promedio."
                        ];
                    }
                }
            }
            
            // Mostrar alertas
            if (!empty($alertas)):
                foreach ($alertas as $alerta): ?>
                    <div class="<?= $alerta['tipo'] ?>-box">
                        <?= $alerta['mensaje'] ?>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="success-box">
                    ✅ Todo parece estar en orden con esta tarifa.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>