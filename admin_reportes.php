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

// Funciones auxiliares para manejo seguro de consultas
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

// Obtener datos principales de forma segura
$ingresos = fetch_or_default("SELECT 
    SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END) AS total_ingresos,
    COUNT(CASE WHEN estado = 'confirmado' THEN 1 END) AS total_pagos,
    AVG(CASE WHEN estado = 'confirmado' THEN monto ELSE NULL END) AS promedio_pago
    FROM pagos", ['total_ingresos' => 0, 'total_pagos' => 0, 'promedio_pago' => 0]);

$ocupacion = fetch_or_default("SELECT 
    COUNT(CASE WHEN estado = 'ocupada' THEN 1 END) AS habitaciones_ocupadas,
    COUNT(CASE WHEN estado = 'disponible' THEN 1 END) AS habitaciones_disponibles,
    COUNT(CASE WHEN estado = 'mantenimiento' THEN 1 END) AS habitaciones_mantenimiento,
    COUNT(*) AS total_habitaciones
    FROM habitaciones", ['habitaciones_ocupadas' => 0, 'habitaciones_disponibles' => 0, 'habitaciones_mantenimiento' => 0, 'total_habitaciones' => 0]);

$reservas_stats = fetch_or_default("SELECT 
    COUNT(CASE WHEN estado = 'confirmada' THEN 1 END) AS reservas_confirmadas,
    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS reservas_pendientes,
    COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) AS reservas_canceladas,
    COUNT(*) AS total_reservas
    FROM reservas", ['reservas_confirmadas' => 0, 'reservas_pendientes' => 0, 'reservas_canceladas' => 0, 'total_reservas' => 0]);

// Verificar tabla comentarios
$comentarios_stats = ['promedio_puntuacion' => 0, 'total_comentarios' => 0, 'comentarios_positivos' => 0];
$comentarios_result = $conn->query("SHOW TABLES LIKE 'comentarios'");
if ($comentarios_result && $comentarios_result->num_rows > 0) {
    $comentarios_stats = fetch_or_default("SELECT 
        AVG(puntuacion) AS promedio_puntuacion,
        COUNT(*) AS total_comentarios,
        COUNT(CASE WHEN puntuacion >= 4 THEN 1 END) AS comentarios_positivos
        FROM comentarios", $comentarios_stats);
}

// Filtros de fecha
$filtro_fecha = "";
if (isset($_GET['desde'], $_GET['hasta'])) {
    $desde = $conn->real_escape_string($_GET['desde']);
    $hasta = $conn->real_escape_string($_GET['hasta']);
    $filtro_fecha = " AND fecha_pago BETWEEN '$desde' AND '$hasta'";
}

// Ingresos mensuales con filtros opcionales
$ingresos_mensuales = fetch_all_or_default("SELECT 
    MONTH(fecha_pago) AS mes,
    MONTHNAME(fecha_pago) AS nombre_mes,
    SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END) AS ingresos_mes,
    COUNT(CASE WHEN estado = 'confirmado' THEN 1 END) AS pagos_mes
    FROM pagos 
    WHERE YEAR(fecha_pago) = YEAR(CURDATE()) $filtro_fecha
    GROUP BY MONTH(fecha_pago), MONTHNAME(fecha_pago)
    ORDER BY MONTH(fecha_pago)");

$tipos_populares = fetch_all_or_default("SELECT 
    th.nombre as tipo_habitacion,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_tipo
    FROM reservas r
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    INNER JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.estado IN ('confirmada', 'completada')
    GROUP BY th.id_tipo, th.nombre
    ORDER BY total_reservas DESC");

// Verificar tabla tarifas
$temporadas_stats = [];
$tarifas_existe = $conn->query("SHOW TABLES LIKE 'tarifas'");
if ($tarifas_existe && $tarifas_existe->num_rows > 0) {
    $temporadas_stats = fetch_all_or_default("SELECT 
        t.temporada,
        COUNT(CASE WHEN r.fecha_entrada BETWEEN t.fecha_inicio AND t.fecha_fin THEN r.id_reserva END) as reservas_temporada,
        AVG(CASE WHEN p.estado = 'confirmado' AND r.fecha_entrada BETWEEN t.fecha_inicio AND t.fecha_fin THEN p.monto ELSE NULL END) as promedio_ingresos
        FROM tarifas t
        LEFT JOIN habitaciones h ON h.id_tipo = t.id_tipo
        LEFT JOIN reservas r ON r.id_habitacion = h.id_habitacion
        LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
        WHERE t.activa = 1
        GROUP BY t.temporada
        ORDER BY reservas_temporada DESC");
}

$fecha_inicio = fetch_or_default("SELECT MIN(fecha_pago) as primera_fecha FROM pagos WHERE fecha_pago IS NOT NULL", ['primera_fecha' => null]);
$fecha_fin = fetch_or_default("SELECT MAX(fecha_pago) as ultima_fecha FROM pagos WHERE fecha_pago IS NOT NULL", ['ultima_fecha' => null]);

$clientes_frecuentes = fetch_all_or_default("SELECT 
    u.nombre,
    u.email,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as total_gastado
    FROM usuarios u
    INNER JOIN reservas r ON u.id_usuario = r.id_usuario
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE u.rol = 'cliente'
    GROUP BY u.id_usuario, u.nombre, u.email
    HAVING total_reservas > 1
    ORDER BY total_reservas DESC
    LIMIT 10");

// Comentarios destacados
$comentarios_destacados = [];
if ($comentarios_result && $comentarios_result->num_rows > 0) {
    $comentarios_destacados = fetch_all_or_default("SELECT nombre, comentario, puntuacion FROM comentarios ORDER BY puntuacion DESC LIMIT 5");
}

$porcentaje_ocupacion = ($ocupacion['total_habitaciones'] > 0) ? 
    round(($ocupacion['habitaciones_ocupadas'] / $ocupacion['total_habitaciones']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - Hotel Rivo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-weight: bold;
            color: #333;
        }
        .filter-group input {
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-group input:focus {
            outline: none;
            border-color: #007bff;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
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
        .stat-subtitle {
            color: #999;
            font-size: 0.9em;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
        }
        .chart-title {
            font-size: 1.4em;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        .report-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .section-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            font-weight: bold;
            font-size: 1.2em;
        }
        .section-content {
            padding: 25px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .metric-row:last-child {
            border-bottom: none;
        }
        .metric-label {
            color: #666;
            font-weight: 500;
        }
        .metric-value {
            font-weight: bold;
            color: #333;
        }
        .metric-value.money {
            color: #28a745;
        }
        .metric-value.percentage {
            color: #007bff;
        }
        .period-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .export-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .export-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .comment-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
        }
        .comment-rating {
            color: #ff9800;
            font-weight: bold;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .reports-grid {
                grid-template-columns: 1fr;
            }
            .filter-form {
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
            <h1>📊 Reportes y Estadísticas</h1>
            <div class="header-subtitle">
                Panel de análisis completo del Hotel Rivo
            </div>
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Reservas</a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">🏨 Habitaciones</a>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-warning">🖨️ Imprimir</button>
                    <button onclick="exportarReporte()" class="btn btn-success">📥 Exportar</button>
                </div>
            </div>
        </div>

        <!-- Filtros de fecha -->
        <div class="filter-section">
            <h3 style="margin: 0 0 20px 0; color: #333;">🔍 Filtros de Análisis</h3>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="desde">📅 Fecha desde:</label>
                    <input type="date" id="desde" name="desde" value="<?= $_GET['desde'] ?? '' ?>">
                </div>
                <div class="filter-group">
                    <label for="hasta">📅 Fecha hasta:</label>
                    <input type="date" id="hasta" name="hasta" value="<?= $_GET['hasta'] ?? '' ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">🔎 Filtrar</button>
                </div>
                <?php if (isset($_GET['desde']) && isset($_GET['hasta'])): ?>
                    <div class="filter-group">
                        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-secondary">🗑️ Quitar filtro</a>
                    </div>
                <?php endif; ?>
            </form>
            
            <?php if (isset($_GET['desde']) && isset($_GET['hasta'])): ?>
                <div class="period-info">
                    <strong>📊 Período analizado:</strong> 
                    <?= date('d/m/Y', strtotime($_GET['desde'])) ?> - <?= date('d/m/Y', strtotime($_GET['hasta'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($ingresos['total_ingresos'], 0, ',', '.') ?></div>
                <div class="stat-label">Ingresos Totales</div>
                <div class="stat-subtitle"><?= $ingresos['total_pagos'] ?> pagos confirmados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $porcentaje_ocupacion ?>%</div>
                <div class="stat-label">Ocupación Actual</div>
                <div class="stat-subtitle"><?= $ocupacion['habitaciones_ocupadas'] ?>/<?= $ocupacion['total_habitaciones'] ?> habitaciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $reservas_stats['reservas_confirmadas'] ?></div>
                <div class="stat-label">Reservas Confirmadas</div>
                <div class="stat-subtitle">De <?= $reservas_stats['total_reservas'] ?> totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($comentarios_stats['promedio_puntuacion'], 1) ?></div>
                <div class="stat-label">Puntuación Promedio</div>
                <div class="stat-subtitle"><?= $comentarios_stats['total_comentarios'] ?> evaluaciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($ingresos['promedio_pago'], 0, ',', '.') ?></div>
                <div class="stat-label">Ticket Promedio</div>
                <div class="stat-subtitle">Por pago</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= count($clientes_frecuentes) ?></div>
                <div class="stat-label">Clientes Frecuentes</div>
                <div class="stat-subtitle">Más de 1 reserva</div>
            </div>
        </div>

        <!-- Gráficos principales -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-title">
                    📈 Ingresos Mensuales - <?= date('Y') ?>
                </div>
                <canvas id="ingresosMensualesChart" height="100"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">
                    🏨 Distribución de Habitaciones
                </div>
                <canvas id="ocupacionChart" height="100"></canvas>
            </div>
        </div>

        <!-- Reportes detallados -->
        <div class="reports-grid">
            <!-- Tipos de habitación más populares -->
            <div class="report-section">
                <div class="section-header">
                    🏆 Tipos de Habitación Más Populares
                </div>
                <div class="section-content">
                    <?php if (empty($tipos_populares)): ?>
                        <p>No hay datos disponibles</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Reservas</th>
                                    <th>Ingresos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos_populares as $tipo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tipo['tipo_habitacion']) ?></td>
                                    <td><?= $tipo['total_reservas'] ?></td>
                                    <td class="metric-value money">$<?= number_format($tipo['ingresos_tipo'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Análisis de ocupación -->
            <div class="report-section">
                <div class="section-header">
                    📊 Análisis de Ocupación
                </div>
                <div class="section-content">
                    <div class="metric-row">
                        <span class="metric-label">Habitaciones Ocupadas</span>
                        <span class="metric-value"><?= $ocupacion['habitaciones_ocupadas'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Habitaciones Disponibles</span>
                        <span class="metric-value"><?= $ocupacion['habitaciones_disponibles'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">En Mantenimiento</span>
                        <span class="metric-value"><?= $ocupacion['habitaciones_mantenimiento'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Tasa de Ocupación</span>
                        <span class="metric-value percentage"><?= $porcentaje_ocupacion ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Estados de reservas -->
            <div class="report-section">
                <div class="section-header">
                    📋 Estados de Reservas
                </div>
                <div class="section-content">
                    <div class="metric-row">
                        <span class="metric-label">Confirmadas</span>
                        <span class="metric-value"><?= $reservas_stats['reservas_confirmadas'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Pendientes</span>
                        <span class="metric-value"><?= $reservas_stats['reservas_pendientes'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Canceladas</span>
                        <span class="metric-value"><?= $reservas_stats['reservas_canceladas'] ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Total</span>
                        <span class="metric-value"><?= $reservas_stats['total_reservas'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Clientes frecuentes -->
            <div class="report-section">
                <div class="section-header">
                    ⭐ Top Clientes Frecuentes
                </div>
                <div class="section-content">
                    <?php if (empty($clientes_frecuentes)): ?>
                        <p>No hay clientes frecuentes registrados</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Reservas</th>
                                    <th>Total Gastado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($clientes_frecuentes, 0, 5) as $cliente): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: bold;"><?= htmlspecialchars($cliente['nombre']) ?></div>
                                        <div style="font-size: 0.8em; color: #666;"><?= htmlspecialchars($cliente['email']) ?></div>
                                    </td>
                                    <td><?= $cliente['total_reservas'] ?></td>
                                    <td class="metric-value money">$<?= number_format($cliente['total_gastado'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comentarios destacados -->
            <?php if (!empty($comentarios_destacados)): ?>
            <div class="report-section">
                <div class="section-header">
                    💬 Comentarios Destacados
                </div>
                <div class="section-content">
                    <?php foreach ($comentarios_destacados as $comentario): ?>
                        <div class="comment-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong><?= htmlspecialchars($comentario['nombre']) ?></strong>
                                <span class="comment-rating"><?= $comentario['puntuacion'] ?>/5 ⭐</span>
                            </div>
                            <div style="font-style: italic; color: #555;">
                                "<?= htmlspecialchars($comentario['comentario']) ?>"
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Loading indicator -->
        <div class="loading" id="loadingIndicator">
            <p>🔄 Actualizando datos...</p>
        </div>
    </div>

    <!-- Script JavaScript limpio y optimizado -->
    <script>
        // Datos para gráficos
        const mesesData = {
            labels: <?= json_encode(array_column($ingresos_mensuales, 'nombre_mes')) ?>,
            datasets: [{
                label: 'Ingresos Confirmados',
                data: <?= json_encode(array_column($ingresos_mensuales, 'ingresos_mes')) ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        };

        const ocupacionData = {
            labels: ['Ocupadas', 'Disponibles', 'Mantenimiento'],
            datasets: [{
                data: [
                    <?= $ocupacion['habitaciones_ocupadas'] ?>, 
                    <?= $ocupacion['habitaciones_disponibles'] ?>, 
                    <?= $ocupacion['habitaciones_mantenimiento'] ?>
                ],
                backgroundColor: ['#dc3545', '#28a745', '#ffc107'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };

        const tiposData = {
            labels: <?= json_encode(array_column($tipos_populares, 'tipo_habitacion')) ?>,
            datasets: [{
                label: 'Reservas',
                data: <?= json_encode(array_column($tipos_populares, 'total_reservas')) ?>,
                backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56', '#4bc0c0', '#9966ff', '#ff9f40']
            }]
        };

        // Configuración de gráficos
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 20, usePointStyle: true }
                }
            }
        };

        // Variables globales
        let chartIngresos = null;
        let chartOcupacion = null;
        let chartTipos = null;

        // Función para crear gráfico de ingresos
        function crearGraficoIngresos() {
            const ctx = document.getElementById('ingresosMensualesChart').getContext('2d');
            if (chartIngresos) chartIngresos.destroy();
            
            chartIngresos = new Chart(ctx, {
                type: 'line',
                data: mesesData,
                options: {
                    ...chartOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return ' + new Intl.NumberFormat('es-CO').format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Ingresos:  + new Intl.NumberFormat('es-CO').format(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Función para crear gráfico de ocupación
        function crearGraficoOcupacion() {
            const ctx = document.getElementById('ocupacionChart').getContext('2d');
            if (chartOcupacion) chartOcupacion.destroy();
            
            chartOcupacion = new Chart(ctx, {
                type: 'doughnut',
                data: ocupacionData,
                options: {
                    ...chartOptions,
                    cutout: '60%',
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Función para exportar reporte
        function exportarReporte() {
            const fechaActual = new Date().toLocaleDateString('es-CO');
            const nombreArchivo = `reporte_hotel_rivo_${fechaActual.replace(/\//g, '_')}.txt`;
            
            let contenido = `REPORTE HOTEL RIVO - ${fechaActual}\n`;
            contenido += `${'='.repeat(50)}\n\n`;
            
            <?php if (isset($_GET['desde']) && isset($_GET['hasta'])): ?>
                contenido += `PERÍODO ANALIZADO: <?= date('d/m/Y', strtotime($_GET['desde'])) ?> - <?= date('d/m/Y', strtotime($_GET['hasta'])) ?>\n\n`;
            <?php endif; ?>
            
            contenido += `RESUMEN EJECUTIVO:\n`;
            contenido += `- Ingresos Totales: $<?= number_format($ingresos['total_ingresos'], 0, ',', '.') ?>\n`;
            contenido += `- Ocupación Actual: <?= $porcentaje_ocupacion ?>%\n`;
            contenido += `- Reservas Confirmadas: <?= $reservas_stats['reservas_confirmadas'] ?>\n`;
            contenido += `- Puntuación Promedio: <?= number_format($comentarios_stats['promedio_puntuacion'], 1) ?>/5\n\n`;
            
            contenido += `DETALLES DE OCUPACIÓN:\n`;
            contenido += `- Habitaciones Ocupadas: <?= $ocupacion['habitaciones_ocupadas'] ?>\n`;
            contenido += `- Habitaciones Disponibles: <?= $ocupacion['habitaciones_disponibles'] ?>\n`;
            contenido += `- En Mantenimiento: <?= $ocupacion['habitaciones_mantenimiento'] ?>\n`;
            contenido += `- Total Habitaciones: <?= $ocupacion['total_habitaciones'] ?>\n\n`;
            
            contenido += `TIPOS DE HABITACIÓN MÁS POPULARES:\n`;
            <?php if (!empty($tipos_populares)): ?>
                <?php foreach ($tipos_populares as $index => $tipo): ?>
                    contenido += `${<?= $index + 1 ?>}. <?= addslashes($tipo['tipo_habitacion']) ?> - <?= $tipo['total_reservas'] ?> reservas ($<?= number_format($tipo['ingresos_tipo'], 0, ',', '.') ?>)\n`;
                <?php endforeach; ?>
            <?php else: ?>
                contenido += `No hay datos disponibles\n`;
            <?php endif; ?>
            
            contenido += `\nCLIENTES FRECUENTES:\n`;
            <?php if (!empty($clientes_frecuentes)): ?>
                <?php foreach (array_slice($clientes_frecuentes, 0, 5) as $index => $cliente): ?>
                    contenido += `${<?= $index + 1 ?>}. <?= addslashes($cliente['nombre']) ?> - <?= $cliente['total_reservas'] ?> reservas ($<?= number_format($cliente['total_gastado'], 0, ',', '.') ?>)\n`;
                <?php endforeach; ?>
            <?php else: ?>
                contenido += `No hay clientes frecuentes registrados\n`;
            <?php endif; ?>
            
            contenido += `\nINGRESOS MENSUALES <?= date('Y') ?>:\n`;
            <?php foreach ($ingresos_mensuales as $mes): ?>
                contenido += `- <?= $mes['nombre_mes'] ?? 'Mes ' . $mes['mes'] ?>: $<?= number_format($mes['ingresos_mes'], 0, ',', '.') ?> (<?= $mes['pagos_mes'] ?> pagos)\n`;
            <?php endforeach; ?>
            
            <?php if (!empty($comentarios_destacados)): ?>
                contenido += `\nCOMENTARIOS DESTACADOS:\n`;
                <?php foreach ($comentarios_destacados as $comentario): ?>
                    contenido += `- <?= addslashes($comentario['nombre']) ?> (<?= $comentario['puntuacion'] ?>/5): "<?= addslashes($comentario['comentario']) ?>"\n`;
                <?php endforeach; ?>
            <?php endif; ?>
            
            contenido += `\n${'='.repeat(50)}\n`;
            contenido += `Reporte generado automáticamente por Sistema Hotel Rivo\n`;
            contenido += `Fecha de generación: ${new Date().toLocaleString('es-CO')}\n`;
            
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
            
            mostrarNotificacion('📥 Reporte exportado exitosamente', 'success');
        }

        // Sistema de notificaciones
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const existentes = document.querySelectorAll('.notificacion-personalizada');
            if (existentes.length >= 3) existentes[0].remove();
            
            const notif = document.createElement('div');
            notif.className = 'notificacion-personalizada';
            
            const colores = { 'success': '#28a745', 'info': '#007bff', 'warning': '#ffc107', 'error': '#dc3545' };
            const iconos = { 'success': '✅', 'info': 'ℹ️', 'warning': '⚠️', 'error': '❌' };
            
            notif.style.cssText = `
                position: fixed; top: 20px; right: 20px; padding: 15px 20px;
                background: ${colores[tipo] || colores.info}; 
                color: ${tipo === 'warning' ? '#212529' : 'white'};
                border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000; animation: slideInRight 0.3s ease;
                max-width: 350px; word-wrap: break-word; font-weight: 500;
            `;
            
            notif.innerHTML = `${iconos[tipo] || iconos.info} ${mensaje}`;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                if (notif.parentNode) {
                    notif.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => { if (notif.parentNode) notif.remove(); }, 300);
                }
            }, tipo === 'error' ? 5000 : 3000);
        }

        // Inicialización del sistema
        function inicializarSistema() {
            console.log('🚀 Inicializando sistema de reportes...');
            
            try {
                crearGraficoIngresos();
                crearGraficoOcupacion();
                console.log('📊 Gráficos creados exitosamente');
            } catch (error) {
                console.error('Error al crear gráficos:', error);
                mostrarNotificacion('⚠️ Error al cargar gráficos', 'warning');
            }

            // Animaciones de entrada
            document.querySelectorAll('.stat-card, .report-section, .chart-container').forEach((card, index) => {
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        if (card) {
                            card.style.transition = 'all 0.6s ease';
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }
                    }, index * 50);
                }
            });

            console.log('✅ Sistema inicializado completamente');
        }

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
            @media print {
                .btn, .actions-header, .filter-section { display: none !important; }
                .chart-container, .report-section { page-break-inside: avoid; }
                body { background: white !important; }
                .header { background: #667eea !important; -webkit-print-color-adjust: exact; }
            }
        `;
        document.head.appendChild(style);

        // Inicialización
        document.addEventListener('DOMContentLoaded', inicializarSistema);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', inicializarSistema);
        } else {
            inicializarSistema();
        }

        // Limpieza al cerrar
        window.addEventListener('beforeunload', function() {
            if (chartIngresos) chartIngresos.destroy();
            if (chartOcupacion) chartOcupacion.destroy();
            if (chartTipos) chartTipos.destroy();
        });
    </script>
</body>
</html>