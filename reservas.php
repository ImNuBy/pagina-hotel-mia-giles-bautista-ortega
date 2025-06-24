<?php
session_start();

// Simplified session check (no redirection)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Mock user ID for simplicity
    $_SESSION['user_name'] = 'Huésped'; // Mock user name
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

require_once 'pricing_system.php';
$pricing = new HotelPricingSystem($conn);

function getAvailableRooms($conn, $id_tipo) {
    $query = "SELECT COUNT(*) as disponibles 
              FROM habitaciones h 
              WHERE h.id_tipo = ? 
              AND h.id_habitacion NOT IN (
                  SELECT id_habitacion 
                  FROM reservas 
                  WHERE estado IN ('pendiente', 'confirmada') 
                  AND CURDATE() <= fecha_salida
              )";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id_tipo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['disponibles'];
}

function fetchRoomTypes($conn, $pricing) {
    $query = "SELECT id_tipo, nombre, precio_noche, capacidad, descripcion FROM tipos_habitacion WHERE activo = 1";
    $result = $conn->query($query);
    $room_types = [];
    
    while ($row = $result->fetch_assoc()) {
        $fecha_actual = date('Y-m-d');
        $precio_data = $pricing->getPrecioEfectivo($row['id_tipo'], $fecha_actual); // Use current date as reference
        $row['precio_effective'] = $precio_data['precio_efectivo'] ?? $row['precio_noche'];
        $row['tarifa_nombre'] = $precio_data['tarifa_nombre'] ?? 'Tarifa Estándar';
        $row['descuento'] = $precio_data['descuento'] ?? 0;
        $row['disponibles'] = getAvailableRooms($conn, $row['id_tipo']);
        $room_types[] = $row;
    }
    
    return $room_types;
}

