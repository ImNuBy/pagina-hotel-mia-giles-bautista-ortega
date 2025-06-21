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

// Obtener filtro de rol desde GET
$rol_filtro = $_GET['rol'] ?? '';

// Construir consulta con filtro de rol
$where_condition = "";
$params = [];
$param_types = "";

if ($rol_filtro) {
    $where_condition = "WHERE u.rol = ?";
    $params[] = $rol_filtro;
    $param_types = "s";
}

// Obtener usuarios con información adicional
$query = "SELECT 
    u.*,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) as reservas_canceladas,
    MAX(r.fecha_reserva) as ultima_reserva
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
    $where_condition
    GROUP BY u.id_usuario, u.nombre, u.email, u.estado, u.fecha_registro, u.rol
    ORDER BY u.fecha_registro DESC";

if ($params) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $usuarios = $stmt->get_result();
} else {
    $usuarios = $conn->query($query);
}

// Obtener estadísticas generales por rol
$stats_query = "SELECT 
    u.rol,
    COUNT(*) as total_usuarios,
    SUM(CASE WHEN u.estado = 'activo' THEN 1 ELSE 0 END) as usuarios_activos,
    SUM(CASE WHEN u.estado = 'inactivo' THEN 1 ELSE 0 END) as usuarios_inactivos,
    SUM(CASE WHEN DATE(u.fecha_registro) = CURDATE() THEN 1 ELSE 0 END) as registros_hoy
    FROM usuarios u
    $where_condition
    GROUP BY u.rol";

if ($params) {
    $stmt_stats = $conn->prepare($stats_query);
    $stmt_stats->bind_param($param_types, ...$params);
    $stmt_stats->execute();
    $stats_result = $stmt_stats->get_result();
} else {
    $stats_result = $conn->query($stats_query);
}

// Procesar estadísticas
$stats = ['total_usuarios' => 0, 'usuarios_activos' => 0, 'usuarios_inactivos' => 0, 'registros_hoy' => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats['total_usuarios'] += $row['total_usuarios'];
    $stats['usuarios_activos'] += $row['usuarios_activos'];
    $stats['usuarios_inactivos'] += $row['usuarios_inactivos'];
    $stats['registros_hoy'] += $row['registros_hoy'];
}

