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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     
    $numero = $_POST['numero'];     
    $id_tipo = $_POST['id_tipo'];     
    $estado = $_POST['estado'];      
    
    // Verificar que el número de habitación no exista
    $check_stmt = $conn->prepare("SELECT id_habitacion FROM habitaciones WHERE numero = ?");
    $check_stmt->bind_param("i", $numero);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $mensaje = "Error: Ya existe una habitación con el número $numero";
        $tipo_mensaje = 'error';
    } else {
        // Insertar nueva habitación SIN precio_noche
        $stmt = $conn->prepare("INSERT INTO habitaciones (numero, id_tipo, estado) VALUES (?, ?, ?)");     
        $stmt->bind_param("iis", $numero, $id_tipo, $estado);      
        
        if ($stmt->execute()) {
            // Obtener el nombre del tipo para el mensaje
            $tipo_query = $conn->prepare("SELECT nombre, precio_noche FROM tipos_habitacion WHERE id_tipo = ?");
            $tipo_query->bind_param("i", $id_tipo);
            $tipo_query->execute();
            $tipo_result = $tipo_query->get_result();
            $tipo_info = $tipo_result->fetch_assoc();
            
            $mensaje = "Habitación #$numero agregada exitosamente. Tipo: " . $tipo_info['nombre'] . " - Precio: $" . number_format($tipo_info['precio_noche'], 0, ',', '.') . "/noche";
            $tipo_mensaje = 'success';
            
            $tipo_query->close();
        } else {         
            $mensaje = "Error al agregar la habitación: " . $stmt->error;
            $tipo_mensaje = 'error';
        }      
        
        $stmt->close();
    }
    
    $check_stmt->close();
}  

