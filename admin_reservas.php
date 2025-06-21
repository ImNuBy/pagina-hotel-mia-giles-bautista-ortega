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

// Obtener reservas con información completa
$reservas = $conn->query("SELECT 
    r.*,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    h.numero as numero_habitacion,
    th.nombre as tipo_habitacion,
    th.precio_noche,
    p.monto as monto_pagado,
    p.estado as estado_pago,
    p.fecha_pago
    FROM reservas r
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    ORDER BY r.fecha_reserva DESC");

// Obtener estadísticas de reservas
$stats = $conn->query("SELECT 
    COUNT(*) as total_reservas,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as reservas_pendientes,
    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as reservas_completadas,
    SUM(CASE WHEN DATE(fecha_entrada) = CURDATE() THEN 1 ELSE 0 END) as checkins_hoy,
    SUM(CASE WHEN DATE(fecha_salida) = CURDATE() THEN 1 ELSE 0 END) as checkouts_hoy
    FROM reservas")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reservas - Hotel Rivo</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-number.confirmadas { color: #28a745; }
        .stat-number.canceladas { color: #dc3545; }
        .stat-number.completadas { color: #6f42c1; }
        .stat-number.checkins { color: #17a2b8; }
        .stat-number.checkouts { color: #fd7e14; }
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
        .habitacion-info {
            color: #666;
            font-size: 0.9em;
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
        .pago-info {
            font-size: 0.9em;
        }
        .pago-pendiente { color: #ffc107; }
        .pago-confirmado { color: #28a745; }
        .pago-rechazado { color: #dc3545; }
        .fechas-estancia {
            font-size: 0.9em;
            color: #666;
        }
        .fecha-entrada {
            color: #28a745;
            font-weight: bold;
        }
        .fecha-salida {
            color: #dc3545;
            font-weight: bold;
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
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-confirm { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-complete { background: #6f42c1; color: white; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 24px; }
        .btn-success { background: #28a745; color: white; padding: 12px 24px; }
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
        .no-reservas {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .urgente {
            background: #ffebee !important;
            border-left: 4px solid #f44336;
        }
        .checkin-hoy {
            background: #e8f5e8 !important;
            border-left: 4px solid #28a745;
        }
        .checkout-hoy {
            background: #fff3e0 !important;
            border-left: 4px solid #ff9800;
        }
        .monto-cell {
            color: #28a745;
            font-weight: bold;
        }
        .form-inline {
            display: inline-block;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Gestión de Reservas</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_pagos.php" class="btn btn-secondary">💳 Ver pagos</a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">🛏️ Ver habitaciones</a>
                </div>
                <div>
                    <a href="admin_nueva_reserva.php" class="btn btn-success">➕ Nueva Reserva</a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas de reservas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_reservas'] ?></div>
                <div>Total Reservas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pendientes"><?= $stats['reservas_pendientes'] ?></div>
                <div>Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmadas"><?= $stats['reservas_confirmadas'] ?></div>
                <div>Confirmadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number canceladas"><?= $stats['reservas_canceladas'] ?></div>
                <div>Canceladas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number completadas"><?= $stats['reservas_completadas'] ?></div>
                <div>Completadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number checkins"><?= $stats['checkins_hoy'] ?></div>
                <div>Check-ins Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number checkouts"><?= $stats['checkouts_hoy'] ?></div>
                <div>Check-outs Hoy</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <label><strong>Filtrar por:</strong></label>
            <select id="filtro-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendientes</option>
                <option value="confirmada">Confirmadas</option>
                <option value="cancelada">Canceladas</option>
                <option value="completada">Completadas</option>
            </select>
            
            <select id="filtro-fecha" onchange="filtrarTabla()">
                <option value="">Todas las fechas</option>
                <option value="hoy">Check-in/out hoy</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
            </select>
            
            <select id="filtro-pago" onchange="filtrarTabla()">
                <option value="">Todos los pagos</option>
                <option value="pendiente">Pago pendiente</option>
                <option value="confirmado">Pago confirmado</option>
                <option value="sin-pago">Sin pago</option>
            </select>
            
            <input type="text" id="buscar-reserva" placeholder="Buscar por cliente o ID..." class="search-box" oninput="filtrarTabla()">
            
            <button onclick="limpiarFiltros()" class="btn btn-secondary">🔄 Limpiar</button>
        </div>
        
        <!-- Tabla de reservas -->
        <div class="reservas-table">
            <div class="table-header">
                <h3>📋 Lista de Reservas</h3>
                <span><?= $reservas->num_rows ?> reservas registradas</span>
            </div>
            
            <?php if ($reservas->num_rows > 0): ?>
                <table id="tabla-reservas">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Habitación</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Fecha Reserva</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reserva = $reservas->fetch_assoc()): ?>
                            <?php
                            $hoy = date('Y-m-d');
                            $fecha_entrada = $reserva['fecha_entrada'];
                            $fecha_salida = $reserva['fecha_salida'];
                            $clase_especial = '';
                            
                            if ($fecha_entrada == $hoy) {
                                $clase_especial = 'checkin-hoy';
                            } elseif ($fecha_salida == $hoy) {
                                $clase_especial = 'checkout-hoy';
                            } elseif ($reserva['estado'] == 'pendiente' && $fecha_entrada <= date('Y-m-d', strtotime('+2 days'))) {
                                $clase_especial = 'urgente';
                            }
                            ?>
                            <tr class="<?= $clase_especial ?>"
                                data-estado="<?= $reserva['estado'] ?>"
                                data-fecha-entrada="<?= $reserva['fecha_entrada'] ?>"
                                data-fecha-salida="<?= $reserva['fecha_salida'] ?>"
                                data-estado-pago="<?= $reserva['estado_pago'] ?>"
                                data-cliente="<?= strtolower($reserva['cliente_nombre']) ?>">
                                <td class="reserva-id">#<?= $reserva['id_reserva'] ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar">
                                            <?= strtoupper(substr($reserva['cliente_nombre'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="cliente-nombre"><?= htmlspecialchars($reserva['cliente_nombre']) ?></div>
                                            <div class="cliente-email"><?= htmlspecialchars($reserva['cliente_email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="habitacion-info">
                                        <strong>Hab. #<?= $reserva['numero_habitacion'] ?></strong><br>
                                        <?= htmlspecialchars($reserva['tipo_habitacion']) ?><br>
                                        <span class="monto-cell">$<?= number_format($reserva['precio_noche'], 0, ',', '.') ?>/noche</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fechas-estancia">
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
                                    <?php if ($fecha_entrada == $hoy): ?>
                                        <div style="font-size: 0.7em; color: #28a745; margin-top: 3px;">
                                            ✅ Check-in hoy
                                        </div>
                                    <?php elseif ($fecha_salida == $hoy): ?>
                                        <div style="font-size: 0.7em; color: #ff9800; margin-top: 3px;">
                                            📤 Check-out hoy
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reserva['monto_pagado']): ?>
                                        <div class="pago-info pago-<?= $reserva['estado_pago'] ?>">
                                            <strong>$<?= number_format($reserva['monto_pagado'], 0, ',', '.') ?></strong><br>
                                            <small><?= ucfirst($reserva['estado_pago']) ?></small>
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
                                    <a href="admin_reserva_detalle.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-view" title="Ver detalles">👁️</a>
                                    
                                    <?php if ($reserva['estado'] == 'pendiente'): ?>
                                        <form class="form-inline" action="admin_confirmar_reserva.php" method="POST">
                                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                            <button type="submit" class="btn btn-confirm" title="Confirmar" 
                                                    onclick="return confirm('¿Confirmar esta reserva?')">
                                                ✅
                                            </button>
                                        </form>
                                        <form class="form-inline" action="admin_cancelar_reserva.php" method="POST">
                                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                            <button type="submit" class="btn btn-cancel" title="Cancelar"
                                                    onclick="return confirm('¿Cancelar esta reserva?')">
                                                ❌
                                            </button>
                                        </form>
                                    <?php elseif ($reserva['estado'] == 'confirmada' && $fecha_salida <= $hoy): ?>
                                        <form class="form-inline" action="admin_completar_reserva.php" method="POST">
                                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                                            <button type="submit" class="btn btn-complete" title="Marcar como completada"
                                                    onclick="return confirm('¿Marcar como completada?')">
                                                ✅ Completar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="admin_reserva_editar.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-edit" title="Editar">✏️</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-reservas">
                    <h3>📅 No hay reservas registradas</h3>
                    <p>Las reservas aparecerán aquí cuando los usuarios realicen reservaciones.</p>
                    <a href="admin_nueva_reserva.php" class="btn btn-success">➕ Crear Primera Reserva</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filtrarTabla() {
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroFecha = document.getElementById('filtro-fecha').value;
            const filtroPago = document.getElementById('filtro-pago').value;
            const buscarTexto = document.getElementById('buscar-reserva').value.toLowerCase();
            const filas = document.querySelectorAll('#tabla-reservas tbody tr');
            
            let filasVisibles = 0;
            const hoy = new Date().toISOString().split('T')[0];
            
            filas.forEach(fila => {
                const estado = fila.dataset.estado;
                const fechaEntrada = fila.dataset.fechaEntrada;
                const fechaSalida = fila.dataset.fechaSalida;
                const estadoPago = fila.dataset.estadoPago;
                const cliente = fila.dataset.cliente;
                const idReserva = fila.querySelector('.reserva-id').textContent.toLowerCase();
                
                let mostrar = true;
                
                // Filtro por estado
                if (filtroEstado && estado !== filtroEstado) {
                    mostrar = false;
                }
                
                // Filtro por fecha
                if (filtroFecha) {
                    if (filtroFecha === 'hoy' && fechaEntrada !== hoy && fechaSalida !== hoy) {
                        mostrar = false;
                    }
                    // Agregar más lógica de fechas según necesites
                }
                
                // Filtro por pago
                if (filtroPago) {
                    if (filtroPago === 'sin-pago' && estadoPago) {
                        mostrar = false;
                    } else if (filtroPago !== 'sin-pago' && estadoPago !== filtroPago) {
                        mostrar = false;
                    }
                }
                
                // Búsqueda por texto
                if (buscarTexto && !cliente.includes(buscarTexto) && !idReserva.includes(buscarTexto)) {
                    mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) filasVisibles++;
            });
            
            // Actualizar contador
            const contador = document.querySelector('.table-header span');
            contador.textContent = `${filasVisibles} reservas mostradas`;
        }
        
        function limpiarFiltros() {
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-fecha').value = '';
            document.getElementById('filtro-pago').value = '';
            document.getElementById('buscar-reserva').value = '';
            filtrarTabla();
        }
    </script>
</body>
</html>