// Obtener conteos por rol para los badges
$roles_count = $conn->query("SELECT 
    rol, 
    COUNT(*) as cantidad,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos
    FROM usuarios 
    GROUP BY rol 
    ORDER BY cantidad DESC");
?> 

<!DOCTYPE html> 
<html lang="es"> 
<head>     
    <link rel="stylesheet" href="css/admin_estilos.css">     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Gestión de Usuarios - Hotel Rivo</title>
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
        .stat-number.total { color: #007bff; }
        .stat-number.activos { color: #28a745; }
        .stat-number.inactivos { color: #dc3545; }
        .stat-number.hoy { color: #ffc107; }
        .usuarios-table {
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
        tr.usuario-inactivo {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .usuario-nombre {
            font-weight: bold;
            font-size: 1.1em;
            color: #007bff;
        }
        .email-cell {
            color: #666;
            font-family: monospace;
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
        .estado-suspendido { background: #fff3cd; color: #856404; }
        .rol-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 8px;
        }
        .rol-admin { background: #dc3545; color: white; }
        .rol-cliente { background: #007bff; color: white; }
        .rol-empleado { background: #28a745; color: white; }
        .rol-manager { background: #6f42c1; color: white; }
        .reservas-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .mini-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .mini-total { background: #e2e3e5; color: #383d41; }
        .mini-confirmada { background: #d4edda; color: #155724; }
        .mini-cancelada { background: #f8d7da; color: #721c24; }
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
        .btn-view { background: #28a745; color: white; }
        .btn-suspend { background: #ffc107; color: #212529; }
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
        .filtros {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-wrap: wrap;
        }
        .filtros select, .filtros input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-box {
            width: 250px;
        }
        .fecha-registro {
            color: #666;
            font-size: 0.9em;
        }
        .ultima-actividad {
            color: #666;
            font-size: 0.85em;
            font-style: italic;
        }
        .no-usuarios {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .usuario-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .usuario-info {
            display: flex;
            align-items: center;
        }
        .filtros-roles {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .roles-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .role-filter-btn {
            padding: 10px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            background: white;
            color: #666;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .role-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .role-filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .role-filter-btn.todos { border-color: #6c757d; }
        .role-filter-btn.todos.active { background: #6c757d; border-color: #6c757d; }
        .role-filter-btn.admin { border-color: #dc3545; }
        .role-filter-btn.admin.active { background: #dc3545; border-color: #dc3545; }
        .role-filter-btn.cliente { border-color: #007bff; }
        .role-filter-btn.cliente.active { background: #007bff; border-color: #007bff; }
        .role-filter-btn.empleado { border-color: #28a745; }
        .role-filter-btn.empleado.active { background: #28a745; border-color: #28a745; }
        .role-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 5px;
        }
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
        .admin-row {
            background: #fff5f5 !important;
            border-left: 4px solid #dc3545;
        }
        .admin-row:hover {
            background: #ffeaa7 !important;
        }
    </style>
</head> 
<body>     
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Usuarios</h1>
            
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Ver reservas</a>
                </div>
                <div>
                    <a href="admin_usuario_crear.php" class="btn btn-success">➕ Crear Usuario</a>
                </div>
            </div>
        </div>

        <!-- Filtros por rol -->
        <div class="filtros-roles">
            <h3>🔍 Filtrar por Rol</h3>
            <div class="roles-buttons">
                <a href="admin_usuarios.php" class="role-filter-btn todos <?= !$rol_filtro ? 'active' : '' ?>">
                    👥 Todos
                    <span class="role-count"><?= array_sum(array_column($roles_count->fetch_all(MYSQLI_ASSOC), 'cantidad')) ?></span>
                </a>
                
                <?php 
                $roles_count->data_seek(0); // Reset pointer
                while ($rol = $roles_count->fetch_assoc()): 
                    $rol_name = $rol['rol'];
                    $rol_icon = match($rol_name) {
                        'admin' => '👑',
                        'cliente' => '🧑‍💼',
                        'empleado' => '👷',
                        'manager' => '📊',
                        default => '👤'
                    };
                ?>
                    <a href="admin_usuarios.php?rol=<?= $rol_name ?>" 
                       class="role-filter-btn <?= $rol_name ?> <?= $rol_filtro == $rol_name ? 'active' : '' ?>">
                        <?= $rol_icon ?> <?= ucfirst($rol_name) ?>
                        <span class="role-count"><?= $rol['cantidad'] ?></span>
                        <?php if ($rol['activos'] != $rol['cantidad']): ?>
                            <small style="opacity: 0.7;">(<?= $rol['activos'] ?> activos)</small>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if ($rol_filtro): ?>
            <div class="filtro-activo">
                <span>📌 Mostrando solo usuarios con rol: <strong><?= ucfirst($rol_filtro) ?></strong></span>
                <a href="admin_usuarios.php" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8em;">❌ Quitar filtro</a>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $stats['total_usuarios'] ?></div>
                <div><?= $rol_filtro ? ucfirst($rol_filtro) . 's' : 'Total Usuarios' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number activos"><?= $stats['usuarios_activos'] ?></div>
                <div>Usuarios Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number inactivos"><?= $stats['usuarios_inactivos'] ?></div>
                <div>Usuarios Inactivos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number hoy"><?= $stats['registros_hoy'] ?></div>
                <div>Registros Hoy</div>
            </div>
        </div>
        
        <!-- Filtros adicionales -->
        <div class="filtros">
            <label><strong>Filtrar por:</strong></label>
            <select id="filtro-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="activo">Solo activos</option>
                <option value="inactivo">Solo inactivos</option>
                <option value="suspendido">Solo suspendidos</option>
            </select>
            
            <select id="filtro-actividad" onchange="filtrarTabla()">
                <option value="">Toda la actividad</option>
                <option value="con-reservas">Con reservas</option>
                <option value="sin-reservas">Sin reservas</option>
                <option value="activos-recientes">Activos últimos 30 días</option>
            </select>
            
            <input type="text" id="buscar-usuario" placeholder="Buscar por nombre o email..." class="search-box" oninput="filtrarTabla()">
            
            <button onclick="limpiarFiltros()" class="btn btn-secondary">🔄 Limpiar</button>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="usuarios-table">
            <div class="table-header">
                <h3>👥 Lista de Usuarios <?= $rol_filtro ? '- ' . ucfirst($rol_filtro) . 's' : '' ?></h3>
                <span><?= $usuarios->num_rows ?> usuarios encontrados</span>
            </div>
            
            <?php if ($usuarios->num_rows > 0): ?>
                <table id="tabla-usuarios">         
                    <thead>
                        <tr>             
                            <th>Usuario</th>             
                            <th>Email</th>             
                            <th>Rol</th>             
                            <th>Estado</th>             
                            <th>Reservas</th>             
                            <th>Registro</th>             
                            <th>Última Actividad</th>             
                            <th>Acciones</th>         
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($usuario = $usuarios->fetch_assoc()): ?>             
                            <tr class="<?= $usuario['estado'] !== 'activo' ? 'usuario-inactivo' : '' ?> <?= $usuario['rol'] == 'admin' ? 'admin-row' : '' ?>" 
                                data-estado="<?= $usuario['estado'] ?>"
                                data-rol="<?= $usuario['rol'] ?>"
                                data-nombre="<?= strtolower($usuario['nombre']) ?>"
                                data-email="<?= strtolower($usuario['email']) ?>"
                                data-reservas="<?= $usuario['total_reservas'] ?>">                 
                                <td>
                                    <div class="usuario-info">
                                        <div class="usuario-avatar" style="background: <?= $usuario['rol'] == 'admin' ? '#dc3545' : ($usuario['rol'] == 'empleado' ? '#28a745' : '#007bff') ?>">
                                            <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="usuario-nombre"><?= htmlspecialchars($usuario['nombre']) ?></div>
                                            <div class="fecha-registro">
                                                ID: <?= $usuario['id_usuario'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>                 
                                <td class="email-cell"><?= htmlspecialchars($usuario['email']) ?></td>                 
                                <td>
                                    <span class="rol-badge rol-<?= $usuario['rol'] ?>">
                                        <?php 
                                        $rol_icons = [
                                            'admin' => '👑',
                                            'cliente' => '🧑‍💼',
                                            'empleado' => '👷',
                                            'manager' => '📊'
                                        ];
                                        echo ($rol_icons[$usuario['rol']] ?? '👤') . ' ' . ucfirst($usuario['rol']);
                                        ?>
                                    </span>
                                </td>                 
                                <td>
                                    <span class="estado-badge estado-<?= $usuario['estado'] ?>">
                                        <?= ucfirst($usuario['estado']) ?>
                                    </span>
                                </td>                 
                                <td>
                                    <?php if ($usuario['rol'] == 'cliente'): ?>
                                        <div class="reservas-stats">
                                            <span class="mini-badge mini-total">
                                                <?= $usuario['total_reservas'] ?> Total
                                            </span>
                                            <?php if ($usuario['reservas_confirmadas'] > 0): ?>
                                                <span class="mini-badge mini-confirmada">
                                                    <?= $usuario['reservas_confirmadas'] ?> Conf.
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($usuario['reservas_canceladas'] > 0): ?>
                                                <span class="mini-badge mini-cancelada">
                                                    <?= $usuario['reservas_canceladas'] ?> Canc.
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #666; font-style: italic;">N/A</span>
                                    <?php endif; ?>
                                </td>                 
                                <td class="fecha-registro">
                                    <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?><br>
                                    <small><?= date('H:i', strtotime($usuario['fecha_registro'])) ?></small>
                                </td>                 
                                <td class="ultima-actividad">
                                    <?php if ($usuario['ultima_reserva'] && $usuario['rol'] == 'cliente'): ?>
                                        Última reserva:<br>
                                        <?= date('d/m/Y', strtotime($usuario['ultima_reserva'])) ?>
                                    <?php else: ?>
                                        Sin actividad
                                    <?php endif; ?>
                                </td>                 
                                <td class="acciones">                     
                                    <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-view" title="Ver perfil">👁️</a>
                                    
                                    <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): // No editar a uno mismo ?>
                                        <a href="admin_usuario_editar.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-edit" title="Editar">✏️</a>
                                        
                                        <?php if ($usuario['estado'] === 'activo' && $usuario['rol'] != 'admin'): ?>
                                            <a href="admin_usuario_suspender.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-suspend" title="Suspender" onclick="return confirm('¿Suspender a <?= htmlspecialchars($usuario['nombre']) ?>?')">⏸️</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($usuario['rol'] != 'admin'): // No eliminar otros admins ?>
                                            <a href="admin_usuario_eliminar.php?id=<?= $usuario['id_usuario'] ?>" 
                                               class="btn btn-delete" title="Eliminar"
                                               onclick="return confirmarEliminacion('<?= htmlspecialchars($usuario['nombre']) ?>', <?= $usuario['total_reservas'] ?>, '<?= $usuario['rol'] ?>')">🗑️</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.8em;">Tú mismo</span>
                                    <?php endif; ?>
                                </td>             
                            </tr>         
                        <?php endwhile; ?>
                    </tbody>     
                </table>
            <?php else: ?>
                <div class="no-usuarios">
                    <h3>👥 No hay usuarios registrados</h3>
                    <p>Los usuarios aparecerán aquí cuando se registren en el sistema.</p>
                    <?php if ($rol_filtro): ?>
                        <p><a href="admin_usuarios.php" class="btn btn-primary">Ver todos los usuarios</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarEliminacion(nombre, reservas, rol) {
            if (rol === 'admin') {
                alert('⚠️ No se puede eliminar a otros administradores por seguridad.');
                return false;
            }
            
            if (reservas > 0) {
                return confirm(`⚠️ ATENCIÓN: ${nombre} tiene ${reservas} reserva(s) asociada(s).\n\nSi elimina este usuario, también se eliminarán todas sus reservas.\n\n¿Está seguro de continuar?`);
            } else {
                return confirm(`¿Está seguro de eliminar al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`);
            }
        }
        
        function filtrarTabla() {
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroActividad = document.getElementById('filtro-actividad').value;
            const buscarTexto = document.getElementById('buscar-usuario').value.toLowerCase();
            const filas = document.querySelectorAll('#tabla-usuarios tbody tr');
            
            let filasVisibles = 0;
            
            filas.forEach(fila => {
                const estado = fila.dataset.estado;
                const nombre = fila.dataset.nombre;
                const email = fila.dataset.email;
                const rol = fila.dataset.rol;
                const reservas = parseInt(fila.dataset.reservas);
                
                let mostrar = true;
                
                // Filtro por estado
                if (filtroEstado && estado !== filtroEstado) {
                    mostrar = false;
                }
                
                // Filtro por actividad (solo para clientes)
                if (filtroActividad && rol === 'cliente') {
                    if (filtroActividad === 'con-reservas' && reservas === 0) {
                        mostrar = false;
                    } else if (filtroActividad === 'sin-reservas' && reservas > 0) {
                        mostrar = false;
                    }
                }
                
                // Búsqueda por texto
                if (buscarTexto && !nombre.includes(buscarTexto) && !email.includes(buscarTexto)) {
                    mostrar = false;
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
                    mensajeNoResultados.innerHTML = '<td colspan="8" style="text-align: center; padding: 40px; color: #666;"><h3>No se encontraron usuarios</h3><p>Intente ajustar los filtros de búsqueda</p></td>';
                    document.querySelector('#tabla-usuarios tbody').appendChild(mensajeNoResultados);
                }
                mensajeNoResultados.style.display = '';
            } else if (mensajeNoResultados) {
                mensajeNoResultados.style.display = 'none';
            }
            
            // Actualizar contadores en tiempo real
        function actualizarContadores() {
            const filas = document.querySelectorAll('#tabla-usuarios tbody tr:not(#no-resultados)');
            const contador = document.querySelector('.table-header span');
            const visibles = Array.from(filas).filter(fila => fila.style.display !== 'none').length;
            contador.textContent = `${visibles} usuarios mostrados`;
        }
        
        // Ejecutar actualización después de cada filtro
        document.getElementById('filtro-estado').addEventListener('change', actualizarContadores);
        document.getElementById('filtro-actividad').addEventListener('change', actualizarContadores);
        document.getElementById('buscar-usuario').addEventListener('input', actualizarContadores);
        
        // Destacar filas de administradores
        document.addEventListener('DOMContentLoaded', function() {
            const adminRows = document.querySelectorAll('.admin-row');
            adminRows.forEach(row => {
                row.title = "👑 Usuario Administrador";
            });
        });
        
        // Función para cambiar rol (si es necesario)
        function cambiarRol(userId, nuevoRol) {
            if (confirm(`¿Está seguro de cambiar el rol de este usuario a "${nuevoRol}"?`)) {
                // Aquí podrías hacer una llamada AJAX para cambiar el rol
                window.location.href = `admin_cambiar_rol.php?id=${userId}&rol=${nuevoRol}`;
            }
        }
        
        // Filtro rápido por estado desde la URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('estado')) {
            document.getElementById('filtro-estado').value = urlParams.get('estado');
            filtrarTabla();
        }
        
        // Resaltar usuarios recién registrados (últimas 24 horas)
        document.addEventListener('DOMContentLoaded', function() {
            const ahora = new Date();
            const hace24h = new Date(ahora.getTime() - (24 * 60 * 60 * 1000));
            
            document.querySelectorAll('#tabla-usuarios tbody tr').forEach(fila => {
                const fechaRegistro = fila.querySelector('.fecha-registro').textContent;
                // Esta función podría mejorarse con una fecha más precisa del servidor
                if (fechaRegistro.includes(ahora.toLocaleDateString('es-ES'))) {
                    fila.style.border = '2px solid #28a745';
                    fila.title = '🆕 Usuario registrado hoy';
                }
            });
        });
    </script>
</body> 
</html> 