// Obtener tipos de habitación activos con información completa
$tipos_habitacion = $conn->query("SELECT 
    th.id_tipo, 
    th.nombre, 
    th.precio_noche, 
    th.capacidad, 
    th.descripcion,
    th.activo,
    COUNT(h.id_habitacion) as total_habitaciones 
    FROM tipos_habitacion th 
    LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo 
    WHERE th.activo = TRUE
    GROUP BY th.id_tipo, th.nombre, th.precio_noche, th.capacidad, th.descripcion, th.activo
    ORDER BY th.precio_noche ASC");

// Obtener habitaciones existentes
$habitaciones_existentes = $conn->query("SELECT 
    h.id_habitacion,
    h.numero, 
    h.estado,
    th.nombre as tipo_nombre, 
    th.precio_noche 
    FROM habitaciones h 
    JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo 
    ORDER BY h.numero ASC");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">     
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Habitación - Hotel Rivo</title>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group small {
            color: #666;
            font-size: 14px;
        }
        .tipo-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .precio-display {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
        .tipo-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .tipo-detail {
            background: white;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .habitaciones-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        .habitacion-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .habitacion-item:last-child {
            border-bottom: none;
        }
        .habitacion-numero {
            font-weight: bold;
            font-size: 1.1em;
            color: #333;
        }
        .habitacion-tipo {
            color: #666;
            margin: 0 15px;
        }
        .habitacion-precio {
            color: #28a745;
            font-weight: bold;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-disponible { background: #d4edda; color: #155724; }
        .estado-ocupada { background: #f8d7da; color: #721c24; }
        .estado-mantenimiento { background: #fff3cd; color: #856404; }
    </style>
</head> 
<body>     
    <div class="container">
        <h1>🏨 Agregar Nueva Habitación</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="form-habitacion">             
            <div class="form-group">
                <label for="numero">Número de Habitación:</label>             
                <input type="number" name="numero" id="numero" required min="1" max="9999" placeholder="Ej: 101, 201, 301...">
                <small>Ingrese un número único para identificar la habitación</small>
            </div>
            
            <div class="form-group">
                <label for="id_tipo">Tipo de Habitación:</label>             
                <select name="id_tipo" id="id_tipo" required onchange="mostrarInfoTipo()">     
                    <option value="">-- Seleccione un tipo --</option>
                    <?php while ($tipo = $tipos_habitacion->fetch_assoc()): ?>                 
                        <option value="<?= $tipo['id_tipo'] ?>" 
                                data-precio="<?= $tipo['precio_noche'] ?>"
                                data-nombre="<?= htmlspecialchars($tipo['nombre']) ?>"
                                data-capacidad="<?= $tipo['capacidad'] ?>"
                                data-descripcion="<?= htmlspecialchars($tipo['descripcion'] ?? '') ?>"
                                data-total="<?= $tipo['total_habitaciones'] ?>">
                            <?= htmlspecialchars($tipo['nombre']) ?> - 
                            $<?= number_format($tipo['precio_noche'], 0, ',', '.') ?>/noche
                            (<?= $tipo['total_habitaciones'] ?> existentes)
                        </option>             
                    <?php endwhile; ?>             
                </select>
            </div>
            
            <div id="tipo-info" class="tipo-info" style="display: none;">
                <h4>📋 Información del Tipo Seleccionado:</h4>
                <div class="tipo-details">
                    <div class="tipo-detail">
                        <strong>Tipo:</strong><br>
                        <span id="info-nombre"></span>
                    </div>
                    <div class="tipo-detail">
                        <strong>Precio por noche:</strong><br>
                        <span id="info-precio" class="precio-display"></span>
                    </div>
                    <div class="tipo-detail">
                        <strong>Capacidad:</strong><br>
                        <span id="info-capacidad"></span> personas
                    </div>
                    <div class="tipo-detail">
                        <strong>Habitaciones existentes:</strong><br>
                        <span id="info-total"></span>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <strong>Descripción:</strong> <span id="info-descripcion"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="estado">Estado Inicial:</label>             
                <select name="estado" id="estado">                 
                    <option value="disponible">✅ Disponible</option>                 
                    <option value="ocupada">🔴 Ocupada</option>                 
                    <option value="mantenimiento">🔧 En Mantenimiento</option>             
                </select>
                <small>Puede cambiar el estado después desde el panel de administración</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-primary">➕ Agregar Habitación</button>
                <a href="admin_dashboard.php" class="btn-secondary">⬅️ Volver al Panel</a>
            </div>
        </form>      
        
        <!-- Estadísticas -->
        <?php
        $stats = $conn->query("SELECT 
            COUNT(*) as total_habitaciones,
            SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
            SUM(CASE WHEN estado = 'ocupada' THEN 1 ELSE 0 END) as ocupadas,
            SUM(CASE WHEN estado = 'mantenimiento' THEN 1 ELSE 0 END) as mantenimiento
            FROM habitaciones")->fetch_assoc();
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_habitaciones'] ?></div>
                <div>Total Habitaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['disponibles'] ?></div>
                <div>Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['ocupadas'] ?></div>
                <div>Ocupadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['mantenimiento'] ?></div>
                <div>En Mantenimiento</div>
            </div>
        </div>
        
        <!-- Lista de habitaciones -->
        <div class="habitaciones-list">
            <div style="padding: 20px; border-bottom: 2px solid #007bff;">
                <h3>🏠 Habitaciones Registradas</h3>
            </div>
            
            <?php if ($habitaciones_existentes->num_rows > 0): ?>
                <?php while ($hab = $habitaciones_existentes->fetch_assoc()): ?>
                    <div class="habitacion-item">
                        <div style="display: flex; align-items: center;">
                            <span class="habitacion-numero">#<?= $hab['numero'] ?></span>
                            <span class="habitacion-tipo"><?= htmlspecialchars($hab['tipo_nombre']) ?></span>
                            <span class="habitacion-precio">$<?= number_format($hab['precio_noche'], 0, ',', '.') ?>/noche</span>
                        </div>
                        <span class="estado-badge estado-<?= $hab['estado'] ?>">
                            <?= ucfirst($hab['estado']) ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <p>🏨 No hay habitaciones registradas aún.</p>
                    <p>¡Agrega la primera habitación usando el formulario de arriba!</p>
                </div>
            <?php endif; ?>
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
                document.getElementById('info-total').textContent = selectedOption.dataset.total;
                document.getElementById('info-descripcion').textContent = selectedOption.dataset.descripcion || 'Sin descripción';
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
        
        // Validación del formulario
        document.getElementById('form-habitacion').addEventListener('submit', function(e) {
            const numero = document.getElementById('numero').value;
            const tipo = document.getElementById('id_tipo').value;
            
            if (!numero || !tipo) {
                e.preventDefault();
                alert('⚠️ Por favor complete todos los campos requeridos.');
                return;
            }
            
            if (numero < 1 || numero > 9999) {
                e.preventDefault();
                alert('⚠️ El número de habitación debe estar entre 1 y 9999.');
                return;
            }
            
            // Confirmar antes de agregar
            const selectedOption = document.getElementById('id_tipo').options[document.getElementById('id_tipo').selectedIndex];
            const tipoNombre = selectedOption.dataset.nombre;
            const precio = parseInt(selectedOption.dataset.precio).toLocaleString();
            
            if (!confirm(`¿Confirma agregar la habitación #${numero}?\n\nTipo: ${tipoNombre}\nPrecio: $${precio}/noche`)) {
                e.preventDefault();
            }
        });
        
        // Focus automático
        document.getElementById('numero').focus();
        
        // Limpiar formulario después de envío exitoso
        <?php if ($tipo_mensaje === 'success'): ?>
        document.getElementById('form-habitacion').reset();
        document.getElementById('tipo-info').style.display = 'none';
        document.getElementById('numero').focus();
        <?php endif; ?>
    </script>
</body> 
</html>