$room_types = fetchRoomTypes($conn, $pricing);
$offer = [
    'nombre' => 'Oferta Verano 2025',
    'descripcion' => 'Descuento especial del 10% en reservas anticipadas',
    'descuento' => 10,
    'fecha_fin' => date('Y-m-d', strtotime('+30 days'))
];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Huésped');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas - Hotel Rivo | Punta Mogotes</title>
    <meta name="description" content="Reserve su estadía en Hotel Rivo. Sistema de reservas online con confirmación inmediata.">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; background-color: #fafafa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
        .navbar { position: fixed; width: 100%; top: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); border-bottom: 1px solid rgba(0,0,0,0.1); z-index: 999; transition: all 0.3s ease; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; }
        .logo { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #c9a961; display: flex; align-items: center; gap: 0.5rem; }
        .nav-menu { display: flex; align-items: center; gap: 2rem; }
        .nav-link { text-decoration: none; color: #4a4a4a; font-weight: 500; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease; position: relative; padding: 0.5rem 0; }
        .nav-link:hover, .nav-link.active { color: #c9a961; }
        .nav-link::after { content: ''; position: absolute; width: 0; height: 2px; bottom: 0; left: 50%; background: linear-gradient(90deg, #c9a961, #dbb971); transition: all 0.3s ease; transform: translateX(-50%); }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .user-welcome { color: #c9a961; font-weight: 500; font-size: 0.9rem; }
        .btn-login { background: transparent; border: 2px solid #c9a961; color: #c9a961; padding: 0.6rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; margin-left: 1rem; }
        .btn-login:hover { background: #c9a961; color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(201, 169, 97, 0.3); }
        .hamburger { display: none; flex-direction: column; cursor: pointer; gap: 4px; }
        .hamburger span { width: 25px; height: 3px; background: #333; transition: 0.3s; border-radius: 2px; }
        .reservas-hero { height: 60vh; background: linear-gradient(45deg, rgba(201, 169, 97, 0.8), rgba(219, 185, 113, 0.8)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><rect fill="%23f0f0f0" width="1200" height="800"/><text x="600" y="400" text-anchor="middle" font-size="48" fill="%23666">Hotel Background</text></svg>') center/cover; display: flex; align-items: center; justify-content: center; text-align: center; color: white; position: relative; margin-top: 70px; }
        .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.3); }
        .reservas-hero-content { position: relative; z-index: 1; }
        .reservas-hero h1 { font-family: 'Playfair Display', serif; font-size: 3rem; font-weight: 700; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .reservas-hero p { font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.95; }
        .hero-divider { width: 80px; height: 3px; background: white; margin: 2rem auto; border-radius: 2px; }
        .oferta-destacada { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); padding: 1rem 2rem; border-radius: 25px; display: inline-block; margin-top: 1rem; }
        .oferta-badge { background: #ff4757; color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-right: 1rem; }
        .reservas-main { padding: 4rem 0; min-height: 60vh; }
        .reservas-content { display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; margin-top: 2rem; }
        .reserva-form-container { background: white; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .form-header { background: linear-gradient(135deg, #c9a961, #dbb971); color: white; padding: 2rem; text-align: center; }
        .form-header h2 { font-family: 'Playfair Display', serif; font-size: 2rem; margin-bottom: 0.5rem; }
        .form-header p { opacity: 0.9; }
        .reserva-form { padding: 2rem; }
        .form-section { margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid #e9ecef; }
        .form-section:last-of-type { border-bottom: none; }
        .form-section h3 { font-size: 1.3rem; color: #333; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: #333; }
        .form-group input, .form-group textarea { padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #c9a961; box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1); }
        .noches-display { margin-top: 1rem; text-align: center; font-size: 1.1rem; color: #c9a961; font-weight: 600; }
        .custom-select-container { position: relative; display: flex; align-items: center; }
        .custom-select { width: 100%; padding: 1rem 3rem 1rem 1rem; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; font-family: inherit; background: white; cursor: pointer; transition: all 0.3s ease; appearance: none; -webkit-appearance: none; -moz-appearance: none; }
        .custom-select:focus { outline: none; border-color: #c9a961; box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1); }
        .custom-select:hover { border-color: #c9a961; }
        .select-arrow { position: absolute; right: 1rem; pointer-events: none; transition: transform 0.3s ease; }
        .custom-select:focus + .select-arrow { transform: rotate(180deg); }
        .custom-select option { padding: 0.8rem; font-size: 1rem; background: white; color: #333; }
        .custom-select option:hover { background: #f8f9fa; }
        .custom-select option:disabled { color: #999; background: #f5f5f5; }
        .habitacion-detalles { margin-top: 1.5rem; opacity: 0; transform: translateY(-20px); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); max-height: 0; overflow: hidden; }
        .habitacion-detalles.show { opacity: 1; transform: translateY(0); max-height: 1000px; animation: slideInDown 0.5s ease-out; }
        @keyframes slideInDown { from { opacity: 0; transform: translateY(-30px); max-height: 0; } to { opacity: 1; transform: translateY(0); max-height: 1000px; } }
        .detalles-card { background: linear-gradient(135deg, #f8f9fa, #ffffff); border: 2px solid #e9ecef; border-radius: 16px; padding: 2rem; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .detalles-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #c9a961, #dbb971); }
        .detalles-card:hover { border-color: #c9a961; box-shadow: 0 8px 25px rgba(201, 169, 97, 0.15); transform: translateY(-2px); }
        .detalles-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
        .detalles-header h4 { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #333; margin: 0; }
        .detalle-precio { text-align: right; }
        .detalle-precio .precio-numero { font-size: 1.6rem; font-weight: 700; color: #c9a961; display: block; }
        .detalle-precio .precio-periodo { font-size: 0.9rem; color: #666; }
        .detalle-descripcion { color: #666; margin-bottom: 1.5rem; line-height: 1.6; font-style: italic; padding: 1rem; background: rgba(201, 169, 97, 0.05); border-radius: 8px; border-left: 4px solid #c9a961; }
        .detalles-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .detalles-info .info-item { background: white; padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.9rem; color: #666; border: 1px solid #e9ecef; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; }
        .detalles-info .info-item:hover { border-color: #c9a961; background: #fff8e1; transform: translateY(-2px); }
        .info-icon { font-size: 1.2rem; }
        .detalles-caracteristicas { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
        .caracteristica { background: linear-gradient(135deg, #c9a961, #dbb971); color: white; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; transition: all 0.3s ease; }
        .caracteristica:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(201, 169, 97, 0.3); }
        .detalles-galeria { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .precio-resumen { background: linear-gradient(135deg, #f8f9fa, #ffffff); border-radius: 12px; padding: 1.5rem; margin-top: 1rem; border: 1px solid #e9ecef; }
        .precio-resumen h3 { font-size: 1.2rem; margin-bottom: 1rem; color: #333; display: flex; align-items: center; gap: 0.5rem; }
        .precio-breakdown { display: flex; flex-direction: column; gap: 0.8rem; }
        .precio-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; font-size: 1rem; }
        .precio-item.descuento { color: #28a745; font-weight: 600; }
        .precio-item.total { border-top: 2px solid #c9a961; font-weight: 700; font-size: 1.2rem; color: #c9a961; margin-top: 0.5rem; padding-top: 1rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; border: none; border-radius: 30px; font-weight: 600; font-size: 1rem; text-decoration: none; cursor: pointer; transition: all 0.3s ease; text-align: center; justify-content: center; }
        .btn-primary { background: linear-gradient(135deg, #c9a961, #dbb971); color: white; box-shadow: 0 4px 15px rgba(201, 169, 97, 0.3); }
        .btn-primary:hover { background: linear-gradient(135deg, #b8985a, #ca9f5f); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201, 169, 97, 0.4); }
        .btn-secondary { background: transparent; border: 2px solid #c9a961; color: #c9a961; }
        .btn-secondary:hover { background: #c9a961; color: white; transform: translateY(-2px); }
        .btn-large { padding: 1.2rem 2.5rem; font-size: 1.1rem; }
        .btn-full { width: 100%; }
        .info-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        .info-card { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .info-card:hover { transform: translateY(-3px); }
        .info-card h3 { font-size: 1.1rem; margin-bottom: 1rem; color: #333; display: flex; align-items: center; gap: 0.5rem; }
        .info-card ul { list-style: none; padding: 0; }
        .info-card ul li { padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; color: #666; transition: color 0.3s ease; }
        .info-card ul li:last-child { border-bottom: none; }
        .info-card ul li:hover { color: #c9a961; }
        .info-card ul li strong { color: #333; }
        .oferta-card { background: linear-gradient(135deg, #fff8e1, #f3e5ab); border: 2px solid #c9a961; position: relative; overflow: hidden; }
        .oferta-card::before { content: '🔥'; position: absolute; top: -10px; right: -10px; font-size: 3rem; opacity: 0.1; }
        .oferta-card h4 { color: #c9a961; margin-bottom: 0.5rem; font-size: 1.2rem; }
        .oferta-descuento { background: linear-gradient(135deg, #c9a961, #dbb971); color: white; padding: 0.6rem 1.2rem; border-radius: 25px; display: inline-block; font-weight: 700; font-size: 1.1rem; box-shadow: 0 4px 8px rgba(201, 169, 97, 0.3); margin: 1rem 0; }
        .contacto-rapido { text-align: center; }
        .contacto-rapido .btn { width: 100%; margin-bottom: 0.5rem; }
        .footer-simple { background: #333; color: white; padding: 2rem 0; margin-top: 4rem; }
        .footer-simple .footer-content { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .footer-simple .footer-logo { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .footer-simple .footer-links { display: flex; gap: 2rem; }
        .footer-simple .footer-links a { color: #ccc; text-decoration: none; transition: color 0.3s ease; }
        .footer-simple .footer-links a:hover { color: #c9a961; }
        .footer-bottom { border-top: 1px solid #555; padding-top: 1rem; text-align: center; color: #ccc; font-size: 0.9rem; }
        @media (max-width: 768px) {
            .hamburger { display: flex; }
            .nav-menu { position: fixed; left: -100%; top: 70px; flex-direction: column; background-color: rgba(255, 255, 255, 0.98); width: 100%; text-align: center; transition: 0.3s; box-shadow: 0 10px 27px rgba(0, 0, 0, 0.05); padding: 2rem 0; gap: 1rem; }
            .nav-menu.active { left: 0; }
            .reservas-hero h1 { font-size: 2rem; }
            .reservas-content { grid-template-columns: 1fr; gap: 2rem; }
            .form-row { grid-template-columns: 1fr; gap: 1rem; }
            .reserva-form { padding: 1.5rem; }
            .form-header { padding: 1.5rem; }
            .detalles-info { grid-template-columns: 1fr; }
            .footer-simple .footer-content { flex-direction: column; gap: 1rem; text-align: center; }
            .footer-simple .footer-links { flex-direction: column; gap: 1rem; }
        }
        @media (max-width: 480px) {
            .container { padding: 0 1rem; }
            .reservas-hero { height: 50vh; }
            .reservas-hero h1 { font-size: 1.8rem; }
            .nav-container { padding: 1rem; }
            .detalles-caracteristicas { flex-direction: column; align-items: stretch; }
            .caracteristica { text-align: center; }
        }
    </style>
</head>
<body>
    <header class="navbar" id="navbar">
        <div class="nav-container">
            <div class="logo">
                <span class="logo-icon">🏨</span>
                <a href="index.php" style="text-decoration: none; color: inherit;">HOTEL RIVO</a>
            </div>
            <nav class="nav-menu" id="nav-menu">
                <a href="index.php" class="nav-link">Inicio</a>
                <a href="index.php#habitaciones" class="nav-link">Habitaciones</a>
                <a href="index.php#servicios" class="nav-link">Servicios</a>
                <a href="index.php#contacto" class="nav-link">Contacto</a>
                <a href="reservas.php" class="nav-link active">Reservas</a>
                <span class="user-welcome">Hola, <?= $user_name ?></span>
                <a href="logout.php" class="btn-login">Cerrar Sesión</a>
            </nav>
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <section class="reservas-hero">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="reservas-hero-content">
                <h1>Reserva tu Estadía</h1>
                <p>Disfruta de una experiencia única en Punta Mogotes</p>
                <div class="hero-divider"></div>
                <div class="oferta-destacada">
                    <span class="oferta-badge">🔥 Oferta Especial</span>
                    <span id="oferta_texto"><?= $offer['nombre'] ?></span>
                </div>
            </div>
        </div>
    </section>

    <main class="reservas-main">
        <div class="container">
            <div class="reservas-content">
                <div class="reserva-form-container">
                    <div class="form-header">
                        <h2>📅 Nueva Reserva</h2>
                        <p>Complete los datos para su reserva</p>
                    </div>

                    <form class="reserva-form" id="reservaForm">
                        <div class="form-section">
                            <h3>📆 Fechas de Estadía</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fecha_entrada">Fecha de Entrada</label>
                                    <input type="date" id="fecha_entrada" name="fecha_entrada" required>
                                </div>
                                <div class="form-group">
                                    <label for="fecha_salida">Fecha de Salida</label>
                                    <input type="date" id="fecha_salida" name="fecha_salida" required>
                                </div>
                            </div>
                            <div class="noches-display">
                                <span id="noches-info">Seleccione las fechas para ver la duración</span>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>🏨 Tipo de Habitación</h3>
                            <div class="form-group">
                                <label for="tipo_habitacion">Seleccione el tipo de habitación</label>
                                <div class="custom-select-container">
                                    <select name="tipo_habitacion" id="tipo_habitacion_select" class="custom-select" required>
                                        <option value="">-- Seleccionar habitación --</option>
                                        <?php foreach ($room_types as $room): ?>
                                            <option value="<?= $room['id_tipo'] ?>" 
                                                    data-precio="<?= $room['precio_effective'] ?>" 
                                                    data-capacidad="<?= $room['capacidad'] ?>" 
                                                    data-disponibles="<?= $room['disponibles'] ?>" 
                                                    data-nombre="<?= htmlspecialchars($room['nombre']) ?>" 
                                                    data-descripcion="<?= htmlspecialchars($room['descripcion']) ?>">
                                                <?= htmlspecialchars($room['nombre']) ?> - $<?= number_format($room['precio_effective'], 0, ',', '.') ?>/noche (<?= $room['disponibles'] ?> disponibles)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="select-arrow">
                                        <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                                            <path d="M1 1L6 6L11 1" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="habitacion-detalles" id="habitacion-detalles">
                                <div class="detalles-card">
                                    <div class="detalles-header">
                                        <h4 id="detalle-nombre"></h4>
                                        <div class="detalle-precio">
                                            <span class="precio-numero" id="detalle-precio">$0</span>
                                            <span class="precio-periodo">/noche</span>
                                        </div>
                                    </div>
                                    <p class="detalle-descripcion" id="detalle-descripcion"></p>
                                    <div class="detalles-info">
                                        <div class="info-item">
                                            <span class="info-icon">👥</span>
                                            <span id="detalle-capacidad"></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-icon">🏨</span>
                                            <span id="detalle-disponibles"></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-icon">🌊</span>
                                            <span>Vista al mar</span>
                                        </div>
                                    </div>
                                    <div class="detalles-caracteristicas">
                                        <span class="caracteristica">WiFi gratuito</span>
                                        <span class="caracteristica">Aire acondicionado</span>
                                        <span class="caracteristica">Minibar</span>
                                        <span class="caracteristica">Balcón privado</span>
                                        <span class="caracteristica" id="caracteristica-premium" style="display: none;">Room service 24h</span>
                                        <span class="caracteristica" id="caracteristica-luxury" style="display: none;">Jacuzzi privado</span>
                                    </div>
                                    <div class="detalles-galeria">
                                        <div class="galeria-item active">
                                            <div class="imagen-placeholder">📸 Imagen 1</div>
                                        </div>
                                        <div class="galeria-item">
                                            <div class="imagen-placeholder">📸 Imagen 2</div>
                                        </div>
                                        <div class="galeria-item">
                                            <div class="imagen-placeholder">📸 Imagen 3</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>👥 Número de Huéspedes</h3>
                            <div class="form-group">
                                <select name="num_huespedes" id="num_huespedes" class="custom-select" required>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?> huésped<?= $i > 1 ? 'es' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>💬 Solicitudes Especiales (Opcional)</h3>
                            <div class="form-group">
                                <textarea name="solicitudes_especiales" id="solicitudes_especiales" rows="4" placeholder="Ejemplo: Cama matrimonial, piso alto, vista al mar, etc."></textarea>
                            </div>
                        </div>

                        <div class="precio-resumen">
                            <h3>💰 Resumen de Precio</h3>
                            <div class="precio-breakdown">
                                <div class="precio-item">Noches: <span id="resumen-noches">0</span></div>
                                <div class="precio-item">Precio por noche: <span id="resumen-precio-noche">$0</span></div>
                                <div class="precio-item">Subtotal: <span id="resumen-subtotal">$0</span></div>
                                <div class="precio-item descuento">Descuento: <span id="resumen-descuento">-$0</span></div>
                                <div class="precio-item total">Total: <span id="resumen-total">$0</span></div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-large btn-full">
                                <span>✅</span> Confirmar Reserva
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-sidebar">
                    <div class="info-card">
                        <h3>ℹ️ Información Importante</h3>
                        <ul>
                            <li><strong>Check-in:</strong> 15:00 hrs</li>
                            <li><strong>Check-out:</strong> 11:00 hrs</li>
                            <li><strong>Cancelación:</strong> Gratuita hasta 24h antes</li>
                            <li><strong>Pago:</strong> Se requiere confirmación</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3>🎯 Servicios Incluidos</h3>
                        <ul>
                            <li>WiFi gratuito</li>
                            <li>Desayuno buffet</li>
                            <li>Acceso al spa</li>
                            <li>Estacionamiento</li>
                            <li>Servicio de concierge</li>
                        </ul>
                    </div>

                    <div class="info-card oferta-card">
                        <h3>🔥 Promoción Activa</h3>
                        <h4 id="oferta-nombre"><?= $offer['nombre'] ?></h4>
                        <p id="oferta-descripcion"><?= $offer['descripcion'] ?></p>
                        <div class="oferta-descuento" id="oferta-descuento">
                            <?= $offer['descuento'] ?>% OFF
                        </div>
                        <small id="oferta-vigencia">Válida hasta: <?= date('d/m/Y', strtotime($offer['fecha_fin'])) ?></small>
                    </div>

                    <div class="contacto-rapido">
                        <h3>📞 ¿Necesita Ayuda?</h3>
                        <p>Nuestro equipo está disponible 24/7</p>
                        <a href="https://wa.me/542231234567" target="_blank" class="btn btn-whatsapp">
                            <span>📱</span> WhatsApp
                        </a>
                        <a href="tel:+542231234567" class="btn btn-call">
                            <span>📞</span> Llamar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer-simple">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <span class="logo-icon">🏨</span>
                    HOTEL RIVO
                </div>
                <div class="footer-links">
                    <a href="index.php">Inicio</a>
                    <a href="index.php#contacto">Contacto</a>
                    <a href="mis_reservas.php">Mis Reservas</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 Hotel Rivo - Punta Mogotes. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fechaEntrada = document.getElementById('fecha_entrada');
            const fechaSalida = document.getElementById('fecha_salida');
            const select = document.getElementById('tipo_habitacion_select');
            const detalles = document.getElementById('habitacion-detalles');

            initializeMobileMenu();

            [fechaEntrada, fechaSalida].forEach(input => {
                input.addEventListener('change', updateCalculations);
            });

            select.addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                if (option.value) {
                    detalles.classList.add('show');
                    document.getElementById('detalle-nombre').textContent = option.dataset.nombre;
                    document.getElementById('detalle-descripcion').textContent = option.dataset.descripcion;
                    document.getElementById('detalle-precio').textContent = parseFloat(option.dataset.precio).toLocaleString();
                    document.getElementById('detalle-capacidad').textContent = `${option.dataset.capacidad} personas`;
                    document.getElementById('detalle-disponibles').textContent = `${option.dataset.disponibles} disponibles`;
                    document.getElementById('caracteristica-premium').style.display = ['2', '3'].includes(option.value) ? 'inline-block' : 'none';
                    document.getElementById('caracteristica-luxury').style.display = option.value === '3' ? 'inline-block' : 'none';
                    animateRoomDetails();
                    updateCalculations();
                } else {
                    detalles.classList.remove('show');
                }
            });

            function updateNochesDisplay() {
                const entrada = new Date(fechaEntrada.value);
                const salida = new Date(fechaSalida.value);
                const nochesInfo = document.getElementById('noches-info');
                const resumenNoches = document.getElementById('resumen-noches');

                if (fechaEntrada.value && fechaSalida.value) {
                    const noches = Math.max(0, Math.ceil((salida - entrada) / (1000 * 60 * 60 * 24)));
                    nochesInfo.textContent = noches > 0 ? `${noches} noche${noches > 1 ? 's' : ''} de estadía` : 'Fechas inválidas';
                    resumenNoches.textContent = noches;
                    nochesInfo.style.color = noches > 0 ? '#c9a961' : '#dc3545';
                } else {
                    nochesInfo.textContent = 'Seleccione las fechas para ver la duración';
                    resumenNoches.textContent = '0';
                    nochesInfo.style.color = '#666';
                }
            }

            function updatePriceCalculation() {
                const entrada = new Date(fechaEntrada.value);
                const salida = new Date(fechaSalida.value);
                const resumenNoches = document.getElementById('resumen-noches');
                const resumenPrecioNoche = document.getElementById('resumen-precio-noche');
                const resumenSubtotal = document.getElementById('resumen-subtotal');
                const resumenDescuento = document.getElementById('resumen-descuento');
                const resumenTotal = document.getElementById('resumen-total');

                if (!fechaEntrada.value || !fechaSalida.value || !select.value) {
                    resumenNoches.textContent = '0';
                    resumenPrecioNoche.textContent = '$0';
                    resumenSubtotal.textContent = '$0';
                    resumenDescuento.textContent = '-$0';
                    resumenTotal.textContent = '$0';
                    return;
                }

                const noches = Math.max(0, Math.ceil((salida - entrada) / (1000 * 60 * 60 * 24)));
                const precioPorNoche = parseFloat(select.options[select.selectedIndex].dataset.precio) || 0;
                const subtotal = noches * precioPorNoche;
                const descuento = subtotal * (<?= $offer['descuento'] ?> / 100) || 0;
                const total = subtotal - descuento;

                resumenNoches.textContent = noches;
                resumenPrecioNoche.textContent = '$' + precioPorNoche.toLocaleString('es-CL');
                resumenSubtotal.textContent = '$' + subtotal.toLocaleString('es-CL');
                resumenDescuento.textContent = '-$' + descuento.toLocaleString('es-CL');
                resumenTotal.textContent = '$' + total.toLocaleString('es-CL');
            }

            function updateCalculations() {
                updateNochesDisplay();
                updatePriceCalculation();
            }

            function animateRoomDetails() {
                const elementos = document.querySelectorAll('.detalles-card .info-item, .detalles-card .caracteristica');
                elementos.forEach((elemento, index) => {
                    elemento.style.opacity = '0';
                    elemento.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        elemento.style.transition = 'all 0.4s ease';
                        elemento.style.opacity = '1';
                        elemento.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }

            function initializeMobileMenu() {
                const hamburger = document.getElementById('hamburger');
                const navMenu = document.getElementById('nav-menu');
                
                if (hamburger && navMenu) {
                    hamburger.addEventListener('click', () => {
                        hamburger.classList.toggle('active');
                        navMenu.classList.toggle('active');
                    });
                    
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.addEventListener('click', () => {
                            hamburger.classList.remove('active');
                            navMenu.classList.remove('active');
                        });
                    });
                }
            }

            document.getElementById('reservaForm').addEventListener('submit', function(e) {
                e.preventDefault();
                alert('Reserva confirmada (simulada)');
                this.reset();
                detalles.classList.remove('show');
                updateCalculations();
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>