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

// Obtener pagos con información detallada
$pagos = $conn->query("SELECT 
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
    h.numero as numero_habitacion
    FROM pagos p 
    INNER JOIN reservas r ON p.id_reserva = r.id_reserva
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    ORDER BY p.fecha_pago DESC");

// Obtener estadísticas de pagos
$stats = $conn->query("SELECT 
    COUNT(*) as total_pagos,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pagos_pendientes,
    SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) as pagos_confirmados,
    SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as pagos_rechazados,
    SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END) as ingresos_confirmados,
    SUM(CASE WHEN estado = 'pendiente' THEN monto ELSE 0 END) as ingresos_pendientes,
    SUM(CASE WHEN DATE(fecha_pago) = CURDATE() THEN monto ELSE 0 END) as ingresos_hoy
    FROM pagos")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pagos - Hotel Rivo</title>
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
        .stat-number.pendientes { color: #ffc107; }
        .stat-number.confirmados { color: #28a745; }
        .stat-number.ingresos { color: #17a2b8; }
        .stat-money {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #28a745;
        }
        .pagos-table {
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
        .pago-id {
            font-weight: bold;
            color: #007bff;
        }
        .monto-cell {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1em;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-confirmado { background: #d4edda; color: #155724; }
        .estado-rechazado { background: #f8d7da; color: #721c24; }
        .estado-cancelado { background: #e2e3e5; color: #383d41; }
        .reserva-info {
            font-size: 0.9em;
            color: #666;
        }
        .cliente-info {
            display: flex;
            align-items: center;
        }
        .cliente-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            font-size: 0.9em;
        }
        .cliente-nombre {
            font-weight: bold;
            color: #333;
        }
        .cliente-email {
            color: #666;
            font-size: 0.8em;
        }
        .acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-confirm { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 24px; }
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
        .filtros {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filtros select, .filtros input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-box {
            width: 250px;
        }
        .fecha-pago {
            color: #666;
            font-size: 0.9em;
        }
        .metodo-pago {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            color: #495057;
        }
        .no-pagos {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .habitacion-info {
            color: #666;
            font-size: 0.9em;
        }
        .form-inline {
            display: inline-block;
            margin-right: 5px;
        }
        .urgente {
            background: #ffebee !important;
            border-left: 4px solid #f44336;
        }
        .periodo-estancia {
            font-size: 0.8em;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Gestión de Pagos</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Ver reservas</a>
                    <a href="admin_usuarios.php" class="btn btn-secondary">👥 Ver usuarios</a>
                </div>
                <div>
                    <a href="admin_reporte_pagos.php" class="btn btn-primary">📊 Generar Reporte</a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas de pagos -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_pagos'] ?></div>
                <div>Total Pagos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pendientes"><?= $stats['pagos_pendientes'] ?></div>
                <div>Pagos Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmados"><?= $stats['pagos_confirmados'] ?></div>
                <div>Pagos Confirmados</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($stats['ingresos_confirmados'], 0, ',', '.') ?></div>
                <div>Ingresos Confirmados</div>
            </div>
        </div>
        
        <!-- Métricas adicionales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($stats['ingresos_pendientes'], 0, ',', '.') ?></div>
                <div>Ingresos Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= number_format($stats['ingresos_hoy'], 0, ',', '.') ?></div>
                <div>Ingresos Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pagos_rechazados'] ?></div>
                <div>Pagos Rechazados</div>
            </div>
            <div class="stat-card">
                <div class="stat-money">$<?= $stats['total_pagos'] > 0 ? number_format($stats['ingresos_confirmados'] / $stats['total_pagos'], 0, ',', '.') : 0 ?></div>
                <div>Promedio por Pago</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <label><strong>Filtrar por:</strong></label>
            <select id="filtro-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="pendiente">Solo pendientes</option>
                <option value="confirmado">Solo confirmados</option>
                <option value="rechazado">Solo rechazados</option>
            </select>
            
            <select id="filtro-fecha" onchange="filtrarTabla()">
                <option value="">Todas las fechas</option>
                <option value="hoy">Hoy</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
            </select>
            
            <input type="text" id="buscar-pago" placeholder="Buscar por cliente o ID..." class="search-box" oninput="filtrarTabla()">
            
            <button onclick="limpiarFiltros()" class="btn btn-secondary">🔄 Limpiar</button>
        </div>
        
        <!-- Tabla de pagos -->
        <div class="pagos-table">
            <div class="table-header">
                <h3>💰 Lista de Pagos</h3>
                <span><?= $pagos->num_rows ?> pagos registrados</span>
            </div>
            
            <?php if ($pagos->num_rows > 0): ?>
                <table id="tabla-pagos">
                    <thead>
                        <tr>
                            <th>ID Pago</th>
                            <th>Cliente</th>
                            <th>Reserva</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Método</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pago = $pagos->fetch_assoc()): ?>
                            <tr class="<?= ($pago['estado'] == 'pendiente' && strtotime($pago['fecha_entrada']) <= strtotime('+3 days')) ? 'urgente' : '' ?>"
                                data-estado="<?= $pago['estado'] ?>"
                                data-fecha="<?= $pago['fecha_pago'] ?>"
                                data-cliente="<?= strtolower($pago['cliente_nombre']) ?>">
                                <td class="pago-id">#<?= $pago['id_pago'] ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar">
                                            <?= strtoupper(substr($pago['cliente_nombre'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="cliente-nombre"><?= htmlspecialchars($pago['cliente_nombre']) ?></div>
                                            <div class="cliente-email"><?= htmlspecialchars($pago['cliente_email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="reserva-info">
                                        <strong>Reserva #<?= $pago['id_reserva'] ?></strong><br>
                                        <div class="habitacion-info">
                                            <?= htmlspecialchars($pago['tipo_habitacion']) ?><br>
                                            Hab. #<?= $pago['numero_habitacion'] ?>
                                        </div>
                                        <div class="periodo-estancia">
                                            <?= date('d/m/Y', strtotime($pago['fecha_entrada'])) ?> - 
                                            <?= date('d/m/Y', strtotime($pago['fecha_salida'])) ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="monto-cell">$<?= number_format($pago['monto'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="estado-badge estado-<?= $pago['estado'] ?>">
                                        <?= ucfirst($pago['estado']) ?>
                                    </span>
                                    <?php if ($pago['estado'] == 'pendiente' && strtotime($pago['fecha_entrada']) <= strtotime('+3 days')): ?>
                                        <div style="font-size: 0.7em; color: #dc3545; margin-top: 3px;">
                                            ⚠️ Check-in próximo
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="metodo-pago">
                                        <?= $pago['metodo_pago'] ? htmlspecialchars($pago['metodo_pago']) : 'No especificado' ?>
                                    </span>
                                </td>
                                <td class="fecha-pago">
                                    <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?><br>
                                    <small><?= date('H:i', strtotime($pago['fecha_pago'])) ?></small>
                                </td>
                                <td class="acciones">
                                    <?php if ($pago['estado'] == 'pendiente'): ?>
                                        <form class="form-inline" action="admin_confirmar_pago.php" method="POST">
                                            <input type="hidden" name="id_pago" value="<?= $pago['id_pago'] ?>">
                                            <button type="submit" class="btn btn-confirm" title="Confirmar pago" 
                                                    onclick="return confirm('¿Confirmar el pago de $<?= number_format($pago['monto'], 0, ',', '.') ?>?')">
                                                ✅ Confirmar
                                            </button>
                                        </form>
                                        <form class="form-inline" action="admin_rechazar_pago.php" method="POST">
                                            <input type="hidden" name="id_pago" value="<?= $pago['id_pago'] ?>">
                                            <button type="submit" class="btn btn-reject" title="Rechazar pago"
                                                    onclick="return confirm('¿Rechazar este pago?')">
                                                ❌ Rechazar
                                            </button>
                                        </form>
                                    <?php elseif ($pago['estado'] == 'confirmado'): ?>
                                        <span style="color: #28a745; font-weight: bold;">✅ Confirmado</span>
                                    <?php elseif ($pago['estado'] == 'rechazado'): ?>
                                        <span style="color: #dc3545; font-weight: bold;">❌ Rechazado</span>
                                    <?php endif; ?>
                                    
                                    <a href="admin_pago_detalle.php?id=<?= $pago['id_pago'] ?>" class="btn btn-view" title="Ver detalles">👁️</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-pagos">
                    <h3>💳 No hay pagos registrados</h3>
                    <p>Los pagos aparecerán aquí cuando los usuarios realicen reservas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filtrarTabla() {
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroFecha = document.getElementById('filtro-fecha').value;
            const buscarTexto = document.getElementById('buscar-pago').value.toLowerCase();
            const filas = document.querySelectorAll('#tabla-pagos tbody tr');
            
            let filasVisibles = 0;
            const hoy = new Date();
            
            filas.forEach(fila => {
                const estado = fila.dataset.estado;
                const fechaPago = new Date(fila.dataset.fecha);
                const cliente = fila.dataset.cliente;
                const idPago = fila.querySelector('.pago-id').textContent.toLowerCase();
                
                let mostrar = true;
                
                // Filtro por estado
                if (filtroEstado && estado !== filtroEstado) {
                    mostrar = false;
                }
                
                // Filtro por fecha
                if (filtroFecha) {
                    const diffTime = Math.abs(hoy - fechaPago);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (filtroFecha === 'hoy' && diffDays > 1) {
                        mostrar = false;
                    } else if (filtroFecha === 'semana' && diffDays > 7) {
                        mostrar = false;
                    } else if (filtroFecha === 'mes' && diffDays > 30) {
                        mostrar = false;
                    }
                }
                
                // Búsqueda por texto
                if (buscarTexto && !cliente.includes(buscarTexto) && !idPago.includes(buscarTexto)) {
                    mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) filasVisibles++;
            });
            
            // Actualizar contador
            const contador = document.querySelector('.table-header span');
            contador.textContent = `${filasVisibles} pagos mostrados`;
        }
        
        function limpiarFiltros() {
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-fecha').value = '';
            document.getElementById('buscar-pago').value = '';
            filtrarTabla();
        }
        
        // Resaltar pagos urgentes
        document.addEventListener('DOMContentLoaded', function() {
            const filasUrgentes = document.querySelectorAll('tr.urgente');
            filasUrgentes.forEach(fila => {
                fila.style.animation = 'pulse 2s infinite';
            });
        });
        
        // Actualización automática cada 30 segundos para pagos pendientes
        setInterval(function() {
            if (document.getElementById('filtro-estado').value === 'pendiente' || 
                document.getElementById('filtro-estado').value === '') {
                // Solo recargar si estamos viendo pagos pendientes
                // location.reload();
            }
        }, 30000);
    </script>
</body>
</html>