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

$mensaje = '';
$tipo_mensaje = '';
$tarifa_creada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = intval($_POST['id_tipo']);
    $precio = floatval($_POST['precio']);
    $temporada = trim($_POST['temporada']);
    $descuento = floatval($_POST['descuento']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $descripcion = trim($_POST['descripcion']);
    
    // Validaciones
    $errores = [];
    
    if ($id_tipo <= 0) {
        $errores[] = "Debe seleccionar un tipo de habitación";
    }
    
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
    } elseif (strtotime($fecha_inicio) < strtotime('today')) {
        $errores[] = "La fecha de inicio no puede ser anterior a hoy";
    }
    
    // Verificar solapamiento de fechas para el mismo tipo de habitación
    if (empty($errores)) {
        $stmt_overlap = $conn->prepare("SELECT id_tarifa FROM tarifas 
                                       WHERE id_tipo = ? AND (
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio <= ? AND fecha_fin >= ?) OR
                                           (fecha_inicio >= ? AND fecha_fin <= ?)
                                       )");
        $stmt_overlap->bind_param("issssss", $id_tipo, $fecha_inicio, $fecha_inicio, $fecha_fin, $fecha_fin, $fecha_inicio, $fecha_fin);
        $stmt_overlap->execute();
        
        if ($stmt_overlap->get_result()->num_rows > 0) {
            $errores[] = "Ya existe una tarifa para este tipo de habitación que se solapa con las fechas seleccionadas";
        }
    }
    
    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("INSERT INTO tarifas (id_tipo, precio, temporada, descuento, fecha_inicio, fecha_fin, descripcion, fecha_creacion) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("idsdsss", $id_tipo, $precio, $temporada, $descuento, $fecha_inicio, $fecha_fin, $descripcion);
            
            if ($stmt->execute()) {
                $nueva_id = $conn->insert_id;
                $tarifa_creada = true;
                $mensaje = "Tarifa creada exitosamente con ID: {$nueva_id}";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear la tarifa";
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

// Obtener tipos de habitación disponibles
$tipos_habitacion = $conn->query("SELECT th.id_tipo, th.nombre, th.descripcion, th.capacidad,
                                  COUNT(h.id_habitacion) as habitaciones_disponibles
                                  FROM tipos_habitacion th
                                  LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
                                  GROUP BY th.id_tipo
                                  ORDER BY th.nombre");

// Obtener temporadas existentes para sugerencias
$temporadas_existentes = $conn->query("SELECT DISTINCT temporada FROM tarifas ORDER BY temporada");

// Obtener estadísticas de precios por tipo para referencia
$precios_referencia = $conn->query("SELECT 
    th.nombre,
    AVG(t.precio) as precio_promedio,
    MIN(t.precio) as precio_minimo,
    MAX(t.precio) as precio_maximo
    FROM tarifas t
    INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
    GROUP BY th.id_tipo
    ORDER BY precio_promedio DESC");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Crear Tarifa - Hotel Rivo</title>
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
        .tipo-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .tipo-option {
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .tipo-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .tipo-option.selected {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .tipo-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }
        .tipo-nombre {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .tipo-detalles {
            font-size: 0.9em;
            color: #666;
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
        .precios-referencia {
            margin-bottom: 20px;
        }
        .precio-ref-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .precio-ref-item:last-child {
            border-bottom: none;
        }
        .preview-tarifa {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
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
            .tipo-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>➕ Crear Nueva Tarifa</h1>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                <div>
                    <a href="admin_tarifas.php" class="btn btn-secondary">← Volver a tarifas</a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($tarifa_creada): ?>
            <div class="success-card">
                <h3>✅ Tarifa Creada Exitosamente</h3>
                <p>La nueva tarifa ha sido agregada al sistema correctamente.</p>
                <div style="margin-top: 20px;">
                    <a href="admin_tarifas.php" class="btn btn-success">💰 Ver todas las tarifas</a>
                    <a href="admin_tarifa_crear.php" class="btn btn-primary">➕ Crear otra tarifa</a>
                    <a href="admin_tarifa_editar.php?id=<?= $nueva_id ?>" class="btn btn-secondary">✏️ Editar tarifa creada</a>
                </div>
            </div>
        <?php else: ?>

            <div class="content-grid">
                <!-- Formulario de creación -->
                <div class="form-card">
                    <h3>📝 Configuración de la Tarifa</h3>
                    
                    <form method="POST" action="" id="form-crear-tarifa">
                        <!-- Selección de tipo de habitación -->
                        <div class="form-group required">
                            <label>🏨 Tipo de Habitación</label>
                            <div class="tipo-selector">
                                <?php while ($tipo = $tipos_habitacion->fetch_assoc()): ?>
                                    <div class="tipo-option" data-tipo="<?= $tipo['id_tipo'] ?>">
                                        <input type="radio" name="id_tipo" value="<?= $tipo['id_tipo'] ?>" 
                                               <?= ($_POST['id_tipo'] ?? '') == $tipo['id_tipo'] ? 'checked' : '' ?> required>
                                        <div class="tipo-nombre"><?= htmlspecialchars($tipo['nombre']) ?></div>
                                        <div class="tipo-detalles">
                                            Capacidad: <?= $tipo['capacidad'] ?> personas<br>
                                            <?= $tipo['habitaciones_disponibles'] ?> habitación(es) disponible(s)
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <!-- Información básica -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="precio">💰 Precio Base (por noche)</label>
                                <input type="number" id="precio" name="precio" 
                                       value="<?= htmlspecialchars($_POST['precio'] ?? '') ?>" 
                                       required min="1" step="0.01" 
                                       placeholder="150000">
                            </div>
                            
                            <div class="form-group required">
                                <label for="temporada">🌤️ Temporada</label>
                                <input type="text" id="temporada" name="temporada" 
                                       value="<?= htmlspecialchars($_POST['temporada'] ?? '') ?>" 
                                       required maxlength="50" 
                                       placeholder="Alta, Baja, Media, Navidad...">
                                
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
                                   value="<?= htmlspecialchars($_POST['descuento'] ?? '0') ?>" 
                                   min="0" max="100" step="1">
                            <div class="descuento-display">
                                <span id="descuento-valor">0</span>% de descuento
                            </div>
                            
                            <div class="precio-preview" id="precio-preview" style="display: none;">
                                <div class="precio-calculado">
                                    <span>Precio original:</span>
                                    <span class="precio-original" id="precio-original">$0</span>
                                </div>
                                <div class="precio-calculado">
                                    <span>Precio final:</span>
                                    <span class="precio-final" id="precio-final">$0</span>
                                </div>
                                <div class="precio-calculado">
                                    <span>Ahorro:</span>
                                    <span class="ahorro" id="ahorro">$0</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fechas -->
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="fecha_inicio">📅 Fecha de Inicio</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>" 
                                       required min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="form-group required">
                                <label for="fecha_fin">📅 Fecha de Fin</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" 
                                       value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="duracion-display" id="duracion-display" style="display: none;">
                            Duración: <span id="duracion-dias">0</span> día(s)
                        </div>
                        
                        <div class="validacion-fechas" id="validacion-fechas" style="display: none;">
                            <!-- Aquí se mostrará validación de fechas -->
                        </div>
                        
                        <!-- Descripción -->
                        <div class="form-group">
                            <label for="descripcion">📝 Descripción (opcional)</label>
                            <textarea id="descripcion" name="descripcion" rows="3" 
                                      placeholder="Descripción adicional de la tarifa, condiciones especiales, etc."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="actions">
                            <div>
                                <a href="admin_tarifas.php" class="btn btn-secondary">❌ Cancelar</a>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-success" id="btn-crear">
                                    ➕ Crear Tarifa
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Panel de información -->
                <div class="info-card">
                    <h4>📊 Precios de Referencia</h4>
                    <div class="precios-referencia">
                        <?php if ($precios_referencia->num_rows > 0): ?>
                            <?php while ($ref = $precios_referencia->fetch_assoc()): ?>
                                <div class="precio-ref-item">
                                    <span><strong><?= htmlspecialchars($ref['nombre']) ?></strong></span>
                                    <span>$<?= number_format($ref['precio_promedio'], 0, ',', '.') ?></span>
                                </div>
                                <div style="font-size: 0.8em; color: #666; margin-bottom: 10px;">
                                    Rango: $<?= number_format($ref['precio_minimo'], 0, ',', '.') ?> - $<?= number_format($ref['precio_maximo'], 0, ',', '.') ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #666; font-size: 0.9em;">No hay precios de referencia disponibles</p>
                        <?php endif; ?>
                    </div>
                    
                    <h4>💡 Consejos para Tarifas</h4>
                    <ul style="font-size: 0.9em; color: #666; padding-left: 20px;">
                        <li><strong>Temporada Alta:</strong> Aumentar precios 20-50% en fechas especiales</li>
                        <li><strong>Temporada Baja:</strong> Ofrecer descuentos para aumentar ocupación</li>
                        <li><strong>Anticipación:</strong> Crear tarifas con al menos 30 días de antelación</li>
                        <li><strong>Flexibilidad:</strong> Permitir modificaciones según demanda</li>
                    </ul>
                    
                    <h4>📋 Vista Previa de Tarifa</h4>
                    <div class="preview-tarifa" id="preview-tarifa">
                        <div><strong>Tipo:</strong> <span id="preview-tipo">No seleccionado</span></div>
                        <div><strong>Temporada:</strong> <span id="preview-temporada">-</span></div>
                        <div><strong>Precio:</strong> <span id="preview-precio">$0</span></div>
                        <div><strong>Descuento:</strong> <span id="preview-descuento">0%</span></div>
                        <div><strong>Precio Final:</strong> <span id="preview-precio-final">$0</span></div>
                        <div><strong>Período:</strong> <span id="preview-fechas">No definido</span></div>
                    </div>
                    
                    <h4>⚠️ Validaciones</h4>
                    <div id="validaciones-panel">
                        <div id="val-tipo" class="validacion-item">🔘 Seleccionar tipo de habitación</div>
                        <div id="val-precio" class="validacion-item">🔘 Ingresar precio válido</div>
                        <div id="val-temporada" class="validacion-item">🔘 Definir temporada</div>
                        <div id="val-fechas" class="validacion-item">🔘 Configurar fechas</div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Variables globales
        let precioBase = 0;
        let descuentoActual = 0;
        
        // Selección de tipo de habitación
        document.querySelectorAll('.tipo-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.tipo-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                actualizarPreview();
                validarTipo();
            });
        });
        
        // Selección de temporada
        function seleccionarTemporada(temporada) {
            document.getElementById('temporada').value = temporada;
            actualizarPreview();
            validarTemporada();
        }
        
        // Cálculo de precios en tiempo real
        document.getElementById('precio').addEventListener('input', function() {
            precioBase = parseFloat(this.value) || 0;
            calcularPrecio();
            actualizarPreview();
            validarPrecio();
        });
        
        document.getElementById('descuento').addEventListener('input', function() {
            descuentoActual = parseFloat(this.value) || 0;
            document.getElementById('descuento-valor').textContent = descuentoActual;
            calcularPrecio();
            actualizarPreview();
        });
        
        function calcularPrecio() {
            const descuentoMonto = precioBase * (descuentoActual / 100);
            const precioFinal = precioBase - descuentoMonto;
            
            if (precioBase > 0) {
                document.getElementById('precio-preview').style.display = 'block';
                document.getElementById('precio-original').textContent = '$' + formatearNumero(precioBase);
                document.getElementById('precio-final').textContent = '$' + formatearNumero(precioFinal);
                document.getElementById('ahorro').textContent = '$' + formatearNumero(descuentoMonto);
                
                if (descuentoActual > 0) {
                    document.getElementById('precio-original').style.textDecoration = 'line-through';
                } else {
                    document.getElementById('precio-original').style.textDecoration = 'none';
                }
            } else {
                document.getElementById('precio-preview').style.display = 'none';
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
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                if (inicio >= fin) {
                    validacionDiv.className = 'validacion-fechas validacion-error';
                    validacionDiv.textContent = '❌ La fecha de fin debe ser posterior a la fecha de inicio';
                    validacionDiv.style.display = 'none';
                duracionDiv.style.display = 'none';
                document.getElementById('val-fechas').innerHTML = '🔘 Configurar fechas';
            }
        }
        
        // Actualizar vista previa
        function actualizarPreview() {
            const tipoSeleccionado = document.querySelector('.tipo-option.selected');
            const temporada = document.getElementById('temporada').value;
            const precio = document.getElementById('precio').value;
            const descuento = document.getElementById('descuento').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            // Actualizar tipo
            if (tipoSeleccionado) {
                document.getElementById('preview-tipo').textContent = tipoSeleccionado.querySelector('.tipo-nombre').textContent;
            } else {
                document.getElementById('preview-tipo').textContent = 'No seleccionado';
            }
            
            // Actualizar temporada
            document.getElementById('preview-temporada').textContent = temporada || '-';
            
            // Actualizar precio
            if (precio > 0) {
                document.getElementById('preview-precio').textContent = 'block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fechas inválidas';
                } else if (inicio < hoy) {
                    validacionDiv.className = 'validacion-fechas validacion-error';
                    validacionDiv.textContent = '❌ La fecha de inicio no puede ser anterior a hoy';
                    validacionDiv.style.display = 'block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fecha de inicio inválida';
                } else {
                    const diffTime = Math.abs(fin - inicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    validacionDiv.className = 'validacion-fechas validacion-ok';
                    validacionDiv.textContent = '✅ Fechas válidas';
                    validacionDiv.style.display = 'block';
                    
                    duracionDiv.style.display = 'block';
                    document.getElementById('duracion-dias').textContent = diffDays;
                    document.getElementById('val-fechas').innerHTML = '✅ Fechas configuradas (' + diffDays + ' días)';
                }
                
                actualizarPreview();
            } else {
                validacionDiv.style.display = ' + formatearNumero(precio);
                const precioFinal = precio - (precio * (descuento / 100));
                document.getElementById('preview-precio-final').textContent = 'block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fechas inválidas';
                } else if (inicio < hoy) {
                    validacionDiv.className = 'validacion-fechas validacion-error';
                    validacionDiv.textContent = '❌ La fecha de inicio no puede ser anterior a hoy';
                    validacionDiv.style.display = 'block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fecha de inicio inválida';
                } else {
                    const diffTime = Math.abs(fin - inicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    validacionDiv.className = 'validacion-fechas validacion-ok';
                    validacionDiv.textContent = '✅ Fechas válidas';
                    validacionDiv.style.display = 'block';
                    
                    duracionDiv.style.display = 'block';
                    document.getElementById('duracion-dias').textContent = diffDays;
                    document.getElementById('val-fechas').innerHTML = '✅ Fechas configuradas (' + diffDays + ' días)';
                }
                
                actualizarPreview();
            } else {
                validacionDiv.style.display = ' + formatearNumero(precioFinal);
            } else {
                document.getElementById('preview-precio').textContent = '$0';
                document.getElementById('preview-precio-final').textContent = '$0';
            }
            
            // Actualizar descuento
            document.getElementById('preview-descuento').textContent = descuento + '%';
            
            // Actualizar fechas
            if (fechaInicio && fechaFin) {
                const inicio = new Date(fechaInicio).toLocaleDateString('es-ES');
                const fin = new Date(fechaFin).toLocaleDateString('es-ES');
                document.getElementById('preview-fechas').textContent = inicio + ' - ' + fin;
            } else {
                document.getElementById('preview-fechas').textContent = 'No definido';
            }
        }
        
        // Funciones de validación
        function validarTipo() {
            const tipoSeleccionado = document.querySelector('.tipo-option.selected');
            if (tipoSeleccionado) {
                document.getElementById('val-tipo').innerHTML = '✅ Tipo seleccionado: ' + tipoSeleccionado.querySelector('.tipo-nombre').textContent;
            } else {
                document.getElementById('val-tipo').innerHTML = '🔘 Seleccionar tipo de habitación';
            }
        }
        
        function validarPrecio() {
            const precio = parseFloat(document.getElementById('precio').value);
            if (precio > 0) {
                document.getElementById('val-precio').innerHTML = '✅ Precio válido: block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fechas inválidas';
                } else if (inicio < hoy) {
                    validacionDiv.className = 'validacion-fechas validacion-error';
                    validacionDiv.textContent = '❌ La fecha de inicio no puede ser anterior a hoy';
                    validacionDiv.style.display = 'block';
                    duracionDiv.style.display = 'none';
                    document.getElementById('val-fechas').innerHTML = '❌ Fecha de inicio inválida';
                } else {
                    const diffTime = Math.abs(fin - inicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    validacionDiv.className = 'validacion-fechas validacion-ok';
                    validacionDiv.textContent = '✅ Fechas válidas';
                    validacionDiv.style.display = 'block';
                    
                    duracionDiv.style.display = 'block';
                    document.getElementById('duracion-dias').textContent = diffDays;
                    document.getElementById('val-fechas').innerHTML = '✅ Fechas configuradas (' + diffDays + ' días)';
                }
                
                actualizarPreview();
            } else {
                validacionDiv.style.display = ' + formatearNumero(precio);
            } else {
                document.getElementById('val-precio').innerHTML = '🔘 Ingresar precio válido';
            }
        }
        
        function validarTemporada() {
            const temporada = document.getElementById('temporada').value.trim();
            if (temporada.length > 0) {
                document.getElementById('val-temporada').innerHTML = '✅ Temporada: ' + temporada;
            } else {
                document.getElementById('val-temporada').innerHTML = '🔘 Definir temporada';
            }
        }
        
        // Formatear números
        function formatearNumero(numero) {
            return new Intl.NumberFormat('es-CO').format(Math.round(numero));
        }
        
        // Validación del formulario
        document.getElementById('form-crear-tarifa').addEventListener('submit', function(e) {
            const tipoSeleccionado = document.querySelector('input[name="id_tipo"]:checked');
            const precio = parseFloat(document.getElementById('precio').value);
            const temporada = document.getElementById('temporada').value.trim();
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            let errores = [];
            
            if (!tipoSeleccionado) {
                errores.push('Debe seleccionar un tipo de habitación');
            }
            
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
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                if (inicio >= fin) {
                    errores.push('La fecha de fin debe ser posterior a la fecha de inicio');
                }
                
                if (inicio < hoy) {
                    errores.push('La fecha de inicio no puede ser anterior a hoy');
                }
            }
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Por favor corrija los siguientes errores:\n\n• ' + errores.join('\n• '));
                return false;
            }
        });
        
        // Event listeners adicionales
        document.getElementById('temporada').addEventListener('input', function() {
            actualizarPreview();
            validarTemporada();
        });
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar fecha mínima
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_inicio').min = hoy;
            
            // Actualizar fecha fin cuando cambie fecha inicio
            document.getElementById('fecha_inicio').addEventListener('change', function() {
                document.getElementById('fecha_fin').min = this.value;
            });
            
            // Inicializar valores si hay datos POST (en caso de error)
            const precioInput = document.getElementById('precio');
            const descuentoInput = document.getElementById('descuento');
            
            if (precioInput.value) {
                precioBase = parseFloat(precioInput.value);
                validarPrecio();
            }
            
            if (descuentoInput.value) {
                descuentoActual = parseFloat(descuentoInput.value);
                document.getElementById('descuento-valor').textContent = descuentoActual;
            }
            
            // Marcar tipo seleccionado si hay datos POST
            const tipoSeleccionado = document.querySelector('input[name="id_tipo"]:checked');
            if (tipoSeleccionado) {
                tipoSeleccionado.closest('.tipo-option').classList.add('selected');
                validarTipo();
            }
            
            // Validar temporada si hay valor
            if (document.getElementById('temporada').value) {
                validarTemporada();
            }
            
            // Calcular precio inicial
            calcularPrecio();
            actualizarPreview();
            validarFechas();
        });
        
        // Sugerencias de precios basadas en tipo seleccionado
        document.querySelectorAll('input[name="id_tipo"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Aquí podrías agregar lógica para sugerir precios basados en el tipo
                // Por ejemplo, obtener el precio promedio de ese tipo via AJAX
            });
        });
        
        // Funciones de ayuda
        function limpiarFormulario() {
            if (confirm('¿Está seguro de limpiar todos los campos del formulario?')) {
                document.getElementById('form-crear-tarifa').reset();
                document.querySelectorAll('.tipo-option').forEach(opt => opt.classList.remove('selected'));
                document.getElementById('precio-preview').style.display = 'none';
                document.getElementById('duracion-display').style.display = 'none';
                document.getElementById('validacion-fechas').style.display = 'none';
                actualizarPreview();
                
                // Resetear validaciones
                document.getElementById('val-tipo').innerHTML = '🔘 Seleccionar tipo de habitación';
                document.getElementById('val-precio').innerHTML = '🔘 Ingresar precio válido';
                document.getElementById('val-temporada').innerHTML = '🔘 Definir temporada';
                document.getElementById('val-fechas').innerHTML = '🔘 Configurar fechas';
            }
        }
        
        // Agregar botón de limpiar
        document.addEventListener('DOMContentLoaded', function() {
            const acciones = document.querySelector('.actions > div:first-child');
            const btnLimpiar = document.createElement('button');
            btnLimpiar.type = 'button';
            btnLimpiar.className = 'btn btn-secondary';
            btnLimpiar.innerHTML = '🔄 Limpiar';
            btnLimpiar.onclick = limpiarFormulario;
            btnLimpiar.style.marginLeft = '10px';
            acciones.appendChild(btnLimpiar);
        });
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('btn-crear').click();
            }
            
            // Escape para limpiar
            if (e.key === 'Escape') {
                limpiarFormulario();
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
    </script>
</body>
</html>