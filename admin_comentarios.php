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

// Eliminar comentario con protección CSRF
if (isset($_POST['eliminar']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $idEliminar = intval($_POST['eliminar']);
    
    // Verificar que el comentario existe
    $stmt_check = $conn->prepare("SELECT id_comentario FROM comentarios WHERE id_comentario = ?");
    $stmt_check->bind_param("i", $idEliminar);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $stmt_delete = $conn->prepare("DELETE FROM comentarios WHERE id_comentario = ?");
        $stmt_delete->bind_param("i", $idEliminar);
        
        if ($stmt_delete->execute()) {
            $mensaje = "Comentario eliminado exitosamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al eliminar el comentario";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "Comentario no encontrado";
        $tipo_mensaje = "error";
    }
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Filtros y orden
$orden = $_GET['orden'] ?? 'fecha_comentario';
$filtro_usuario = intval($_GET['usuario'] ?? 0);
$filtro_habitacion = intval($_GET['habitacion'] ?? 0);
$filtro_puntuacion = intval($_GET['puntuacion'] ?? 0);
$buscar = trim($_GET['buscar'] ?? '');

// Construir consulta con filtros seguros
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($filtro_usuario > 0) {
    $where_conditions[] = "u.id_usuario = ?";
    $params[] = $filtro_usuario;
    $param_types .= "i";
}

if ($filtro_habitacion > 0) {
    $where_conditions[] = "h.id_habitacion = ?";
    $params[] = $filtro_habitacion;
    $param_types .= "i";
}

if ($filtro_puntuacion > 0) {
    $where_conditions[] = "c.puntuacion = ?";
    $params[] = $filtro_puntuacion;
    $param_types .= "i";
}

if (!empty($buscar)) {
    $where_conditions[] = "(c.comentario LIKE ? OR u.nombre LIKE ?)";
    $buscar_param = "%$buscar%";
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $param_types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Validar orden
$orden_valido = in_array($orden, ['fecha_comentario', 'puntuacion', 'nombre', 'numero']) ? $orden : 'fecha_comentario';
$direccion_orden = ($orden_valido === 'puntuacion') ? 'DESC' : 'DESC';

$sql = "SELECT 
    c.id_comentario, 
    c.comentario, 
    c.puntuacion, 
    c.fecha_comentario,
    u.id_usuario, 
    u.nombre, 
    u.email,
    h.id_habitacion, 
    h.numero,
    th.nombre as tipo_habitacion,
    COUNT(cr.id_comentario) as total_comentarios_usuario
    FROM comentarios c
    INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN comentarios cr ON u.id_usuario = cr.id_usuario
    WHERE $where_clause
    GROUP BY c.id_comentario, c.comentario, c.puntuacion, c.fecha_comentario, u.id_usuario, u.nombre, u.email, h.id_habitacion, h.numero, th.nombre
    ORDER BY c.$orden_valido $direccion_orden";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$comentarios = $stmt->get_result();

// Obtener estadísticas
$stats_sql = "SELECT 
    COUNT(*) as total_comentarios,
    AVG(puntuacion) as puntuacion_promedio,
    COUNT(CASE WHEN puntuacion >= 4 THEN 1 END) as comentarios_positivos,
    COUNT(CASE WHEN puntuacion <= 2 THEN 1 END) as comentarios_negativos,
    COUNT(CASE WHEN fecha_comentario >= DATE(NOW() - INTERVAL 7 DAY) THEN 1 END) as comentarios_semana
    FROM comentarios c
    INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
    WHERE $where_clause";

$stmt_stats = $conn->prepare($stats_sql);
if (!empty($params)) {
    $stmt_stats->bind_param($param_types, ...$params);
}
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Obtener usuarios para filtro
$usuarios = $conn->query("SELECT id_usuario, nombre FROM usuarios WHERE rol = 'cliente' ORDER BY nombre");

// Obtener habitaciones para filtro
$habitaciones = $conn->query("SELECT h.id_habitacion, h.numero, th.nombre as tipo 
                             FROM habitaciones h 
                             LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo 
                             ORDER BY h.numero");

// Top habitaciones por comentarios
$top_habitaciones = $conn->query("SELECT 
    h.numero,
    th.nombre as tipo,
    COUNT(c.id_comentario) as total_comentarios,
    AVG(c.puntuacion) as promedio_puntuacion
    FROM comentarios c
    INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    GROUP BY h.id_habitacion
    ORDER BY total_comentarios DESC
    LIMIT 5");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Gestión de Comentarios - Hotel Rivo</title>
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
        }
        .stat-number.total { color: #007bff; }
        .stat-number.promedio { color: #28a745; }
        .stat-number.positivos { color: #17a2b8; }
        .stat-number.negativos { color: #dc3545; }
        .stat-number.semana { color: #ffc107; }
        .content-grid {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 30px;
        }
        .comentarios-table, .sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: #007bff;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar {
            padding: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .comentario-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .comentario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .usuario-info {
            display: flex;
            align-items: center;
        }
        .usuario-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            font-size: 0.9em;
        }
        .usuario-nombre {
            font-weight: bold;
            color: #333;
        }
        .habitacion-info {
            font-size: 0.9em;
            color: #666;
        }
        .puntuacion {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .estrella {
            color: #ffc107;
            font-size: 1.2em;
        }
        .estrella.vacia {
            color: #e9ecef;
        }
        .comentario-texto {
            color: #333;
            line-height: 1.5;
            margin: 10px 0;
        }
        .comentario-fecha {
            font-size: 0.85em;
            color: #666;
        }
        .acciones {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .filtros {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group select, .form-group input {
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        .search-box {
            width: 100%;
        }
        .orden-links {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .orden-link {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 20px;
            background: #f8f9fa;
            color: #333;
            font-weight: bold;
            transition: all 0.3s;
        }
        .orden-link:hover, .orden-link.active {
            background: #007bff;
            color: white;
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
        .no-comentarios {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .top-habitaciones {
            margin-top: 20px;
        }
        .habitacion-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .habitacion-item:last-child {
            border-bottom: none;
        }
        .habitacion-numero {
            font-weight: bold;
            color: #007bff;
        }
        .habitacion-stats {
            font-size: 0.9em;
            color: #666;
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .comentario-puntuacion {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        .puntuacion-5, .puntuacion-4 { background: #d4edda; color: #155724; }
        .puntuacion-3 { background: #fff3cd; color: #856404; }
        .puntuacion-2, .puntuacion-1 { background: #f8d7da; color: #721c24; }
        .filtro-activo {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .filtros-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>💬 Gestión de Comentarios</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_usuarios.php" class="btn btn-secondary">👥 Ver usuarios</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Ver reservas</a>
                </div>
                <div>
                    <a href="admin_reporte_comentarios.php" class="btn btn-primary">📊 Generar Reporte</a>
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_comentarios'] ?></div>
                <div>Total Comentarios</div>
            </div>
            <div class="stat-card">
                <div class="stat-number promedio"><?= number_format($stats['puntuacion_promedio'], 1) ?></div>
                <div>Puntuación Promedio</div>
            </div>
            <div class="stat-card">
                <div class="stat-number positivos"><?= $stats['comentarios_positivos'] ?></div>
                <div>Comentarios Positivos (4-5★)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number negativos"><?= $stats['comentarios_negativos'] ?></div>
                <div>Comentarios Negativos (1-2★)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number semana"><?= $stats['comentarios_semana'] ?></div>
                <div>Esta Semana</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <h3>🔍 Filtros y Búsqueda</h3>
            <form method="GET" action="">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="usuario">👤 Cliente</label>
                        <select id="usuario" name="usuario">
                            <option value="0">Todos los clientes</option>
                            <?php while ($u = $usuarios->fetch_assoc()): ?>
                                <option value="<?= $u['id_usuario'] ?>" <?= $filtro_usuario == $u['id_usuario'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="habitacion">🏨 Habitación</label>
                        <select id="habitacion" name="habitacion">
                            <option value="0">Todas las habitaciones</option>
                            <?php while ($h = $habitaciones->fetch_assoc()): ?>
                                <option value="<?= $h['id_habitacion'] ?>" <?= $filtro_habitacion == $h['id_habitacion'] ? 'selected' : '' ?>>
                                    Hab. <?= htmlspecialchars($h['numero']) ?> - <?= htmlspecialchars($h['tipo']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="puntuacion">⭐ Puntuación</label>
                        <select id="puntuacion" name="puntuacion">
                            <option value="0">Todas las puntuaciones</option>
                            <option value="5" <?= $filtro_puntuacion == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ Excelente (5)</option>
                            <option value="4" <?= $filtro_puntuacion == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ Muy Bueno (4)</option>
                            <option value="3" <?= $filtro_puntuacion == 3 ? 'selected' : '' ?>>⭐⭐⭐ Bueno (3)</option>
                            <option value="2" <?= $filtro_puntuacion == 2 ? 'selected' : '' ?>>⭐⭐ Regular (2)</option>
                            <option value="1" <?= $filtro_puntuacion == 1 ? 'selected' : '' ?>>⭐ Malo (1)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="buscar">🔎 Buscar en comentarios</label>
                        <input type="text" id="buscar" name="buscar" 
                               value="<?= htmlspecialchars($buscar) ?>" 
                               placeholder="Buscar texto en comentarios..."
                               class="search-box">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 Aplicar Filtros</button>
                    </div>
                </div>
                
                <!-- Mantener orden actual en filtros -->
                <input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>">
            </form>
        </div>

        <?php if ($filtro_usuario > 0 || $filtro_habitacion > 0 || $filtro_puntuacion > 0 || !empty($buscar)): ?>
            <div class="filtro-activo">
                <span>📌 Filtros activos aplicados</span>
                <a href="admin_comentarios.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8em;">❌ Limpiar filtros</a>
            </div>
        <?php endif; ?>
        
        <!-- Enlaces de ordenamiento -->
        <div class="orden-links">
            <strong>Ordenar por:</strong>
            <a href="?orden=fecha_comentario<?= $filtro_usuario ? '&usuario=' . $filtro_usuario : '' ?><?= $filtro_habitacion ? '&habitacion=' . $filtro_habitacion : '' ?><?= $filtro_puntuacion ? '&puntuacion=' . $filtro_puntuacion : '' ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>" 
               class="orden-link <?= $orden === 'fecha_comentario' ? 'active' : '' ?>">
                📅 Fecha
            </a>
            <a href="?orden=puntuacion<?= $filtro_usuario ? '&usuario=' . $filtro_usuario : '' ?><?= $filtro_habitacion ? '&habitacion=' . $filtro_habitacion : '' ?><?= $filtro_puntuacion ? '&puntuacion=' . $filtro_puntuacion : '' ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>" 
               class="orden-link <?= $orden === 'puntuacion' ? 'active' : '' ?>">
                ⭐ Puntuación
            </a>
            <a href="?orden=nombre<?= $filtro_usuario ? '&usuario=' . $filtro_usuario : '' ?><?= $filtro_habitacion ? '&habitacion=' . $filtro_habitacion : '' ?><?= $filtro_puntuacion ? '&puntuacion=' . $filtro_puntuacion : '' ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>" 
               class="orden-link <?= $orden === 'nombre' ? 'active' : '' ?>">
                👤 Cliente
            </a>
            <a href="?orden=numero<?= $filtro_usuario ? '&usuario=' . $filtro_usuario : '' ?><?= $filtro_habitacion ? '&habitacion=' . $filtro_habitacion : '' ?><?= $filtro_puntuacion ? '&puntuacion=' . $filtro_puntuacion : '' ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>" 
               class="orden-link <?= $orden === 'numero' ? 'active' : '' ?>">
                🏨 Habitación
            </a>
        </div>

        <div class="content-grid">
            <!-- Lista de comentarios -->
            <div class="comentarios-table">
                <div class="table-header">
                    <h3>💬 Comentarios de Clientes</h3>
                    <span><?= $comentarios->num_rows ?> comentarios encontrados</span>
                </div>
                
                <div style="padding: 20px;">
                    <?php if ($comentarios->num_rows > 0): ?>
                        <?php while ($comentario = $comentarios->fetch_assoc()): ?>
                            <div class="comentario-card">
                                <div class="comentario-header">
                                    <div class="usuario-info">
                                        <div class="usuario-avatar">
                                            <?= strtoupper(substr($comentario['nombre'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="usuario-nombre"><?= htmlspecialchars($comentario['nombre']) ?></div>
                                            <div class="habitacion-info">
                                                🏨 Habitación <?= htmlspecialchars($comentario['numero']) ?> 
                                                - <?= htmlspecialchars($comentario['tipo_habitacion']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="acciones">
                                        <span class="comentario-puntuacion puntuacion-<?= $comentario['puntuacion'] ?>">
                                            <?= $comentario['puntuacion'] ?>⭐
                                        </span>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('¿Está seguro de eliminar este comentario?')">
                                            <input type="hidden" name="eliminar" value="<?= $comentario['id_comentario'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Eliminar comentario">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="puntuacion">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="estrella <?= $i <= $comentario['puntuacion'] ? '' : 'vacia' ?>">★</span>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; color: #666;">
                                        (<?= $comentario['puntuacion'] ?>/5)
                                    </span>
                                </div>
                                
                                <div class="comentario-texto">
                                    "<?= htmlspecialchars($comentario['comentario']) ?>"
                                </div>
                                
                                <div class="comentario-fecha">
                                    📅 <?= date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])) ?>
                                    <span style="margin-left: 15px; color: #007bff;">
                                        👤 Total comentarios del usuario: <?= $comentario['total_comentarios_usuario'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-comentarios">
                            <h3>💬 No hay comentarios</h3>
                            <p>No se encontraron comentarios con los filtros aplicados.</p>
                            <?php if ($filtro_usuario > 0 || $filtro_habitacion > 0 || $filtro_puntuacion > 0 || !empty($buscar)): ?>
                                <p><a href="admin_comentarios.php" class="btn btn-primary">Ver todos los comentarios</a></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar con información adicional -->
            <div class="sidebar">
                <h4>📊 Análisis Rápido</h4>
                
                <div style="margin-bottom: 25px;">
                    <h5>🎯 Distribución de Puntuaciones</h5>
                    <?php
                    $distribucion = $conn->query("SELECT 
                        puntuacion, 
                        COUNT(*) as cantidad,
                        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM comentarios)) as porcentaje
                        FROM comentarios 
                        GROUP BY puntuacion 
                        ORDER BY puntuacion DESC");
                    ?>
                    
                    <?php while ($dist = $distribucion->fetch_assoc()): ?>
                        <div style="margin: 10px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span><?= $dist['puntuacion'] ?>⭐</span>
                                <span><strong><?= $dist['cantidad'] ?></strong> (<?= number_format($dist['porcentaje'], 1) ?>%)</span>
                            </div>
                            <div style="background: #e9ecef; height: 8px; border-radius: 4px; margin-top: 5px;">
                                <div style="background: #007bff; height: 100%; width: <?= $dist['porcentaje'] ?>%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="top-habitaciones">
                    <h5>🏆 Top Habitaciones (por comentarios)</h5>
                    <?php if ($top_habitaciones->num_rows > 0): ?>
                        <?php while ($hab = $top_habitaciones->fetch_assoc()): ?>
                            <div class="habitacion-item">
                                <div>
                                    <div class="habitacion-numero">Hab. <?= $hab['numero'] ?></div>
                                    <div class="habitacion-stats"><?= htmlspecialchars($hab['tipo']) ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: #007bff;">
                                        <?= $hab['total_comentarios'] ?> comentarios
                                    </div>
                                    <div class="habitacion-stats">
                                        Promedio: <?= number_format($hab['promedio_puntuacion'], 1) ?>⭐
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #666; font-size: 0.9em;">No hay datos disponibles</p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 25px;">
                    <h5>💡 Consejos de Gestión</h5>
                    <ul style="font-size: 0.9em; color: #666; padding-left: 20px;">
                        <li><strong>Comentarios negativos:</strong> Responder rápidamente y ofrecer soluciones</li>
                        <li><strong>Tendencias:</strong> Identificar patrones en quejas recurrentes</li>
                        <li><strong>Habitaciones top:</strong> Analizar qué las hace especiales</li>
                        <li><strong>Seguimiento:</strong> Contactar clientes insatisfechos</li>
                    </ul>
                </div>
                
                <div style="margin-top: 25px;">
                    <h5>🔗 Acciones Rápidas</h5>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="admin_usuarios.php?estado=activo" class="btn btn-secondary">
                            👥 Ver clientes activos
                        </a>
                        <a href="admin_habitaciones.php" class="btn btn-secondary">
                            🏨 Gestionar habitaciones
                        </a>
                        <a href="admin_reservas.php" class="btn btn-secondary">
                            📅 Ver reservas recientes
                        </a>
                        <button onclick="exportarComentarios()" class="btn btn-primary">
                            📊 Exportar datos
                        </button>
                    </div>
                </div>
                
                <?php
                // Comentarios recientes (últimos 3)
                $recientes = $conn->query("SELECT 
                    c.comentario, c.puntuacion, c.fecha_comentario,
                    u.nombre, h.numero
                    FROM comentarios c
                    INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
                    INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
                    ORDER BY c.fecha_comentario DESC
                    LIMIT 3");
                ?>
                
                <div style="margin-top: 25px;">
                    <h5>🕒 Comentarios Recientes</h5>
                    <?php while ($reciente = $recientes->fetch_assoc()): ?>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0; font-size: 0.9em;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <strong><?= htmlspecialchars($reciente['nombre']) ?></strong>
                                <span><?= $reciente['puntuacion'] ?>⭐</span>
                            </div>
                            <div style="color: #666; margin-bottom: 5px;">
                                "<?= htmlspecialchars(substr($reciente['comentario'], 0, 60)) ?><?= strlen($reciente['comentario']) > 60 ? '...' : '' ?>"
                            </div>
                            <div style="font-size: 0.8em; color: #999;">
                                Hab. <?= $reciente['numero'] ?> • <?= date('d/m H:i', strtotime($reciente['fecha_comentario'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para exportar comentarios
        function exportarComentarios() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Cliente,Email,Habitacion,Tipo,Puntuacion,Comentario,Fecha\n";
            
            // Obtener datos de la tabla actual
            const comentarios = document.querySelectorAll('.comentario-card');
            comentarios.forEach(card => {
                const nombre = card.querySelector('.usuario-nombre').textContent;
                const habitacion = card.querySelector('.habitacion-info').textContent;
                const puntuacion = card.querySelector('.comentario-puntuacion').textContent.replace('⭐', '');
                const comentario = card.querySelector('.comentario-texto').textContent.replace(/"/g, '""');
                const fecha = card.querySelector('.comentario-fecha').textContent;
                
                csvContent += `"${nombre}","","${habitacion}","",${puntuacion},"${comentario}","${fecha}"\n`;
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "comentarios_hotel_rivo.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Búsqueda en tiempo real
        let timeoutId;
        document.getElementById('buscar').addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    // Podrías implementar búsqueda AJAX aquí
                    console.log('Búsqueda:', this.value);
                }
            }, 500);
        });
        
        // Filtro rápido por puntuación
        function filtrarPorPuntuacion(puntuacion) {
            const url = new URL(window.location);
            url.searchParams.set('puntuacion', puntuacion);
            window.location.href = url.toString();
        }
        
        // Confirmar eliminación mejorada
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const comentarioTexto = this.closest('.comentario-card').querySelector('.comentario-texto').textContent;
                const usuario = this.closest('.comentario-card').querySelector('.usuario-nombre').textContent;
                
                const mensaje = `¿Está seguro de eliminar este comentario?\n\nUsuario: ${usuario}\nComentario: ${comentarioTexto.substring(0, 100)}${comentarioTexto.length > 100 ? '...' : ''}\n\nEsta acción no se puede deshacer.`;
                
                if (confirm(mensaje)) {
                    this.submit();
                }
            });
        });
        
        // Estadísticas en tiempo real
        function actualizarEstadisticas() {
            const comentariosVisibles = document.querySelectorAll('.comentario-card:not([style*="display: none"])');
            const contador = document.querySelector('.table-header span');
            
            let total = comentariosVisibles.length;
            let suma = 0;
            
            comentariosVisibles.forEach(card => {
                const puntuacion = parseInt(card.querySelector('.comentario-puntuacion').textContent);
                suma += puntuacion;
            });
            
            const promedio = total > 0 ? (suma / total).toFixed(1) : 0;
            
            contador.textContent = `${total} comentarios encontrados (Promedio: ${promedio}⭐)`;
        }
        
        // Resaltar términos de búsqueda
        function resaltarBusqueda() {
            const termino = document.getElementById('buscar').value.toLowerCase();
            if (termino.length >= 3) {
                document.querySelectorAll('.comentario-texto').forEach(elemento => {
                    const texto = elemento.textContent;
                    const regex = new RegExp(`(${termino})`, 'gi');
                    elemento.innerHTML = texto.replace(regex, '<mark>$1</mark>');
                });
            }
        }
        
        // Funciones de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + F para enfocar búsqueda
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('buscar').focus();
            }
            
            // Ctrl + E para exportar
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportarComentarios();
            }
        });
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar tooltips
            document.getElementById('buscar').title = "Buscar en comentarios (Ctrl+F)";
            
            // Resaltar términos si hay búsqueda activa
            resaltarBusqueda();
            
            // Actualizar estadísticas iniciales
            actualizarEstadisticas();
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
        
        // Auto-refresh cada 5 minutos para comentarios nuevos
        setTimeout(function() {
            if (confirm('Han pasado 5 minutos. ¿Desea actualizar la página para ver comentarios nuevos?')) {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>