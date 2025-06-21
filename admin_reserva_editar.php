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

$id_reserva = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_reserva <= 0) {
    header('Location: admin_reservas.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener datos actuales de la reserva
$reserva_query = "SELECT 
    r.*,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    h.numero as numero_habitacion,
    th.nombre as tipo_habitacion,
    th.precio_noche
    FROM reservas r
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    WHERE r.id_reserva = $id_reserva";

$result = $conn->query($reserva_query);

if ($result->num_rows == 0) {
    header('Location: admin_reservas.php');
    exit;
}

$reserva = $result->fetch_assoc();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_entrada = $_POST['fecha_entrada'];
    $fecha_salida = $_POST['fecha_salida'];
    $id_habitacion = (int)$_POST['id_habitacion'];
    $estado = $_POST['estado'];
    
    // Validaciones
    $errores = [];
    
    if (strtotime($fecha_entrada) >= strtotime($fecha_salida)) {
        $errores[] = "La fecha de salida debe ser posterior a la fecha de entrada";
    }
    
    if (strtotime($fecha_entrada) < strtotime(date('Y-m-d')) && $estado == 'pendiente') {
        $errores[] = "No se puede poner una reserva pendiente con fecha de entrada pasada";
    }
    
    // Verificar disponibilidad de la habitación (si cambió)
    if ($id_habitacion != $reserva['id_habitacion']) {
        $disponibilidad = $conn->query("SELECT COUNT(*) as ocupada 
                                      FROM reservas 
                                      WHERE id_habitacion = $id_habitacion 
                                      AND id_reserva != $id_reserva
                                      AND estado IN ('confirmada', 'pendiente')
                                      AND ((fecha_entrada <= '$fecha_entrada' AND fecha_salida > '$fecha_entrada')
                                      OR (fecha_entrada < '$fecha_salida' AND fecha_salida >= '$fecha_salida')
                                      OR (fecha_entrada >= '$fecha_entrada' AND fecha_salida <= '$fecha_salida'))")->fetch_assoc();
        
        if ($disponibilidad['ocupada'] > 0) {
            $errores[] = "La habitación seleccionada no está disponible para las fechas indicadas";
        }
    }
    
    if (empty($errores)) {
        $update_query = "UPDATE reservas SET 
                        fecha_entrada = '$fecha_entrada',
                        fecha_salida = '$fecha_salida',
                        id_habitacion = $id_habitacion,
                        estado = '$estado'
                        WHERE id_reserva = $id_reserva";
        
        if ($conn->query($update_query)) {
            $mensaje = "✅ Reserva actualizada exitosamente";
            $tipo_mensaje = 'success';
            
            // Actualizar datos para mostrar
            $result = $conn->query($reserva_query);
            $reserva = $result->fetch_assoc();
        } else {
            $mensaje = "❌ Error al actualizar la reserva: " . $conn->error;
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = "❌ Errores encontrados:\n" . implode("\n", $errores);
        $tipo_mensaje = 'error';
    }
}

// Obtener habitaciones disponibles
$habitaciones = $conn->query("SELECT 
    h.id_habitacion, h.numero, 
    th.nombre as tipo_habitacion, th.precio_noche
    FROM habitaciones h
    JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    WHERE th.activo = 1
    ORDER BY h.numero ASC");

// Obtener usuarios para cambiar cliente (opcional)
$usuarios = $conn->query("SELECT id_usuario, nombre, email FROM usuarios WHERE rol = 'cliente' ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva #<?= $reserva['id_reserva'] ?> - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .mensaje {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: bold;
            white-space: pre-line;
        }
        .mensaje.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .reserva-actual {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-valor {
            font-size: 1.3em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .info-label {
            color: #666;
            font-size: 0.9em;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-group small {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .habitacion-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
            display: none;
        }
        .precio-calculo {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #2196f3;
        }
        .estado-actual {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-left: 10px;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-confirmada { background: #d4edda; color: #155724; }
        .estado-cancelada { background: #f8d7da; color: #721c24; }
        .estado-completada { background: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
    <div class="container">
        <div class="actions-header">
            <h1>✏️ Editar Reserva #<?= $reserva['id_reserva'] ?></h1>
            <div>
                <a href="admin_reserva_detalle.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-secondary">👁️ Ver Detalle</a>
                <a href="admin_reservas.php" class="btn btn-secondary">← Volver</a>
            </div>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <!-- Información actual de la reserva -->
        <div class="reserva-actual">
            <h3>📋 Información Actual de la Reserva</h3>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($reserva['cliente_nombre']) ?> (<?= htmlspecialchars($reserva['cliente_email']) ?>)</p>
            <p><strong>Estado:</strong> 
                <span class="estado-actual estado-<?= $reserva['estado'] ?>">
                    <?= ucfirst($reserva['estado']) ?>
                </span>
            </p>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-valor">Hab. #<?= $reserva['numero_habitacion'] ?></div>
                    <div class="info-label"><?= htmlspecialchars($reserva['tipo_habitacion']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-valor">$<?= number_format($reserva['precio_noche'], 0, ',', '.') ?></div>
                    <div class="info-label">Precio por noche</div>
                </div>
                <div class="info-item">
                    <div class="info-valor"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></div>
                    <div class="info-label">Check-in actual</div>
                </div>
                <div class="info-item">
                    <div class="info-valor"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></div>
                    <div class="info-label">Check-out actual</div>
                </div>
            </div>
        </div>
        
        <!-- Formulario de edición -->
        <form method="POST" id="form-editar">
            <h3>✏️ Modificar Datos de la Reserva</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="fecha_entrada">Nueva Fecha de Entrada:</label>
                    <input type="date" id="fecha_entrada" name="fecha_entrada" 
                           value="<?= $reserva['fecha_entrada'] ?>" 
                           required min="<?= date('Y-m-d') ?>" onchange="calcularCosto()">
                    <small>Fecha de check-in</small>
                </div>
                
                <div class="form-group">
                    <label for="fecha_salida">Nueva Fecha de Salida:</label>
                    <input type="date" id="fecha_salida" name="fecha_salida" 
                           value="<?= $reserva['fecha_salida'] ?>" 
                           required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" onchange="calcularCosto()">
                    <small>Fecha de check-out</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="id_habitacion">Habitación:</label>
                <select id="id_habitacion" name="id_habitacion" required onchange="mostrarInfoHabitacion()">
                    <?php while ($habitacion = $habitaciones->fetch_assoc()): ?>
                        <option value="<?= $habitacion['id_habitacion'] ?>"
                                data-numero="<?= $habitacion['numero'] ?>"
                                data-tipo="<?= htmlspecialchars($habitacion['tipo_habitacion']) ?>"
                                data-precio="<?= $habitacion['precio_noche'] ?>"
                                <?= $habitacion['id_habitacion'] == $reserva['id_habitacion'] ? 'selected' : '' ?>>
                            Habitación #<?= $habitacion['numero'] ?> - <?= htmlspecialchars($habitacion['tipo_habitacion']) ?> 
                            ($<?= number_format($habitacion['precio_noche'], 0, ',', '.') ?>/noche)
                        </option>
                    <?php endwhile; ?>
                </select>
                <small>Seleccione la habitación para la reserva</small>
            </div>
            
            <div id="habitacion-info" class="habitacion-info">
                <h4>💡 Información de la Habitación Seleccionada:</h4>
                <p><strong>Habitación:</strong> #<span id="info-numero"></span></p>
                <p><strong>Tipo:</strong> <span id="info-tipo"></span></p>
                <p><strong>Precio por noche:</strong> $<span id="info-precio"></span></p>
            </div>
            
            <div class="form-group">
                <label for="estado">Estado de la Reserva:</label>
                <select id="estado" name="estado" required>
                    <option value="pendiente" <?= $reserva['estado'] == 'pendiente' ? 'selected' : '' ?>>
                        🟡 Pendiente
                    </option>
                    <option value="confirmada" <?= $reserva['estado'] == 'confirmada' ? 'selected' : '' ?>>
                        🟢 Confirmada
                    </option>
                    <option value="cancelada" <?= $reserva['estado'] == 'cancelada' ? 'selected' : '' ?>>
                        🔴 Cancelada
                    </option>
                    <option value="completada" <?= $reserva['estado'] == 'completada' ? 'selected' : '' ?>>
                        ⚫ Completada
                    </option>
                </select>
                <small>Estado actual de la reserva</small>
            </div>
            
            <div id="calculo-precio" class="precio-calculo" style="display: none;">
                <h4>💰 Cálculo del Costo:</h4>
                <p><strong>Número de noches:</strong> <span id="num-noches">0</span></p>
                <p><strong>Precio por noche:</strong> $<span id="precio-noche">0</span></p>
                <p><strong>Total estimado:</strong> $<span id="total-estimado">0</span></p>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" onclick="return confirmarCambios()">
                    💾 Guardar Cambios
                </button>
                <a href="admin_reserva_detalle.php?id=<?= $reserva['id_reserva'] ?>" class="btn btn-secondary">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        function mostrarInfoHabitacion() {
            const select = document.getElementById('id_habitacion');
            const selectedOption = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('habitacion-info');
            
            if (selectedOption.value) {
                document.getElementById('info-numero').textContent = selectedOption.dataset.numero;
                document.getElementById('info-tipo').textContent = selectedOption.dataset.tipo;
                document.getElementById('info-precio').textContent = parseInt(selectedOption.dataset.precio).toLocaleString();
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
            
            calcularCosto();
        }
        
        function calcularCosto() {
            const fechaEntrada = document.getElementById('fecha_entrada').value;
            const fechaSalida = document.getElementById('fecha_salida').value;
            const habitacionSelect = document.getElementById('id_habitacion');
            const selectedOption = habitacionSelect.options[habitacionSelect.selectedIndex];
            
            if (fechaEntrada && fechaSalida && selectedOption.dataset.precio) {
                const entrada = new Date(fechaEntrada);
                const salida = new Date(fechaSalida);
                const diffTime = Math.abs(salida - entrada);
                const noches = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const precio = parseInt(selectedOption.dataset.precio);
                const total = noches * precio;
                
                if (noches > 0) {
                    document.getElementById('num-noches').textContent = noches;
                    document.getElementById('precio-noche').textContent = precio.toLocaleString();
                    document.getElementById('total-estimado').textContent = total.toLocaleString();
                    document.getElementById('calculo-precio').style.display = 'block';
                } else {
                    document.getElementById('calculo-precio').style.display = 'none';
                }
            } else {
                document.getElementById('calculo-precio').style.display = 'none';
            }
        }
        
        function confirmarCambios() {
            const fechaEntrada = document.getElementById('fecha_entrada').value;
            const fechaSalida = document.getElementById('fecha_salida').value;
            const habitacion = document.getElementById('id_habitacion').options[document.getElementById('id_habitacion').selectedIndex];
            const estado = document.getElementById('estado').value;
            
            const mensaje = `¿Confirma los cambios a la reserva?\n\n` +
                          `Check-in: ${fechaEntrada}\n` +
                          `Check-out: ${fechaSalida}\n` +
                          `Habitación: #${habitacion.dataset.numero} - ${habitacion.dataset.tipo}\n` +
                          `Estado: ${estado}\n\n` +
                          `¿Continuar?`;
            
            return confirm(mensaje);
        }
        
        // Validaciones en tiempo real
        document.getElementById('fecha_entrada').addEventListener('change', function() {
            const entrada = new Date(this.value);
            const salida = document.getElementById('fecha_salida');
            const minSalida = new Date(entrada);
            minSalida.setDate(minSalida.getDate() + 1);
            
            salida.min = minSalida.toISOString().split('T')[0];
            
            if (salida.value && new Date(salida.value) <= entrada) {
                salida.value = '';
                alert('La fecha de salida debe ser posterior a la fecha de entrada');
            }
        });
        
        // Mostrar información inicial
        document.addEventListener('DOMContentLoaded', function() {
            mostrarInfoHabitacion();
        });
    </script>
</body>
</html>