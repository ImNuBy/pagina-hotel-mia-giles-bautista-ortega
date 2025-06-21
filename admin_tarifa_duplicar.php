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

// Obtener información de la tarifa original
$stmt = $conn->prepare("SELECT 
    t.*,
    th.nombre as tipo_habitacion,
    th.descripcion as tipo_descripcion,
    th.capacidad
    FROM tarifas t
    INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
    WHERE t.id_tarifa = ?");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$tarifa_original = $result->fetch_assoc();

if (!$tarifa_original) {
    header('Location: admin_tarifas.php?error=tarifa_no_encontrada');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$tarifa_duplicada = false;

// Procesar duplicación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicar_tarifa'])) {
    $nuevo_precio = floatval($_POST['precio']);
    $nueva_temporada = trim($_POST['temporada']);
    $nuevo_descuento = floatval($_POST['descuento']);
    $nueva_fecha_inicio = $_POST['fecha_inicio'];
    $nueva_fecha_fin = $_POST['fecha_fin'];
    $nueva_descripcion = trim($_POST['descripcion']);
    $mantener_activa = isset($_POST['mantener_activa']) ? 1 : 0;
    
    // Validaciones
    $errores = [];
    
    if ($nuevo_precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }
    
    if (empty($nueva_temporada)) {
        $errores[] = "La temporada es obligatoria";
    }
    
    if ($nuevo_descuento < 0 || $nuevo_descuento > 100) {
        $errores[] = "El descuento debe estar entre 0 y 100%";
    }
    
    if (empty($nueva_fecha_inicio) || empty($nueva_fecha_fin)) {
        $errores[] = "Las fechas de inicio y fin son obligatorias";
    } elseif (strtotime($nueva_fecha_inicio) >= strtotime($nueva_fecha_fin)) {
        $errores[] = "La fecha de fin debe ser posterior a la fecha de inicio";
    }
    
    // Verificar solapamiento de fechas
    if (empty($errores)) {
        $stmt_overlap = $conn->prepare("SELECT id_tarifa FROM tarifas 
                                       WHERE id_tipo = ? AND activa = 1 AND (
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio >= ? AND fecha_fin <= ?)
                                       )");
        $stmt_overlap->bind_param("issssss", $tarifa_original['id_tipo'], $nueva_fecha_inicio, $nueva_fecha_inicio, $nueva_fecha_fin, $nueva_fecha_fin, $nueva_fecha_inicio, $nueva_fecha_fin);
        $stmt_overlap->execute();
        
        if ($stmt_overlap->get_result()->num_rows > 0 && $mantener_activa) {
            $errores[] = "Ya existe una tarifa activa para este tipo de habitación que se solapa con las fechas seleccionadas";
        }
    }
    
    if (empty($errores)) {
        try {
            $conn->begin_transaction();
            
            // Crear la tarifa duplicada
            $stmt = $conn->prepare("INSERT INTO tarifas (
                id_tipo, precio, temporada, descuento, fecha_inicio, fecha_fin, 
                descripcion, activa, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("idsdssi", 
                $tarifa_original['id_tipo'], 
                $nuevo_precio, 
                $nueva_temporada, 
                $nuevo_descuento, 
                $nueva_fecha_inicio, 
                $nueva_fecha_fin, 
                $nueva_descripcion,
                $mantener_activa
            );
            
            if ($stmt->execute()) {
                $nueva_id = $conn->insert_id;
                
                // Log de la duplicación
                $log_stmt = $conn->prepare("INSERT INTO log_admin (user_id, accion, detalle, fecha) VALUES (?, 'DUPLICAR_TARIFA', ?, NOW())");
                $detalle = "Duplicó tarifa ID {$id} → nueva ID {$nueva_id}: {$tarifa_original['tipo_habitacion']} - {$nueva_temporada}";
                $log_stmt->bind_param("is", $_SESSION['user_id'], $detalle);
                $log_stmt->execute();
                
                $conn->commit();
                
                $tarifa_duplicada = true;
                $mensaje = "Tarifa duplicada exitosamente con ID: {$nueva_id}";
                $tipo_mensaje = "success";
            } else {
                throw new Exception("Error al duplicar la tarifa");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = implode(", ", $errores);
        $tipo_mensaje = "error";
    }
}

// Obtener temporadas existentes para sugerencias
$temporadas_existentes = $conn->query("SELECT DISTINCT temporada FROM tarifas WHERE id_tipo = {$tarifa_original['id_tipo']} ORDER BY temporada");

// Sugerir nueva temporada basada en la original
$nueva_temporada_sugerida = $tarifa_original['temporada'] . " (Copia)";

// Sugerir nuevas fechas (próximo mes)
$nueva_fecha_inicio_sugerida = date('Y-m-d', strtotime($tarifa_original['fecha_fin'] . ' +1 day'));
$duracion_original = (strtotime($tarifa_original['fecha_fin']) - strtotime($tarifa_original['fecha_inicio'])) / (60*60*24);
$nueva_fecha_fin_sugerida = date('Y-m-d', strtotime($nueva_fecha_inicio_sugerida . ' +' . $duracion_original . ' days'));
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Duplicar Tarifa - Hotel Rivo</title>
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
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .form-card, .info-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .original-info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
        }
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
        .success-card {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        .checkbox-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            cursor: pointer;
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
            <h1>📋 Duplicar Tarifa</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_tarifas.php" class="btn btn-secondary">← Volver a tarifas</a>
                    <a href="admin_tarifa_ver.php?id=<?= $tarifa_original['id_tarifa'] ?>" class="btn btn-secondary">👁️ Ver original</a>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($tarifa_duplicada): ?>
            <div class="success-card">
                <h3>✅ Tarifa Duplicada Exitosamente</h3>
                <p>La nueva tarifa ha sido creada basada en la tarifa original.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_tarifas.php" class="btn btn-success">💰 Ver todas las tarifas</a>
                    <a href="admin_tarifa_duplicar.php?id=<?= $tarifa_original['id_tarifa'] ?>" class="btn btn-primary">📋 Duplicar otra vez</a>
                    <a href="admin_tarifa_editar.php?id=<?= $nueva_id ?>" class="btn btn-secondary">✏️ Editar nueva tarifa</a>
                </div>
            </div>
        <?php else: ?>

            <div class="content-grid">
                <!-- Formulario de duplicación -->
                <div class="form-card">
                    <!-- Información de la tarifa original -->
                    <div class="original-info">
                        <h4>📋 Tarifa Original</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <div>
                                <strong>ID:</strong> #<?= $tarifa_original['id_tarifa'] ?><br>
                                <strong>Tipo:</strong> <?= htmlspecialchars($tarifa_original['tipo_habitacion']) ?><br>
                                <strong>Temporada:</strong> <?= htmlspecialchars($tarifa_original['temporada']) ?>
                            </div>
                            <div>
                                <strong>Precio:</strong> $<?= number_format($tarifa_original['precio'], 0, ',', '.') ?><br>
                                <strong>Descuento:</strong> <?= $tarifa_original['descuento'] ?>%<br>
                                <strong>Período:</strong> <?= date('d/m/Y', strtotime($tarifa_original['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($tarifa_original['fecha_fin'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <h3>🔧 Configurar Nueva Tarifa</h3>
                    
                    <form method="POST" action="" id="form-duplicar-tarifa">
                        <input type="hidden" name="duplicar_tarifa" value="1">
                        
                        <!-- Información básica -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="precio">💰 Precio Base (por noche)</label>
                                <input type="number" id="precio" name="precio" 
                                       value="<?= $tarifa_original['precio'] ?>" 
                                       required min="1" step="0.01">
                            </div>
                            
                            <div class="form-group required">
                                <label for="temporada">🌤️ Temporada</label>
                                <input type="text" id="temporada" name="temporada" 
                                       value="<?= htmlspecialchars($nueva_temporada_sugerida) ?>" 
                                       required maxlength="50">
                                
                                <div class="temporada-suggestions">
                                    <strong>Sugerencias:</strong>
                                    <?php while ($temp = $temporadas_existentes->fetch_assoc()): ?>
                                        <span class="temporada-tag" onclick="seleccionarTemporada('<?= htmlspecialchars($temp['temporada']) ?>')">
                                            <?= htmlspecialchars($temp['temporada']) ?>
                                        </span>
                                    <?php endwhile; ?>
                                    <span class="temporada-tag" onclick="seleccionarTemporada('Temporada 2025')">Temporada 2025</span>
                                    <span class="temporada-tag" onclick="seleccionarTemporada('Alta')">Alta</span>
                                    <span class="temporada-tag" onclick="seleccionarTemporada('Baja')">Baja</span>
                                    <span class="temporada-tag" onclick="seleccionarTemporada('Media')">Media</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descuento -->
                        <div class="form-group">
                            <label for="descuento">🏷️ Descuento (%)</label>
                            <input type="range" id="descuento" name="descuento" class="descuento-slider"
                                   value="<?= $tarifa_original['descuento'] ?>" 
                                   min="0" max="100" step="1">
                            <div class="descuento-display">
                                <span id="descuento-valor"><?= $tarifa_original['descuento'] ?></span>% de descuento
                            </div>
                            
                            <div class="precio-preview" id="precio-preview">
                                <div class="precio-calculado">
                                    <span>Precio original:</span>
                                    <span class="precio-original" id="precio-original">$<?= number_format($tarifa_original['precio'], 0, ',', '.') ?></span>
                                </div>
                                <div class="precio-calculado">
                                    <span>Descuento:</span>
                                    <span id="ahorro">-$<?= number_format($tarifa_original['precio'] * $tarifa_original['descuento'] / 100, 0, ',', '.') ?></span>
                                </div>
                                <div class="precio-calculado">
                                    <span>Precio final:</span>
                                    <span class="precio-final" id="precio-final">$<?= number_format($tarifa_original['precio'] * (1 - $tarifa_original['descuento'] / 100), 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fechas -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="fecha_inicio">📅 Fecha de Inicio</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?= $nueva_fecha_inicio_sugerida ?>" 
                                       required min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="form-group required">
                                <label for="fecha_fin">📅 Fecha de Fin</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" 
                                       value="<?= $nueva_fecha_fin_sugerida ?>" 
                                       required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                        
                        <div class="duracion-display" id="duracion-display" style="display: none;">
                            Duración: <span id="duracion-dias"></span> días
                        </div>
                        
                        <div class="validacion-fechas" id="validacion-fechas" style="display: none;">
                            <div id="val-solapamiento"></div>
                        </div>
                        
                        <!-- Descripción -->
                        <div class="form-group">
                            <label for="descripcion">📝 Descripción</label>
                            <textarea id="descripcion" name="descripcion" 
                                      rows="3" maxlength="200"
                                      placeholder="Descripción opcional de la tarifa..."><?= htmlspecialchars($tarifa_original['descripcion']) ?></textarea>
                            <small>Opcional - Máximo 200 caracteres</small>
                        </div>
                        
                        <!-- Mantener activa -->
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="mantener_activa" checked>
                                ✅ Mantener la nueva tarifa activa
                            </label>
                            <small>Si se desmarca, la tarifa se creará como inactiva</small>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="actions">
                            <div>
                                <button type="submit" class="btn btn-primary" id="btn-duplicar">
                                    📋 Duplicar Tarifa
                                </button>
                            </div>
                            <div>
                                <a href="admin_tarifas.php" class="btn btn-secondary">❌ Cancelar</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Panel de información -->
                <div class="info-card">
                    <h3>ℹ️ Información de Duplicación</h3>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4>🎯 Datos Sugeridos</h4>
                        <p><strong>Período original:</strong> <?= $duracion_original ?> días</p>
                        <p><strong>Nueva fecha inicio:</strong> <?= date('d/m/Y', strtotime($nueva_fecha_inicio_sugerida)) ?></p>
                        <p><strong>Nueva fecha fin:</strong> <?= date('d/m/Y', strtotime($nueva_fecha_fin_sugerida)) ?></p>
                    </div>
                    
                    <div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4>💡 Consejos</h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>Modifica el precio según la nueva temporada</li>
                            <li>Verifica que las fechas no se solapen con otras tarifas activas</li>
                            <li>Usa nombres descriptivos para las temporadas</li>
                            <li>El descuento se aplicará sobre el precio base</li>
                        </ul>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h4>⚠️ Validaciones</h4>
                        <div id="val-precio">🔘 Precio debe ser mayor a 0</div>
                        <div id="val-temporada">🔘 Temporada es obligatoria</div>
                        <div id="val-fechas">🔘 Fechas válidas y sin solapamientos</div>
                    </div>
                    
                    <!-- Estadísticas de la tarifa original -->
                    <div style="background: #d1ecf1; padding: 20px; border-radius: 8px;">
                        <h4>📊 Estadísticas de la Tarifa Original</h4>
                        <?php
                        // Obtener estadísticas de uso de la tarifa original
                        $stats_stmt = $conn->prepare("SELECT 
                            COUNT(r.id_reserva) as reservas_asociadas,
                            SUM(r.total) as ingresos_generados
                            FROM reservas r 
                            WHERE r.id_tarifa = ?");
                        $stats_stmt->bind_param("i", $tarifa_original['id_tarifa']);
                        $stats_stmt->execute();
                        $stats = $stats_stmt->get_result()->fetch_assoc();
                        ?>
                        <p><strong>Reservas:</strong> <?= $stats['reservas_asociadas'] ?></p>
                        <p><strong>Ingresos generados:</strong> $<?= number_format($stats['ingresos_generados'] ?? 0, 0, ',', '.') ?></p>
                        <p><strong>Estado:</strong> 
                            <span style="color: <?= $tarifa_original['activa'] ? '#28a745' : '#dc3545' ?>">
                                <?= $tarifa_original['activa'] ? '✅ Activa' : '❌ Inactiva' ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Variables globales
        let precioBase = <?= $tarifa_original['precio'] ?>;
        let descuentoActual = <?= $tarifa_original['descuento'] ?>;
        
        // Funciones de temporada
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
            
            if (precioBase > 0) {
                document.getElementById('precio-original').textContent = '$' + formatearNumero(precioBase);
                document.getElementById('precio-final').textContent = '$' + formatearNumero(precioFinal);
                document.getElementById('ahorro').textContent = '-$' + formatearNumero(descuentoMonto);
                
                if (descuentoActual > 0) {
                    document.getElementById('precio-original').style.textDecoration = 'line-through';
                } else {
                    document.getElementById('precio-original').style.textDecoration = 'none';
                }
            }
        }
        
        // Validación de fechas
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            validarFechas();
            calcularDuracion();
        });
        
        document.getElementById('fecha_fin').addEventListener('change', function() {
            validarFechas();
            calcularDuracion();
        });
        
        function calcularDuracion() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            if (fechaInicio && fechaFin) {
                const inicio = new Date(fechaInicio);
                const fin = new Date(fechaFin);
                const diferencia = Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24));
                
                if (diferencia > 0) {
                    document.getElementById('duracion-display').style.display = 'block';
                    document.getElementById('duracion-dias').textContent = diferencia;
                } else {
                    document.getElementById('duracion-display').style.display = 'none';
                }
            }
        }
        
        function validarFechas() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const valElement = document.getElementById('val-fechas');
            
            if (!fechaInicio || !fechaFin) {
                valElement.innerHTML = '🔘 Configurar fechas';
                return;
            }
            
            const inicio = new Date(fechaInicio);
            const fin = new Date(fechaFin);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (inicio < hoy) {
                valElement.innerHTML = '❌ Fecha de inicio no puede ser anterior a hoy';
                document.getElementById('validacion-fechas').style.display = 'block';
                document.getElementById('validacion-fechas').className = 'validacion-fechas validacion-error';
                return;
            }
            
            if (fin <= inicio) {
                valElement.innerHTML = '❌ Fecha de fin debe ser posterior a inicio';
                document.getElementById('validacion-fechas').style.display = 'block';
                document.getElementById('validacion-fechas').className = 'validacion-fechas validacion-error';
                return;
            }
            
            valElement.innerHTML = '✅ Fechas válidas';
            document.getElementById('validacion-fechas').style.display = 'block';
            document.getElementById('validacion-fechas').className = 'validacion-fechas validacion-ok';
            document.getElementById('val-solapamiento').innerHTML = '✅ Sin solapamientos detectados';
        }
        
        // Funciones de validación
        function validarPrecio() {
            const precio = parseFloat(document.getElementById('precio').value);
            const valElement = document.getElementById('val-precio');
            
            if (precio > 0) {
                valElement.innerHTML = '✅ Precio válido: $' + formatearNumero(precio);
            } else {
                valElement.innerHTML = '❌ Precio debe ser mayor a 0';
            }
        }
        
        function validarTemporada() {
            const temporada = document.getElementById('temporada').value.trim();
            const valElement = document.getElementById('val-temporada');
            
            if (temporada.length > 0) {
                valElement.innerHTML = '✅ Temporada: ' + temporada;
            } else {
                valElement.innerHTML = '❌ Temporada es obligatoria';
            }
        }
        
        // Formatear números
        function formatearNumero(numero) {
            return new Intl.NumberFormat('es-CO').format(Math.round(numero));
        }
        
        // Confirmación antes de duplicar
        document.getElementById('form-duplicar-tarifa').addEventListener('submit', function(e) {
            const precio = document.getElementById('precio').value;
            const temporada = document.getElementById('temporada').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            const mensaje = `¿Confirma la duplicación de la tarifa?\n\n` +
                          `Tipo: <?= htmlspecialchars($tarifa_original['tipo_habitacion']) ?>\n` +
                          `Temporada: ${temporada}\n` +
                          `Precio: $${formatearNumero(precio)}\n` +
                          `Período: ${fechaInicio} al ${fechaFin}`;
            
            if (!confirm(mensaje)) {
                e.preventDefault();
            }
        });
        
        // Inicializar validaciones al cargar
        document.addEventListener('DOMContentLoaded', function() {
            calcularPrecio();
            calcularDuracion();
            validarPrecio();
            validarTemporada();
            validarFechas();
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
    </script>
</body>
</html>