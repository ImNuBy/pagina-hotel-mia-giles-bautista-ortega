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

// Estadísticas principales mejoradas (corregidas con validación)
try {
    $ingresos = $conn->query("SELECT 
        SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END) AS total_ingresos,
        COUNT(CASE WHEN estado = 'confirmado' THEN 1 END) AS total_pagos,
        AVG(CASE WHEN estado = 'confirmado' THEN monto ELSE NULL END) AS promedio_pago
        FROM pagos")->fetch_assoc();
} catch (Exception $e) {
    $ingresos = ['total_ingresos' => 0, 'total_pagos' => 0, 'promedio_pago' => 0];
}

// Validar datos de ingresos
$ingresos = $ingresos ?: ['total_ingresos' => 0, 'total_pagos' => 0, 'promedio_pago' => 0];

try {
    $ocupacion = $conn->query("SELECT 
        COUNT(CASE WHEN estado = 'ocupada' THEN 1 END) AS habitaciones_ocupadas,
        COUNT(CASE WHEN estado = 'disponible' THEN 1 END) AS habitaciones_disponibles,
        COUNT(CASE WHEN estado = 'mantenimiento' THEN 1 END) AS habitaciones_mantenimiento,
        COUNT(*) AS total_habitaciones
        FROM habitaciones")->fetch_assoc();
} catch (Exception $e) {
    $ocupacion = ['habitaciones_ocupadas' => 0, 'habitaciones_disponibles' => 0, 'habitaciones_mantenimiento' => 0, 'total_habitaciones' => 0];
}

// Validar datos de ocupación
$ocupacion = $ocupacion ?: ['habitaciones_ocupadas' => 0, 'habitaciones_disponibles' => 0, 'habitaciones_mantenimiento' => 0, 'total_habitaciones' => 0];

try {
    $reservas_stats = $conn->query("SELECT 
        COUNT(CASE WHEN estado = 'confirmada' THEN 1 END) AS reservas_confirmadas,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS reservas_pendientes,
        COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) AS reservas_canceladas,
        COUNT(*) AS total_reservas
        FROM reservas")->fetch_assoc();
} catch (Exception $e) {
    $reservas_stats = ['reservas_confirmadas' => 0, 'reservas_pendientes' => 0, 'reservas_canceladas' => 0, 'total_reservas' => 0];
}

// Validar datos de reservas
$reservas_stats = $reservas_stats ?: ['reservas_confirmadas' => 0, 'reservas_pendientes' => 0, 'reservas_canceladas' => 0, 'total_reservas' => 0];

// Verificar si existe la tabla comentarios antes de consultarla
$comentarios_result = $conn->query("SHOW TABLES LIKE 'comentarios'");
if ($comentarios_result->num_rows > 0) {
    $comentarios_stats = $conn->query("SELECT 
        AVG(puntuacion) AS promedio_puntuacion,
        COUNT(*) AS total_comentarios,
        COUNT(CASE WHEN puntuacion >= 4 THEN 1 END) AS comentarios_positivos
        FROM comentarios")->fetch_assoc();
} else {
    $comentarios_stats = [
        'promedio_puntuacion' => 0,
        'total_comentarios' => 0,
        'comentarios_positivos' => 0
    ];
}

// Ingresos mensuales del año actual (corregida con validación)
try {
    $ingresos_mensuales = $conn->query("SELECT 
        MONTH(fecha_pago) AS mes,
        MONTHNAME(fecha_pago) AS nombre_mes,
        SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END) AS ingresos_mes,
        COUNT(CASE WHEN estado = 'confirmado' THEN 1 END) AS pagos_mes
        FROM pagos 
        WHERE YEAR(fecha_pago) = YEAR(CURDATE())
        GROUP BY MONTH(fecha_pago), MONTHNAME(fecha_pago)
        ORDER BY MONTH(fecha_pago)")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $ingresos_mensuales = [];
}

// Asegurar que $ingresos_mensuales sea siempre un array
if (!is_array($ingresos_mensuales)) {
    $ingresos_mensuales = [];
}

// Tipos de habitación más reservados (corregida con manejo de errores)
try {
    $tipos_populares = $conn->query("SELECT 
        th.nombre as tipo_habitacion,
        COUNT(r.id_reserva) as total_reservas,
        SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_tipo
        FROM reservas r
        INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
        INNER JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
        LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
        WHERE r.estado IN ('confirmada', 'completada')
        GROUP BY th.id_tipo, th.nombre
        ORDER BY total_reservas DESC")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $tipos_populares = [];
}

// Asegurar que $tipos_populares sea siempre un array
if (!is_array($tipos_populares)) {
    $tipos_populares = [];
}

// Verificar estructura de tablas y obtener datos de forma segura
try {
    // Verificar si existe la tabla tarifas
    $tarifas_existe = $conn->query("SHOW TABLES LIKE 'tarifas'")->num_rows > 0;
    
    if ($tarifas_existe) {
        // Estadísticas de temporadas (corregida)
        $temporadas_stats = $conn->query("SELECT 
            t.temporada,
            COUNT(CASE WHEN r.fecha_entrada BETWEEN t.fecha_inicio AND t.fecha_fin THEN r.id_reserva END) as reservas_temporada,
            AVG(CASE WHEN p.estado = 'confirmado' AND r.fecha_entrada BETWEEN t.fecha_inicio AND t.fecha_fin THEN p.monto ELSE NULL END) as promedio_ingresos
            FROM tarifas t
            LEFT JOIN habitaciones h ON h.id_tipo = t.id_tipo
            LEFT JOIN reservas r ON r.id_habitacion = h.id_habitacion
            LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
            WHERE t.activa = 1
            GROUP BY t.temporada
            ORDER BY reservas_temporada DESC")->fetch_all(MYSQLI_ASSOC);
    } else {
        $temporadas_stats = [];
    }
} catch (Exception $e) {
    $temporadas_stats = [];
}

// Asegurar que $temporadas_stats sea siempre un array
if (!is_array($temporadas_stats)) {
    $temporadas_stats = [];
}

// Manejo seguro de fechas para el período de análisis
try {
    $fecha_inicio = $conn->query("SELECT MIN(fecha_pago) as primera_fecha FROM pagos WHERE fecha_pago IS NOT NULL")->fetch_assoc();
    $fecha_fin = $conn->query("SELECT MAX(fecha_pago) as ultima_fecha FROM pagos WHERE fecha_pago IS NOT NULL")->fetch_assoc();
} catch (Exception $e) {
    $fecha_inicio = ['primera_fecha' => null];
    $fecha_fin = ['ultima_fecha' => null];
}

// Clientes frecuentes (corregida y con manejo de errores)
try {
    $clientes_frecuentes = $conn->query("SELECT 
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
        LIMIT 10")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $clientes_frecuentes = [];
}

// Asegurar que $clientes_frecuentes sea siempre un array
if (!is_array($clientes_frecuentes)) {
    $clientes_frecuentes = [];
}

// Calcular porcentaje de ocupación de forma segura
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
            border-radius: 10px;
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
            margin-right: 10px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-success { background: #28a745; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 3em;
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
        }
        .stat-subtitle {
            color: #999;
            font-size: 0.9em;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
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
                    <button onclick="actualizarDatos()" class="btn btn-primary">🔄 Actualizar</button>
                    <button onclick="exportarReporte()" class="btn btn-success">📥 Exportar</button>
                </div>
            </div>
        </div>

        <!-- Período de análisis -->
        <div class="period-info">
            <strong>📅 Período de análisis:</strong> 
            <?= $fecha_inicio['primera_fecha'] ? date('d/m/Y', strtotime($fecha_inicio['primera_fecha'])) : 'N/A' ?> - 
            <?= $fecha_fin['ultima_fecha'] ? date('d/m/Y', strtotime($fecha_fin['ultima_fecha'])) : 'N/A' ?>
            (Datos actualizados en tiempo real)
        </div>

        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($ingresos['total_ingresos'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Ingresos Totales</div>
                <div class="stat-subtitle"><?= $ingresos['total_pagos'] ?? 0 ?> pagos realizados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $porcentaje_ocupacion ?>%</div>
                <div class="stat-label">Ocupación Actual</div>
                <div class="stat-subtitle"><?= $ocupacion['habitaciones_ocupadas'] ?? 0 ?>/<?= $ocupacion['total_habitaciones'] ?? 0 ?> habitaciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $reservas_stats['reservas_confirmadas'] ?? 0 ?></div>
                <div class="stat-label">Reservas Confirmadas</div>
                <div class="stat-subtitle">De <?= $reservas_stats['total_reservas'] ?? 0 ?> totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($comentarios_stats['promedio_puntuacion'] ?? 0, 1) ?></div>
                <div class="stat-label">Puntuación Promedio</div>
                <div class="stat-subtitle"><?= $comentarios_stats['total_comentarios'] ?? 0 ?> evaluaciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($ingresos['promedio_pago'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Ticket Promedio</div>
                <div class="stat-subtitle">Por reserva</div>
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
                        <span class="metric-value"><?= $ocupacion['habitaciones_ocupadas'] ?? 0 ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Habitaciones Disponibles</span>
                        <span class="metric-value"><?= $ocupacion['habitaciones_disponibles'] ?? 0 ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">En Mantenimiento</span>
                        <span class="metric-value"><?= $ocupacion['habitaciones_mantenimiento'] ?? 0 ?></span>
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
                        <span class="metric-value"><?= $reservas_stats['reservas_confirmadas'] ?? 0 ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Pendientes</span>
                        <span class="metric-value"><?= $reservas_stats['reservas_pendientes'] ?? 0 ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Canceladas</span>
                        <span class="metric-value"><?= $reservas_stats['reservas_canceladas'] ?? 0 ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Total</span>
                        <span class="metric-value"><?= $reservas_stats['total_reservas'] ?? 0 ?></span>
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
                                    <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                    <td><?= $cliente['total_reservas'] ?></td>
                                    <td class="metric-value money">$<?= number_format($cliente['total_gastado'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div class="loading" id="loadingIndicator">
            <p>🔄 Actualizando datos...</p>
        </div>
    </div>

    <script>
        // Datos para gráficos
        const mesesData = {
            labels: [<?php 
                foreach ($ingresos_mensuales as $mes) {
                    echo "'" . ($mes['nombre_mes'] ?? 'Mes ' . $mes['mes']) . "',";
                } 
            ?>],
            datasets: [{
                label: 'Ingresos (COP)',
                data: [<?php 
                    foreach ($ingresos_mensuales as $mes) {
                        echo ($mes['ingresos_mes'] ?? 0) . ",";
                    } 
                ?>],
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
                    <?= $ocupacion['habitaciones_ocupadas'] ?? 0 ?>, 
                    <?= $ocupacion['habitaciones_disponibles'] ?? 0 ?>, 
                    <?= $ocupacion['habitaciones_mantenimiento'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#dc3545',
                    '#28a745', 
                    '#ffc107'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };

        // Configuración de gráficos
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        };

        // Crear gráfico de ingresos mensuales
        const ctxIngresos = document.getElementById('ingresosMensualesChart').getContext('2d');
        new Chart(ctxIngresos, {
            type: 'line',
            data: mesesData,
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + new Intl.NumberFormat('es-CO').format(value);
                            }
                        }
                    }
                },
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Ingresos: $' + new Intl.NumberFormat('es-CO').format(context.parsed.y);
                            }
                        }
                    }
                }
            }
        });

        // Crear gráfico de ocupación
        const ctxOcupacion = document.getElementById('ocupacionChart').getContext('2d');
        new Chart(ctxOcupacion, {
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
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Funciones de interacción
        function actualizarDatos() {
            const loading = document.getElementById('loadingIndicator');
            loading.style.display = 'block';
            
            // Simular actualización
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        function exportarReporte() {
            // Preparar datos para exportar
            const fechaActual = new Date().toLocaleDateString('es-CO');
            const nombreArchivo = `reporte_hotel_rivo_${fechaActual.replace(/\//g, '_')}.txt`;
            
            let contenido = `REPORTE HOTEL RIVO - ${fechaActual}\n`;
            contenido += `${'='.repeat(50)}\n\n`;
            contenido += `RESUMEN EJECUTIVO:\n`;
            contenido += `- Ingresos Totales: $<?= number_format($ingresos['total_ingresos'] ?? 0, 0, ',', '.') ?>\n`;
            contenido += `- Ocupación Actual: <?= $porcentaje_ocupacion ?>%\n`;
            contenido += `- Reservas Confirmadas: <?= $reservas_stats['reservas_confirmadas'] ?? 0 ?>\n`;
            contenido += `- Puntuación Promedio: <?= number_format($comentarios_stats['promedio_puntuacion'] ?? 0, 1) ?>/5\n\n`;
            
            contenido += `DETALLES DE OCUPACIÓN:\n`;
            contenido += `- Habitaciones Ocupadas: <?= $ocupacion['habitaciones_ocupadas'] ?? 0 ?>\n`;
            contenido += `- Habitaciones Disponibles: <?= $ocupacion['habitaciones_disponibles'] ?? 0 ?>\n`;
            contenido += `- En Mantenimiento: <?= $ocupacion['habitaciones_mantenimiento'] ?? 0 ?>\n`;
            contenido += `- Total Habitaciones: <?= $ocupacion['total_habitaciones'] ?? 0 ?>\n\n`;
            
            // Crear y descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombreArchivo;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            alert('📥 Reporte exportado exitosamente');
        }

        // Auto-actualización cada 5 minutos
        setInterval(() => {
            console.log('🔄 Verificando actualizaciones...');
            // Aquí podrías hacer una petición AJAX para verificar cambios
        }, 300000);

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .report-section, .chart-container');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Función para imprimir reporte
        function imprimirReporte() {
            window.print();
        }

        // Función para generar PDF (requiere librerías adicionales)
        function generarPDF() {
            alert('📄 Función de PDF en desarrollo. Use "Exportar" por ahora.');
        }

        // Función para filtrar por fechas
        function filtrarPorFechas() {
            const fechaInicio = prompt('Fecha de inicio (YYYY-MM-DD):');
            const fechaFin = prompt('Fecha de fin (YYYY-MM-DD):');
            
            if (fechaInicio && fechaFin) {
                window.location.href = `?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
            }
        }

        // Tooltips informativos
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Función para comparar períodos
        function compararPeriodos() {
            alert('📊 Comparación de períodos: Función premium disponible en la versión avanzada');
        }

        // Notificaciones en tiempo real
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${tipo === 'success' ? '#28a745' : '#007bff'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideInRight 0.3s ease;
            `;
            notif.textContent = mensaje;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }

        // CSS para animaciones de notificaciones
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
                .btn, .actions-header { display: none !important; }
                .chart-container { page-break-inside: avoid; }
                body { background: white !important; }
            }
        `;
        document.head.appendChild(style);

        // Simulación de datos en tiempo real
        function simularActualizacionTiempoReal() {
            const elementos = document.querySelectorAll('.stat-number');
            elementos.forEach(el => {
                el.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    el.style.animation = '';
                }, 500);
            });
            mostrarNotificacion('📊 Datos actualizados', 'success');
        }

        // Agregar animación pulse
        const pulseStyle = document.createElement('style');
        pulseStyle.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(pulseStyle);

        // Simulación de actualización cada 30 segundos para demo
        setInterval(simularActualizacionTiempoReal, 30000);
    </script>

    <!-- Sección adicional de herramientas -->
    <div style="background: white; margin-top: 30px; padding: 25px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <h3 style="margin: 0 0 20px 0; color: #333;">🛠️ Herramientas Adicionales</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <button onclick="filtrarPorFechas()" class="btn btn-secondary">📅 Filtrar por Fechas</button>
            <button onclick="compararPeriodos()" class="btn btn-secondary">📊 Comparar Períodos</button>
            <button onclick="imprimirReporte()" class="btn btn-secondary">🖨️ Imprimir</button>
            <button onclick="generarPDF()" class="btn btn-secondary">📄 Generar PDF</button>
        </div>
        <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
            <small><strong>💡 Consejo:</strong> Para análisis detallados, use los filtros de fecha y compare diferentes períodos para identificar tendencias.</small>
        </div>
    </div>
</body>
</html>