<?php 
session_start(); 
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {     
    header('Location: login.php');     
    exit; 
}  

$mensaje = '';
$tipo_mensaje = '';

if (isset($_GET['id'])) {     
    $id_tipo = (int)$_GET['id'];      
    
    // Conexión a la base de datos     
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');      
    
    if ($conn->connect_error) {         
        die("Conexión fallida: " . $conn->connect_error);     
    }      
    
    // Obtener los datos del tipo de habitación con información adicional
    $sql = "SELECT t.*, COUNT(h.id_habitacion) as habitaciones_asignadas,
                   SUM(CASE WHEN h.estado = 'ocupada' THEN 1 ELSE 0 END) as habitaciones_ocupadas
            FROM tipos_habitacion t
            LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo
            WHERE t.id_tipo = $id_tipo
            GROUP BY t.id_tipo";
    
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {         
        $tipo = $result->fetch_assoc();     
    } else {         
        die("❌ Tipo de habitación no encontrado.");     
    }      
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {         
        // Recibiendo y validando datos del formulario         
        $nombre = trim($_POST['nombre']);         
        $descripcion = trim($_POST['descripcion']);         
        $precio_noche = floatval($_POST['precio_noche']);
        $capacidad = intval($_POST['capacidad']);
        $metros_cuadrados = !empty($_POST['metros_cuadrados']) ? intval($_POST['metros_cuadrados']) : null;
        $amenidades = !empty($_POST['amenidades']) ? trim($_POST['amenidades']) : '';
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        $errores = [];
        
        if (empty($nombre)) {
            $errores[] = "El nombre es requerido";
        }
        
        if ($precio_noche <= 0) {
            $errores[] = "El precio debe ser mayor a 0";
        }
        
        if ($capacidad < 1 || $capacidad > 10) {
            $errores[] = "La capacidad debe estar entre 1 y 10 personas";
        }
        
        // Verificar que no exista otro tipo con el mismo nombre
        $nombre_escaped = $conn->real_escape_string($nombre);
        $check_sql = "SELECT id_tipo FROM tipos_habitacion WHERE nombre = '$nombre_escaped' AND id_tipo != $id_tipo";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $errores[] = "Ya existe otro tipo de habitación con ese nombre";
        }
        
        // Validar si se intenta desactivar un tipo con habitaciones ocupadas
        if (!$activo && $tipo['habitaciones_ocupadas'] > 0) {
            $errores[] = "No se puede desactivar: hay {$tipo['habitaciones_ocupadas']} habitaciones ocupadas de este tipo";
        }
        
        if (empty($errores)) {
            // Escapar datos para SQL
            $descripcion_escaped = $conn->real_escape_string($descripcion);
            $amenidades_escaped = $conn->real_escape_string($amenidades);
            
            // Actualizar el tipo de habitación
            $sql = "UPDATE tipos_habitacion SET 
                        nombre = '$nombre_escaped', 
                        descripcion = '$descripcion_escaped', 
                        precio_noche = $precio_noche,
                        capacidad = $capacidad,
                        metros_cuadrados = " . ($metros_cuadrados !== null ? $metros_cuadrados : "NULL") . ",
                        amenidades = '$amenidades_escaped',
                        activo = $activo
                    WHERE id_tipo = $id_tipo";                  
            
            if ($conn->query($sql) === TRUE) {             
                $mensaje = "✅ Tipo de habitación '$nombre' actualizado exitosamente.";
                $tipo_mensaje = 'success';
                
                // Actualizar los datos para mostrar
                $result = $conn->query($sql);
                $tipo = $conn->query("SELECT t.*, COUNT(h.id_habitacion) as habitaciones_asignadas,
                                             SUM(CASE WHEN h.estado = 'ocupada' THEN 1 ELSE 0 END) as habitaciones_ocupadas
                                      FROM tipos_habitacion t
                                      LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo
                                      WHERE t.id_tipo = $id_tipo
                                      GROUP BY t.id_tipo")->fetch_assoc();
            } else {             
                $mensaje = "❌ Error al actualizar el tipo de habitación: " . $conn->error;
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = "❌ Errores encontrados:\n" . implode("\n", $errores);
            $tipo_mensaje = 'error';
        }
    } 
} else {     
    die("❌ ID de tipo de habitación no proporcionado."); 
} ?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Editar <?= htmlspecialchars($tipo['nombre']) ?> - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
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
        .info-actual {
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
            margin-bottom: 5px;
        }
        .info-valor.precio { color: #28a745; }
        .info-valor.capacidad { color: #007bff; }
        .info-valor.habitaciones { color: #6f42c1; }
        .info-valor.ocupadas { color: #dc3545; }
        .info-label {
            color: #666;
            font-size: 0.9em;
        }
        .estado-actual {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin: 10px 0;
        }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-inactivo { background: #f8d7da; color: #721c24; }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
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
        .precio-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .precio-display {
            font-size: 1.3em;
            font-weight: bold;
            color: #28a745;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.5);
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
        .amenidades-help {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .amenidades-ejemplos {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .amenidad-tag {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            cursor: pointer;
        }
        .amenidad-tag:hover {
            background: #0056b3;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <h1>✏️ Editar Tipo de Habitación</h1>          
        
        <div style="margin-bottom: 20px;">
            <a href="admin_tipos_habitacion.php" class="btn btn-secondary">← Volver a tipos</a>
            
            <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Panel principal</a>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>          
        
        <!-- Información actual -->
        <div class="info-actual">
            <h3>📋 Información Actual del Tipo</h3>
            <div style="margin-bottom: 15px;">
                <strong>Estado:</strong>
                <span class="estado-actual <?= $tipo['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                    <?= $tipo['activo'] ? '✅ ACTIVO' : '❌ INACTIVO' ?>
                </span>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-valor precio">$<?= number_format($tipo['precio_noche'], 0, ',', '.') ?></div>
                    <div class="info-label">Precio por noche</div>
                </div>
                <div class="info-item">
                    <div class="info-valor capacidad"><?= $tipo['capacidad'] ?></div>
                    <div class="info-label">Capacidad máxima</div>
                </div>
                <div class="info-item">
                    <div class="info-valor habitaciones"><?= $tipo['habitaciones_asignadas'] ?></div>
                    <div class="info-label">Habitaciones asignadas</div>
                </div>
                <div class="info-item">
                    <div class="info-valor ocupadas"><?= $tipo['habitaciones_ocupadas'] ?></div>
                    <div class="info-label">Actualmente ocupadas</div>
                </div>
            </div>
            
            <?php if ($tipo['descripcion']): ?>
                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 6px;">
                    <strong>Descripción actual:</strong> <?= htmlspecialchars($tipo['descripcion']) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tipo['amenidades']): ?>
                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 6px;">
                    <strong>Amenidades actuales:</strong> <?= htmlspecialchars($tipo['amenidades']) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($tipo['habitaciones_ocupadas'] > 0): ?>
            <div class="warning-box">
                <strong>⚠️ Atención:</strong> Este tipo tiene <?= $tipo['habitaciones_ocupadas'] ?> habitaciones ocupadas actualmente. 
                Los cambios de precio afectarán a todas las habitaciones de este tipo, pero no se puede desactivar mientras haya habitaciones ocupadas.
            </div>
        <?php endif; ?>
        
        <!-- Formulario de edición -->
        <form action="admin_editar_tipo.php?id=<?= $tipo['id_tipo'] ?>" method="POST" id="form-editar">
            <h3>✏️ Modificar Datos</h3>
            
            <div class="form-group">
                <label for="nombre">Nombre del Tipo:</label>
                <input type="text" id="nombre" name="nombre" 
                       value="<?= htmlspecialchars($tipo['nombre']) ?>" 
                       required maxlength="100">
                <small>Nombre único para identificar este tipo</small>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" 
                          required maxlength="500"><?= htmlspecialchars($tipo['descripcion']) ?></textarea>
                <small>Descripción que verán los huéspedes (máximo 500 caracteres)</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="precio_noche">Precio por Noche:</label>
                    <input type="number" id="precio_noche" name="precio_noche" 
                           value="<?= $tipo['precio_noche'] ?>" 
                           required min="1000" max="1000000" step="1000" 
                           oninput="actualizarPreview()">
                    <small>Precio en pesos argentinos (mínimo $1,000)</small>
                    <div id="precio-preview" class="precio-preview">
                        <div>Nuevo precio: <span id="precio-display" class="precio-display">$<?= number_format($tipo['precio_noche'], 0, ',', '.') ?></span></div>
                        <?php if ($tipo['habitaciones_asignadas'] > 0): ?>
                            <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                                Se aplicará a <?= $tipo['habitaciones_asignadas'] ?> habitaciones
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="capacidad">Capacidad Máxima:</label>
                    <input type="number" id="capacidad" name="capacidad" 
                           value="<?= $tipo['capacidad'] ?>" 
                           required min="1" max="10">
                    <small>Número máximo de huéspedes (1-10 personas)</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="metros_cuadrados">Metros Cuadrados (opcional):</label>
                <input type="number" id="metros_cuadrados" name="metros_cuadrados" 
                       value="<?= $tipo['metros_cuadrados'] ?>" 
                       min="10" max="200">
                <small>Tamaño de la habitación en metros cuadrados</small>
            </div>
            
            <div class="form-group">
                <label for="amenidades">Amenidades:</label>
                <textarea id="amenidades" name="amenidades" 
                          maxlength="300"><?= htmlspecialchars($tipo['amenidades']) ?></textarea>
                <small>Separar con comas. Ej: Wi-Fi gratuito, Aire acondicionado, TV LED</small>
                
                <div class="amenidades-help">
                    <strong>💡 Amenidades sugeridas (haz clic para agregar):</strong>
                    <div class="amenidades-ejemplos">
                        <span class="amenidad-tag" onclick="agregarAmenidad('Wi-Fi gratuito')">Wi-Fi gratuito</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Aire acondicionado')">Aire acondicionado</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('TV LED Smart')">TV LED Smart</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Minibar')">Minibar</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Vista al mar')">Vista al mar</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Balcón privado')">Balcón privado</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Jacuzzi')">Jacuzzi</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Caja fuerte')">Caja fuerte</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Escritorio')">Escritorio</span>
                        <span class="amenidad-tag" onclick="agregarAmenidad('Amenities premium')">Amenities premium</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Estado del Tipo:</label>
                <div class="checkbox-group">
                    <input type="checkbox" id="activo" name="activo" value="1" 
                           <?= $tipo['activo'] ? 'checked' : '' ?>
                           <?= ($tipo['habitaciones_ocupadas'] > 0) ? 'disabled' : '' ?>>
                    <label for="activo">Tipo activo (disponible para nuevas reservas)</label>
                </div>
                <?php if ($tipo['habitaciones_ocupadas'] > 0): ?>
                    <small style="color: #dc3545;">No se puede desactivar mientras haya habitaciones ocupadas</small>
                <?php else: ?>
                    <small>Desmarcar para desactivar este tipo temporalmente</small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" onclick="return confirmarCambios()">
                    💾 Guardar Cambios
                </button>
                <a href="admin_tipos_habitacion.php" class="btn btn-secondary">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        function actualizarPreview() {
            const precio = document.getElementById('precio_noche').value;
            const display = document.getElementById('precio-display');
            
            if (precio && precio > 0) {
                display.textContent = '$' + parseInt(precio).toLocaleString();
            }
        }
        
        function agregarAmenidad(amenidad) {
            const textarea = document.getElementById('amenidades');
            const valor = textarea.value.trim();
            
            // Verificar si la amenidad ya existe
            if (!valor.includes(amenidad)) {
                if (valor) {
                    textarea.value = valor + ', ' + amenidad;
                } else {
                    textarea.value = amenidad;
                }
            }
        }
        
        function confirmarCambios() {
            const nombre = document.getElementById('nombre').value;
            const precio = document.getElementById('precio_noche').value;
            const capacidad = document.getElementById('capacidad').value;
            const activo = document.getElementById('activo').checked;
            
            const mensaje = `¿Confirma los cambios al tipo de habitación?\n\n` +
                          `Nombre: ${nombre}\n` +
                          `Precio: $${parseInt(precio).toLocaleString()}/noche\n` +
                          `Capacidad: ${capacidad} personas\n` +
                          `Estado: ${activo ? 'Activo' : 'Inactivo'}\n\n` +
                          `${<?= $tipo['habitaciones_asignadas'] ?> > 0 ? 'Este cambio afectará a <?= $tipo['habitaciones_asignadas'] ?> habitaciones.' : ''}`;
            
            return confirm(mensaje);
        }
        
        // Validaciones en tiempo real
        document.getElementById('precio_noche').addEventListener('input', function() {
            const precio = parseInt(this.value);
            if (precio < 1000) {
                this.setCustomValidity('El precio mínimo es $1,000');
            } else if (precio > 1000000) {
                this.setCustomValidity('El precio máximo es $1,000,000');
            } else {
                this.setCustomValidity('');
            }
            actualizarPreview();
        });
        
        document.getElementById('capacidad').addEventListener('input', function() {
            const capacidad = parseInt(this.value);
            if (capacidad < 1) {
                this.setCustomValidity('La capacidad mínima es 1 persona');
            } else if (capacidad > 10) {
                this.setCustomValidity('La capacidad máxima es 10 personas');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body> 
</html>