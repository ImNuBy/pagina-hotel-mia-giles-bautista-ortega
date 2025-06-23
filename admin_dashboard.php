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

// Estadísticas principales REALES
$stats = fetch_or_default("SELECT 
    (SELECT COUNT(*) FROM reservas) AS total_reservas,
    (SELECT COUNT(*) FROM reservas WHERE estado = 'confirmada') AS reservas_confirmadas,
    (SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente') AS reservas_pendientes,
    (SELECT COUNT(*) FROM habitaciones WHERE estado = 'disponible') AS habitaciones_disponibles,
    (SELECT COUNT(*) FROM habitaciones WHERE estado = 'ocupada') AS habitaciones_ocupadas,
    (SELECT COUNT(*) FROM habitaciones) AS total_habitaciones,
    (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS total_clientes,
    (SELECT COALESCE(SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END), 0) FROM pagos) AS ingresos_totales,
    (SELECT COUNT(*) FROM reservas WHERE fecha_entrada = CURDATE()) AS checkins_hoy,
    (SELECT COUNT(*) FROM reservas WHERE fecha_salida = CURDATE() AND estado = 'checkin') AS checkouts_hoy", 
    ['total_reservas' => 0, 'reservas_confirmadas' => 0, 'reservas_pendientes' => 0, 'habitaciones_disponibles' => 0, 
     'habitaciones_ocupadas' => 0, 'total_habitaciones' => 0, 'total_clientes' => 0, 'ingresos_totales' => 0, 
     'checkins_hoy' => 0, 'checkouts_hoy' => 0]);

// Calcular porcentaje de ocupación REAL
$porcentaje_ocupacion = $stats['total_habitaciones'] > 0 ? 
    round(($stats['habitaciones_ocupadas'] / $stats['total_habitaciones']) * 100, 1) : 0;

// Últimas reservas REALES con información completa
$ultimas_reservas = fetch_all_or_default("
    SELECT 
        r.id_reserva, 
        u.nombre as cliente_nombre, 
        u.email as cliente_email,
        h.numero as habitacion_numero, 
        th.nombre as tipo_habitacion,
        r.fecha_entrada, 
        r.fecha_salida, 
        r.estado,
        r.fecha_reserva,
        p.estado as estado_pago,
        p.monto as monto_pago,
        DATEDIFF(r.fecha_entrada, CURDATE()) as dias_hasta_entrada
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    INNER JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    ORDER BY r.fecha_reserva DESC
    LIMIT 5");

// Actividad reciente del sistema REAL
$actividad_reciente = fetch_all_or_default("
    SELECT 
        'reserva' as tipo,
        CONCAT('Nueva reserva de ', u.nombre, ' para habitación ', h.numero) as descripcion,
        r.fecha_reserva as fecha,
        r.estado
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE r.fecha_reserva >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    UNION ALL
    
    SELECT 
        'pago' as tipo,
        CONCAT('Pago ', p.estado, ' por $', FORMAT(p.monto, 0), ' - Reserva #', p.id_reserva) as descripcion,
        p.fecha_pago as fecha,
        p.estado
    FROM pagos p
    WHERE p.fecha_pago >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    ORDER BY fecha DESC
    LIMIT 8");

// Próximos eventos importantes REALES
$proximos_eventos = fetch_all_or_default("
    SELECT 
        'checkin' as tipo,
        CONCAT('Check-in: ', u.nombre, ' - Hab. ', h.numero) as descripcion,
        r.fecha_entrada as fecha,
        r.estado
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE r.fecha_entrada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND r.estado IN ('confirmada', 'pendiente')
    
    UNION ALL
    
    SELECT 
        'checkout' as tipo,
        CONCAT('Check-out: ', u.nombre, ' - Hab. ', h.numero) as descripcion,
        r.fecha_salida as fecha,
        r.estado
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE r.fecha_salida BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND r.estado = 'checkin'
    
    ORDER BY fecha ASC
    LIMIT 6");

// Obtener información del usuario administrador
$admin_info = fetch_or_default("SELECT nombre, email FROM usuarios WHERE id_usuario = {$_SESSION['user_id']}", 
    ['nombre' => 'Administrador', 'email' => '']);

// Contadores adicionales para módulos
$contadores_modulos = fetch_or_default("SELECT 
    (SELECT COUNT(*) FROM ofertas_especiales WHERE activa = 1) AS ofertas_activas,
    (SELECT COUNT(*) FROM tarifas WHERE activa = 1) AS tarifas_activas,
    (SELECT COUNT(*) FROM tipos_habitacion WHERE activo = 1) AS tipos_activos,
    (SELECT COUNT(*) FROM comentarios WHERE DATE(fecha_comentario) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS comentarios_semana,
    (SELECT AVG(puntuacion) FROM comentarios) AS promedio_comentarios", 
    ['ofertas_activas' => 0, 'tarifas_activas' => 0, 'tipos_activos' => 0, 'comentarios_semana' => 0, 'promedio_comentarios' => 0]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Hotel Rivo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
        }

        /* Header principal */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 2.2em;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .header-subtitle {
            color: #666;
            font-size: 1em;
        }

        .admin-info {
            text-align: right;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            min-width: 200px;
        }

        .admin-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.1em;
        }

        .admin-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9em;
        }

        /* Botón del sidebar */
        .sidebar-toggle {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5em;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
        }

        /* Sidebar desplegable */
        .sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            z-index: 1000;
            transition: right 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            overflow-y: auto;
            border-left: 1px solid rgba(255,255,255,0.2);
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        }

        .sidebar.active {
            right: 0;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .sidebar-content {
            padding: 20px;
        }

        /* Tabs para organizar contenido */
        .sidebar-tabs {
            display: flex;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px;
        }

        .tab-button {
            flex: 1;
            padding: 12px 8px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Secciones compactas */
        .compact-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .section-title {
            font-size: 1em;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Items de actividad compactos */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }

        .activity-item:hover {
            background: #f1f3f4;
            transform: translateX(3px);
        }

        .activity-icon {
            font-size: 1.2em;
            width: 30px;
            text-align: center;
            margin-right: 10px;
        }

        .activity-content {
            flex: 1;
            font-size: 0.85em;
        }

        .activity-description {
            color: #333;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .activity-time {
            color: #666;
            font-size: 0.8em;
        }

        /* Eventos próximos */
        .evento-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 3px solid #667eea;
            border: 1px solid #e9ecef;
        }

        .evento-hoy {
            border-left-color: #ffc107;
            background: #fff8e1;
        }

        .evento-info {
            font-size: 0.85em;
        }

        .evento-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .evento-date {
            color: #666;
            font-size: 0.8em;
        }

        /* Resumen del día - Grid compacto */
        .resumen-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .resumen-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .resumen-number {
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 4px;
        }

        .resumen-label {
            font-size: 0.8em;
            color: #666;
        }

        .resumen-summary {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .resumen-summary h4 {
            font-size: 0.9em;
            color: #333;
            margin-bottom: 8px;
        }

        .resumen-text {
            font-size: 0.8em;
            color: #666;
            line-height: 1.4;
        }

        /* Acciones rápidas */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .quick-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .quick-btn:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-1px);
        }

        /* Overlay para cerrar sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Estadísticas principales */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-detail {
            color: #999;
            font-size: 0.8em;
        }

        /* Estilos adicionales para el contenido principal */
        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .section-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h3 {
            margin: 0;
            font-size: 1.2em;
            font-weight: 600;
        }

        .badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .section-content {
            padding: 30px;
        }

        /* Estilos para reservas */
        .reserva-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .reserva-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .reserva-info h4 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 1.1em;
        }

        .reserva-details {
            font-size: 0.9em;
            color: #666;
            line-height: 1.4;
        }

        .reserva-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
        }

        .estado-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estado-pendiente { 
            background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
            color: #856404; 
        }
        .estado-confirmada { 
            background: linear-gradient(135deg, #d4edda, #a8e6cf); 
            color: #155724; 
        }
        .estado-checkin { 
            background: linear-gradient(135deg, #cce5ff, #74b9ff); 
            color: #004085; 
        }
        .estado-completada { 
            background: linear-gradient(135deg, #e2e3e5, #b2bec3); 
            color: #383d41; 
        }

        .actions-group {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-success { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white; 
        }
        .btn-danger { 
            background: linear-gradient(135deg, #dc3545, #e17055); 
            color: white; 
        }
        .btn-info { 
            background: linear-gradient(135deg, #17a2b8, #00b894); 
            color: white; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white; 
        }
        .btn-warning { 
            background: linear-gradient(135deg, #ffc107, #fdcb6e); 
            color: #212529; 
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Management grid */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .management-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }

        .management-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .management-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
            border-color: #667eea;
            text-decoration: none;
            color: inherit;
        }

        .management-card:hover::before {
            transform: scaleX(1);
        }

        .management-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .management-icon {
            font-size: 2.5em;
            width: 60px;
            text-align: center;
            flex-shrink: 0;
        }

        .management-info {
            flex: 1;
        }

        .management-info h4 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 1.1em;
        }

        .management-info p {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .management-stats {
            font-size: 0.8em;
            color: #999;
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                right: -100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .sidebar-toggle {
                width: 50px;
                height: 50px;
                font-size: 1.2em;
            }

            .reserva-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .reserva-actions {
                align-items: flex-start;
                width: 100%;
            }

            .actions-group {
                flex-wrap: wrap;
            }

            .management-grid {
                grid-template-columns: 1fr;
            }

            .management-card {
                flex-direction: column;
                text-align: center;
            }

            .management-icon {
                width: 100%;
            }
        }

        /* Cerrar sesión al final */
        .logout-section {
            margin-top: 40px;
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-logout:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            text-decoration: none;
            color: white;
        }

        /* Botón para ir al index */
        .btn-index {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-index:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <!-- Overlay para cerrar sidebar -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Botón para abrir sidebar -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        ☰
    </button>

    <!-- Sidebar desplegable -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📊 Panel de Control</h3>
            <p>Hotel Rivo</p>
        </div>
        
        <div class="sidebar-content">
            <!-- Tabs para organizar el contenido -->
            <div class="sidebar-tabs">
                <button class="tab-button active" onclick="switchTab('actividad')">Actividad</button>
                <button class="tab-button" onclick="switchTab('eventos')">Eventos</button>
                <button class="tab-button" onclick="switchTab('resumen')">Resumen</button>
                <button class="tab-button" onclick="switchTab('acciones')">Acciones</button>
            </div>

            <!-- Tab: Actividad Reciente -->
            <div id="tab-actividad" class="tab-content active">
                <div class="compact-section">
                    <div class="section-title">
                        🔔 Actividad Reciente
                    </div>
                    
                    <?php if (empty($actividad_reciente)): ?>
                        <div class="no-data">
                            <p>Sin actividad reciente</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($actividad_reciente as $actividad): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?= $actividad['tipo'] === 'reserva' ? '📅' : '💳' ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description">
                                        <?= htmlspecialchars($actividad['descripcion']) ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('H:i d/m', strtotime($actividad['fecha'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Próximos Eventos -->
            <div id="tab-eventos" class="tab-content">
                <div class="compact-section">
                    <div class="section-title">
                        📅 Próximos Eventos
                    </div>
                    
                    <?php if (empty($proximos_eventos)): ?>
                        <div class="no-data">
                            <p>No hay eventos próximos</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($proximos_eventos as $evento): ?>
                            <?php $es_hoy = date('Y-m-d', strtotime($evento['fecha'])) === date('Y-m-d'); ?>
                            <div class="evento-item <?= $es_hoy ? 'evento-hoy' : '' ?>">
                                <div class="evento-info">
                                    <div class="evento-title">
                                        <?= htmlspecialchars($evento['descripcion']) ?>
                                    </div>
                                    <div class="evento-date">
                                        <?= date('d/m/Y', strtotime($evento['fecha'])) ?>
                                        <?= $es_hoy ? '(HOY)' : '' ?>
                                    </div>
                                </div>
                                <div>
                                    <?= $evento['tipo'] === 'checkin' ? '🟢' : '🔴' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Resumen del Día -->
            <div id="tab-resumen" class="tab-content">
                <div class="compact-section">
                    <div class="section-title">
                        📋 Resumen del Día
                    </div>
                    
                    <div class="resumen-grid">
                        <div class="resumen-item">
                            <div class="resumen-number"><?= $stats['reservas_pendientes'] ?></div>
                            <div class="resumen-label">Pendientes</div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-number"><?= $stats['checkins_hoy'] ?></div>
                            <div class="resumen-label">Check-ins</div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-number"><?= $stats['checkouts_hoy'] ?></div>
                            <div class="resumen-label">Check-outs</div>
                        </div>
                        <div class="resumen-item">
                            <div class="resumen-number"><?= $porcentaje_ocupacion ?>%</div>
                            <div class="resumen-label">Ocupación</div>
                        </div>
                    </div>
                    
                    <div class="resumen-summary">
                        <h4>Estado General:</h4>
                        <div class="resumen-text">
                            • Ocupación: <?= $porcentaje_ocupacion ?>% (<?= $stats['habitaciones_ocupadas'] ?>/<?= $stats['total_habitaciones'] ?>)<br>
                            • Ingresos totales: $<?= number_format($stats['ingresos_totales'], 0, ',', '.') ?><br>
                            • Próximas llegadas: <?= count(array_filter($proximos_eventos, function($e) { return $e['tipo'] === 'checkin'; })) ?><br>
                            • Estado: <?= $stats['habitaciones_disponibles'] > 0 ? 'Operación normal' : 'Capacidad completa' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Acciones Rápidas -->
            <div id="tab-acciones" class="tab-content">
                <div class="compact-section">
                    <div class="section-title">
                        ⚡ Acciones Rápidas
                    </div>
                    
                    <div class="quick-actions">
                        <a href="admin_nueva_reserva.php" class="quick-btn">
                            <span>➕</span> Nueva Reserva
                        </a>
                        <a href="admin_habitacion_nueva.php" class="quick-btn">
                            <span>🛏️</span> Nueva Habitación
                        </a>
                        <a href="admin_tarifa_crear.php" class="quick-btn">
                            <span>💰</span> Nueva Tarifa
                        </a>
                        <a href="admin_reportes.php" class="quick-btn">
                            <span>📊</span> Ver Reportes
                        </a>
                        <a href="admin_checkin_checkout.php" class="quick-btn">
                            <span>🏨</span> Check-In/Out
                        </a>
                        <a href="admin_usuarios.php" class="quick-btn">
                            <span>👥</span> Gestión Usuarios
                        </a>
                        <div style="border-top: 1px solid #e9ecef; margin: 15px 0; padding-top: 15px;">
                            <a href="index.php" class="quick-btn" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                <span>🏠</span> Ir al Sitio Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Header principal -->
        <div class="header">
            <div class="header-title">
                <h1>🏨 Panel de Administración</h1>
                <div class="header-subtitle">
                    Sistema de gestión integral - Hotel Rivo
                </div>
            </div>
            <div class="admin-info">
                <h3>👨‍💼 <?= htmlspecialchars($admin_info['nombre']) ?></h3>
                <p><?= htmlspecialchars($admin_info['email']) ?></p>
                <p><?= date('l, d \d\e F') ?></p>
                <div style="margin-top: 10px;">
                    <a href="index.php" class="btn-index" title="Ir al sitio web del hotel">
                        🏠 Sitio Web
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes del sistema -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="mensaje <?= ($_GET['msg'] == 'confirmada') ? 'success' : 'error' ?>" style="padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; border-left: 4px solid;">
                <?php
                    if ($_GET['msg'] == 'confirmada') echo "✅ Reserva confirmada correctamente.";
                    if ($_GET['msg'] == 'cancelada') echo "❌ Reserva cancelada correctamente.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_reservas'] ?></div>
                <div class="stat-label">Total Reservas</div>
                <div class="stat-detail"><?= $stats['reservas_confirmadas'] ?> confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $porcentaje_ocupacion ?>%</div>
                <div class="stat-label">Ocupación</div>
                <div class="stat-detail"><?= $stats['habitaciones_ocupadas'] ?>/<?= $stats['total_habitaciones'] ?> habitaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['habitaciones_disponibles'] ?></div>
                <div class="stat-label">Disponibles</div>
                <div class="stat-detail">Habitaciones libres</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_clientes'] ?></div>
                <div class="stat-label">Clientes</div>
                <div class="stat-detail">Registrados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($stats['ingresos_totales'], 0, ',', '.') ?></div>
                <div class="stat-label">Ingresos</div>
                <div class="stat-detail">Totales confirmados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['checkins_hoy'] + $stats['checkouts_hoy'] ?></div>
                <div class="stat-label">Movimientos Hoy</div>
                <div class="stat-detail"><?= $stats['checkins_hoy'] ?> in / <?= $stats['checkouts_hoy'] ?> out</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <!-- Últimas reservas -->
        <div class="section">
            <div class="section-header">
                <h3>📋 Últimas Reservas</h3>
                <span class="badge"><?= count($ultimas_reservas) ?> recientes</span>
            </div>
            <div class="section-content">
                <?php if (empty($ultimas_reservas)): ?>
                    <div class="no-data">
                        <p>No hay reservas registradas</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ultimas_reservas as $reserva): ?>
                        <div class="reserva-item">
                            <div class="reserva-info">
                                <h4><?= htmlspecialchars($reserva['cliente_nombre']) ?></h4>
                                <div class="reserva-details">
                                    🏨 Habitación <?= $reserva['habitacion_numero'] ?> (<?= htmlspecialchars($reserva['tipo_habitacion']) ?>) • 
                                    📅 <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?> - <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
                                    <?php if ($reserva['monto_pago']): ?>
                                        • 💰 $<?= number_format($reserva['monto_pago'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="reserva-actions">
                                <span class="estado-badge estado-<?= $reserva['estado'] ?>">
                                    <?= ucfirst($reserva['estado']) ?>
                                </span>
                                <div class="actions-group">
                                    <?php if ($reserva['estado'] === 'pendiente'): ?>
                                        <form action="admin_confirmar_reserva.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                            <button type="submit" class="btn btn-success" 
                                                    onclick="return confirm('¿Confirmar esta reserva?')">
                                                ✅ Confirmar
                                            </button>
                                        </form>
                                        <form action="admin_cancelar_reserva.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                            <button type="submit" class="btn btn-danger"
                                                    onclick="return confirm('¿Cancelar esta reserva?')">
                                                ❌ Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="admin_reserva_ver.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-info">
                                        👁️ Ver
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="admin_reservas.php" class="btn btn-primary" style="padding: 12px 24px;">
                            📋 Ver todas las reservas
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Módulos de gestión -->
        <div class="section">
            <div class="section-header">
                <h3>⚙️ Módulos de Gestión</h3>
                <span class="badge">11 módulos</span>
            </div>
            <div class="section-content">
                <div class="management-grid">
                    <a href="admin_habitaciones.php" class="management-card">
                        <div class="management-icon">🛏️</div>
                        <div class="management-info">
                            <h4>Habitaciones</h4>
                            <p>Gestión de habitaciones y estados</p>
                            <div class="management-stats"><?= $stats['total_habitaciones'] ?> total • <?= $stats['habitaciones_disponibles'] ?> disponibles</div>
                        </div>
                    </a>
                    
                    <a href="admin_reservas.php" class="management-card">
                        <div class="management-icon">📅</div>
                        <div class="management-info">
                            <h4>Reservas</h4>
                            <p>Administrar todas las reservas</p>
                            <div class="management-stats"><?= $stats['total_reservas'] ?> total • <?= $stats['reservas_pendientes'] ?> pendientes</div>
                        </div>
                    </a>
                    
                    <a href="admin_checkin_checkout.php" class="management-card">
                        <div class="management-icon">🏨</div>
                        <div class="management-info">
                            <h4>Check-In/Out</h4>
                            <p>Gestión de llegadas y salidas</p>
                            <div class="management-stats"><?= $stats['checkins_hoy'] ?> hoy • <?= count(array_filter($proximos_eventos, function($e) { return $e['tipo'] === 'checkin' && date('Y-m-d', strtotime($e['fecha'])) > date('Y-m-d'); })) ?> próximos</div>
                        </div>
                    </a>
                    
                    <a href="admin_usuarios.php" class="management-card">
                        <div class="management-icon">👥</div>
                        <div class="management-info">
                            <h4>Usuarios</h4>
                            <p>Gestión de clientes y usuarios</p>
                            <div class="management-stats"><?= $stats['total_clientes'] ?> clientes registrados</div>
                        </div>
                    </a>
                    
                    <a href="admin_tarifas.php" class="management-card">
                        <div class="management-icon">💰</div>
                        <div class="management-info">
                            <h4>Tarifas</h4>
                            <p>Precios y temporadas</p>
                            <div class="management-stats"><?= $contadores_modulos['tarifas_activas'] ?> tarifas activas</div>
                        </div>
                    </a>
                    
                    <a href="admin_pagos.php" class="management-card">
                        <div class="management-icon">💳</div>
                        <div class="management-info">
                            <h4>Pagos</h4>
                            <p>Control de transacciones</p>
                            <div class="management-stats">$<?= number_format($stats['ingresos_totales'], 0, ',', '.') ?> totales</div>
                        </div>
                    </a>
                    
                    <a href="admin_ofertas.php" class="management-card">
                        <div class="management-icon">🎯</div>
                        <div class="management-info">
                            <h4>Ofertas</h4>
                            <p>Promociones especiales</p>
                            <div class="management-stats"><?= $contadores_modulos['ofertas_activas'] ?> ofertas activas</div>
                        </div>
                    </a>
                    
                    <a href="admin_reportes.php" class="management-card">
                        <div class="management-icon">📊</div>
                        <div class="management-info">
                            <h4>Reportes</h4>
                            <p>Estadísticas y análisis</p>
                            <div class="management-stats">Análisis en tiempo real</div>
                        </div>
                    </a>
                    
                    <a href="admin_tipos_habitacion.php" class="management-card">
                        <div class="management-icon">🏷️</div>
                        <div class="management-info">
                            <h4>Tipos</h4>
                            <p>Categorías de habitaciones</p>
                            <div class="management-stats"><?= $contadores_modulos['tipos_activos'] ?> tipos configurados</div>
                        </div>
                    </a>
                    
                    <a href="admin_historial_usuarios.php" class="management-card">
                        <div class="management-icon">📜</div>
                        <div class="management-info">
                            <h4>Historial</h4>
                            <p>Historial de clientes</p>
                            <div class="management-stats"><?= $stats['total_reservas'] ?> registros históricos</div>
                        </div>
                    </a>
                    
                    <a href="admin_comentarios.php" class="management-card">
                        <div class="management-icon">💬</div>
                        <div class="management-info">
                            <h4>Comentarios</h4>
                            <p>Reseñas y opiniones</p>
                            <div class="management-stats"><?= number_format($contadores_modulos['promedio_comentarios'], 1) ?>⭐ promedio • <?= $contadores_modulos['comentarios_semana'] ?> esta semana</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Sección de cierre de sesión -->
        <div class="logout-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0 0 5px 0; color: #333;">🚪 Finalizar Sesión</h3>
                    <p style="color: #666; margin: 0;">¿Desea cerrar la sesión de administrador?</p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; margin-right: 10px; padding: 12px 20px;">
                        🏠 Ir al Sitio Web
                    </a>
                    <a href="logout.php" class="btn-logout" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        🚪 Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Estado del sidebar
        let sidebarOpen = false;

        // Función para alternar el sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            sidebarOpen = !sidebarOpen;
            
            if (sidebarOpen) {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                toggleBtn.innerHTML = '✕';
                toggleBtn.style.background = 'linear-gradient(135deg, #dc3545, #c82333)';
                document.body.style.overflow = 'hidden';
            } else {
                closeSidebar();
            }
        }

        // Función para cerrar el sidebar
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            toggleBtn.innerHTML = '☰';
            toggleBtn.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            document.body.style.overflow = 'auto';
            sidebarOpen = false;
        }

        // Función para cambiar de tab
        function switchTab(tabName) {
            // Remover clase active de todos los botones y contenidos
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Agregar clase active al botón y contenido seleccionado
            event.target.classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // Cerrar sidebar con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebarOpen) {
                closeSidebar();
            }
        });

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            // Animar estadísticas
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animar secciones
            setTimeout(() => {
                const sections = document.querySelectorAll('.section');
                sections.forEach((section, index) => {
                    section.style.opacity = '0';
                    section.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        section.style.transition = 'all 0.6s ease';
                        section.style.opacity = '1';
                        section.style.transform = 'translateY(0)';
                    }, index * 150);
                });
            }, 500);
        });

        // Efectos hover para estadísticas
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Auto-ocultar mensajes existentes
        setTimeout(function() {
            const mensajes = document.querySelectorAll('.mensaje');
            mensajes.forEach(mensaje => {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (mensaje.parentNode) {
                        mensaje.remove();
                    }
                }, 500);
            });
        }, 5000);

        console.log('🏨 Panel de administración con datos reales cargado correctamente');
    </script>
</body>
</html>