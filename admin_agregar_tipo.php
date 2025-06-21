<?php 
session_start(); 
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {     
    header('Location: login.php');     
    exit; 
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     
    // Conexión a la base de datos     
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');      
    
    if ($conn->connect_error) {         
        die("Conexión fallida: " . $conn->connect_error);     
    }      
    
    // Recibiendo y validando datos del formulario     
    $nombre = trim($_POST['nombre']);     
    $descripcion = trim($_POST['descripcion']);     
    $precio_noche = floatval($_POST['precio_noche']);
    $capacidad = intval($_POST['capacidad']);
    $metros_cuadrados = !empty($_POST['metros_cuadrados']) ? intval($_POST['metros_cuadrados']) : null;
    $amenidades = !empty($_POST['amenidades']) ? trim($_POST['amenidades']) : '';
    
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
    
    // Verificar que no exista un tipo con el mismo nombre
    $check_stmt = $conn->prepare("SELECT id_tipo FROM tipos_habitacion WHERE nombre = ?");
    $check_stmt->bind_param("s", $nombre);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errores[] = "Ya existe un tipo de habitación con ese nombre";
    }
    
    if (empty($errores)) {
        // Insertar el nuevo tipo de habitación con prepared statement
        $stmt = $conn->prepare("INSERT INTO tipos_habitacion (nombre, descripcion, precio_noche, capacidad, metros_cuadrados, amenidades, activo) VALUES (?, ?, ?, ?, ?, ?, TRUE)");
        $stmt->bind_param("ssdiss", $nombre, $descripcion, $precio_noche, $capacidad, $metros_cuadrados, $amenidades);
        
        if ($stmt->execute()) {
            $nuevo_id = $conn->insert_id;
            $mensaje = "✅ Nuevo tipo de habitación '$nombre' agregado exitosamente. (ID: $nuevo_id)";
            $tipo_mensaje = 'success';
            
            // Limpiar el formulario después del éxito
            $_POST = [];
        } else {
            $mensaje = "❌ Error al agregar el tipo de habitación: " . $stmt->error;
            $tipo_mensaje = 'error';
        }
        
        $stmt->close();
    } else {
        $mensaje = "❌ Errores encontrados:\n" . implode("\n", $errores);
        $tipo_mensaje = 'error';
    }
    
    $check_stmt->close();
    $conn->close(); 
}

// Obtener tipos existentes para referencia
$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');
$tipos_existentes = $conn->query("SELECT nombre, precio_noche, capacidad FROM tipos_habitacion ORDER BY precio_noche ASC");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Agregar Tipo de Habitación - Hotel Rivo</title>
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
        .tipos-existentes {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .tipo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .tipo-item:last-child {
            border-bottom: none;
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
    </style>
</head> 
<body>     
    <div class="container">
        <h1>🏷️ Agregar Nuevo Tipo de Habitación</h1>          
        
        <div style="margin-bottom: 20px;">
            <a href="admin_tipos_habitacion.php" class="btn btn-secondary">← Volver a tipos</a>
            
            <a href="admin_dashboard.php" class="btn btn-secondary">🏠 Panel principal</a>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>          
        
        <form action="admin_agregar_tipo.php" method="POST" id="form-tipo">
            <div class="form-group">
                <label for="nombre">Nombre del Tipo:</label>
                <input type="text" id="nombre" name="nombre" 
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                       required maxlength="100" placeholder="Ej: Suite Deluxe, Habitación Estándar">
                <small>Nombre único para identificar este tipo de habitación</small>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" 
                          required maxlength="500" 
                          placeholder="Describe las características, comodidades y atractivos de este tipo de habitación..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                <small>Descripción detallada que verán los huéspedes (máximo 500 caracteres)</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="precio_noche">Precio por Noche:</label>
                    <input type="number" id="precio_noche" name="precio_noche" 
                           value="<?= htmlspecialchars($_POST['precio_noche'] ?? '') ?>" 
                           required min="1000" max="1000000" step="1000" 
                           placeholder="70000" oninput="actualizarPreview()">
                    <small>Precio en pesos argentinos (mínimo $1,000)</small>
                    <div id="precio-preview" class="precio-preview" style="display: none;">
                        <div>Precio por noche: <span id="precio-display" class="precio-display">$0</span></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="capacidad">Capacidad Máxima:</label>
                    <input type="number" id="capacidad" name="capacidad" 
                           value="<?= htmlspecialchars($_POST['capacidad'] ?? '') ?>" 
                           required min="1" max="10" placeholder="2">
                    <small>Número máximo de huéspedes (1-10 personas)</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="metros_cuadrados">Metros Cuadrados (opcional):</label>
                <input type="number" id="metros_cuadrados" name="metros_cuadrados" 
                       value="<?= htmlspecialchars($_POST['metros_cuadrados'] ?? '') ?>" 
                       min="10" max="200" placeholder="30">
                <small>Tamaño de la habitación en metros cuadrados</small>
            </div>
            
            <div class="form-group">
                <label for="amenidades">Amenidades:</label>
                <textarea id="amenidades" name="amenidades" 
                          maxlength="300" 
                          placeholder="Wi-Fi gratuito, Aire acondicionado, TV LED, Minibar, Vista al mar..."><?= htmlspecialchars($_POST['amenidades'] ?? '') ?></textarea>
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
                <button type="submit" class="btn btn-primary" onclick="return confirmarCreacion()">
                    ➕ Crear Tipo de Habitación
                </button>
                <a href="admin_tipos_habitacion.php" class="btn btn-secondary">
                    ❌ Cancelar
                </a>
            </div>
        </form>
        
        <!-- Tipos existentes para referencia -->
        <?php if ($tipos_existentes->num_rows > 0): ?>
            <div class="tipos-existentes">
                <h3>📋 Tipos Existentes (para referencia)</h3>
                <?php while ($tipo = $tipos_existentes->fetch_assoc()): ?>
                    <div class="tipo-item">
                        <div>
                            <strong><?= htmlspecialchars($tipo['nombre']) ?></strong>
                            <div style="font-size: 0.9em; color: #666;">
                                Capacidad: <?= $tipo['capacidad'] ?> personas
                            </div>
                        </div>
                        <div style="font-weight: bold; color: #28a745;">
                            $<?= number_format($tipo['precio_noche'], 0, ',', '.') ?>/noche
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function actualizarPreview() {
            const precio = document.getElementById('precio_noche').value;
            const preview = document.getElementById('precio-preview');
            const display = document.getElementById('precio-display');
            
            if (precio && precio > 0) {
                display.textContent = '$' + parseInt(precio).toLocaleString();
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
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
        
        function confirmarCreacion() {
            const nombre = document.getElementById('nombre').value;
            const precio = document.getElementById('precio_noche').value;
            const capacidad = document.getElementById('capacidad').value;
            
            if (!nombre || !precio || !capacidad) {
                alert('⚠️ Por favor complete todos los campos requeridos.');
                return false;
            }
            
            const mensaje = `¿Confirma la creación del nuevo tipo?\n\n` +
                          `Nombre: ${nombre}\n` +
                          `Precio: $${parseInt(precio).toLocaleString()}/noche\n` +
                          `Capacidad: ${capacidad} personas`;
            
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
        
        // Actualizar preview al cargar si hay valor
        document.addEventListener('DOMContentLoaded', function() {
            actualizarPreview();
        });
    </script>
</body> 
</html>