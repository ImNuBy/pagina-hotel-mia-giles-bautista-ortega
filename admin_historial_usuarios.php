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

// Obtener usuarios con estadísticas de reservas
$usuarios = fetch_all_or_default("SELECT 
    u.id_usuario,
    u.nombre,
    u.email,
    u.telefono,
    u.fecha_registro,
    u.rol,
    COUNT(r.id_reserva) as total_reservas,
    SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
    SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as reservas_completadas,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as total_gastado,
    MAX(r.fecha_reserva) as ultima_reserva
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE u.rol = 'cliente'
    GROUP BY u.id_usuario, u.nombre, u.email, u.telefono, u.fecha_registro, u.rol
    ORDER BY total_reservas DESC, u.nombre ASC");

// Estadísticas generales
$stats = fetch_or_default("SELECT 
    COUNT(DISTINCT u.id_usuario) as total_clientes,
    COUNT(DISTINCT CASE WHEN r.id_reserva IS NOT NULL THEN u.id_usuario END) as clientes_con_reservas,
    AVG(reservas_por_cliente.total) as promedio_reservas_por_cliente,
    SUM(CASE WHEN p.estado = 'confirmado' THEN p.monto ELSE 0 END) as ingresos_totales
    FROM usuarios u
    LEFT JOIN reservas r ON u.id_usuario = r.id_usuario
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    LEFT JOIN (
        SELECT id_usuario, COUNT(*) as total 
        FROM reservas 
        GROUP BY id_usuario
    ) reservas_por_cliente ON u.id_usuario = reservas_por_cliente.id_usuario
    WHERE u.rol = 'cliente'", 
    ['total_clientes' => 0, 'clientes_con_reservas' => 0, 'promedio_reservas_por_cliente' => 0, 'ingresos_totales' => 0]);

// Filtros
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? 'todos';

if ($filtro_busqueda) {
    $busqueda_segura = $conn->real_escape_string($filtro_busqueda);
    $usuarios = array_filter($usuarios, function($usuario) use ($busqueda_segura) {
        return stripos($usuario['nombre'], $busqueda_segura) !== false || 
               stripos($usuario['email'], $busqueda_segura) !== false;
    });
}

if ($filtro_tipo !== 'todos') {
    switch ($filtro_tipo) {
        case 'activos':
            $usuarios = array_filter($usuarios, function($u) { return $u['total_reservas'] > 0; });
            break;
        case 'frecuentes':
            $usuarios = array_filter($usuarios, function($u) { return $u['total_reservas'] > 2; });
            break;
        case 'nuevos':
            $usuarios = array_filter($usuarios, function($u) { return $u['total_reservas'] == 0; });
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Usuarios - Hotel Rivo</title>
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
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
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
        .btn-info { background: #17a2b8; color: white; }
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
            background: linear-gradient(90deg, #6c5ce7, #a29bfe);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
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
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-weight: bold;
            color: #333;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #6c5ce7;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-content {
            padding: 25px;
        }
        .usuarios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .usuario-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        .usuario-card:hover {
            border-color: #6c5ce7;
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.15);
            transform: translateY(-3px);
        }
        .usuario-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .usuario-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.2em;
        }
        .usuario-email {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .usuario-fecha {
            color: #999;
            font-size: 0.8em;
        }
        .usuario-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .stat-item-number {
            font-weight: bold;
            font-size: 1.2em;
            color: #6c5ce7;
        }
        .stat-item-label {
            font-size: 0.8em;
            color: #666;
            margin-top: 2px;
        }
        .usuario-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-frecuente { background: #d4edda; color: #155724; }
        .badge-activo { background: #cce5ff; color: #004085; }
        .badge-nuevo { background: #fff3cd; color: #856404; }
        .badge-vip { background: #f8d7da; color: #721c24; }
        .actions-user {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .no-usuarios {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        @media (max-width: 768px) {
            .usuarios-grid {
                grid-template-columns: 1fr;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
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
            <h1>👥 Historial de Usuarios</h1>
            <div class="header-subtitle">
                Gestión completa de clientes y su historial de reservas
            </div>
            <div class="actions-header">
                <div>
                    <a href="admin_dashboard.php" class="btn btn-secondary">← Volver al panel</a>
                    <a href="admin_reservas.php" class="btn btn-secondary">📅 Reservas</a>
                    <a href="admin_usuarios.php" class="btn btn-secondary">👤 Gestión Usuarios</a>
                </div>
                <div>
                    <button onclick="exportarUsuarios()" class="btn btn-success">📥 Exportar Lista</button>
                    <button onclick="actualizarDatos()" class="btn btn-primary">🔄 Actualizar</button>
                </div>
            </div>
        </div>

        <!-- Estadísticas generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_clientes'] ?></div>
                <div class="stat-label">Total Clientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['clientes_con_reservas'] ?></div>
                <div class="stat-label">Con Reservas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['promedio_reservas_por_cliente'], 1) ?></div>
                <div class="stat-label">Reservas Promedio</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($stats['ingresos_totales'], 0, ',', '.') ?></div>
                <div class="stat-label">Ingresos Totales</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <h3 style="margin: 0 0 20px 0; color: #333;">🔍 Filtros de Búsqueda</h3>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="busqueda">Buscar cliente:</label>
                    <input type="text" id="busqueda" name="busqueda" 
                           placeholder="Nombre o email..." 
                           value="<?= htmlspecialchars($filtro_busqueda) ?>">
                </div>
                <div class="filter-group">
                    <label for="tipo">Tipo de cliente:</label>
                    <select id="tipo" name="tipo">
                        <option value="todos" <?= $filtro_tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="activos" <?= $filtro_tipo === 'activos' ? 'selected' : '' ?>>Con reservas</option>
                        <option value="frecuentes" <?= $filtro_tipo === 'frecuentes' ? 'selected' : '' ?>>Frecuentes (3+ reservas)</option>
                        <option value="nuevos" <?= $filtro_tipo === 'nuevos' ? 'selected' : '' ?>>Sin reservas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">🔎 Filtrar</button>
                </div>
                <?php if ($filtro_busqueda || $filtro_tipo !== 'todos'): ?>
                    <div class="filter-group">
                        <a href="?" class="btn btn-secondary">🗑️ Limpiar</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de usuarios -->
        <div class="main-content">
            <div class="section-header">
                <span>📋 Lista de Clientes</span>
                <span><?= count($usuarios) ?> clientes encontrados</span>
            </div>
            <div class="section-content">
                <?php if (empty($usuarios)): ?>
                    <div class="no-usuarios">
                        <h3>👥 No se encontraron clientes</h3>
                        <p>No hay clientes que coincidan con los filtros seleccionados.</p>
                        <a href="?" class="btn btn-primary">Ver todos los clientes</a>
                    </div>
                <?php else: ?>
                    <div class="usuarios-grid">
                        <?php foreach ($usuarios as $usuario): ?>
                            <div class="usuario-card" onclick="verHistorialUsuario(<?= $usuario['id_usuario'] ?>)">
                                <div class="usuario-header">
                                    <div class="usuario-info">
                                        <h3><?= htmlspecialchars($usuario['nombre']) ?></h3>
                                        <div class="usuario-email"><?= htmlspecialchars($usuario['email']) ?></div>
                                        <div class="usuario-fecha">
                                            Cliente desde: <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="usuario-badges">
                                    <?php if ($usuario['total_reservas'] >= 3): ?>
                                        <span class="badge badge-frecuente">Cliente Frecuente</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($usuario['total_reservas'] > 0): ?>
                                        <span class="badge badge-activo">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-nuevo">Nuevo</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($usuario['total_gastado'] > 500000): ?>
                                        <span class="badge badge-vip">Cliente VIP</span>
                                    <?php endif; ?>
                                </div>

                                <div class="usuario-stats">
                                    <div class="stat-item">
                                        <div class="stat-item-number"><?= $usuario['total_reservas'] ?></div>
                                        <div class="stat-item-label">Reservas</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-number"><?= $usuario['reservas_confirmadas'] ?></div>
                                        <div class="stat-item-label">Confirmadas</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-number">$<?= number_format($usuario['total_gastado'], 0, ',', '.') ?></div>
                                        <div class="stat-item-label">Total Gastado</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-number">
                                            <?= $usuario['ultima_reserva'] ? date('d/m/Y', strtotime($usuario['ultima_reserva'])) : 'N/A' ?>
                                        </div>
                                        <div class="stat-item-label">Última Reserva</div>
                                    </div>
                                </div>

                                <div class="actions-user">
                                    <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>" 
                                       class="btn btn-primary btn-small"
                                       onclick="event.stopPropagation()">
                                        📋 Ver Historial
                                    </a>
                                    <a href="admin_usuario_ver.php?id=<?= $usuario['id_usuario'] ?>" 
                                       class="btn btn-info btn-small"
                                       onclick="event.stopPropagation()">
                                        👤 Ver Perfil
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Función para ver historial de usuario
        function verHistorialUsuario(idUsuario) {
            window.location.href = `admin_historial_usuario.php?id=${idUsuario}`;
        }

        // Función para actualizar datos
        function actualizarDatos() {
            location.reload();
        }

        // Función para exportar lista de usuarios
        function exportarUsuarios() {
            const fechaActual = new Date().toLocaleDateString('es-CO');
            const nombreArchivo = `clientes_hotel_rivo_${fechaActual.replace(/\//g, '_')}.txt`;
            
            let contenido = `LISTA DE CLIENTES - HOTEL RIVO\n`;
            contenido += `Reporte generado: ${fechaActual}\n`;
            contenido += `${'='.repeat(50)}\n\n`;
            
            contenido += `RESUMEN:\n`;
            contenido += `- Total de clientes: <?= $stats['total_clientes'] ?>\n`;
            contenido += `- Clientes con reservas: <?= $stats['clientes_con_reservas'] ?>\n`;
            contenido += `- Promedio reservas por cliente: <?= number_format($stats['promedio_reservas_por_cliente'], 1) ?>\n`;
            contenido += `- Ingresos totales: $<?= number_format($stats['ingresos_totales'], 0, ',', '.') ?>\n\n`;
            
            contenido += `LISTADO DETALLADO:\n`;
            contenido += `${'='.repeat(50)}\n`;
            
            <?php foreach ($usuarios as $index => $usuario): ?>
                contenido += `\n${<?= $index + 1 ?>}. <?= addslashes($usuario['nombre']) ?>\n`;
                contenido += `   Email: <?= addslashes($usuario['email']) ?>\n`;
                contenido += `   Cliente desde: <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>\n`;
                contenido += `   Total reservas: <?= $usuario['total_reservas'] ?>\n`;
                contenido += `   Reservas confirmadas: <?= $usuario['reservas_confirmadas'] ?>\n`;
                contenido += `   Total gastado: $<?= number_format($usuario['total_gastado'], 0, ',', '.') ?>\n`;
                contenido += `   Última reserva: <?= $usuario['ultima_reserva'] ? date('d/m/Y', strtotime($usuario['ultima_reserva'])) : 'N/A' ?>\n`;
            <?php endforeach; ?>
            
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
            
            mostrarNotificacion('📥 Lista de clientes exportada exitosamente', 'success');
        }

        // Sistema de notificaciones
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const existentes = document.querySelectorAll('.notificacion-usuarios');
            if (existentes.length >= 3) existentes[0].remove();
            
            const notif = document.createElement('div');
            notif.className = 'notificacion-usuarios';
            
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
                        if (notif.parentNode) notif.remove();
                    }, 300);
                }
            }, 3000);
        }

        // Búsqueda en tiempo real
        document.getElementById('busqueda').addEventListener('input', function() {
            const valor = this.value.toLowerCase();
            const tarjetas = document.querySelectorAll('.usuario-card');
            
            tarjetas.forEach(tarjeta => {
                const nombre = tarjeta.querySelector('h3').textContent.toLowerCase();
                const email = tarjeta.querySelector('.usuario-email').textContent.toLowerCase();
                
                if (nombre.includes(valor) || email.includes(valor)) {
                    tarjeta.style.display = 'block';
                } else {
                    tarjeta.style.display = 'none';
                }
            });
        });

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elementos = document.querySelectorAll('.stat-card, .filter-section, .main-content');
            elementos.forEach((elemento, index) => {
                elemento.style.opacity = '0';
                elemento.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    elemento.style.transition = 'all 0.6s ease';
                    elemento.style.opacity = '1';
                    elemento.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animar tarjetas de usuarios
            setTimeout(() => {
                const tarjetas = document.querySelectorAll('.usuario-card');
                tarjetas.forEach((tarjeta, index) => {
                    tarjeta.style.opacity = '0';
                    tarjeta.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        tarjeta.style.transition = 'all 0.6s ease';
                        tarjeta.style.opacity = '1';
                        tarjeta.style.transform = 'translateY(0)';
                    }, index * 50);
                });
            }, 500);
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
                .btn, .actions-header, .filter-section { display: none !important; }
                .usuario-card { page-break-inside: avoid; }
                body { background: white !important; }
                .header { background: #6c5ce7 !important; -webkit-print-color-adjust: exact; }
            }
        `;
        document.head.appendChild(style);

        console.log('👥 Sistema de historial de usuarios inicializado correctamente');
    </script>
</body>
</html>