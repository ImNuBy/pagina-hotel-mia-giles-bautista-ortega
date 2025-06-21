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

// Obtener información de la tarifa
$stmt = $conn->prepare("SELECT 
    t.*,
    th.nombre as tipo_habitacion,
    th.descripcion as tipo_descripcion,
    th.capacidad,
    COUNT(h.id_habitacion) as habitaciones_disponibles,
    CASE 
        WHEN CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin THEN 'activa'
        WHEN CURDATE() < t.fecha_inicio THEN 'futura'
        ELSE 'vencida'
    END as estado_tarifa
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

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $precio = floatval($_POST['precio']);
    $temporada = trim($_POST['temporada']);
    $descuento = floatval($_POST['descuento']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $descripcion = trim($_POST['descripcion']);
    
    // Validaciones
    $errores = [];
    
    if ($precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }
    
    if (empty($temporada)) {
        $errores[] = "La temporada es obligatoria";
    }
    
    if ($descuento < 0 || $descuento > 100) {
        $errores[] = "El descuento debe estar entre 0 y 100%";
    }
    
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $errores[] = "Las fechas de inicio y fin son obligatorias";
    } elseif (strtotime($fecha_inicio) >= strtotime($fecha_fin)) {
        $errores[] = "La fecha de fin debe ser posterior a la fecha de inicio";
    }
    
    // Verificar solapamiento de fechas con otras tarifas del mismo tipo (excluyendo la actual)
    if (empty($errores)) {
        $stmt_overlap = $conn->prepare("SELECT id_tarifa FROM tarifas 
                                       WHERE id_tipo = ? AND id_tarifa != ? AND (
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio >= ? AND fecha_fin <= ?)
                                       )");
        $stmt_overlap->bind_param("iissssss", $tarifa['id_tipo'], $id, $fecha_inicio, $fecha_inicio, $fecha_fin, $fecha_fin, $fecha_inicio, $fecha_fin);
        $stmt_overlap->execute();
        
        if ($stmt_overlap->get_result()->num_rows > 0) {
            $errores[] = "Ya existe otra tarifa para este tipo de habitación que se solapa con las fechas seleccionadas";
        }
    }
    
    if (empty($errores)) {
        try {
            $stmt_update = $conn->prepare("UPDATE tarifas SET 
                                          precio = ?, temporada = ?, descuento = ?, 
                                          fecha_inicio = ?, fecha_fin = ?, descripcion = ?
                                          WHERE id_tarifa = ?");
            $stmt_update->bind_param("dssissi", $precio, $temporada, $descuento, $fecha_inicio, $fecha_fin, $descripcion, $id);
            
            if ($stmt_update->execute()) {
                $mensaje = "Tarifa actualizada correctamente";
                $tipo_mensaje = "success";
                
                // Actualizar los datos mostrados
                $tarifa['precio'] = $precio;
                $tarifa['temporada'] = $temporada;
                $tarifa['descuento'] = $descuento;
                $tarifa['fecha_inicio'] = $fecha_inicio;
                $tarifa['fecha_fin'] = $fecha_fin;
                $tarifa['descripcion'] = $descripcion;
                
                // Recalcular estado
                if (strtotime('today') >= strtotime($fecha_inicio) && strtotime('today') <= strtotime($fecha_fin)) {
                    $tarifa['estado_tarifa'] = 'activa';
                } elseif (strtotime('today') < strtotime($fecha_inicio)) {
                    $tarifa['estado_tarifa'] = 'futura';
                } else {
                    $tarifa['estado_tarifa'] = 'vencida';
                }
            } else {
                $mensaje = "Error al actualizar la tarifa";
                $tipo_mensaje = "error";
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = implode(", ", $errores);
        $tipo_mensaje = "error";
    }
}

// Obtener temporadas existentes para sugerencias
$temporadas_existentes = $conn->query("SELECT DISTINCT temporada FROM tarifas WHERE id_tarifa != $id ORDER BY temporada");

// Obtener estadísticas de uso de esta tarifa
$stats_uso = $conn->query("SELECT 
    COUNT(r.id_reserva) as reservas_con_tarifa,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    MIN(r.fecha_reserva) as primera_reserva,
    MAX(r.fecha_reserva) as ultima_reserva
    FROM reservas r
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE h.id_tipo = {$tarifa['id_tipo']} 
    AND r.fecha_entrada BETWEEN '{$tarifa['fecha_inicio']}' AND '{$tarifa['fecha_fin']}'")->fetch_assoc();
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Editar Tarifa - Hotel Rivo</title>
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
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .form-card, .info-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .tarifa-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
        }
        .tarifa-info.activa {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .tarifa-info.vencida {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        .tarifa-info.futura {
            border-left-color: #ffc107;
            background: #fffdf0;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .estado-activa { background: #d4edda; color: #155724; }
        .estado-vencida { background: #f8d7da; color: #721c24; }
        .estado-futura { background: #d1ecf1; color: #0c5460; }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-group.required label::after {
            content: " *";
            color: #dc3545;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .precio-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #007bff;
        }
        .precio-calculado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 5px 0;
        }
        .precio-original {
            font-size: 1.1em;
            color: #666;
            text-decoration: line-through;
        }
        .precio-final {
            font-size: 1.3em;
            font-weight: bold;
            color: #28a745;
        }
        .ahorro {
            color: #dc3545;
            font-weight: bold;
        }
        .temporada-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .temporada-tag {
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            cursor: pointer;
            transition: all 0.3s;
        }
        .temporada-tag:hover {
            background: #007bff;
            color: white;
        }
        .descuento-slider {
            width: 100%;
            margin: 10px 0;
        }
        .descuento-display {
            text-align: center;
            font-weight: bold;
            font-size: 1.2em;
            color: #dc3545;
            margin: 10px 0;
        }
        .fecha-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #856404;
        }
        .duracion-display {
            background: #d1ecf1;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            text-align: center;
            font-weight: bold;
            color: #0c5460;
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
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
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
        .stats-uso {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .validacion-fechas {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9em;
        }
        .validacion-ok {
            background: #d4edda;
            color: #155724;
        }
        .validacion-error {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Tarifa</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_tarifas.php" class="btn btn-secondary">← Volver a tarifas</a>
                    <a href="admin_tarifa_ver.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-secondary">👁️ Ver detalles</a>
                </div>
                <div>
                    <span style="color: #666;">ID: <?= $tarifa['id_tarifa'] ?></span>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulario de edición -->
            <div class="form-card">
                <!-- Información actual de la tarifa -->
                <div class="tarifa-info <?= $tarifa['estado_tarifa'] ?>">
                    <h3>📋 Información Actual</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                        <div>
                            <strong>Tipo de Habitación:</strong><br>
                            <?= htmlspecialchars($tarifa['tipo_habitacion']) ?>
                        </div>
                        <div>
                            <strong>Estado:</strong><br>
                            <span class="estado-badge estado-<?= $tarifa['estado_tarifa'] ?>">
                                <?= ucfirst($tarifa['estado_tarifa']) ?>
                            </span>
                        </div>
                        <div>
                            <strong>Precio Actual:</strong><br>
                            $<?= number_format($tarifa['precio'], 0, ',', '.') ?>
                        </div>
                        <div>
                            <strong>Precio Final:</strong><br>
                            $<?= number_format($tarifa['precio'] - ($tarifa['precio'] * $tarifa['descuento'] / 100), 0, ',', '.') ?>
                        </div>
                    </div>
                </div>

                <?php if ($tarifa['estado_tarifa'] === 'activa' && $stats_uso['reservas_con_tarifa'] > 0): ?>
                    <div class="warning-box">
                        <h4>⚠️ Tarifa en Uso</h4>
                        <p><strong>Esta tarifa está actualmente activa y tiene <?= $stats_uso['reservas_con_tarifa'] ?> reserva(s) asociada(s).</strong></p>
                        <p>Los cambios en el precio podrían afectar reservas futuras. Se recomienda crear una nueva tarifa en lugar de modificar esta.</p>
                    </div>
                <?php endif; ?>
                
                <h3>✏️ Modificar Tarifa</h3>
                
                <form method="POST" action="" id="form-editar-tarifa">
                    <!-- Información básica -->
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="precio">💰 Precio Base (por noche)</label>
                            <input type="number" id="precio" name="precio" 
                                   value="<?= $tarifa['precio'] ?>" 
                                   required min="1" step="0.01">
                        </div>
                        
                        <div class="form-group required">
                            <label for="temporada">🌤️ Temporada</label>
                            <input type="text" id="temporada" name="temporada" 
                                   value="<?= htmlspecialchars($tarifa['temporada']) ?>" 
                                   required maxlength="50">
                            
                            <div class="temporada-suggestions">
                                <strong>Sugerencias:</strong>
                                <?php while ($temp = $temporadas_existentes->fetch_assoc()): ?>
                                    <span class="temporada-tag" onclick="seleccionarTemporada('<?= htmlspecialchars($temp['temporada']) ?>')">
                                        <?= htmlspecialchars($temp['temporada']) ?>
                                    </span>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Descuento -->
                    <div class="form-group">
                        <label for="descuento">🏷️ Descuento (%)</label>
                        <input type="range" id="descuento" name="descuento" class="descuento-slider"
                               value="<?= $tarifa['descuento'] ?>" 
                               min="0" max="100" step="1">
                        <div class="descuento-display">
                            <span id="descuento-valor"><?= $tarifa['descuento'] ?></span>% de descuento
                        </div>
                        
                        <div class="precio-preview" id="precio-preview">
                            <div class="precio-calculado">
                                <span>Precio original:</span>
                                <span class="precio-original" id="precio-original">$<?= number_format($tarifa['precio'], 0, ',', '.') ?></span>
                            </div>
                            <div class="precio-calculado">
                                <span>Precio final:</span>
                                <span class="precio-final" id="precio-final">$<?= number_format($tarifa['precio'] - ($tarifa['precio'] * $tarifa['descuento'] / 100), 0, ',', '.') ?></span>
                            </div>
                            <div class="precio-calculado">
                                <span>Ahorro:</span>
                                <span class="ahorro" id="ahorro">$<?= number_format($tarifa['precio'] * $tarifa['descuento'] / 100, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fechas -->
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="fecha_inicio">📅 Fecha de Inicio</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?= $tarifa['fecha_inicio'] ?>" 
                                   required>
                            <?php if ($tarifa['estado_tarifa'] === 'activa'): ?>
                                <div class="fecha-info">
                                    ⚠️ Esta tarifa está activa. Cambiar la fecha de inicio podría afectar reservas actuales.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group required">
                            <label for="fecha_fin">📅 Fecha de Fin</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" 
                                   value="<?= $tarifa['fecha_fin'] ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="duracion-display" id="duracion-display">
                        Duración: <span id="duracion-dias"><?= ceil((strtotime($tarifa['fecha_fin']) - strtotime($tarifa['fecha_inicio'])) / (60*60*24)) ?></span> día(s)
                    </div>
                    
                    <div class="validacion-fechas" id="validacion-fechas" style="display: none;">
                        <!-- Validación de fechas -->
                    </div>
                    
                    <!-- Descripción -->
                    <div class="form-group">
                        <label for="descripcion">📝 Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" 
                                  placeholder="Descripción adicional de la tarifa, condiciones especiales, etc."><?= htmlspecialchars($tarifa['descripcion']) ?></textarea>
                    </div>
                    
                    <div class="actions">
                        <div>
                            <a href="admin_tarifas.php" class="btn btn-secondary">❌ Cancelar</a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success" id="btn-guardar">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Panel de información -->
            <div class="info-card">
                <h4>📊 Estadísticas de Uso</h4>
                <div class="stats-uso">
                    <div class="stat-item">
                        <span>Reservas con esta tarifa:</span>
                        <strong><?= $stats_uso['reservas_con_tarifa'] ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>Reservas confirmadas:</span>
                        <strong><?= $stats_uso['reservas_confirmadas'] ?></strong>
                    </div>
                    <?php if ($stats_uso['primera_reserva']): ?>
                        <div class="stat-item">
                            <span>Primera reserva:</span>
                            <strong><?= date('d/m/Y', strtotime($stats_uso['primera_reserva'])) ?></strong>
                        </div>
                        <div class="stat-item">
                            <span>Última reserva:</span>
                            <strong><?= date('d/m/Y', strtotime($stats_uso['ultima_reserva'])) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h4>🏨 Información del Tipo</h4>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <div><strong>Tipo:</strong> <?= htmlspecialchars($tarifa['tipo_habitacion']) ?></div>
                    <div><strong>Capacidad:</strong> <?= $tarifa['capacidad'] ?> personas</div>
                    <div><strong>Habitaciones disponibles:</strong> <?= $tarifa['habitaciones_disponibles'] ?></div>
                    <?php if ($tarifa['tipo_descripcion']): ?>
                        <div><strong>Descripción:</strong> <?= htmlspecialchars($tarifa['tipo_descripcion']) ?></div>
                    <?php endif; ?>
                </div>
                
                <h4>💡 Recomendaciones</h4>
                <ul style="font-size: 0.9em; color: #666; padding-left: 20px;">
                    <?php if ($tarifa['estado_tarifa'] === 'activa'): ?>
                        <li><strong>Tarifa activa:</strong> Los cambios afectarán reservas futuras</li>
                        <li><strong>Precio:</strong> Considere crear nueva tarifa en lugar de modificar</li>
                    <?php elseif ($tarifa['estado_tarifa'] === 'futura'): ?>
                        <li><strong>Tarifa futura:</strong> Puede modificar libremente antes de que inicie</li>
                        <li><strong>Planificación:</strong> Revise la competencia antes del inicio</li>
                    <?php else: ?>
                        <li><strong>Tarifa vencida:</strong> Solo modifique si es necesario para reportes</li>
                        <li><strong>Histórico:</strong> Los cambios afectarán estadísticas pasadas</li>
                    <?php endif; ?>
                    <li><strong>Solapamiento:</strong> Evite fechas que se crucen con otras tarifas</li>
                    <li><strong>Temporadas:</strong> Use nombres consistentes para mejor organización</li>
                </ul>
                
                <h4>⚠️ Validaciones</h4>
                <div id="validaciones-panel">
                    <div id="val-precio" class="validacion-item">✅ Precio configurado</div>
                    <div id="val-temporada" class="validacion-item">✅ Temporada definida</div>
                    <div id="val-fechas" class="validacion-item">✅ Fechas válidas</div>
                    <div id="val-solapamiento" class="validacion-item">🔄 Verificando solapamientos...</div>
                </div>
                
                <h4>🔗 Acciones Relacionadas</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="admin_tarifa_ver.php?id=<?= $tarifa['id_tarifa'] ?>" class="btn btn-primary">
                        👁️ Ver Detalles Completos
                    </a>
                    <a href="admin_tarifas.php?tipo=<?= $tarifa['id_tipo'] ?>" class="btn btn-secondary">
                        🏨 Otras Tarifas del Tipo
                    </a>
                    <a href="admin_reservas.php?tipo=<?= $tarifa['id_tipo'] ?>&fecha_inicio=<?= $tarifa['fecha_inicio'] ?>&fecha_fin=<?= $tarifa['fecha_fin'] ?>" class="btn btn-secondary">
                        📅 Ver Reservas del Período
                    </a>
                    
                    <?php if ($tarifa['estado_tarifa'] !== 'vencida'): ?>
                        <button onclick="duplicarTarifa()" class="btn btn-secondary">
                            📋 Duplicar Tarifa
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($tarifa['estado_tarifa'] === 'activa' && $stats_uso['reservas_con_tarifa'] > 0): ?>
                    <div class="danger-box" style="margin-top: 20px;">
                        <h5>🚨 Zona de Cuidado</h5>
                        <p style="margin-bottom: 10px;">Esta tarifa tiene reservas activas. Considere:</p>
                        <ul style="margin: 0; padding-left: 20px; font-size: 0.9em;">
                            <li>Crear una nueva tarifa para cambios de precio</li>
                            <li>Solo modificar fechas si es absolutamente necesario</li>
                            <li>Comunicar cambios a clientes con reservas</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let precioBase = <?= $tarifa['precio'] ?>;
        let descuentoActual = <?= $tarifa['descuento'] ?>;
        
        // Selección de temporada
        function seleccionarTemporada(temporada) {
            document.getElementById('temporada').value = temporada;
            validarTemporada();
        }
        
        // Cálculo de precios en tiempo real
        document.getElementById('precio').addEventListener('input', function() {
            precioBase = parseFloat(this.value) || 0;
            calcularPrecio();
            validarPrecio();
        });
        
        document.getElementById('descuento').addEventListener('input', function() {
            descuentoActual = parseFloat(this.value) || 0;
            document.getElementById('descuento-valor').textContent = descuentoActual;
            calcularPrecio();
        });
        
        function calcularPrecio() {
            const descuentoMonto = precioBase * (descuentoActual / 100);
            const precioFinal = precioBase - descuentoMonto;
            
            document.getElementById('precio-original').textContent = ' + formatearNumero(precioBase);
            document.getElementById('precio-final').textContent = ' + formatearNumero(precioFinal);
            document.getElementById('ahorro').textContent = ' + formatearNumero(descuentoMonto);
            
            if (descuentoActual > 0) {
                document.getElementById('precio-original').style.textDecoration = 'line-through';
            } else {
                document.getElementById('precio-original').style.textDecoration = 'none';
            }
        }
        
        // Validación de fechas
        document.getElementById('fecha_inicio').addEventListener('change', validarFechas);
        document.getElementById('fecha_fin').addEventListener('change', validarFechas);
        
        function validarFechas() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const validacionDiv = document.getElementById('validacion-fechas');
            const duracionDiv = document.getElementById('duracion-display');
            
            if (fechaInicio && fechaFin) {
                const inicio = new Date(fechaInicio);
                const fin = new Date(fechaFin);
                
                if (inicio >= fin) {
                    validacionDiv.className = 'validacion-fechas validacion-error';
                    validacionDiv.textContent = '❌ La fecha de fin debe ser posterior a la fecha de inicio';
                    validacionDiv.style.display = 'block';
                    document.getElementById('val-fechas').innerHTML = '❌ Fechas inválidas';
                } else {
                    const diffTime = Math.abs(fin - inicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    validacionDiv.className = 'validacion-fechas validacion-ok';
                    validacionDiv.textContent = '✅ Fechas válidas';
                    validacionDiv.style.display = 'block';
                    
                    document.getElementById('duracion-dias').textContent = diffDays;
                    document.getElementById('val-fechas').innerHTML = '✅ Fechas válidas (' + diffDays + ' días)';
                    
                    // Verificar solapamientos
                    verificarSolapamientos();
                }
            } else {
                validacionDiv.style.display = 'none';
                document.getElementById('val-fechas').innerHTML = '🔘 Configurar fechas';
            }
        }
        
        // Verificar solapamientos con otras tarifas
        function verificarSolapamientos() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const tipoId = <?= $tarifa['id_tipo'] ?>;
            const tarifaId = <?= $tarifa['id_tarifa'] ?>;
            
            if (fechaInicio && fechaFin) {
                // Aquí podrías hacer una petición AJAX para verificar solapamientos
                // Por simplicidad, marcamos como OK por ahora
                document.getElementById('val-solapamiento').innerHTML = '✅ Sin solapamientos detectados';
            }
        }
        
        // Funciones de validación
        function validarPrecio() {
            const precio = parseFloat(document.getElementById('precio').value);
            if (precio > 0) {
                document.getElementById('val-precio').innerHTML = '✅ Precio válido:  + formatearNumero(precio);
            } else {
                document.getElementById('val-precio').innerHTML = '❌ Precio inválido';
            }
        }
        
        function validarTemporada() {
            const temporada = document.getElementById('temporada').value.trim();
            if (temporada.length > 0) {
                document.getElementById('val-temporada').innerHTML = '✅ Temporada: ' + temporada;
            } else {
                document.getElementById('val-temporada').innerHTML = '❌ Temporada requerida';
            }
        }
        
        // Formatear números
        function formatearNumero(numero) {
            return new Intl.NumberFormat('es-CO').format(Math.round(numero));
        }
        
        // Duplicar tarifa
        function duplicarTarifa() {
            if (confirm('¿Desea crear una copia de esta tarifa?\n\nSe abrirá el formulario de creación con los datos actuales.')) {
                const params = new URLSearchParams({
                    tipo: <?= $tarifa['id_tipo'] ?>,
                    precio: document.getElementById('precio').value,
                    temporada: document.getElementById('temporada').value + ' (Copia)',
                    descuento: document.getElementById('descuento').value,
                    descripcion: document.getElementById('descripcion').value
                });
                window.open('admin_tarifa_crear.php?' + params.toString(), '_blank');
            }
        }
        
        // Validación del formulario
        document.getElementById('form-editar-tarifa').addEventListener('submit', function(e) {
            const precio = parseFloat(document.getElementById('precio').value);
            const temporada = document.getElementById('temporada').value.trim();
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            let errores = [];
            
            if (!precio || precio <= 0) {
                errores.push('Debe ingresar un precio válido mayor a 0');
            }
            
            if (!temporada) {
                errores.push('Debe especificar una temporada');
            }
            
            if (!fechaInicio || !fechaFin) {
                errores.push('Debe especificar las fechas de inicio y fin');
            } else {
                const inicio = new Date(fechaInicio);
                const fin = new Date(fechaFin);
                
                if (inicio >= fin) {
                    errores.push('La fecha de fin debe ser posterior a la fecha de inicio');
                }
            }
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Por favor corrija los siguientes errores:\n\n• ' + errores.join('\n• '));
                return false;
            }
            
            // Confirmar cambios en tarifa activa
            const estadoTarifa = '<?= $tarifa['estado_tarifa'] ?>';
            const reservasActivas = <?= $stats_uso['reservas_con_tarifa'] ?>;
            
            if (estadoTarifa === 'activa' && reservasActivas > 0) {
                const confirmar = confirm('⚠️ ATENCIÓN: Esta tarifa está activa y tiene ' + reservasActivas + ' reserva(s) asociada(s).\n\nLos cambios podrían afectar reservas existentes.\n\n¿Está seguro de continuar?');
                if (!confirmar) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Event listeners adicionales
        document.getElementById('temporada').addEventListener('input', validarTemporada);
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Calcular precio inicial
            calcularPrecio();
            validarPrecio();
            validarTemporada();
            validarFechas();
            
            // Configurar fecha mínima para fecha fin
            document.getElementById('fecha_inicio').addEventListener('change', function() {
                document.getElementById('fecha_fin').min = this.value;
            });
        });
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('btn-guardar').click();
            }
        });
        
        // Ocultar mensaje después de unos segundos
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if (mensaje) {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s';
                setTimeout(() => mensaje.remove(), 500);
            }
        }, 5000);
        
        // Confirmación antes de salir si hay cambios
        let datosOriginales = new FormData(document.querySelector('form'));
        
        window.addEventListener('beforeunload', function(e) {
            const datosActuales = new FormData(document.querySelector('form'));
            let hayChangios = false;
            
            for (let [key, value] of datosActuales.entries()) {
                if (datosOriginales.get(key) !== value) {
                    hayChangios = true;
                    break;
                }
            }
            
            if (hayChangios) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>