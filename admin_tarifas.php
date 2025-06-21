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

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'actualizar_precio_base':
                try {
                    $id_tipo = intval($_POST['id_tipo']);
                    $nuevo_precio = floatval($_POST['nuevo_precio']);
                    
                    if ($pricing->actualizarPrecioBase($id_tipo, $nuevo_precio)) {
                        $mensaje = "Precio base actualizado correctamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar el precio base";
                        $tipo_mensaje = "error";
                    }
                } catch (Exception $e) {
                    $mensaje = $e->getMessage();
                    $tipo_mensaje = "error";
                }
                break;
                
            case 'toggle_tarifa':
                $id_tarifa = intval($_POST['id_tarifa']);
                $nuevo_estado = intval($_POST['nuevo_estado']);
                
                $stmt = $conn->prepare("UPDATE tarifas SET activa = ? WHERE id_tarifa = ?");
                $stmt->bind_param("ii", $nuevo_estado, $id_tarifa);
                
                if ($stmt->execute()) {
                    $mensaje = $nuevo_estado ? "Tarifa activada" : "Tarifa desactivada";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al cambiar estado de la tarifa";
                    $tipo_mensaje = "error";
                }
                break;
        }
    }
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener tipos de habitación con precios efectivos
$tipos_habitacion = $conn->query("
    SELECT th.*, COUNT(h.id_habitacion) as habitaciones_totales
    FROM tipos_habitacion th
    LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
    WHERE th.activo = 1
    GROUP BY th.id_tipo
    ORDER BY th.nombre
");

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

// Obtener tarifas
$tarifas = $conn->query("SELECT 
    t.id_tarifa,
    t.precio,
    t.temporada,
    t.descuento,
    t.fecha_inicio,
    t.fecha_fin,
    t.activa,
    th.id_tipo,
    th.nombre AS tipo_habitacion,
    th.precio_noche,
    COUNT(h.id_habitacion) as habitaciones_disponibles,
    CASE 
        WHEN t.activa = 1 AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN t.activa = 1 AND CURDATE() < t.fecha_inicio THEN 'futura'
        WHEN t.activa = 0 THEN 'desactivada'
        ELSE 'vencida'
    END as estado_tarifa,
    (t.precio - (t.precio * t.descuento / 100)) as precio_final
    FROM tarifas t
    INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
    LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
    GROUP BY t.id_tarifa
    ORDER BY t.fecha_inicio DESC, th.nombre");

// Estadísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total_tarifas,
    COUNT(CASE WHEN t.activa = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin THEN 1 END) as tarifas_activas,
    COUNT(CASE WHEN t.activa = 1 AND CURDATE() < fecha_inicio THEN 1 END) as tarifas_futuras,
    AVG(CASE WHEN t.activa = 1 THEN precio END) as precio_promedio
    FROM tarifas t")->fetch_assoc();
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Gestión de Tarifas - Hotel Rivo</title>
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
        .pricing-alert {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
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
            color: #007bff;
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
        .section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .section-header {
            background: #007bff;
            color: white;
            padding: 20px;
        }
        .section-header.precios { 
            background: #28a745; 
        }
        .tipo-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
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
            margin-bottom: 8px;
        }
        .precio-efectivo {
            color: #28a745;
            font-weight: bold;
            font-size: 1.2em;
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
        .tarifa-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 15px;
            border-left: 4px solid #007bff;
        }
        .tarifa-card.activa {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .tarifa-card.vencida {
            border-left-color: #dc3545;
            background: #fff8f8;
            opacity: 0.8;
        }
        .tarifa-card.futura {
            border-left-color: #ffc107;
            background: #fffdf0;
        }
        .tarifa-card.desactivada {
            border-left-color: #6c757d;
            background: #f8f9fa;
            opacity: 0.7;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-activa { background: #d4edda; color: #155724; }
        .estado-vencida { background: #f8d7da; color: #721c24; }
        .estado-futura { background: #d1ecf1; color: #0c5460; }
        .estado-desactivada { background: #e2e3e5; color: #383d41; }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2ed573;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn-edit { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-view { background: #28a745; color: white; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 24px; }
        .btn-success { background: #28a745; color: white; padding: 12px 24px; }
        .btn-warning { background: #ffc107; color: #212529; padding: 12px 24px; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .mensaje {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .no-editable {
            color: #6c757d;
            font-size: 0.8em;
            font-style: italic;
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
        .sidebar {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>💰 Gestión de Tarifas y Precios Dinámicos</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">🏨 Ver habitaciones</a>
                </div>
                <div>
                    <a href="admin_tarifa_crear.php" class="btn btn-success">➕ Nueva Tarifa</a>
                </div>
            </div>
        </div>

        <!-- Alerta del sistema de precios dinámicos -->
        <div class="pricing-alert">
            <h3 style="margin: 0 0 10px 0;">🎯 Sistema de Precios Dinámicos Activo</h3>
            <p style="margin: 0; opacity: 0.9;">
                Las tarifas especiales sobrescriben automáticamente los precios base cuando están vigentes.
            </p>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_tarifas'] ?></div>
                <div>Total Tarifas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['tarifas_activas'] ?></div>
                <div>Tarifas Activas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['tarifas_futuras'] ?></div>
                <div>Tarifas Futuras</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($stats['precio_promedio'] ?? 0, 0, ',', '.') ?></div>
                <div>Precio Promedio</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <!-- Sección de Precios Base -->
                <div class="section">
                    <div class="section-header precios">
                        <h3>💰 Precios Base de Tipos de Habitación</h3>
                    </div>
                    
                    <?php foreach ($tipos_con_precios as $tipo): ?>
                        <div class="tipo-row">
                            <div class="tipo-info">
                                <div class="tipo-nombre"><?= htmlspecialchars($tipo['nombre']) ?></div>
                                
                                <div style="margin: 8px 0;">
                                    <?php if ($tipo['fuente_precio'] == 'tarifa'): ?>
                                        <!-- Precio con tarifa activa -->
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
                                        <span class="precio-efectivo">$<?= number_format($tipo['precio_efectivo_hoy'], 0, ',', '.') ?>/noche</span>
                                        <span style="color: #6c757d; font-size: 0.9em;">(Precio base)</span>
                                        
                                        <?php if ($tipo['puede_editar_precio_base']): ?>
                                            <a href="#" onclick="editarPrecioBase(<?= $tipo['id_tipo'] ?>, '<?= htmlspecialchars($tipo['nombre']) ?>', <?= $tipo['precio_noche'] ?>)" class="editar-precio-btn">
                                                ✏️ Editar precio base
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="font-size: 0.9em; color: #666;">
                                    <?= $tipo['habitaciones_totales'] ?> habitación(es) • Capacidad: <?= $tipo['capacidad'] ?> personas
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sección de Tarifas -->
                <div class="section">
                    <div class="section-header">
                        <h3>🎯 Tarifas Especiales</h3>
                    </div>
                    
                    <?php if ($tarifas->num_rows > 0): ?>
                        <?php while ($tarifa = $tarifas->fetch_assoc()): ?>
                            <div class="tarifa-card <?= $tarifa['estado_tarifa'] ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <div>
                                        <div style="font-size: 1.3em; font-weight: bold; color: #333;"><?= htmlspecialchars($tarifa['tipo_habitacion']) ?></div>
                                        <div style="font-size: 0.9em; color: #666;">
                                            <?= $tarifa['habitaciones_disponibles'] ?> habitación(es) disponible(s)
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="estado-badge estado-<?= $tarifa['estado_tarifa'] ?>">
                                            <?= ucfirst($tarifa['estado_tarifa']) ?>
                                        </span>
                                        <div style="margin-top: 5px;">
                                            <span style="background: #007bff; color: white; padding: 4px 10px; border-radius: 15px; font-size: 0.85em;">
                                                <?= htmlspecialchars($tarifa['temporada']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 15px 0;">
                                    <div style="text-align: center; padding: 10px; background: white; border-radius: 6px;">
                                        <div style="font-weight: bold; color: #666; margin-bottom: 5px;">Precio Base</div>
                                        <div style="font-size: 1.2em; color: <?= $tarifa['descuento'] > 0 ? '#666; text-decoration: line-through' : '#28a745; font-weight: bold' ?>;">
                                            $<?= number_format($tarifa['precio'], 0, ',', '.') ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($tarifa['descuento'] > 0): ?>
                                        <div style="text-align: center; padding: 10px; background: white; border-radius: 6px;">
                                            <div style="font-weight: bold; color: #666; margin-bottom: 5px;">Precio Final</div>
                                            <div style="font-size: 1.5em; font-weight: bold; color: #28a745;">
                                                $<?= number_format($tarifa['precio_final'], 0, ',', '.') ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin: 10px 0; font-size: 0.9em; color: #666;">
                                    <span><strong>Inicio:</strong> <?= date('d/m/Y', strtotime($tarifa['fecha_inicio'])) ?></span>
                                    <span><strong>Fin:</strong> <?= date('d/m/Y', strtotime($tarifa['fecha_fin'])) ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 0.9em; color: #666;">Activa:</span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_tarifa">
                                            <input type="hidden" name="id_tarifa" value="<?= $tarifa['id_tarifa'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $tarifa['activa'] ? 0 : 1 ?>">
                                            
                                            <label class="toggle-switch">
                                                <input type="checkbox" 
                                                       <?= $tarifa['activa'] ? 'checked' : '' ?>
                                                       onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                        </form>
                                    </div>
                                    
                                    <div style="display: flex; gap: 8px;">
                                        <a href="admin_tarifa_ver.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-view">👁️ Ver</a>
                                        <a href="admin_tarifa_editar.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-edit">✏️ Editar</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; color: #666;">
                            <h3>🎯 No hay tarifas especiales</h3>
                            <p><a href="admin_tarifa_crear.php" class="btn btn-success">➕ Crear primera tarifa</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <h4>💡 Consejos de Gestión</h4>
                <ul style="font-size: 0.9em; color: #666; padding-left: 20px;">
                    <li><strong>Precios dinámicos:</strong> Las tarifas sobrescriben automáticamente precios base</li>
                    <li><strong>Temporadas altas:</strong> Crear tarifas especiales para fechas de alta demanda</li>
                    <li><strong>Promociones:</strong> Usar descuentos para aumentar ocupación en temporadas bajas</li>
                </ul>
                
                <h4>🔗 Acciones Rápidas</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="admin_tarifa_crear.php" class="btn btn-success">
                        ➕ Nueva Tarifa
                    </a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">
                        🏨 Gestionar Habitaciones
                    </a>
                    <a href="admin_tipos_habitacion.php" class="btn btn-secondary">
                        🏷️ Tipos de Habitación
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar precio base -->
    <div id="modalPrecioBase" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 400px; max-width: 90%;">
            <h3 style="margin: 0 0 20px 0;">✏️ Editar Precio Base</h3>
            
            <form method="POST" id="formPrecioBase">
                <input type="hidden" name="action" value="actualizar_precio_base">
                <input type="hidden" name="id_tipo" id="modalIdTipo">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold;">Tipo de Habitación:</label>
                    <div id="modalTipoNombre" style="padding: 10px; background: #f8f9fa; border-radius: 6px;"></div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="modalNuevoPrecio" style="display: block; margin-bottom: 8px; font-weight: bold;">Nuevo Precio Base:</label>
                    <input type="number" id="modalNuevoPrecio" name="nuevo_precio" 
                           style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;"
                           min="1000" step="1000" required>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="cerrarModalPrecioBase()" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        💾 Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editarPrecioBase(idTipo, nombre, precioActual) {
            document.getElementById('modalIdTipo').value = idTipo;
            document.getElementById('modalTipoNombre').textContent = nombre;
            document.getElementById('modalNuevoPrecio').value = precioActual;
            document.getElementById('modalPrecioBase').style.display = 'block';
        }
        
        function cerrarModalPrecioBase() {
            document.getElementById('modalPrecioBase').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalPrecioBase').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalPrecioBase();
            }
        });
        
        // Validación del formulario de precio base
        document.getElementById('formPrecioBase').addEventListener('submit', function(e) {
            const nuevoPrecio = document.getElementById('modalNuevoPrecio').value;
            const nombre = document.getElementById('modalTipoNombre').textContent;
            
            if (!confirm('¿Confirmar cambio de precio base para ' + nombre + ' a $' + parseInt(nuevoPrecio).toLocaleString() + '?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>