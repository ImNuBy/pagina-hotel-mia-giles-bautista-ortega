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

// Configuración de fechas por defecto
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t'); // Último día del mes actual
$estado_filtro = $_GET['estado'] ?? '';
$metodo_filtro = $_GET['metodo'] ?? '';

// Construir consulta con filtros
$where_conditions = ["p.fecha_pago BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];
$param_types = "ss";

if ($estado_filtro) {
    $where_conditions[] = "p.estado = ?";
    $params[] = $estado_filtro;
    $param_types .= "s";
}

if ($metodo_filtro) {
    $where_conditions[] = "p.metodo_pago = ?";
    $params[] = $metodo_filtro;
    $param_types .= "s";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Consulta principal de pagos
$query_pagos = "SELECT 
    p.id_pago, 
    p.id_reserva, 
    p.monto, 
    p.estado, 
    p.fecha_pago,
    p.metodo_pago,
    r.fecha_entrada,
    r.fecha_salida,
    r.estado as estado_reserva,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    th.nombre as tipo_habitacion,
    h.numero as numero_habitacion,
    DATEDIFF(r.fecha_salida, r.fecha_entrada) as noches
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    $where_clause
    ORDER BY p.fecha_pago DESC";

$stmt = $conn->prepare($query_pagos);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$pagos = $stmt->get_result();

// Estadísticas del periodo
$query_stats = "SELECT 
    COUNT(*) as total_pagos,
    SUM(CASE WHEN p.estado = 'pendiente' THEN 1 ELSE 0 END) as pagos_pendientes,
    SUM(CASE WHEN p.estado = 'confirmado' THEN 1 ELSE 0 END) as pagos_confirmados,
    SUM(CASE WHEN p.estado = 'rechazado' THEN 1 ELSE 0 END) as pagos_rechazados,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_confirmados,
    SUM(CASE WHEN p.estado = 'pendiente' THEN p.monto ELSE 0 END) as ingresos_pendientes,
    SUM(p.monto) as ingresos_totales,
    AVG(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE NULL END) as promedio_pago,
    MIN(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE NULL END) as pago_minimo,
    MAX(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE NULL END) as pago_maximo
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    $where_clause";

$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->bind_param($param_types, ...$params);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Estadísticas por método de pago
$query_metodos = "SELECT 
    p.metodo_pago,
    COUNT(*) as cantidad,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    $where_clause AND p.estado = 'confirmado'
    GROUP BY p.metodo_pago
    ORDER BY ingresos DESC";

$stmt_metodos = $conn->prepare($query_metodos);
$stmt_metodos->bind_param($param_types, ...$params);
$stmt_metodos->execute();
$metodos = $stmt_metodos->get_result();

// Estadísticas por tipo de habitación
$query_habitaciones = "SELECT 
    th.nombre as tipo_habitacion,
    COUNT(*) as reservas,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos,
    AVG(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE NULL END) as promedio
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    $where_clause AND p.estado = 'confirmado'
    GROUP BY th.id_tipo, th.nombre
    ORDER BY ingresos DESC";

$stmt_habitaciones = $conn->prepare($query_habitaciones);
$stmt_habitaciones->bind_param($param_types, ...$params);
$stmt_habitaciones->execute();
$habitaciones = $stmt_habitaciones->get_result();

// Ingresos por día para el gráfico
$query_diario = "SELECT 
    DATE(p.fecha_pago) as fecha,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_dia,
    COUNT(CASE WHEN p.estado = 'confirmado' THEN 1 ELSE NULL END) as pagos_dia
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    $where_clause
    GROUP BY DATE(p.fecha_pago)
    ORDER BY fecha ASC";

$stmt_diario = $conn->prepare($query_diario);
$stmt_diario->bind_param($param_types, ...$params);
$stmt_diario->execute();
$ingresos_diarios = $stmt_diario->get_result();

// Obtener métodos de pago únicos para el filtro
$metodos_disponibles = $conn->query("SELECT DISTINCT metodo_pago FROM pagos WHERE metodo_pago IS NOT NULL ORDER BY metodo_pago");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Reporte de Pagos - Hotel Rivo</title>
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
        .filtros-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        .stat-money {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #28a745;
        }
        .stat-number.total { color: #007bff; }
        .stat-number.pendientes { color: #ffc107; }
        .stat-number.confirmados { color: #28a745; }
        .stat-number.rechazados { color: #dc3545; }
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-card, .analytics-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
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
            padding: 12px 15px;
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
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
        }
        .estado-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-confirmado { background: #d4edda; color: #155724; }
        .estado-rechazado { background: #f8d7da; color: #721c24; }
        .monto-cell {
            color: #28a745;
            font-weight: bold;
        }
        .analytic-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .analytic-item:last-child {
            border-bottom: none;
        }
        .analytic-label {
            font-weight: bold;
            color: #666;
        }
        .analytic-value {
            color: #007bff;
            font-weight: bold;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 5px 0;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s;
        }
        .export-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .periodo-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .resumen-ejecutivo {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }
        @media print {
            body { 
                background: white; 
                font-size: 12px;
            }
            .header, .filtros-card, .export-section { 
                box-shadow: none; 
                border: 1px solid #ddd;
            }
            .btn { display: none; }
            .no-print { display: none; }
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>📊 Reporte de Pagos</h1>
            <div class="actions">
                <div>
                    <a href="admin_pagos.php" class="btn btn-secondary">← Volver a pagos</a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                </div>
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
                    <button onclick="exportarExcel()" class="btn btn-success">📄 Exportar Excel</button>
                </div>
            </div>
        </div>

        <!-- Filtros de reporte -->
        <div class="filtros-card">
            <h3>🔍 Filtros de Reporte</h3>
            <form method="GET" action="">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="fecha_inicio">📅 Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= $fecha_inicio ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">📅 Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= $fecha_fin ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="estado">⚡ Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?= $estado_filtro == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="confirmado" <?= $estado_filtro == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                            <option value="rechazado" <?= $estado_filtro == 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="metodo">💳 Método de Pago</label>
                        <select id="metodo" name="metodo">
                            <option value="">Todos los métodos</option>
                            <?php while ($metodo = $metodos_disponibles->fetch_assoc()): ?>
                                <option value="<?= $metodo['metodo_pago'] ?>" <?= $metodo_filtro == $metodo['metodo_pago'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($metodo['metodo_pago']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 Generar Reporte</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Información del periodo -->
        <div class="periodo-info">
            <h4>📋 Información del Reporte</h4>
            <p><strong>Periodo:</strong> <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
            <p><strong>Filtros aplicados:</strong> 
                <?= $estado_filtro ? "Estado: " . ucfirst($estado_filtro) . " | " : "" ?>
                <?= $metodo_filtro ? "Método: " . $metodo_filtro . " | " : "" ?>
                Total de registros: <?= $stats['total_pagos'] ?>
            </p>
            <p><strong>Generado:</strong> <?= date('d/m/Y H:i:s') ?> por <?= $_SESSION['user_name'] ?? 'Administrador' ?></p>
        </div>

        <!-- Resumen ejecutivo -->
        <div class="resumen-ejecutivo">
            <h4>📈 Resumen Ejecutivo</h4>
            <p><strong>Ingresos totales confirmados:</strong> $<?= number_format($stats['ingresos_confirmados'], 0, ',', '.') ?></p>
            <p><strong>Tasa de confirmación:</strong> <?= $stats['total_pagos'] > 0 ? round(($stats['pagos_confirmados'] / $stats['total_pagos']) * 100, 1) : 0 ?>%</p>
            <p><strong>Ticket promedio:</strong> $<?= $stats['promedio_pago'] ? number_format($stats['promedio_pago'], 0, ',', '.') : 0 ?></p>
            <p><strong>Ingresos pendientes:</strong> $<?= number_format($stats['ingresos_pendientes'], 0, ',', '.') ?></p>
        </div>
        
        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_pagos'] ?></div>
                <div>Total Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($stats['ingresos_confirmados'], 0, ',', '.') ?></div>
                <div>Ingresos Confirmados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmados"><?= $stats['pagos_confirmados'] ?></div>
                <div>Pagos Confirmados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pendientes"><?= $stats['pagos_pendientes'] ?></div>
                <div>Pagos Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= $stats['promedio_pago'] ? number_format($stats['promedio_pago'], 0, ',', '.') : 0 ?></div>
                <div>Promedio por Pago</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= $stats['pago_maximo'] ? number_format($stats['pago_maximo'], 0, ',', '.') : 0 ?></div>
                <div>Pago Máximo</div>
            </div>
        </div>

        <!-- Análisis por métodos y tipos -->
        <div class="content-grid">
            <!-- Análisis por método de pago -->
            <div class="analytics-card">
                <h4>💳 Ingresos por Método de Pago</h4>
                <?php 
                $metodos->data_seek(0); // Resetear el puntero
                $total_metodos = $stats['ingresos_confirmados'];
                if ($metodos->num_rows > 0): 
                ?>
                    <?php while ($metodo = $metodos->fetch_assoc()): ?>
                        <div class="analytic-item">
                            <span class="analytic-label"><?= htmlspecialchars($metodo['metodo_pago']) ?></span>
                            <span class="analytic-value">$<?= number_format($metodo['ingresos'], 0, ',', '.') ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $total_metodos > 0 ? ($metodo['ingresos'] / $total_metodos) * 100 : 0 ?>%"></div>
                        </div>
                        <small style="color: #666;"><?= $metodo['cantidad'] ?> pagos (<?= $total_metodos > 0 ? round(($metodo['ingresos'] / $total_metodos) * 100, 1) : 0 ?>%)</small>
                        <br><br>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center;">No hay datos de métodos de pago confirmados</p>
                <?php endif; ?>
            </div>

            <!-- Análisis por tipo de habitación -->
            <div class="analytics-card">
                <h4>🏨 Ingresos por Tipo de Habitación</h4>
                <?php if ($habitaciones->num_rows > 0): ?>
                    <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                        <div class="analytic-item">
                            <span class="analytic-label"><?= htmlspecialchars($habitacion['tipo_habitacion']) ?></span>
                            <span class="analytic-value">$<?= number_format($habitacion['ingresos'], 0, ',', '.') ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $stats['ingresos_confirmados'] > 0 ? ($habitacion['ingresos'] / $stats['ingresos_confirmados']) * 100 : 0 ?>%"></div>
                        </div>
                        <small style="color: #666;"><?= $habitacion['reservas'] ?> reservas | Promedio: $<?= number_format($habitacion['promedio'], 0, ',', '.') ?></small>
                        <br><br>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #666; text-align: center;">No hay datos de habitaciones confirmadas</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabla detallada de pagos -->
        <div class="table-card">
            <div class="table-header">
                <h3>📋 Detalle de Pagos</h3>
                <span><?= $pagos->num_rows ?> registros encontrados</span>
            </div>
            
            <?php if ($pagos->num_rows > 0): ?>
                <table id="tabla-pagos">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Habitación</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Método</th>
                            <th>Fecha</th>
                            <th>Noches</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pagos->data_seek(0); // Resetear el puntero
                        while ($pago = $pagos->fetch_assoc()): 
                        ?>
                            <tr>
                                <td>#<?= $pago['id_pago'] ?></td>
                                <td><?= htmlspecialchars($pago['cliente_nombre']) ?></td>
                                <td>
                                    <?= htmlspecialchars($pago['tipo_habitacion']) ?><br>
                                    <small>Hab. #<?= $pago['numero_habitacion'] ?></small>
                                </td>
                                <td class="monto-cell">$<?= number_format($pago['monto'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="estado-badge estado-<?= $pago['estado'] ?>">
                                        <?= ucfirst($pago['estado']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($pago['metodo_pago'] ?? 'N/A') ?></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?><br>
                                    <small><?= date('H:i', strtotime($pago['fecha_pago'])) ?></small>
                                </td>
                                <td><?= $pago['noches'] ?> noches</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>📊 No hay datos para mostrar</h3>
                    <p>No se encontraron pagos con los filtros aplicados.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Análisis temporal -->
        <?php if ($ingresos_diarios->num_rows > 0): ?>
            <div class="chart-card">
                <h4>📈 Evolución de Ingresos Diarios</h4>
                <div style="overflow-x: auto;">
                    <table style="min-width: 600px;">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ingresos</th>
                                <th>Pagos</th>
                                <th>Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dia = $ingresos_diarios->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($dia['fecha'])) ?></td>
                                    <td class="monto-cell">$<?= number_format($dia['ingresos_dia'], 0, ',', '.') ?></td>
                                    <td><?= $dia['pagos_dia'] ?></td>
                                    <td>$<?= $dia['pagos_dia'] > 0 ? number_format($dia['ingresos_dia'] / $dia['pagos_dia'], 0, ',', '.') : 0 ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function exportarExcel() {
            // Crear una nueva ventana con los datos para descargar como CSV
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Encabezados del reporte
            csvContent += "REPORTE DE PAGOS - HOTEL RIVO\n";
            csvContent += "Periodo:,<?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>\n";
            csvContent += "Generado:,<?= date('d/m/Y H:i:s') ?>\n";
            csvContent += "Por:,<?= $_SESSION['user_name'] ?? 'Administrador' ?>\n\n";
            
            // Resumen ejecutivo
            csvContent += "RESUMEN EJECUTIVO\n";
            csvContent += "Total de pagos,<?= $stats['total_pagos'] ?>\n";
            csvContent += "Pagos confirmados,<?= $stats['pagos_confirmados'] ?>\n";
            csvContent += "Pagos pendientes,<?= $stats['pagos_pendientes'] ?>\n";
            csvContent += "Pagos rechazados,<?= $stats['pagos_rechazados'] ?>\n";
            csvContent += "Ingresos confirmados,$<?= number_format($stats['ingresos_confirmados'], 0, '.', '') ?>\n";
            csvContent += "Ingresos pendientes,$<?= number_format($stats['ingresos_pendientes'], 0, '.', '') ?>\n";
            csvContent += "Promedio por pago,$<?= $stats['promedio_pago'] ? number_format($stats['promedio_pago'], 0, '.', '') : 0 ?>\n\n";
            
            // Detalle de pagos
            csvContent += "DETALLE DE PAGOS\n";
            csvContent += "ID,Cliente,Email,Habitacion,Tipo,Monto,Estado,Metodo,Fecha,Noches\n";
            
            <?php 
            $pagos->data_seek(0); // Resetear puntero
            while ($pago = $pagos->fetch_assoc()): 
            ?>
            csvContent += "<?= $pago['id_pago'] ?>,<?= str_replace(',', ';', $pago['cliente_nombre']) ?>,<?= $pago['cliente_email'] ?>,<?= $pago['numero_habitacion'] ?>,<?= str_replace(',', ';', $pago['tipo_habitacion']) ?>,<?= $pago['monto'] ?>,<?= $pago['estado'] ?>,<?= $pago['metodo_pago'] ?? 'N/A' ?>,<?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?>,<?= $pago['noches'] ?>\n";
            <?php endwhile; ?>
            
            // Crear y descargar archivo
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "reporte_pagos_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Configurar fechas máximas
        document.getElementById('fecha_inicio').max = new Date().toISOString().split('T')[0];
        document.getElementById('fecha_fin').max = new Date().toISOString().split('T')[0];
        
        // Validar que fecha fin no sea menor que fecha inicio
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            document.getElementById('fecha_fin').min = this.value;
        });
        
        document.getElementById('fecha_fin').addEventListener('change', function() {
            document.getElementById('fecha_inicio').max = this.value;
        });
        
        // Efectos de animación para las estadísticas
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number, .stat-money');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/[^0-9]/g, ''));
                if (finalValue > 0) {
                    animateNumber(stat, finalValue);
                }
            });
        });
        
        function animateNumber(element, target) {
            const duration = 1500;
            const increment = target / (duration / 16);
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (element.classList.contains('stat-money')) {
                    element.textContent = ' + Math.floor(current).toLocaleString();
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 16);
        }
        
        // Funciones para reportes rápidos
        function reporteHoy() {
            const hoy = new Date().toISOString().split('T')[0];
            window.location.href = `?fecha_inicio=${hoy}&fecha_fin=${hoy}`;
        }
        
        function reporteSemana() {
            const hoy = new Date();
            const lunes = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() + 1));
            const domingo = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() + 7));
            
            const fechaInicio = lunes.toISOString().split('T')[0];
            const fechaFin = domingo.toISOString().split('T')[0];
            
            window.location.href = `?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        }
        
        function reporteMes() {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            const ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
            
            const fechaInicio = primerDia.toISOString().split('T')[0];
            const fechaFin = ultimoDia.toISOString().split('T')[0];
            
            window.location.href = `?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
        }
        
        // Agregar tooltips a las estadísticas
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseover', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseout', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body> 
</html>