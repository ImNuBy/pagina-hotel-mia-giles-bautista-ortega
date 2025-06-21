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

$id = intval($_GET['id']); 

// Obtener habitación con información del tipo
$habitacion_query = "SELECT 
    h.*,
    t.nombre as tipo_nombre,
    t.precio_noche,
    t.capacidad,
    t.descripcion
    FROM habitaciones h
    JOIN tipos_habitacion t ON h.id_tipo = t.id_tipo
    WHERE h.id_habitacion = ?";

$stmt = $conn->prepare($habitacion_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$habitacion = $result->fetch_assoc();

if (!$habitacion) {     
    echo "❌ Habitación no encontrada.";     
    exit; 
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     
    $numero = intval($_POST['numero']);     
    $id_tipo = intval($_POST['id_tipo']);     
    $estado = $conn->real_escape_string($_POST['estado']);      
    
    // Verificar que el número no esté siendo usado por otra habitación
    $check_stmt = $conn->prepare("SELECT id_habitacion FROM habitaciones WHERE numero = ? AND id_habitacion != ?");
    $check_stmt->bind_param("ii", $numero, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $mensaje = "❌ Error: Ya existe otra habitación con el número $numero";
        $tipo_mensaje = 'error';
    } else {
        // Actualizar habitación SIN precio_noche
        $update_stmt = $conn->prepare("UPDATE habitaciones SET numero = ?, id_tipo = ?, estado = ? WHERE id_habitacion = ?");     
        $update_stmt->bind_param("iisi", $numero, $id_tipo, $estado, $id);     
        
        if ($update_stmt->execute()) {
            $mensaje = "✅ Habitación actualizada exitosamente";
            $tipo_mensaje = 'success';
            
            // Actualizar los datos para mostrar
            $stmt->execute();
            $habitacion = $stmt->get_result()->fetch_assoc();
        } else {
            $mensaje = "❌ Error al actualizar la habitación: " . $conn->error;
            $tipo_mensaje = 'error';
        }
        
        $update_stmt->close();
    }
    
    $check_stmt->close();
} 

// Obtener todos los tipos de habitación activos para el selector
$tipos_habitacion = $conn->query("SELECT id_tipo, nombre, precio_noche, capacidad FROM tipos_habitacion WHERE activo = TRUE ORDER BY precio_noche ASC");
?>  

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Editar Habitación #<?= $habitacion['numero'] ?> - Hotel Rivo</title>
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
        .habitacion-actual {
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
            max-width: 300px;
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
        .tipo-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
            display: none;
        }
        .precio-display {
            font-size: 1.1em;
            font-weight: bold;
            color: #28a745;
        }
        .estado-actual {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-left: 10px;
        }
        .estado-disponible { background: #d4edda; color: #155724; }
        .estado-ocupada { background: #f8d7da; color: #721c24; }
        .estado-mantenimiento { background: #fff3cd; color: #856404; }
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
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .historial {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <h1>🛏️ Editar Habitación #<?= $habitacion['numero'] ?></h1>     
        
        <div style="margin-bottom: 20px;">
            <a href="admin_habitaciones.php" class="btn btn-secondary">← Volver a Lista</a>
            <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Panel Principal</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>
        
        <!-- Información actual de la habitación -->
        <div class="habitacion-actual">
            <h3>📋 Información Actual</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-valor">#<?= $habitacion['numero'] ?></div>
                    <div class="info-label">Número</div>
                </div>
                <div class="info-item">
                    <div class="info-valor"><?= htmlspecialchars($habitacion['tipo_nombre']) ?></div>
                    <div class="info-label">Tipo</div>
                </div>
                <div class="info-item">
                    <div class="info-valor precio-display">$<?= number_format($habitacion['precio_noche'], 0, ',', '.') ?></div>
                    <div class="info-label">Precio por noche</div>
                </div>
                <div class="info-item">
                    <div class="info-valor"><?= $habitacion['capacidad'] ?></div>
                    <div class="info-label">Capacidad</div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <strong>Estado actual:</strong>
                <span class="estado-actual estado-<?= $habitacion['estado'] ?>">
                    <?= ucfirst($habitacion['estado']) ?>
                </span>
            </div>
            <?php if ($habitacion['descripcion']): ?>
                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 6px;">
                    <strong>Descripción del tipo:</strong> <?= htmlspecialchars($habitacion['descripcion']) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Formulario de edición -->
        <form method="POST" action="" id="form-editar">
            <h3>✏️ Modificar Datos</h3>
            
            <div class="form-group">
                <label for="numero">Número de Habitación:</label>         
                <input type="number" name="numero" id="numero" 
                       value="<?= htmlspecialchars($habitacion['numero']) ?>" 
                       required min="1" max="9999">
                <small style="color: #666;">Debe ser único en el hotel</small>
            </div>
            
            <div class="form-group">
                <label for="id_tipo">Tipo de Habitación:</label>         
                <select name="id_tipo" id="id_tipo" required onchange="mostrarInfoTipo()">
                    <?php while ($tipo = $tipos_habitacion->fetch_assoc()): ?>
                        <option value="<?= $tipo['id_tipo'] ?>"
                                data-precio="<?= $tipo['precio_noche'] ?>"
                                data-nombre="<?= htmlspecialchars($tipo['nombre']) ?>"
                                data-capacidad="<?= $tipo['capacidad'] ?>"
                                <?= $tipo['id_tipo'] == $habitacion['id_tipo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipo['nombre']) ?> - 
                            $<?= number_format($tipo['precio_noche'], 0, ',', '.') ?>/noche
                            (<?= $tipo['capacidad'] ?> personas)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div id="tipo-info" class="tipo-info">
                <h4>💡 Información del Tipo Seleccionado:</h4>
                <p><strong>Tipo:</strong> <span id="info-nombre"></span></p>
                <p><strong>Precio por noche:</strong> <span id="info-precio" class="precio-display"></span></p>
                <p><strong>Capacidad:</strong> <span id="info-capacidad"></span> personas</p>
                <p><em>El precio se toma automáticamente del tipo de habitación</em></p>
            </div>
            
            <div class="form-group">
                <label for="estado">Estado:</label>         
                <select name="estado" id="estado" required>             
                    <option value="disponible" <?= $habitacion['estado'] == 'disponible' ? 'selected' : '' ?>>
                        ✅ Disponible
                    </option>             
                    <option value="ocupada" <?= $habitacion['estado'] == 'ocupada' ? 'selected' : '' ?>>
                        🔴 Ocupada
                    </option>             
                    <option value="mantenimiento" <?= $habitacion['estado'] == 'mantenimiento' ? 'selected' : '' ?>>
                        🔧 En Mantenimiento
                    </option>         
                </select>
                <small style="color: #666;">Cambie según la situación actual de la habitación</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" onclick="return confirmarCambios()">
                    💾 Guardar Cambios
                </button>
                <a href="admin_habitaciones.php" class="btn btn-secondary">
                    ❌ Cancelar
                </a>
            </div>
        </form>
        
        <!-- Información adicional -->
        <div class="historial">
            <h4>📊 Información del Sistema</h4>
            <p><strong>ID de la habitación:</strong> <?= $habitacion['id_habitacion'] ?></p>
            <p><strong>Tipo ID:</strong> <?= $habitacion['id_tipo'] ?></p>
            <p><em>Nota: El precio se obtiene automáticamente del tipo de habitación y se aplica a todas las habitaciones de este tipo.</em></p>
        </div>
    </div>

    <script>
        function mostrarInfoTipo() {
            const select = document.getElementById('id_tipo');
            const selectedOption = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('tipo-info');
            
            if (selectedOption.value) {
                document.getElementById('info-nombre').textContent = selectedOption.dataset.nombre;
                document.getElementById('info-precio').textContent = '$' + parseInt(selectedOption.dataset.precio).toLocaleString();
                document.getElementById('info-capacidad').textContent = selectedOption.dataset.capacidad;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
        
        function confirmarCambios() {
            const numero = document.getElementById('numero').value;
            const tipoSelect = document.getElementById('id_tipo');
            const estado = document.getElementById('estado').value;
            
            const tipoNombre = tipoSelect.options[tipoSelect.selectedIndex].dataset.nombre;
            const precio = parseInt(tipoSelect.options[tipoSelect.selectedIndex].dataset.precio).toLocaleString();
            
            const mensaje = `¿Confirma los siguientes cambios?\n\n` +
                          `Número: ${numero}\n` +
                          `Tipo: ${tipoNombre}\n` +
                          `Precio: $${precio}/noche\n` +
                          `Estado: ${estado.charAt(0).toUpperCase() + estado.slice(1)}`;
            
            return confirm(mensaje);
        }
        
        // Mostrar información del tipo actual al cargar
        document.addEventListener('DOMContentLoaded', function() {
            mostrarInfoTipo();
        });
        
        // Validación adicional
        document.getElementById('form-editar').addEventListener('submit', function(e) {
            const numero = document.getElementById('numero').value;
            
            if (numero < 1 || numero > 9999) {
                e.preventDefault();
                alert('⚠️ El número de habitación debe estar entre 1 y 9999.');
                return;
            }
        });
    </script>
</body> 
</html>