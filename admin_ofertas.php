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

// Función auxiliar para manejo seguro de consultas
function fetch_or_default($query, $default) {
    global $conn;
    try {
        $result = $conn->query($query);
        return $result ? $result->fetch_assoc() : $default;
    } catch (Exception $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return $default;
    }
}

function fetch_all_or_default($query) {
    global $conn;
    try {
        $result = $conn->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Exception $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return [];
    }
}

// Verificar si existe la tabla ofertas_especiales
$tabla_existe = false;
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'ofertas_especiales'");
    $tabla_existe = $check_table && $check_table->num_rows > 0;
} catch (Exception $e) {
    error_log("Error verificando tabla: " . $e->getMessage());
}

// Crear tabla si no existe
if (!$tabla_existe) {
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS ofertas_especiales (
            id_oferta INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT,
            descuento DECIMAL(5,2) NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            activa BOOLEAN DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table);
        $tabla_existe = true;
    } catch (Exception $e) {
        error_log("Error creando tabla: " . $e->getMessage());
    }
}

// Procesar acciones (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $descuento = floatval($_POST['descuento']);
        $fecha_inicio = $_POST['fecha_inicio'];
        $fecha_fin = $_POST['fecha_fin'];
        $activa = isset($_POST['activa']) ? 1 : 0;
        
        // Validaciones
        $errores = [];
        if (empty($nombre)) $errores[] = "El nombre es obligatorio";
        if ($descuento <= 0 || $descuento > 100) $errores[] = "El descuento debe estar entre 1% y 100%";
        if (empty($fecha_inicio) || empty($fecha_fin)) $errores[] = "Las fechas son obligatorias";
        if (strtotime($fecha_inicio) >= strtotime($fecha_fin)) $errores[] = "La fecha de fin debe ser posterior a la de inicio";
        
        if (empty($errores)) {
            try {
                $stmt = $conn->prepare("INSERT INTO ofertas_especiales (nombre, descripcion, descuento, fecha_inicio, fecha_fin, activa) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdssi", $nombre, $descripcion, $descuento, $fecha_inicio, $fecha_fin, $activa);
                
                if ($stmt->execute()) {
                    $mensaje = "✅ Oferta creada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "❌ Error al crear la oferta";
                    $tipo_mensaje = "error";
                }
            } catch (Exception $e) {
                $mensaje = "❌ Error: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "❌ " . implode(", ", $errores);
            $tipo_mensaje = "error";
        }
    }
    
    if ($accion === 'toggle_estado') {
        $id_oferta = intval($_POST['id_oferta']);
        $nuevo_estado = intval($_POST['nuevo_estado']);
        
        try {
            $stmt = $conn->prepare("UPDATE ofertas_especiales SET activa = ? WHERE id_oferta = ?");
            $stmt->bind_param("ii", $nuevo_estado, $id_oferta);
            
            if ($stmt->execute()) {
                $estado_texto = $nuevo_estado ? "activada" : "desactivada";
                $mensaje = "✅ Oferta $estado_texto exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (Exception $e) {
            $mensaje = "❌ Error al cambiar estado: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
    
    if ($accion === 'eliminar') {
        $id_oferta = intval($_POST['id_oferta']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM ofertas_especiales WHERE id_oferta = ?");
            $stmt->bind_param("i", $id_oferta);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Oferta eliminada exitosamente";
                $tipo_mensaje = "success";
            }
        } catch (Exception $e) {
            $mensaje = "❌ Error al eliminar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener ofertas con estadísticas
$ofertas = [];
$stats = ['total' => 0, 'activas' => 0, 'vencidas' => 0, 'proximas' => 0];

if ($tabla_existe) {
    $ofertas = fetch_all_or_default("SELECT *, 
        CASE 
            WHEN fecha_fin < CURDATE() THEN 'vencida'
            WHEN fecha_inicio > CURDATE() THEN 'proxima'
            WHEN fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() THEN 'vigente'
            ELSE 'desconocido'
        END as estado_temporal
        FROM ofertas_especiales 
        ORDER BY fecha_inicio DESC");
    
    $stats = fetch_or_default("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN activa = 1 THEN 1 ELSE 0 END) as activas,
        SUM(CASE WHEN fecha_fin < CURDATE() THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN fecha_inicio > CURDATE() THEN 1 ELSE 0 END) as proximas
        FROM ofertas_especiales", $stats);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ofertas Especiales - Hotel Rivo</title>
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
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .header-subtitle {
            opacity: 0.9;
            font-size: 1.1em;
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
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
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b35, #f7931e);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.1em;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .section-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            font-weight: bold;
            font-size: 1.2em;
        }
        .section-content {
            padding: 25px;
        }
        .ofertas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .ofertas-table th,
        .ofertas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .ofertas-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .ofertas-table tr:hover {
            background: #f8f9fa;
        }
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            font-weight: bold;
            font-size: 1.2em;
        }
        .form-content {
            padding: 25px;
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
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
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
        .estado-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-vigente { background: #d4edda; color: #155724; }
        .estado-vencida { background: #f8d7da; color: #721c24; }
        .estado-proxima { background: #d1ecf1; color: #0c5460; }
        .estado-activa { background: #d4edda; color: #155724; }
        .estado-inactiva { background: #f8d7da; color: #721c24; }
        .descuento-badge {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .actions-cell {
            white-space: nowrap;
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
        .no-ofertas {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .preview-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            .actions-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header principal -->
        <div class="header">
            <h1>🎯 Gestión de Ofertas Especiales</h1>
            <div class="header-subtitle">
                Administra promociones y descuentos del Hotel Rivo
            </div>
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_tarifas.php" class="btn btn-secondary">💰 Tarifas</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Reservas</a>
                </div>
                <div>
                    <button onclick="scrollToForm()" class="btn btn-primary">➕ Nueva Oferta</button>
                    <button onclick="exportarOfertas()" class="btn btn-success">📥 Exportar</button>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Ofertas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['activas'] ?></div>
                <div class="stat-label">Activas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['vencidas'] ?></div>
                <div class="stat-label">Vencidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['proximas'] ?></div>
                <div class="stat-label">Próximas</div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-grid">
            <!-- Lista de ofertas -->
            <div class="main-content">
                <div class="section-header">
                    📋 Lista de Ofertas Especiales
                </div>
                <div class="section-content">
                    <?php if (!$tabla_existe): ?>
                        <div class="no-ofertas">
                            <h3>⚠️ Tabla no encontrada</h3>
                            <p>La tabla 'ofertas_especiales' no existe en la base de datos.</p>
                            <p>Se intentará crear automáticamente al agregar la primera oferta.</p>
                        </div>
                    <?php elseif (empty($ofertas)): ?>
                        <div class="no-ofertas">
                            <h3>🎯 No hay ofertas registradas</h3>
                            <p>Comienza creando tu primera oferta especial para atraer más clientes.</p>
                            <button onclick="scrollToForm()" class="btn btn-primary">➕ Crear Primera Oferta</button>
                        </div>
                    <?php else: ?>
                        <table class="ofertas-table">
                            <thead>
                                <tr>
                                    <th>Oferta</th>
                                    <th>Descuento</th>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ofertas as $oferta): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">
                                                <?= htmlspecialchars($oferta['nombre']) ?>
                                            </div>
                                            <div style="color: #666; font-size: 0.9em;">
                                                <?= htmlspecialchars($oferta['descripcion'] ?? 'Sin descripción') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="descuento-badge">
                                                <?= $oferta['descuento'] ?>% OFF
                                            </span>
                                        </td>
                                        <td>
                                            <div><?= date('d/m/Y', strtotime($oferta['fecha_inicio'])) ?></div>
                                            <div style="color: #666;">hasta</div>
                                            <div><?= date('d/m/Y', strtotime($oferta['fecha_fin'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="estado-badge estado-<?= $oferta['estado_temporal'] ?>">
                                                <?= ucfirst($oferta['estado_temporal']) ?>
                                            </div>
                                            <div style="margin-top: 5px;">
                                                <span class="estado-badge estado-<?= $oferta['activa'] ? 'activa' : 'inactiva' ?>">
                                                    <?= $oferta['activa'] ? 'Activa' : 'Inactiva' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Cambiar estado de la oferta?')">
                                                <input type="hidden" name="accion" value="toggle_estado">
                                                <input type="hidden" name="id_oferta" value="<?= $oferta['id_oferta'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?= $oferta['activa'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-small <?= $oferta['activa'] ? 'btn-warning' : 'btn-success' ?>">
                                                    <?= $oferta['activa'] ? '⏸️ Pausar' : '▶️ Activar' ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Eliminar esta oferta permanentemente?')">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_oferta" value="<?= $oferta['id_oferta'] ?>">
                                                <button type="submit" class="btn btn-small btn-danger">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario para nueva oferta -->
            <div class="form-card" id="form-oferta">
                <div class="form-header">
                    ➕ Nueva Oferta Especial
                </div>
                <div class="form-content">
                    <form method="POST" id="form-crear-oferta">
                        <input type="hidden" name="accion" value="crear">
                        
                        <div class="form-group">
                            <label for="nombre">🎯 Nombre de la Oferta</label>
                            <input type="text" id="nombre" name="nombre" 
                                   placeholder="Ej: Oferta de Verano 2024" 
                                   required maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion">📝 Descripción</label>
                            <textarea id="descripcion" name="descripcion" 
                                      rows="3" maxlength="500"
                                      placeholder="Describe los detalles de la oferta..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="descuento">💰 Descuento (%)</label>
                            <input type="number" id="descuento" name="descuento" 
                                   min="1" max="100" step="0.01" 
                                   placeholder="15" required>
                            <small>Ingrese el porcentaje de descuento (1-100%)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_inicio">📅 Fecha de Inicio</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_fin">📅 Fecha de Fin</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        </div>
                        
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="activa" checked>
                                ✅ Activar oferta inmediatamente
                            </label>
                        </div>
                        
                        <div class="preview-section" id="preview-oferta" style="display: none;">
                            <h4>👁️ Vista Previa:</h4>
                            <div id="preview-content"></div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                ✨ Crear Oferta
                            </button>
                            <button type="button" onclick="limpiarFormulario()" class="btn btn-secondary">
                                🔄 Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funciones de navegación
        function scrollToForm() {
            document.getElementById('form-oferta').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
            document.getElementById('nombre').focus();
        }

        // Limpiar formulario
        function limpiarFormulario() {
            document.getElementById('form-crear-oferta').reset();
            document.getElementById('preview-oferta').style.display = 'none';
            document.getElementById('nombre').focus();
        }

        // Vista previa en tiempo real
        function actualizarPreview() {
            const nombre = document.getElementById('nombre').value;
            const descripcion = document.getElementById('descripcion').value;
            const descuento = document.getElementById('descuento').value;
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            if (nombre && descuento && fechaInicio && fechaFin) {
                const preview = document.getElementById('preview-oferta');
                const content = document.getElementById('preview-content');
                
                content.innerHTML = `
                    <div style="border: 2px dashed #ff6b35; padding: 15px; border-radius: 8px; background: white;">
                        <div style="font-weight: bold; font-size: 1.2em; color: #ff6b35; margin-bottom: 8px;">
                            ${nombre}
                        </div>
                        <div style="background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; padding: 8px 15px; border-radius: 20px; display: inline-block; font-weight: bold; margin-bottom: 10px;">
                            ${descuento}% OFF
                        </div>
                        ${descripcion ? `<div style="color: #666; margin-bottom: 10px;">${descripcion}</div>` : ''}
                        <div style="font-size: 0.9em; color: #888;">
                            Válida del ${new Date(fechaInicio).toLocaleDateString('es-CO')} al ${new Date(fechaFin).toLocaleDateString('es-CO')}
                        </div>
                    </div>
                `;
                preview.style.display = 'block';
            } else {
                document.getElementById('preview-oferta').style.display = 'none';
            }
        }

        // Validaciones en tiempo real
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            const fechaInicio = this.value;
            const fechaFinInput = document.getElementById('fecha_fin');
            
            if (fechaInicio) {
                const minFechaFin = new Date(fechaInicio);
                minFechaFin.setDate(minFechaFin.getDate() + 1);
                fechaFinInput.min = minFechaFin.toISOString().split('T')[0];
                
                if (fechaFinInput.value && fechaFinInput.value <= fechaInicio) {
                    fechaFinInput.value = minFechaFin.toISOString().split('T')[0];
                }
            }
            actualizarPreview();
        });

        // Event listeners para vista previa
        ['nombre', 'descripcion', 'descuento', 'fecha_inicio', 'fecha_fin'].forEach(id => {
            document.getElementById(id).addEventListener('input', actualizarPreview);
        });

        // Validación del formulario
        document.getElementById('form-crear-oferta').addEventListener('submit', function(e) {
            const descuento = parseFloat(document.getElementById('descuento').value);
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const nombre = document.getElementById('nombre').value.trim();
            
            let errores = [];
            
            if (!nombre) {
                errores.push('El nombre es obligatorio');
            }
            
            if (!descuento || descuento <= 0 || descuento > 100) {
                errores.push('El descuento debe estar entre 1% y 100%');
            }
            
            if (!fechaInicio || !fechaFin) {
                errores.push('Las fechas son obligatorias');
            } else if (new Date(fechaInicio) >= new Date(fechaFin)) {
                errores.push('La fecha de fin debe ser posterior a la fecha de inicio');
            }
            
            if (errores.length > 0) {
                e.preventDefault();
                mostrarNotificacion('❌ ' + errores.join(', '), 'error');
                return false;
            }
            
            // Confirmación antes de crear
            const mensaje = `¿Crear la oferta "${nombre}" con ${descuento}% de descuento?`;
            if (!confirm(mensaje)) {
                e.preventDefault();
                return false;
            }
        });

        // Exportar ofertas
        function exportarOfertas() {
            const fechaActual = new Date().toLocaleDateString('es-CO');
            const nombreArchivo = `ofertas_especiales_${fechaActual.replace(/\//g, '_')}.txt`;
            
            let contenido = `OFERTAS ESPECIALES - HOTEL RIVO\n`;
            contenido += `Reporte generado: ${fechaActual}\n`;
            contenido += `${'='.repeat(50)}\n\n`;
            
            contenido += `RESUMEN:\n`;
            contenido += `- Total de ofertas: <?= $stats['total'] ?>\n`;
            contenido += `- Ofertas activas: <?= $stats['activas'] ?>\n`;
            contenido += `- Ofertas vencidas: <?= $stats['vencidas'] ?>\n`;
            contenido += `- Ofertas próximas: <?= $stats['proximas'] ?>\n\n`;
            
            contenido += `DETALLE DE OFERTAS:\n`;
            contenido += `${'='.repeat(50)}\n`;
            
            <?php if (!empty($ofertas)): ?>
                <?php foreach ($ofertas as $index => $oferta): ?>
                    contenido += `\n${<?= $index + 1 ?>}. <?= addslashes($oferta['nombre']) ?>\n`;
                    contenido += `   Descuento: <?= $oferta['descuento'] ?>%\n`;
                    contenido += `   Período: <?= date('d/m/Y', strtotime($oferta['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($oferta['fecha_fin'])) ?>\n`;
                    contenido += `   Estado: <?= ucfirst($oferta['estado_temporal']) ?> (<?= $oferta['activa'] ? 'Activa' : 'Inactiva' ?>)\n`;
                    <?php if ($oferta['descripcion']): ?>
                        contenido += `   Descripción: <?= addslashes($oferta['descripcion']) ?>\n`;
                    <?php endif; ?>
                    contenido += `   Creada: <?= date('d/m/Y H:i', strtotime($oferta['fecha_creacion'])) ?>\n`;
                <?php endforeach; ?>
            <?php else: ?>
                contenido += `\nNo hay ofertas registradas.\n`;
            <?php endif; ?>
            
            contenido += `\n${'='.repeat(50)}\n`;
            contenido += `Reporte generado automáticamente por Sistema Hotel Rivo\n`;
            contenido += `Fecha y hora: ${new Date().toLocaleString('es-CO')}\n`;
            
            // Crear y descargar archivo
            const blob = new Blob([contenido], { type: 'text/plain; charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombreArchivo;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            mostrarNotificacion('📥 Reporte de ofertas exportado exitosamente', 'success');
        }

        // Sistema de notificaciones
        function mostrarNotificacion(mensaje, tipo = 'info') {
            // Eliminar notificaciones anteriores si existen muchas
            const existentes = document.querySelectorAll('.notificacion-ofertas');
            if (existentes.length >= 3) {
                existentes[0].remove();
            }
            
            const notif = document.createElement('div');
            notif.className = 'notificacion-ofertas';
            
            const colores = {
                'success': '#28a745',
                'info': '#007bff',
                'warning': '#ffc107',
                'error': '#dc3545'
            };
            
            const iconos = {
                'success': '✅',
                'info': 'ℹ️',
                'warning': '⚠️',
                'error': '❌'
            };
            
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${colores[tipo] || colores.info};
                color: ${tipo === 'warning' ? '#212529' : 'white'};
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideInRight 0.3s ease;
                max-width: 350px;
                word-wrap: break-word;
                font-weight: 500;
            `;
            
            notif.innerHTML = `${iconos[tipo] || iconos.info} ${mensaje}`;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                if (notif.parentNode) {
                    notif.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        if (notif.parentNode) {
                            notif.remove();
                        }
                    }, 300);
                }
            }, tipo === 'error' ? 5000 : 3000);
        }

        // Sugerencias de nombres de ofertas
        const sugerenciasNombres = [
            'Oferta de Verano 2024',
            'Descuento Fin de Semana',
            'Promoción Estancia Larga',
            'Oferta San Valentín',
            'Descuento Temporada Baja',
            'Promoción Familia',
            'Oferta Reserva Anticipada',
            'Descuento Ejecutivo',
            'Promoción Luna de Miel',
            'Oferta Black Friday'
        ];

        // Autocompletado para nombres
        document.getElementById('nombre').addEventListener('input', function() {
            const valor = this.value.toLowerCase();
            if (valor.length >= 3) {
                const sugerencias = sugerenciasNombres.filter(s => 
                    s.toLowerCase().includes(valor)
                );
                
                // Podrías implementar un dropdown de sugerencias aquí
                console.log('Sugerencias:', sugerencias);
            }
        });

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elementos = document.querySelectorAll('.stat-card, .main-content, .form-card');
            elementos.forEach((elemento, index) => {
                elemento.style.opacity = '0';
                elemento.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    elemento.style.transition = 'all 0.6s ease';
                    elemento.style.opacity = '1';
                    elemento.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // CSS para animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            @media print {
                .btn, .actions-header, .form-card { display: none !important; }
                .ofertas-table { page-break-inside: avoid; }
                body { background: white !important; }
                .header { background: #ff6b35 !important; -webkit-print-color-adjust: exact; }
            }
        `;
        document.head.appendChild(style);

        // Auto-guardar borrador (opcional)
        function guardarBorrador() {
            const formData = {
                nombre: document.getElementById('nombre').value,
                descripcion: document.getElementById('descripcion').value,
                descuento: document.getElementById('descuento').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value
            };
            
            localStorage.setItem('borrador_oferta', JSON.stringify(formData));
        }

        function cargarBorrador() {
            const borrador = localStorage.getItem('borrador_oferta');
            if (borrador) {
                const data = JSON.parse(borrador);
                Object.keys(data).forEach(key => {
                    const elemento = document.getElementById(key);
                    if (elemento && data[key]) {
                        elemento.value = data[key];
                    }
                });
                actualizarPreview();
            }
        }

        // Auto-guardar cada 30 segundos
        setInterval(guardarBorrador, 30000);

        // Cargar borrador al inicio
        window.addEventListener('load', cargarBorrador);

        // Limpiar borrador al enviar exitosamente
        document.getElementById('form-crear-oferta').addEventListener('submit', function() {
            setTimeout(() => {
                localStorage.removeItem('borrador_oferta');
            }, 1000);
        });

        // Efecto hover en las tarjetas de estadísticas
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Ocultar mensajes automáticamente
        setTimeout(function() {
            const mensaje = document.querySelector('.mensaje');
            if (mensaje) {
                mensaje.style.opacity = '0';
                mensaje.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (mensaje.parentNode) {
                        mensaje.remove();
                    }
                }, 500);
            }
        }, 5000);

        console.log('🎯 Sistema de gestión de ofertas inicializado correctamente');
    </script>
</body>
</html>