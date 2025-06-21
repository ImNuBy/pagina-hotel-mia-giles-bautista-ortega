<?php 
session_start(); 
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {     
    header('Location: login.php');     
    exit; 
}  

// Incluir el sistema de precios dinámicos
require_once 'pricing_system.php';

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');  

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$pricing = new HotelPricingSystem($conn);

// Obtener habitaciones con información del tipo y precios efectivos
$habitaciones = $conn->query("SELECT 
    h.id_habitacion,
    h.numero,
    h.estado,
    t.id_tipo,
    t.nombre as tipo_nombre,
    t.precio_noche,
    t.capacidad,
    t.activo as tipo_activo
    FROM habitaciones h
    JOIN tipos_habitacion t ON h.id_tipo = t.id_tipo
    ORDER BY h.numero ASC");

// Obtener estadísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total_habitaciones,
    SUM(CASE WHEN h.estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN h.estado = 'ocupada' THEN 1 ELSE 0 END) as ocupadas,
    SUM(CASE WHEN h.estado = 'mantenimiento' THEN 1 ELSE 0 END) as mantenimiento,
    COUNT(DISTINCT h.id_tipo) as tipos_utilizados
    FROM habitaciones h
    JOIN tipos_habitacion t ON h.id_tipo = t.id_tipo")->fetch_assoc();

// Obtener estadísticas por tipo con precios efectivos
$tipos_habitacion = $conn->query("SELECT 
    t.id_tipo,
    t.nombre as tipo_nombre,
    t.precio_noche,
    COUNT(h.id_habitacion) as total_habitaciones,
    SUM(CASE WHEN h.estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN h.estado = 'ocupada' THEN 1 ELSE 0 END) as ocupadas,
    SUM(CASE WHEN h.estado = 'mantenimiento' THEN 1 ELSE 0 END) as mantenimiento
    FROM tipos_habitacion t
    LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo
    WHERE t.activo = TRUE
    GROUP BY t.id_tipo, t.nombre, t.precio_noche
    ORDER BY t.precio_noche ASC");

// Crear array con información de precios efectivos
$tipos_con_precios = [];
while ($tipo = $tipos_habitacion->fetch_assoc()) {
    $precio_efectivo = $pricing->getPrecioEfectivo($tipo['id_tipo']);
    $puede_editar_base = $pricing->puedeEditarPrecioBase($tipo['id_tipo']);
    
    $tipos_con_precios[] = array_merge($tipo, [
        'precio_efectivo_hoy' => $precio_efectivo['precio_efectivo'],
        'fuente_precio' => $precio_efectivo['fuente'],
        'tarifa_activa' => $precio_efectivo['tarifa_nombre'],
        'descuento_activo' => $precio_efectivo['descuento'],
        'puede_editar_precio_base' => $puede_editar_base
    ]);
}
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Gestión de Habitaciones - Hotel Rivo</title>
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
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-number.total { color: #007bff; }
        .stat-number.disponible { color: #28a745; }
        .stat-number.ocupada { color: #dc3545; }
        .stat-number.mantenimiento { color: #ffc107; }
        .tipos-summary {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .tipo-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
            position: relative;
        }
        .tipo-row:last-child {
            border-bottom: none;
        }
        .tipo-info {
            flex: 1;
        }
        .tipo-nombre {
            font-weight: bold;
            font-size: 1.1em;
        }
        .precio-info {
            margin: 8px 0;
        }
        .precio-base {
            color: #6c757d;
            font-size: 0.9em;
            text-decoration: line-through;
        }
        .precio-efectivo {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1em;
        }
        .tarifa-badge {
            background: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .descuento-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .precio-base-only {
            color: #28a745;
            font-weight: bold;
        }
        .tipo-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .mini-stat {
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
        }
        .mini-stat.disponible { background: #d4edda; color: #155724; }
        .mini-stat.ocupada { background: #f8d7da; color: #721c24; }
        .mini-stat.mantenimiento { background: #fff3cd; color: #856404; }
        .habitaciones-table {
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
        .pricing-alert {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
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
        .habitacion-numero {
            font-weight: bold;
            font-size: 1.1em;
            color: #007bff;
        }
        .tipo-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #495057;
        }
        .precio-cell {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .precio-efectivo-cell {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1em;
        }
        .precio-base-cell {
            color: #6c757d;
            font-size: 0.9em;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-disponible { background: #d4edda; color: #155724; }
        .estado-ocupada { background: #f8d7da; color: #721c24; }
        .estado-mantenimiento { background: #fff3cd; color: #856404; }
        .tipo-inactivo {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .acciones {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
        }
        .btn-edit { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 24px; }
        .btn-warning { background: #ffc107; color: #212529; padding: 12px 24px; }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-box {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
        }
        .filtros {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
        }
        .filtros select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .editar-precio-btn {
            background: #ffc107;
            color: #212529;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8em;
            margin-left: 8px;
        }
        .no-editable {
            color: #6c757d;
            font-size: 0.8em;
            font-style: italic;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>🏨 Gestión de Habitaciones</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_tipos_habitacion.php" class="btn btn-secondary">🏷️ Gestionar Tipos</a>
                    <a href="admin_pricing_management.php" class="btn btn-warning">💰 Gestión de Precios</a>
                </div>
                <div>
                    <a href="admin_habitacion_nueva.php" class="btn btn-primary">➕ Agregar Nueva Habitación</a>
                </div>
            </div>
        </div>

        <!-- Alerta sobre sistema de precios -->
        <div class="pricing-alert">
            <strong>💡 Sistema de Precios Dinámicos Activo:</strong> 
            Los precios mostrados incluyen tarifas especiales vigentes. 
            Las tarifas activas sobrescriben automáticamente los precios base de los tipos de habitación.
            <a href="admin_pricing_management.php" style="color: #007bff; text-decoration: underline;">Gestionar precios →</a>
        </div>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_habitaciones'] ?></div>
                <div>Total Habitaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number disponible"><?= $stats['disponibles'] ?></div>
                <div>Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number ocupada"><?= $stats['ocupadas'] ?></div>
                <div>Ocupadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number mantenimiento"><?= $stats['mantenimiento'] ?></div>
                <div>En Mantenimiento</div>
            </div>
        </div>
        
        <!-- Resumen por tipos con precios dinámicos -->
        <div class="tipos-summary">
            <h3>📊 Resumen por Tipo de Habitación (Precios Efectivos Hoy)</h3>
            <?php foreach ($tipos_con_precios as $tipo): ?>
                <div class="tipo-row">
                    <div class="tipo-info">
                        <div class="tipo-nombre"><?= htmlspecialchars($tipo['tipo_nombre']) ?></div>
                        
                        <div class="precio-info">
                            <?php if ($tipo['fuente_precio'] == 'tarifa'): ?>
                                <!-- Precio con tarifa activa -->
                                <?php if ($tipo['descuento_activo'] > 0): ?>
                                    <span class="precio-base">$<?= number_format($tipo['precio_noche'], 0, ',', '.') ?>/noche</span>
                                <?php endif; ?>
                                <span class="precio-efectivo">$<?= number_format($tipo['precio_efectivo_hoy'], 0, ',', '.') ?>/noche</span>
                                <span class="tarifa-badge">🎯 <?= htmlspecialchars($tipo['tarifa_activa']) ?></span>
                                <?php if ($tipo['descuento_activo'] > 0): ?>
                                    <span class="descuento-badge"><?= $tipo['descuento_activo'] ?>% OFF</span>
                                <?php endif; ?>
                                
                                <?php if (!$tipo['puede_editar_precio_base']): ?>
                                    <div class="no-editable">
                                        🔒 Precio base no editable (tarifa activa)
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Solo precio base -->
                                <span class="precio-base-only">$<?= number_format($tipo['precio_efectivo_hoy'], 0, ',', '.') ?>/noche</span>
                                <span style="color: #6c757d; font-size: 0.9em;">(Precio base)</span>
                                
                                <?php if ($tipo['puede_editar_precio_base']): ?>
                                    <a href="admin_tipos_habitacion.php?editar=<?= $tipo['id_tipo'] ?>" class="editar-precio-btn">
                                        ✏️ Editar precio base
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tipo-stats">
                        <div class="mini-stat disponible">
                            <?= $tipo['disponibles'] ?> Disponibles
                        </div>
                        <div class="mini-stat ocupada">
                            <?= $tipo['ocupadas'] ?> Ocupadas
                        </div>
                        <div class="mini-stat mantenimiento">
                            <?= $tipo['mantenimiento'] ?> Mantenimiento
                        </div>
                        <div style="font-weight: bold;">
                            Total: <?= $tipo['total_habitaciones'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <label><strong>Filtrar por:</strong></label>
            <select id="filtro-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="disponible">Disponible</option>
                <option value="ocupada">Ocupada</option>
                <option value="mantenimiento">Mantenimiento</option>
            </select>
            
            <input type="text" id="buscar-numero" placeholder="Buscar por número..." class="search-box" oninput="filtrarTabla()">
            
            <button onclick="limpiarFiltros()" class="btn btn-secondary" style="padding: 8px 12px;">
                🔄 Limpiar filtros
            </button>
        </div>
        
        <!-- Tabla de habitaciones -->
        <div class="habitaciones-table">
            <div class="table-header">
                <h3>🛏️ Lista Completa de Habitaciones</h3>
                <div>
                    <span style="font-size: 0.9em; opacity: 0.9;">
                        💰 Precios actualizados en tiempo real
                    </span>
                </div>
            </div>
            
            <table id="tabla-habitaciones">         
                <thead>
                    <tr>             
                        <th>ID</th>             
                        <th>Número</th>             
                        <th>Tipo</th>             
                        <th>Precio Efectivo/Noche</th>             
                        <th>Capacidad</th>             
                        <th>Estado</th>             
                        <th>Estado Tipo</th>             
                        <th>Acciones</th>         
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $habitaciones->data_seek(0); // Reset pointer
                    while ($hab = $habitaciones->fetch_assoc()): 
                        // Obtener precio efectivo para esta habitación
                        $precio_efectivo_hab = $pricing->getPrecioEfectivo($hab['id_tipo']);
                    ?>             
                        <tr class="<?= !$hab['tipo_activo'] ? 'tipo-inactivo' : '' ?>" 
                            data-estado="<?= $hab['estado'] ?>" 
                            data-tipo="<?= $hab['id_tipo'] ?>"
                            data-numero="<?= $hab['numero'] ?>">                 
                            <td><?= $hab['id_habitacion'] ?></td>                 
                            <td class="habitacion-numero">#<?= $hab['numero'] ?></td>                 
                            <td>
                                <span class="tipo-badge"><?= htmlspecialchars($hab['tipo_nombre']) ?></span>
                            </td>                 
                            <td class="precio-cell">
                                <div class="precio-efectivo-cell">
                                    $<?= number_format($precio_efectivo_hab['precio_efectivo'], 0, ',', '.') ?>
                                </div>
                                
                                <?php if ($precio_efectivo_hab['fuente'] == 'tarifa'): ?>
                                    <div style="display: flex; align-items: center; gap: 5px; margin-top: 5px;">
                                        <span class="tarifa-badge" style="font-size: 0.7em;">
                                            🎯 <?= htmlspecialchars($precio_efectivo_hab['tarifa_nombre']) ?>
                                        </span>
                                        <?php if ($precio_efectivo_hab['descuento'] > 0): ?>
                                            <span class="descuento-badge" style="font-size: 0.7em;">
                                                <?= $precio_efectivo_hab['descuento'] ?>% OFF
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($precio_efectivo_hab['descuento'] > 0): ?>
                                        <div class="precio-base-cell">
                                            Precio orig: $<?= number_format($hab['precio_noche'], 0, ',', '.') ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="precio-base-cell">
                                        💰 Precio base
                                    </div>
                                <?php endif; ?>
                            </td>                 
                            <td><?= $hab['capacidad'] ?> personas</td>                 
                            <td>
                                <span class="estado-badge estado-<?= $hab['estado'] ?>">
                                    <?= ucfirst($hab['estado']) ?>
                                </span>
                            </td>                 
                            <td>
                                <?php if (!$hab['tipo_activo']): ?>
                                    <span style="color: #dc3545; font-weight: bold;">⚠️ Tipo Inactivo</span>
                                <?php else: ?>
                                    <span style="color: #28a745;">✅ Activo</span>
                                <?php endif; ?>
                            </td>                 
                            <td class="acciones">                     
                                <a href="admin_habitacion_editar.php?id=<?= $hab['id_habitacion'] ?>" class="btn btn-edit">✏️ Editar</a>                     
                                <a href="admin_habitacion_eliminar.php?id=<?= $hab['id_habitacion'] ?>" 
                                   class="btn btn-delete"
                                   onclick="return confirmarEliminacion(<?= $hab['numero'] ?>, '<?= htmlspecialchars($hab['tipo_nombre']) ?>')">
                                   🗑️ Eliminar
                                </a>                 
                            </td>             
                        </tr>         
                    <?php endwhile; ?>
                </tbody>     
            </table>
            
            <?php if ($habitaciones->num_rows == 0): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No hay habitaciones registradas</h3>
                    <p>¡Comience agregando la primera habitación!</p>
                    <a href="admin_habitacion_nueva.php" class="btn btn-primary">➕ Agregar Primera Habitación</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel de información sobre precios dinámicos -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 30px;">
            <h3>💡 Información sobre el Sistema de Precios Dinámicos</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #007bff;">
                    <h4 style="color: #007bff; margin-top: 0;">🎯 Tarifas Activas</h4>
                    <p>Las tarifas especiales sobrescriben automáticamente los precios base cuando están vigentes.</p>
                </div>
                
                <div style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-top: 0;">💰 Precios Base</h4>
                    <p>Solo se usan cuando no hay tarifas activas. No se pueden editar si hay tarifas vigentes.</p>
                </div>
                
                <div style="padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #ffc107;">
                    <h4 style="color: #ffc107; margin-top: 0;">⚡ Actualización Automática</h4>
                    <p>Los precios se actualizan automáticamente según las tarifas programadas.</p>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 8px; border-left: 4px solid #007bff;">
                <strong>🔧 Gestión de Precios:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Para crear nuevas tarifas especiales → <a href="admin_tarifa_crear.php" style="color: #007bff;">Crear Tarifa</a></li>
                    <li>Para ver todas las tarifas → <a href="admin_tarifas.php" style="color: #007bff;">Ver Tarifas</a></li>
                    <li>Para gestión completa de precios → <a href="admin_pricing_management.php" style="color: #007bff;">Panel de Precios</a></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function confirmarEliminacion(numero, tipo) {
            return confirm(`¿Está seguro de eliminar la habitación #${numero}?\n\nTipo: ${tipo}\n\n⚠️ Esta acción no se puede deshacer.`);
        }
        
        function filtrarTabla() {
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroTipo = document.getElementById('filtro-tipo').value;
            const buscarNumero = document.getElementById('buscar-numero').value.toLowerCase();
            const filas = document.querySelectorAll('#tabla-habitaciones tbody tr');
            
            let filasVisibles = 0;
            
            filas.forEach(fila => {
                const estado = fila.dataset.estado;
                const tipo = fila.dataset.tipo;
                const numero = fila.dataset.numero;
                
                let mostrar = true;
                
                // Filtro por estado
                if (filtroEstado && estado !== filtroEstado) {
                    mostrar = false;
                }
                
                // Filtro por tipo
                if (filtroTipo && tipo !== filtroTipo) {
                    mostrar = false;
                }
                
                // Búsqueda por número
                if (buscarNumero && !numero.includes(buscarNumero)) {
                    mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) filasVisibles++;
            });
            
            // Mostrar mensaje si no hay resultados
            let mensajeNoResultados = document.getElementById('no-resultados');
            if (filasVisibles === 0) {
                if (!mensajeNoResultados) {
                    mensajeNoResultados = document.createElement('tr');
                    mensajeNoResultados.id = 'no-resultados';
                    mensajeNoResultados.innerHTML = '<td colspan="8" style="text-align: center; padding: 40px; color: #666;"><h3>No se encontraron habitaciones</h3><p>Intente ajustar los filtros de búsqueda</p></td>';
                    document.querySelector('#tabla-habitaciones tbody').appendChild(mensajeNoResultados);
                }
                mensajeNoResultados.style.display = '';
            } else if (mensajeNoResultados) {
                mensajeNoResultados.style.display = 'none';
            }
        }
        
        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-tipo').value = '';
            document.getElementById('buscar-numero').value = '';
            filtrarTabla();
        }

        // Actualizar precios cada 5 minutos
        setInterval(() => {
            const now = new Date();
            if (now.getMinutes() % 5 === 0 && now.getSeconds() < 10) {
                console.log('Actualizando precios...');
                // Solo mostrar un indicador visual, no recargar automáticamente
                showUpdateNotification();
            }
        }, 1000);

        function showUpdateNotification() {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #007bff;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 1000;
                cursor: pointer;
            `;
            notification.innerHTML = `
                <strong>💰 Precios actualizados</strong><br>
                <small>Haz clic para recargar la página</small>
            `;
            
            notification.onclick = () => location.reload();
            document.body.appendChild(notification);
            
            // Auto-remove después de 5 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Tooltip para explicar precios dinámicos
        document.querySelectorAll('.precio-cell').forEach(cell => {
            cell.title = 'Los precios se actualizan automáticamente según las tarifas vigentes. Haz clic en "Gestión de Precios" para administrar tarifas.';
        });
    </script>
</body> 
</html>

<?php
$conn->close();
?>
            
            <select id="filtro-tipo" onchange="filtrarTabla()">
                <option value="">Todos los tipos</option>
                <?php 
                $tipos_filtro = $conn->query("SELECT DISTINCT t.id_tipo, t.nombre FROM tipos_habitacion t JOIN habitaciones h ON t.id_tipo = h.id_tipo ORDER BY t.nombre");
                while ($tipo = $tipos_filtro->fetch_assoc()): 
                ?>
                    <option value="<?= $tipo['id_tipo'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                <?php endwhile; ?>