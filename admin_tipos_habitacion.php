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

// Obtener todos los tipos de habitación con estadísticas
$tipos_habitacion = $conn->query("SELECT 
    t.*,
    COUNT(h.id_habitacion) as total_habitaciones,
    SUM(CASE WHEN h.estado = 'disponible' THEN 1 ELSE 0 END) as habitaciones_disponibles,
    SUM(CASE WHEN h.estado = 'ocupada' THEN 1 ELSE 0 END) as habitaciones_ocupadas,
    SUM(CASE WHEN h.estado = 'mantenimiento' THEN 1 ELSE 0 END) as habitaciones_mantenimiento
    FROM tipos_habitacion t
    LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo
    GROUP BY t.id_tipo, t.nombre, t.descripcion, t.precio_noche, t.capacidad, t.activo
    ORDER BY t.activo DESC, t.precio_noche ASC");

// Obtener estadísticas generales
$stats = $conn->query("SELECT 
    COUNT(DISTINCT t.id_tipo) as total_tipos,
    COUNT(DISTINCT CASE WHEN t.activo = 1 THEN t.id_tipo END) as tipos_activos,
    COUNT(DISTINCT h.id_habitacion) as total_habitaciones,
    AVG(t.precio_noche) as precio_promedio
    FROM tipos_habitacion t
    LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo")->fetch_assoc();
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Gestión de Tipos de Habitación - Hotel Rivo</title>
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
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-number.tipos { color: #007bff; }
        .stat-number.activos { color: #28a745; }
        .stat-number.habitaciones { color: #6f42c1; }
        .stat-number.precio { color: #fd7e14; }
        .tipos-table {
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
        tr.tipo-inactivo {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .tipo-nombre {
            font-weight: bold;
            font-size: 1.1em;
            color: #007bff;
        }
        .precio-cell {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1em;
        }
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado-activo { background: #d4edda; color: #155724; }
        .estado-inactivo { background: #f8d7da; color: #721c24; }
        .habitaciones-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mini-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .mini-disponible { background: #d4edda; color: #155724; }
        .mini-ocupada { background: #f8d7da; color: #721c24; }
        .mini-mantenimiento { background: #fff3cd; color: #856404; }
        .mini-total { background: #e2e3e5; color: #383d41; }
        .acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-edit { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-primary { background: #007bff; color: white; padding: 12px 24px; }
        .btn-secondary { background: #6c757d; color: white; padding: 12px 24px; }
        .btn-success { background: #28a745; color: white; padding: 12px 24px; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .descripcion-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .descripcion-cell:hover {
            white-space: normal;
            overflow: visible;
        }
        .filtros {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filtros select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .no-tipos {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .warning-habitaciones {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 8px;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>🏷️ Gestión de Tipos de Habitación</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_habitaciones.php" class="btn btn-secondary">🛏️ Ver habitaciones</a>
                </div>
                <div>
                    <a href="admin_agregar_tipo.php" class="btn btn-success">➕ Agregar Nuevo Tipo</a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number tipos"><?= $stats['total_tipos'] ?></div>
                <div>Tipos de Habitación</div>
            </div>
            <div class="stat-card">
                <div class="stat-number activos"><?= $stats['tipos_activos'] ?></div>
                <div>Tipos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number habitaciones"><?= $stats['total_habitaciones'] ?></div>
                <div>Total Habitaciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number precio">$<?= number_format($stats['precio_promedio'], 0, ',', '.') ?></div>
                <div>Precio Promedio</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <label><strong>Filtrar por:</strong></label>
            <select id="filtro-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="activo">Solo activos</option>
                <option value="inactivo">Solo inactivos</option>
            </select>
            
            <select id="filtro-precio" onchange="filtrarTabla()">
                <option value="">Todos los precios</option>
                <option value="bajo">Hasta $50,000</option>
                <option value="medio">$50,001 - $100,000</option>
                <option value="alto">Más de $100,000</option>
            </select>
            
            <button onclick="limpiarFiltros()" class="btn btn-secondary">🔄 Limpiar Filtros</button>
        </div>
        
        <!-- Tabla de tipos -->
        <div class="tipos-table">
            <div class="table-header">
                <h3>📋 Lista de Tipos de Habitación</h3>
                <span><?= $tipos_habitacion->num_rows ?> tipos registrados</span>
            </div>
            
            <?php if ($tipos_habitacion->num_rows > 0): ?>
                <table id="tabla-tipos">         
                    <thead>
                        <tr>             
                            <th>ID</th>             
                            <th>Nombre</th>             
                            <th>Precio/Noche</th>             
                            <th>Capacidad</th>             
                            <th>Descripción</th>             
                            <th>Habitaciones</th>             
                            <th>Estado</th>             
                            <th>Acciones</th>         
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($tipo = $tipos_habitacion->fetch_assoc()): ?>             
                            <tr class="<?= !$tipo['activo'] ? 'tipo-inactivo' : '' ?>" 
                                data-estado="<?= $tipo['activo'] ? 'activo' : 'inactivo' ?>"
                                data-precio="<?= $tipo['precio_noche'] ?>">                 
                                <td><?= $tipo['id_tipo'] ?></td>                 
                                <td class="tipo-nombre"><?= htmlspecialchars($tipo['nombre']) ?></td>                 
                                <td class="precio-cell">$<?= number_format($tipo['precio_noche'], 0, ',', '.') ?></td>                 
                                <td><?= $tipo['capacidad'] ?> personas</td>                 
                                <td class="descripcion-cell" title="<?= htmlspecialchars($tipo['descripcion']) ?>">
                                    <?= htmlspecialchars($tipo['descripcion']) ?>
                                </td>                 
                                <td>
                                    <div class="habitaciones-stats">
                                        <span class="mini-badge mini-total">
                                            <?= $tipo['total_habitaciones'] ?> Total
                                        </span>
                                        <?php if ($tipo['habitaciones_disponibles'] > 0): ?>
                                            <span class="mini-badge mini-disponible">
                                                <?= $tipo['habitaciones_disponibles'] ?> Disp.
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($tipo['habitaciones_ocupadas'] > 0): ?>
                                            <span class="mini-badge mini-ocupada">
                                                <?= $tipo['habitaciones_ocupadas'] ?> Ocup.
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($tipo['habitaciones_mantenimiento'] > 0): ?>
                                            <span class="mini-badge mini-mantenimiento">
                                                <?= $tipo['habitaciones_mantenimiento'] ?> Mant.
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>                 
                                <td>
                                    <span class="estado-badge <?= $tipo['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                        <?= $tipo['activo'] ? 'ACTIVO' : 'INACTIVO' ?>
                                    </span>
                                    <?php if (!$tipo['activo'] && $tipo['total_habitaciones'] > 0): ?>
                                        <span class="warning-habitaciones">
                                            ⚠️ Con habitaciones
                                        </span>
                                    <?php endif; ?>
                                </td>                 
                                <td class="acciones">                     
                                    <a href="admin_editar_tipo.php?id=<?= $tipo['id_tipo'] ?>" class="btn btn-edit">✏️ Editar</a>                     
                                    <a href="admin_eliminar_tipo.php?id=<?= $tipo['id_tipo'] ?>" 
                                       class="btn btn-delete"
                                       onclick="return confirmarEliminacion('<?= htmlspecialchars($tipo['nombre']) ?>', <?= $tipo['total_habitaciones'] ?>)">
                                       🗑️ Eliminar
                                    </a>                 
                                </td>             
                            </tr>         
                        <?php endwhile; ?>
                    </tbody>     
                </table>
            <?php else: ?>
                <div class="no-tipos">
                    <h3>📋 No hay tipos de habitación registrados</h3>
                    <p>¡Comience creando el primer tipo de habitación para su hotel!</p>
                    <a href="admin_agregar_tipo.php" class="btn btn-success">➕ Crear Primer Tipo</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarEliminacion(nombre, habitaciones) {
            if (habitaciones > 0) {
                return confirm(`⚠️ ATENCIÓN: El tipo "${nombre}" tiene ${habitaciones} habitaciones asignadas.\n\nSi elimina este tipo, también se eliminarán todas las habitaciones asociadas.\n\n¿Está seguro de continuar?`);
            } else {
                return confirm(`¿Está seguro de eliminar el tipo "${nombre}"?\n\nEsta acción no se puede deshacer.`);
            }
        }
        
        function filtrarTabla() {
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroPrecio = document.getElementById('filtro-precio').value;
            const filas = document.querySelectorAll('#tabla-tipos tbody tr');
            
            let filasVisibles = 0;
            
            filas.forEach(fila => {
                const estado = fila.dataset.estado;
                const precio = parseInt(fila.dataset.precio);
                
                let mostrar = true;
                
                // Filtro por estado
                if (filtroEstado && estado !== filtroEstado) {
                    mostrar = false;
                }
                
                // Filtro por precio
                if (filtroPrecio) {
                    if (filtroPrecio === 'bajo' && precio > 50000) {
                        mostrar = false;
                    } else if (filtroPrecio === 'medio' && (precio <= 50000 || precio > 100000)) {
                        mostrar = false;
                    } else if (filtroPrecio === 'alto' && precio <= 100000) {
                        mostrar = false;
                    }
                }
                
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) filasVisibles++;
            });
            
            // Mostrar mensaje si no hay resultados
            let mensajeNoResultados = document.getElementById('no-resultados');
            if (filasVisibles === 0) {
                if (!mensajeNoResultados) {
                    mensajeNoResultados = document.createElement('tr');
                    mensajeNoResultados.id = 'no-resultados';
                    mensajeNoResultados.innerHTML = '<td colspan="8" style="text-align: center; padding: 40px; color: #666;"><h3>No se encontraron tipos</h3><p>Intente ajustar los filtros de búsqueda</p></td>';
                    document.querySelector('#tabla-tipos tbody').appendChild(mensajeNoResultados);
                }
                mensajeNoResultados.style.display = '';
            } else if (mensajeNoResultados) {
                mensajeNoResultados.style.display = 'none';
            }
        }
        
        function limpiarFiltros() {
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-precio').value = '';
            filtrarTabla();
        }
    </script>
</body> 
</html>