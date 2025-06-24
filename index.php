<?php
// Iniciar sesión al principio, antes de cualquier salida
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Suprimir warnings para producción
error_reporting(E_ERROR | E_PARSE);

// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

// Inicializar variables por defecto
$tipos_habitacion = [];
$stats_hotel = [
    'habitaciones_disponibles' => 10,
    'total_habitaciones' => 40,
    'total_clientes' => 150,
    'rating_promedio' => 4.8,
    'reservas_confirmadas' => 85
];
$testimonios = [];
$ofertas_activas = [];
$servicios_hotel = [];
$porcentaje_ocupacion = 75;
$is_logged_in = false;
$is_admin = false;
$user_info = ['nombre' => 'Huésped', 'email' => ''];

// Verificar conexión
if ($conn->connect_error) {
    $connection_error = true;
} else {
    $connection_error = false;
    
    // Función auxiliar para manejo seguro de consultas
    function fetch_or_default($query, $default) {
        global $conn;
        try {
            $result = $conn->query($query);
            return $result && $result->num_rows > 0 ? $result->fetch_assoc() : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    function fetch_all_or_default($query) {
        global $conn;
        try {
            $result = $conn->query($query);
            return $result && $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    // Verificar si el usuario está logueado
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    $is_admin = $is_logged_in && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

    // Obtener información del usuario si está logueado
    if ($is_logged_in) {
        $user_info = fetch_or_default(
            "SELECT nombre, email FROM usuarios WHERE id_usuario = " . intval($_SESSION['user_id']), 
            ['nombre' => 'Usuario', 'email' => '']
        );
    }

    // Obtener tipos de habitación con datos seguros
    $tipos_habitacion = fetch_all_or_default("
        SELECT 
            th.id_tipo,
            th.nombre,
            th.descripcion,
            th.precio_noche,
            th.capacidad,
            th.activo,
            COALESCE(COUNT(h.id_habitacion), 0) as total_habitaciones,
            COALESCE(COUNT(CASE WHEN h.estado = 'disponible' THEN 1 END), 0) as habitaciones_disponibles,
            COALESCE(AVG(c.puntuacion), 0) as rating_promedio,
            COALESCE(COUNT(c.id_comentario), 0) as total_comentarios
        FROM tipos_habitacion th
        LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
        LEFT JOIN comentarios c ON h.id_habitacion = c.id_habitacion
        WHERE th.activo = 1
        GROUP BY th.id_tipo, th.nombre, th.descripcion, th.precio_noche, th.capacidad, th.activo
        ORDER BY th.precio_noche ASC
    ");

    // Si no hay tipos de habitación en la BD, usar datos por defecto
    if (empty($tipos_habitacion)) {
        $tipos_habitacion = [
            [
                'id_tipo' => 1,
                'nombre' => 'Habitación Estándar',
                'descripcion' => 'Elegante habitación con todas las comodidades esenciales',
                'precio_noche' => 20000,
                'capacidad' => 2,
                'total_habitaciones' => 15,
                'habitaciones_disponibles' => 8,
                'rating_promedio' => 4.5,
                'total_comentarios' => 45
            ],
            [
                'id_tipo' => 2,
                'nombre' => 'Suite Deluxe',
                'descripcion' => 'Amplia suite con vista panorámica al mar',
                'precio_noche' => 35000,
                'capacidad' => 4,
                'total_habitaciones' => 20,
                'habitaciones_disponibles' => 5,
                'rating_promedio' => 4.8,
                'total_comentarios' => 62
            ],
            [
                'id_tipo' => 3,
                'nombre' => 'Suite Presidencial',
                'descripcion' => 'La experiencia más exclusiva con terraza privada',
                'precio_noche' => 75000,
                'capacidad' => 6,
                'total_habitaciones' => 5,
                'habitaciones_disponibles' => 2,
                'rating_promedio' => 5.0,
                'total_comentarios' => 28
            ]
        ];
    }

    // Obtener estadísticas generales del hotel
    $stats_hotel = fetch_or_default("
        SELECT 
            COALESCE((SELECT COUNT(*) FROM habitaciones WHERE estado = 'disponible'), 10) as habitaciones_disponibles,
            COALESCE((SELECT COUNT(*) FROM habitaciones), 40) as total_habitaciones,
            COALESCE((SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'), 150) as total_clientes,
            COALESCE((SELECT AVG(puntuacion) FROM comentarios), 4.8) as rating_promedio,
            COALESCE((SELECT COUNT(*) FROM reservas WHERE estado = 'confirmada'), 85) as reservas_confirmadas
    ", $stats_hotel);

    // Obtener comentarios recientes para testimonios
    $testimonios = fetch_all_or_default("
        SELECT 
            c.comentario,
            c.puntuacion,
            c.fecha_comentario,
            u.nombre as cliente_nombre,
            h.numero as habitacion_numero,
            th.nombre as tipo_habitacion
        FROM comentarios c
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
        INNER JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
        WHERE c.puntuacion >= 4
        ORDER BY c.fecha_comentario DESC
        LIMIT 6
    ");

    // Si no hay testimonios en la BD, usar datos por defecto
    if (empty($testimonios)) {
        $testimonios = [
            [
                'comentario' => 'Una experiencia extraordinaria. El servicio es impecable y las vistas son simplemente espectaculares.',
                'puntuacion' => 5,
                'fecha_comentario' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'cliente_nombre' => 'María González',
                'habitacion_numero' => '205',
                'tipo_habitacion' => 'Suite Deluxe'
            ],
            [
                'comentario' => 'Hotel Rivo superó todas nuestras expectativas. La atención al detalle es excepcional.',
                'puntuacion' => 5,
                'fecha_comentario' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'cliente_nombre' => 'Carlos Mendoza',
                'habitacion_numero' => '301',
                'tipo_habitacion' => 'Suite Presidencial'
            ],
            [
                'comentario' => 'El spa es increíble y la gastronomía del restaurante es de nivel mundial.',
                'puntuacion' => 4,
                'fecha_comentario' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'cliente_nombre' => 'Ana Silva',
                'habitacion_numero' => '102',
                'tipo_habitacion' => 'Habitación Estándar'
            ]
        ];
    }

    // Obtener ofertas especiales activas
    $ofertas_activas = fetch_all_or_default("
        SELECT * FROM ofertas_especiales 
        WHERE activa = 1 
        AND fecha_inicio <= CURDATE() 
        AND fecha_fin >= CURDATE()
        ORDER BY descuento DESC
        LIMIT 3
    ");

    // Calcular porcentaje de ocupación
    if ($stats_hotel['total_habitaciones'] > 0) {
        $habitaciones_ocupadas = $stats_hotel['total_habitaciones'] - $stats_hotel['habitaciones_disponibles'];
        $porcentaje_ocupacion = round(($habitaciones_ocupadas / $stats_hotel['total_habitaciones']) * 100, 1);
    }
}

// Servicios del hotel (datos fijos para evitar errores)
$servicios_hotel = [
    ['nombre' => 'Piscina Infinity', 'descripcion' => 'Piscina climatizada con vista al mar', 'icono' => '🏊‍♂️'],
    ['nombre' => 'Gimnasio 24h', 'descripcion' => 'Equipamiento de última generación', 'icono' => '🏋️‍♀️'],
    ['nombre' => 'Valet Parking', 'descripcion' => 'Servicio de estacionamiento gratuito', 'icono' => '🚗'],
    ['nombre' => 'Concierge', 'descripcion' => 'Asistencia personalizada 24/7', 'icono' => '🛎️'],
    ['nombre' => 'Transfer', 'descripcion' => 'Traslado desde el aeropuerto', 'icono' => '🧳'],
    ['nombre' => 'WiFi Premium', 'descripcion' => 'Internet de alta velocidad gratuito', 'icono' => '📶'],
    ['nombre' => 'Lavandería', 'descripcion' => 'Servicio de lavado y planchado', 'icono' => '🧺'],
    ['nombre' => 'Room Service', 'descripcion' => 'Servicio a la habitación las 24h', 'icono' => '☕']
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Rivo | Punta Mogotes - Experiencia de Lujo Frente al Mar</title>
    <meta name="description" content="Hotel Rivo en Punta Mogotes, Mar del Plata. Experiencia de lujo frente al mar con habitaciones elegantes, spa y gastronomía de primer nivel. <?= $stats_hotel['habitaciones_disponibles'] ?> habitaciones disponibles.">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
</head>
<body>

<!-- Navbar -->
<header class="navbar" id="navbar">
    <div class="nav-container">
        <div class="logo">
            <span class="logo-icon">🏨</span>
            HOTEL RIVO
        </div>
        <nav class="nav-menu" id="nav-menu">
            <a href="#inicio" class="nav-link active">Inicio</a>
            <a href="#habitaciones" class="nav-link">Habitaciones</a>
            <a href="#experiencias" class="nav-link">Experiencias</a>
            <a href="#servicios" class="nav-link">Servicios</a>
            <?php if (!empty($ofertas_activas)): ?>
                <a href="#ofertas" class="nav-link special">Ofertas</a>
            <?php endif; ?>
            <a href="#galeria" class="nav-link">Galería</a>
            <a href="#contacto" class="nav-link">Contacto</a>
            <a href="#" class="nav-link special" id="open-tarifas">Tarifas</a>
            
            <?php if ($is_logged_in): ?>
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php" class="btn-admin">Panel Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-login">Cerrar Sesión</a>
            <?php else: ?>
                <a href="login.php" class="btn-login">Iniciar Sesión</a>
            <?php endif; ?>
        </nav>
        <div class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</header>

<!-- Hero Section -->
<section id="inicio" class="hero">
    <div class="hero-background">
        <div class="hero-slide active" style="background-image: url('images/fondo_prueba.jpg')"></div>
        <div class="hero-slide" style="background-image: url('images/hotel-frente.jpg')"></div>
        <div class="hero-slide" style="background-image: url('images/habitacion-vista-mar.jpg')"></div>
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-text">
            <span class="hero-welcome">Bienvenido<?= $is_logged_in ? ', ' . htmlspecialchars($user_info['nombre']) : '' ?> a</span>
            <h1 class="hero-title">Hotel Rivo</h1>
            <p class="hero-subtitle">Una experiencia de lujo frente al mar en Punta Mogotes</p>
            <div class="hero-divider"></div>
            <p class="hero-description">Donde la elegancia se encuentra con el océano</p>
            <?php if (!empty($ofertas_activas)): ?>
                <div class="hero-offer">
                    <span class="offer-badge">¡Oferta Especial!</span>
                    <span class="offer-text"><?= $ofertas_activas[0]['descuento'] ?>% de descuento - <?= htmlspecialchars($ofertas_activas[0]['nombre']) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-actions">
            <button class="btn btn-primary btn-large" onclick="window.location.href='reservas.php'">
                <span>📅</span> Reservar Ahora
            </button>
            <button class="btn btn-secondary btn-large" id="check-rates">
                <span>💰</span> Ver Tarifas
            </button>
            <button class="btn btn-outline btn-large" onclick="scrollToSection('habitaciones')">
                <span>🏨</span> Explorar
            </button>
        </div>
    </div>
    <div class="hero-scroll-indicator">
        <span>Desliza para descubrir</span>
        <div class="scroll-arrow">↓</div>
    </div>
</section>

<!-- Quick Info Bar -->
<section class="quick-info">
    <div class="container">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon">🌊</div>
                <div class="info-content">
                    <h3>Vista al Mar</h3>
                    <p>Ubicación privilegiada frente al océano</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">⭐</div>
                <div class="info-content">
                    <h3><?= number_format($stats_hotel['rating_promedio'], 1) ?>/5 Estrellas</h3>
                    <p>Calificación promedio de huéspedes</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">🏨</div>
                <div class="info-content">
                    <h3><?= $stats_hotel['habitaciones_disponibles'] ?> Disponibles</h3>
                    <p>De <?= $stats_hotel['total_habitaciones'] ?> habitaciones totales</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">👥</div>
                <div class="info-content">
                    <h3><?= $stats_hotel['total_clientes'] ?>+ Huéspedes</h3>
                    <p>Nos han elegido para su estadía</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Ofertas Especiales (si hay activas) -->
<?php if (!empty($ofertas_activas)): ?>
<section id="ofertas" class="ofertas-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Promociones</span>
            <h2>Ofertas Especiales</h2>
            <div class="section-divider"></div>
            <p>Aprovecha nuestras promociones exclusivas por tiempo limitado</p>
        </div>
        
        <div class="ofertas-grid">
            <?php foreach ($ofertas_activas as $oferta): ?>
                <div class="oferta-card">
                    <div class="oferta-badge"><?= $oferta['descuento'] ?>% OFF</div>
                    <div class="oferta-content">
                        <h3><?= htmlspecialchars($oferta['nombre']) ?></h3>
                        <p><?= htmlspecialchars($oferta['descripcion']) ?></p>
                        <div class="oferta-validity">
                            <span>Válida hasta: <?= date('d/m/Y', strtotime($oferta['fecha_fin'])) ?></span>
                        </div>
                        <button class="btn btn-primary" onclick="window.location.href='reservas.php?oferta=<?= $oferta['id_oferta'] ?>'">
                            Reservar con Descuento
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Habitaciones Section -->
<section id="habitaciones" class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Alojamiento</span>
            <h2>Habitaciones & Suites</h2>
            <div class="section-divider"></div>
            <p>Espacios diseñados para brindar el máximo confort y sofisticación con vistas espectaculares al mar</p>
        </div>
        
        <div class="rooms-grid">
            <?php foreach ($tipos_habitacion as $index => $tipo): ?>
                <div class="room-card <?= $index === 1 ? 'featured' : '' ?>" data-room="<?= $tipo['id_tipo'] ?>">
                    <div class="room-image">
                        <img src="images/fondo_prueba.jpg" alt="<?= htmlspecialchars($tipo['nombre']) ?>" loading="lazy">
                        <div class="room-overlay">
                            <?php if ($index === 1): ?>
                                <div class="room-badge premium">Recomendado</div>
                            <?php elseif ($index === 0): ?>
                                <div class="room-badge">Más Popular</div>
                            <?php else: ?>
                                <div class="room-badge luxury">Exclusiva</div>
                            <?php endif; ?>
                            <div class="room-actions">
                                <button class="btn-room-details" onclick="showRoomDetails(<?= $tipo['id_tipo'] ?>)">Ver Detalles</button>
                                <button class="btn-room-book" onclick="bookRoom(<?= $tipo['id_tipo'] ?>)">Reservar</button>
                            </div>
                        </div>
                    </div>
                    <div class="room-info">
                        <div class="room-header">
                            <h3><?= htmlspecialchars($tipo['nombre']) ?></h3>
                            <div class="room-price">
                                <span class="price-from">desde</span>
                                <span class="price-amount">$<?= number_format($tipo['precio_noche'], 0, ',', '.') ?></span>
                                <span class="price-period">/noche</span>
                            </div>
                        </div>
                        <p class="room-description"><?= htmlspecialchars($tipo['descripcion']) ?></p>
                        <div class="room-amenities">
                            <span class="amenity">👥 <?= $tipo['capacidad'] ?> personas</span>
                            <span class="amenity">🏨 <?= $tipo['habitaciones_disponibles'] ?> disponibles</span>
                            <?php if ($tipo['rating_promedio'] > 0): ?>
                                <span class="amenity">⭐ <?= number_format($tipo['rating_promedio'], 1) ?>/5</span>
                            <?php endif; ?>
                            <span class="amenity">🌊 Vista al mar</span>
                        </div>
                        <div class="room-features">
                            <span>WiFi gratuito</span>
                            <span>Aire acondicionado</span>
                            <span>Minibar</span>
                            <span>Balcón</span>
                            <?php if ($tipo['precio_noche'] > 50000): ?>
                                <span>Room service 24h</span>
                                <span>Jacuzzi privado</span>
                            <?php endif; ?>
                        </div>
                        <div class="room-availability">
                            <?php if ($tipo['habitaciones_disponibles'] > 0): ?>
                                <span class="availability-good">✅ Disponible</span>
                            <?php else: ?>
                                <span class="availability-low">⚠️ Sin disponibilidad</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Servicios Section -->
<section id="servicios" class="section servicios-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Comodidades</span>
            <h2>Servicios Premium</h2>
            <div class="section-divider"></div>
            <p>Todo lo que necesitas para una experiencia perfecta</p>
        </div>
        
        <div class="services-grid">
            <?php foreach ($servicios_hotel as $servicio): ?>
                <div class="service-item">
                    <div class="service-icon"><?= $servicio['icono'] ?></div>
                    <h3><?= htmlspecialchars($servicio['nombre']) ?></h3>
                    <p><?= htmlspecialchars($servicio['descripcion']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Experiencias Section -->
<section id="experiencias" class="section experiencias-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Actividades</span>
            <h2>Experiencias Únicas</h2>
            <div class="section-divider"></div>
            <p>Descubre las actividades exclusivas que hemos diseñado para hacer tu estadía inolvidable</p>
        </div>
        
        <div class="experiences-grid">
            <div class="experience-card">
                <div class="experience-image">
                    <img src="images/fondo_prueba.jpg" alt="Spa & Wellness" loading="lazy">
                    <div class="experience-overlay">
                        <h3>Spa & Wellness</h3>
                        <p>Tratamientos relajantes con vista al mar</p>
                        <button class="btn-experience">Conocer más</button>
                    </div>
                </div>
            </div>
            
            <div class="experience-card">
                <div class="experience-image">
                    <img src="images/fondo_prueba.jpg" alt="Gastronomía Gourmet" loading="lazy">
                    <div class="experience-overlay">
                        <h3>Gastronomía Gourmet</h3>
                        <p>Restaurante con chef internacional</p>
                        <button class="btn-experience">Conocer más</button>
                    </div>
                </div>
            </div>
            
            <div class="experience-card">
                <div class="experience-image">
                    <img src="images/fondo_prueba.jpg" alt="Actividades Acuáticas" loading="lazy">
                    <div class="experience-overlay">
                        <h3>Actividades Acuáticas</h3>
                        <p>Deportes y excursiones en el mar</p>
                        <button class="btn-experience">Conocer más</button>
                    </div>
                </div>
            </div>
            
            <div class="experience-card">
                <div class="experience-image">
                    <img src="images/fondo_prueba.jpg" alt="Eventos Exclusivos" loading="lazy">
                    <div class="experience-overlay">
                        <h3>Eventos Exclusivos</h3>
                        <p>Salones para celebraciones especiales</p>
                        <button class="btn-experience">Conocer más</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonios Section -->
<?php if (!empty($testimonios)): ?>
<section class="testimonials-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Opiniones</span>
            <h2>Huéspedes Satisfechos</h2>
            <div class="section-divider"></div>
            <p>Lo que dicen nuestros huéspedes sobre su experiencia</p>
        </div>
        
        <div class="testimonials-carousel">
            <?php foreach (array_slice($testimonios, 0, 3) as $index => $testimonio): ?>
                <div class="testimonial <?= $index === 0 ? 'active' : '' ?>">
                    <div class="testimonial-content">
                        <div class="stars">
                            <?php for ($i = 0; $i < $testimonio['puntuacion']; $i++): ?>⭐<?php endfor; ?>
                        </div>
                        <p>"<?= htmlspecialchars($testimonio['comentario']) ?>"</p>
                        <div class="testimonial-author">
                            <img src="images/fondo_prueba.jpg" alt="Cliente" loading="lazy">
                            <div>
                                <h4><?= htmlspecialchars($testimonio['cliente_nombre']) ?></h4>
                                <span><?= htmlspecialchars($testimonio['tipo_habitacion']) ?> - Hab. <?= $testimonio['habitacion_numero'] ?></span>
                                <div class="testimonial-date"><?= date('M Y', strtotime($testimonio['fecha_comentario'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="testimonial-dots">
            <?php for ($i = 0; $i < min(3, count($testimonios)); $i++): ?>
                <span class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="currentTestimonial(<?= $i + 1 ?>)"></span>
            <?php endfor; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Estadísticas Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid-main">
            <div class="stat-item">
                <div class="stat-number"><?= $stats_hotel['total_habitaciones'] ?></div>
                <div class="stat-label">Habitaciones de Lujo</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats_hotel['rating_promedio'], 1) ?></div>
                <div class="stat-label">Calificación Promedio</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats_hotel['total_clientes'] ?>+</div>
                <div class="stat-label">Huéspedes Satisfechos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $porcentaje_ocupacion ?>%</div>
                <div class="stat-label">Ocupación Promedio</div>
            </div>
        </div>
    </div>
</section>

<!-- Galería Section -->
<section id="galeria" class="section galeria-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Imágenes</span>
            <h2>Galería</h2>
            <div class="section-divider"></div>
            <p>Descubre la belleza de nuestras instalaciones</p>
        </div>
        
        <div class="gallery-grid">
            <div class="gallery-item large">
                <img src="images/fondo_prueba.jpg" alt="Vista principal del hotel" loading="lazy">
                <div class="gallery-overlay">
                    <span>Vista Principal</span>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Restaurante" loading="lazy">
                <div class="gallery-overlay">
                    <span>Restaurante</span>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Spa" loading="lazy">
                <div class="gallery-overlay">
                    <span>Spa & Wellness</span>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Piscina" loading="lazy">
                <div class="gallery-overlay">
                    <span>Piscina Infinity</span>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Vista al mar" loading="lazy">
                <div class="gallery-overlay">
                    <span>Vista al Mar</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reserva Section -->
<section class="reservation-section">
    <div class="container">
        <div class="reservation-content">
            <div class="reservation-text">
                <h2>¿Listo para una experiencia inolvidable?</h2>
                <p>Reserva ahora y disfruta de una estadía de lujo frente al mar en Punta Mogotes</p>
                <div class="reservation-features">
                    <span>✓ Cancelación gratuita</span>
                    <span>✓ Mejor precio garantizado</span>
                    <span>✓ Confirmación inmediata</span>
                    <?php if ($stats_hotel['habitaciones_disponibles'] <= 5): ?>
                        <span class="urgent">⚠️ Solo <?= $stats_hotel['habitaciones_disponibles'] ?> habitaciones disponibles</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="reservation-actions">
                <button class="btn btn-primary btn-large" onclick="window.location.href='reservas.php'">
                    <span>📅</span> Reservar Ahora
                </button>
                <button class="btn btn-secondary btn-large" id="check-rates-2">
                    <span>💰</span> Ver Tarifas
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Contacto Section -->
<section id="contacto" class="section contacto-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Ubicación</span>
            <h2>Encuentranos</h2>
            <div class="section-divider"></div>
            <p>Estamos ubicados en el corazón de Punta Mogotes con acceso directo a la playa</p>
        </div>
        
        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div class="contact-content">
                        <h3>Dirección</h3>
                        <p>Av. Costanera Sur 1234<br>Punta Mogotes, Mar del Plata<br>Buenos Aires, Argentina</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div class="contact-content">
                        <h3>Teléfono</h3>
                        <p>+54 223 XXX-XXXX<br>WhatsApp: +54 9 223 XXX-XXXX</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">✉️</div>
                    <div class="contact-content">
                        <h3>Email</h3>
                        <p>reservas@hotelrivo.com<br>info@hotelrivo.com</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">🕐</div>
                    <div class="contact-content">
                        <h3>Check-in / Check-out</h3>
                        <p>Check-in: 15:00 hs<br>Check-out: 11:00 hs</p>
                    </div>
                </div>
            </div>
            
            <div class="contact-map">
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps?q=Acevedo+M.+A.+2115,+Punta+Mogotes,+B7603CXC,+Mar+del+Plata,+Provincia+de+Buenos+Aires&output=embed"
                        height="400" 
                        style="border:0; border-radius: 15px;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Ubicación Hotel Rivo - Punta Mogotes">
                    </iframe>
                    
                    <!-- Botón flotante para abrir opciones -->
                    <button class="map-options-btn" id="mapOptionsBtn">
                        <span class="map-icon">📍</span>
                        <span class="map-text">Navegar</span>
                    </button>
                    
                    <!-- Popup con opciones de navegación -->
                    <div class="map-popup" id="mapPopup">
                        <div class="map-popup-content">
                            <div class="map-popup-header">
                                <h4>🏨 Hotel Rivo</h4>
                                <button class="map-popup-close" id="mapPopupClose">&times;</button>
                            </div>
                            <p class="map-address">📍 Acevedo M. A. 2115, Punta Mogotes</p>
                            <div class="map-popup-actions">
                                <a href="https://goo.gl/maps/example" target="_blank" class="map-nav-btn google-maps">
                                    <span class="nav-icon">🗺️</span>
                                    <span>Google Maps</span>
                                </a>
                                <a href="https://waze.com/ul/example" target="_blank" class="map-nav-btn waze">
                                    <span class="nav-icon">🚗</span>
                                    <span>Waze</span>
                                </a>
                                <a href="https://maps.apple.com/?q=Punta+Mogotes+Mar+del+Plata" target="_blank" class="map-nav-btn apple-maps">
                                    <span class="nav-icon">🍎</span>
                                    <span>Apple Maps</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-logo">
                    <span class="logo-icon">🏨</span>
                    HOTEL RIVO
                </div>
                <p>Experiencia de lujo frente al mar en Punta Mogotes, Mar del Plata.</p>
                <div class="footer-stats">
                    <small><?= $stats_hotel['habitaciones_disponibles'] ?> habitaciones disponibles | ⭐ <?= number_format($stats_hotel['rating_promedio'], 1) ?>/5 rating</small>
                </div>
                <div class="footer-social">
                    <a href="#" class="social-link">📘</a>
                    <a href="#" class="social-link">📷</a>
                    <a href="#" class="social-link">🐦</a>
                    <a href="#" class="social-link">📺</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Enlaces Rápidos</h3>
                <ul>
                    <li><a href="#habitaciones">Habitaciones</a></li>
                    <li><a href="#experiencias">Experiencias</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="reservas.php">Reservas</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="mis_reservas.php">Mis Reservas</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Servicios</h3>
                <ul>
                    <li><a href="#">Spa & Wellness</a></li>
                    <li><a href="#">Restaurante</a></li>
                    <li><a href="#">Eventos</a></li>
                    <li><a href="#">Actividades</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contacto</h3>
                <ul>
                    <li>📍 Punta Mogotes, Mar del Plata</li>
                    <li>📞 +54 223 XXX-XXXX</li>
                    <li>✉️ info@hotelrivo.com</li>
                </ul>
                <?php if ($is_admin): ?>
                    <div style="margin-top: 1rem;">
                        <a href="admin_dashboard.php" style="color: #c9a961; font-weight: 600;">🎛️ Panel de Administración</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 Hotel Rivo - Punta Mogotes. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="#">Términos y Condiciones</a>
                <a href="#">Política de Privacidad</a>
                <?php if (!$is_logged_in): ?>
                    <a href="login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<!-- Modal de Tarifas -->
<div id="modal-tarifas" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Consultar Tarifas</h2>
            <span class="close" id="close-modal">&times;</span>
        </div>
        
        <form id="form-tarifas" class="tarifas-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="checkin">Check-in</label>
                    <input type="date" id="checkin" name="checkin" required>
                </div>
                <div class="form-group">
                    <label for="checkout">Check-out</label>
                    <input type="date" id="checkout" name="checkout" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="habitacion">Tipo de habitación</label>
                    <select id="habitacion" name="habitacion" required>
                        <option value="">Seleccionar habitación</option>
                        <?php foreach ($tipos_habitacion as $tipo): ?>
                            <option value="<?= $tipo['id_tipo'] ?>" data-precio="<?= $tipo['precio_noche'] ?>" <?= $tipo['habitaciones_disponibles'] == 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($tipo['nombre']) ?> - $<?= number_format($tipo['precio_noche'], 0, ',', '.') ?>/noche
                                <?= $tipo['habitaciones_disponibles'] == 0 ? ' (Sin disponibilidad)' : ' (' . $tipo['habitaciones_disponibles'] . ' disponibles)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="huespedes">Huéspedes</label>
                    <select id="huespedes" name="huespedes" required>
                        <option value="1">1 huésped</option>
                        <option value="2">2 huéspedes</option>
                        <option value="3">3 huéspedes</option>
                        <option value="4">4 huéspedes</option>
                        <option value="5">5+ huéspedes</option>
                    </select>
                </div>
            </div>
            
            <div class="price-summary">
                <div class="price-breakdown">
                    <div class="price-item">
                        <span>Noches:</span>
                        <span id="noches-total">0</span>
                    </div>
                    <div class="price-item">
                        <span>Precio por noche:</span>
                        <span id="precio-noche">$0</span>
                    </div>
                    <?php if (!empty($ofertas_activas)): ?>
                        <div class="price-item discount">
                            <span>Descuento disponible:</span>
                            <span><?= $ofertas_activas[0]['descuento'] ?>% OFF</span>
                        </div>
                    <?php endif; ?>
                    <div class="price-item total">
                        <span>Total estimado:</span>
                        <span id="precio-total">$0</span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-primary btn-full" onclick="continueReservation()">
                    Continuar con la Reserva
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Botón flotante de WhatsApp -->
<div class="whatsapp-float">
    <a href="https://wa.me/542231234567?text=Hola, estoy interesado en hacer una reserva en Hotel Rivo" target="_blank" class="whatsapp-btn">
        <span class="whatsapp-icon">📱</span>
        <span class="whatsapp-text">WhatsApp</span>
    </a>
</div>

<!-- Botón de scroll to top -->
<button class="scroll-top" id="scrollTop">↑</button>

<!-- Mensaje de error de conexión (solo para desarrollo) -->
<?php if ($connection_error && isset($_GET['debug'])): ?>
<div style="position: fixed; top: 10px; left: 10px; background: #ff4757; color: white; padding: 10px; border-radius: 5px; z-index: 10000;">
    ⚠️ Usando datos por defecto - Error de conexión a BD
</div>
<?php endif; ?>

<style>
/* Reset y configuración base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', sans-serif;
    background-color: #fafafa;
    color: #2c2c2c;
    line-height: 1.7;
    letter-spacing: 0.3px;
    overflow-x: hidden;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* Navbar mejorado */
.navbar {
    position: fixed;
    width: 100%;
    top: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-bottom: 1px solid rgba(0,0,0,0.1);
    z-index: 999;
    transition: all 0.3s ease;
    padding: 0;
}

.navbar.scrolled {
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
}

.logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
    letter-spacing: 2px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.logo-icon {
    font-size: 1.8rem;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.nav-link {
    text-decoration: none;
    color: #4a4a4a;
    font-weight: 500;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    position: relative;
    padding: 0.5rem 0;
}

.nav-link:hover, .nav-link.active {
    color: #c9a961;
}

.nav-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: 0;
    left: 50%;
    background: linear-gradient(90deg, #c9a961, #dbb971);
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.nav-link:hover::after, .nav-link.active::after {
    width: 100%;
}

.nav-link.special {
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
    padding: 0.7rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
}

.nav-link.special::after {
    display: none;
}

.nav-link.special:hover {
    background: linear-gradient(135deg, #b8985a, #ca9f5f);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(201, 169, 97, 0.3);
}

.btn-login, .btn-admin {
    background: transparent;
    border: 2px solid #c9a961;
    color: #c9a961;
    padding: 0.6rem 1.5rem;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    margin-left: 1rem;
}

.btn-admin {
    background: #c9a961;
    color: white;
}

.btn-login:hover, .btn-admin:hover {
    background: #c9a961;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(201, 169, 97, 0.3);
}

.hamburger {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 4px;
}

.hamburger span {
    width: 25px;
    height: 3px;
    background: #333;
    transition: 0.3s;
    border-radius: 2px;
}

/* Hero Section mejorado */
.hero {
    height: 100vh;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    overflow: hidden;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -2;
}

.hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 2s ease-in-out;
}

.hero-slide.active {
    opacity: 1;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0,0,0,0.4), rgba(0,0,0,0.6));
    z-index: -1;
}

.hero-content {
    max-width: 800px;
    padding: 2rem;
    color: white;
    z-index: 1;
}

.hero-welcome {
    font-size: 1.2rem;
    color: #c9a961;
    margin-bottom: 1rem;
    display: block;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 500;
}

.hero-title {
    font-family: 'Playfair Display', serif;
    font-size: 4rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    background: linear-gradient(135deg, #ffffff, #f0f0f0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    color: #f0f0f0;
    font-weight: 400;
}

.hero-divider {
    width: 100px;
    height: 2px;
    background: linear-gradient(90deg, #c9a961, #dbb971);
    margin: 2rem auto;
    border-radius: 2px;
}

.hero-description {
    font-size: 1.1rem;
    color: #e0e0e0;
    margin-bottom: 2rem;
    font-style: italic;
}

.hero-offer {
    background: rgba(201, 169, 97, 0.9);
    padding: 1rem 2rem;
    border-radius: 30px;
    margin-bottom: 2rem;
    display: inline-block;
}

.offer-badge {
    background: #ff4757;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 1rem;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.hero-scroll-indicator {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    text-align: center;
    animation: bounce 2s infinite;
}

.scroll-arrow {
    font-size: 1.5rem;
    margin-top: 0.5rem;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    40% {
        transform: translateX(-50%) translateY(-10px);
    }
    60% {
        transform: translateX(-50%) translateY(-5px);
    }
}

/* Botones mejorados */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    border: none;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
    box-shadow: 0 4px 15px rgba(201, 169, 97, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #b8985a, #ca9f5f);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(201, 169, 97, 0.4);
}

.btn-secondary {
    background: transparent;
    border: 2px solid #c9a961;
    color: #c9a961;
}

.btn-secondary:hover {
    background: #c9a961;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(201, 169, 97, 0.3);
}

.btn-outline {
    background: transparent;
    border: 2px solid white;
    color: white;
}

.btn-outline:hover {
    background: white;
    color: #333;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
}

.btn-large {
    padding: 1.2rem 2.5rem;
    font-size: 1.1rem;
}

.btn-full {
    width: 100%;
    justify-content: center;
}

/* Quick Info Bar */
.quick-info {
    background: white;
    padding: 2rem 0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: relative;
    z-index: 10;
    margin-top: -2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    transition: transform 0.3s ease;
}

.info-item:hover {
    transform: translateY(-5px);
}

.info-icon {
    font-size: 2rem;
    width: 60px;
    text-align: center;
}

.info-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.3rem;
}

.info-content p {
    color: #666;
    font-size: 0.9rem;
}

/* Ofertas Section */
.ofertas-section {
    background: linear-gradient(135deg, #fff8e1, #f3e5ab);
    padding: 4rem 0;
}

.ofertas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.oferta-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    position: relative;
    transition: transform 0.3s ease;
}

.oferta-card:hover {
    transform: translateY(-10px);
}

.oferta-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #ff4757;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 1.2rem;
}

.oferta-content {
    padding: 2rem;
}

.oferta-content h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: #333;
}

.oferta-validity {
    background: #f8f9fa;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    color: #666;
    margin: 1rem 0;
    display: inline-block;
}

/* Sections */
.section {
    padding: 5rem 0;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-subtitle {
    color: #c9a961;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 1rem;
    display: block;
}

.section-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 3rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1.5rem;
}

.section-divider {
    width: 80px;
    height: 3px;
    background: linear-gradient(90deg, #c9a961, #dbb971);
    margin: 1.5rem auto;
    border-radius: 2px;
}

.section-header p {
    font-size: 1.1rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Habitaciones */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2.5rem;
}

.room-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
}

.room-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
}

.room-card.featured {
    border: 3px solid #c9a961;
    transform: scale(1.05);
}

.room-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.room-card:hover .room-image img {
    transform: scale(1.1);
}

.room-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(0,0,0,0.4), rgba(0,0,0,0.6));
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.room-card:hover .room-overlay {
    opacity: 1;
}

.room-badge {
    align-self: flex-start;
    background: #c9a961;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.room-badge.premium {
    background: linear-gradient(135deg, #c9a961, #dbb971);
}

.room-badge.luxury {
    background: linear-gradient(135deg, #8b4513, #a0522d);
}

.room-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-room-details, .btn-room-book {
    padding: 0.7rem 1.2rem;
    border: none;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-room-details {
    background: transparent;
    border: 2px solid white;
    color: white;
}

.btn-room-book {
    background: #c9a961;
    color: white;
}

.room-info {
    padding: 2rem;
}

.room-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.room-header h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
}

.room-price {
    text-align: right;
}

.price-from {
    font-size: 0.8rem;
    color: #666;
}

.price-amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #c9a961;
    display: block;
}

.price-period {
    font-size: 0.8rem;
    color: #666;
}

.room-description {
    color: #666;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.room-amenities {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    margin-bottom: 1.5rem;
}

.amenity {
    background: #f8f9fa;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #666;
}

.room-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.room-features span {
    background: #c9a961;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.room-availability {
    text-align: center;
    margin-top: 1rem;
}

.availability-good {
    color: #28a745;
    font-weight: 600;
}

.availability-low {
    color: #dc3545;
    font-weight: 600;
}

/* Servicios */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.service-item {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.service-item:hover {
    transform: translateY(-10px);
}

.service-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.service-item h3 {
    font-size: 1.3rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1rem;
}

.service-item p {
    color: #666;
    line-height: 1.6;
}

/* Experiencias */
.experiencias-section {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.experiences-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.experience-card {
    position: relative;
    height: 300px;
    border-radius: 15px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.experience-card:hover {
    transform: scale(1.05);
}

.experience-image {
    width: 100%;
    height: 100%;
    position: relative;
}

.experience-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.experience-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(0,0,0,0.6), rgba(201,169,97,0.8));
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: white;
    padding: 2rem;
    transition: all 0.3s ease;
}

.experience-overlay h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    margin-bottom: 1rem;
}

.experience-overlay p {
    margin-bottom: 2rem;
    opacity: 0.9;
}

.btn-experience {
    background: transparent;
    border: 2px solid white;
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-experience:hover {
    background: white;
    color: #333;
}

/* Testimonios */
.testimonials-section {
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
}

.testimonials-carousel {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
}

.testimonial {
    display: none;
    text-align: center;
    padding: 2rem;
}

.testimonial.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stars {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
}

.testimonial-content p {
    font-size: 1.2rem;
    font-style: italic;
    margin-bottom: 2rem;
    line-height: 1.8;
}

.testimonial-author {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.testimonial-author img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}

.testimonial-author h4 {
    margin-bottom: 0.3rem;
}

.testimonial-author span {
    opacity: 0.8;
    font-size: 0.9rem;
}

.testimonial-date {
    font-size: 0.8rem;
    opacity: 0.7;
    margin-top: 0.3rem;
}

.testimonial-dots {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot.active {
    background: white;
    transform: scale(1.3);
}

/* Estadísticas */
.stats-section {
    background: #333;
    color: white;
    padding: 4rem 0;
}

.stats-grid-main {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}

.stat-item {
    text-align: center;
    padding: 2rem;
}

.stat-number {
    font-size: 3rem;
    font-weight: bold;
    color: #c9a961;
    margin-bottom: 1rem;
}

.stat-label {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Galería */
.galeria-section {
    background: #f8f9fa;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    grid-auto-rows: 250px;
}

.gallery-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.gallery-item.large {
    grid-column: span 2;
    grid-row: span 2;
}

.gallery-item:hover {
    transform: scale(1.02);
}

.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    padding: 2rem;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.gallery-item:hover .gallery-overlay {
    transform: translateY(0);
}

/* Reserva */
.reservation-section {
    background: #333;
    color: white;
    padding: 4rem 0;
}

.reservation-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 3rem;
}

.reservation-text h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.reservation-text p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.reservation-features {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.reservation-features span {
    color: #c9a961;
    font-weight: 500;
}

.reservation-features .urgent {
    color: #ff4757;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.reservation-actions {
    display: flex;
    gap: 1rem;
    flex-shrink: 0;
}

/* Contacto */
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.contact-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.contact-icon {
    font-size: 1.5rem;
    width: 50px;
    text-align: center;
}

.contact-content h3 {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.contact-content p {
    color: #666;
    line-height: 1.6;
}

.map-container {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    height: 400px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}

.map-container iframe {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 15px;
}

/* Botón flotante para abrir opciones */
.map-options-btn {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
    border: none;
    border-radius: 25px;
    padding: 0.8rem 1.2rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(201, 169, 97, 0.4);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 10;
}

.map-options-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(201, 169, 97, 0.5);
}

.map-icon {
    font-size: 1.1rem;
}

/* Popup de navegación */
.map-popup {
    position: absolute;
    bottom: 80px;
    right: 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.9);
    transition: all 0.3s ease;
    z-index: 15;
    min-width: 280px;
}

.map-popup.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.map-popup-content {
    padding: 0;
}

.map-popup-header {
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.map-popup-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.map-popup-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.map-popup-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.map-address {
    padding: 1rem 1.5rem 0.5rem;
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.map-popup-actions {
    padding: 0.5rem 1.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.map-nav-btn {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    border: 2px solid transparent;
}

.map-nav-btn.google-maps {
    background: #f8f9fa;
    color: #4285f4;
    border-color: #e8f0fe;
}

.map-nav-btn.google-maps:hover {
    background: #e8f0fe;
    border-color: #4285f4;
    transform: translateX(5px);
}

.map-nav-btn.waze {
    background: #f8f9fa;
    color: #00d4ff;
    border-color: #e0f7ff;
}

.map-nav-btn.waze:hover {
    background: #e0f7ff;
    border-color: #00d4ff;
    transform: translateX(5px);
}

.map-nav-btn.apple-maps {
    background: #f8f9fa;
    color: #007aff;
    border-color: #e8f4ff;
}

.map-nav-btn.apple-maps:hover {
    background: #e8f4ff;
    border-color: #007aff;
    transform: translateX(5px);
}

.nav-icon {
    font-size: 1.2rem;
}

.map-overlay {
    position: absolute;
    top: 20px;
    left: 20px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    max-width: 280px;
    transform: translateY(-10px);
    opacity: 0;
    animation: fadeInUp 0.8s ease forwards;
    animation-delay: 0.5s;
}

@keyframes fadeInUp {
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.map-info h4 {
    color: #c9a961;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.map-info p {
    color: #666;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.map-actions {
    display: flex;
    gap: 0.5rem;
    flex-direction: column;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    border-radius: 20px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
}

.map-placeholder {
    background: #f8f9fa;
    border-radius: 15px;
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    border: 2px dashed #ddd;
}

.map-content h3 {
    margin-bottom: 1rem;
    color: #666;
}

.map-content p {
    margin-bottom: 1.5rem;
    color: #999;
}

/* Footer */
.footer {
    background: #1a1a1a;
    color: white;
    padding: 3rem 0 1rem;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-section h3 {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #c9a961;
}

.footer-section ul {
    list-style: none;
}

.footer-section ul li {
    margin-bottom: 0.5rem;
}

.footer-section ul li a {
    color: #ccc;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer-section ul li a:hover {
    color: #c9a961;
}

.footer-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-stats {
    margin: 1rem 0;
    padding: 0.5rem 1rem;
    background: rgba(201, 169, 97, 0.1);
    border-radius: 8px;
    color: #c9a961;
    font-size: 0.9rem;
}

.footer-social {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.social-link {
    width: 40px;
    height: 40px;
    background: #333;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: #c9a961;
    transform: translateY(-3px);
}

.footer-bottom {
    border-top: 1px solid #333;
    padding-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-links {
    display: flex;
    gap: 2rem;
}

.footer-links a {
    color: #ccc;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: #c9a961;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
}

.modal.show {
    display: flex;
}

.modal-content {
    background-color: white;
    padding: 0;
    border-radius: 15px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease;
    margin: auto;
    position: relative;
}

/* Scroll personalizado para el modal */
.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: #c9a961;
    border-radius: 10px;
    transition: background 0.3s ease;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: #b8985a;
}

/* Para Firefox */
.modal-content {
    scrollbar-width: thin;
    scrollbar-color: #c9a961 #f1f1f1;
}

.tarifas-form {
    padding: 2rem;
    max-height: calc(90vh - 120px);
    overflow-y: auto;
}

/* Scroll personalizado para el formulario */
.tarifas-form::-webkit-scrollbar {
    width: 6px;
}

.tarifas-form::-webkit-scrollbar-track {
    background: transparent;
}

.tarifas-form::-webkit-scrollbar-thumb {
    background: rgba(201, 169, 97, 0.3);
    border-radius: 10px;
    transition: background 0.3s ease;
}

.tarifas-form::-webkit-scrollbar-thumb:hover {
    background: rgba(201, 169, 97, 0.6);
}

@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    background: linear-gradient(135deg, #c9a961, #dbb971);
    color: white;
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    margin: 0;
}

.close {
    font-size: 2rem;
    font-weight: bold;
    cursor: pointer;
    color: white;
    transition: color 0.3s ease;
}

.close:hover {
    color: #333;
}

.tarifas-form {
    padding: 2rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.form-group input,
.form-group select {
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #c9a961;
}

.form-group select option:disabled {
    color: #999;
    background: #f5f5f5;
}

.price-summary {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin: 2rem 0;
}

.price-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding: 0.5rem 0;
}

.price-item.discount {
    color: #28a745;
    font-weight: 600;
}

.price-item.total {
    border-top: 2px solid #c9a961;
    font-weight: bold;
    font-size: 1.1rem;
    color: #c9a961;
    margin-top: 1rem;
    padding-top: 1rem;
}

.form-actions {
    margin-top: 2rem;
}

/* WhatsApp flotante */
.whatsapp-float {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 999;
}

.whatsapp-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #25D366;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 4px 20px rgba(37, 211, 102, 0.3);
    transition: all 0.3s ease;
}

.whatsapp-btn:hover {
    background: #128C7E;
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(37, 211, 102, 0.4);
}

.whatsapp-icon {
    font-size: 1.2rem;
}

/* Scroll to top */
.scroll-top {
    position: fixed;
    bottom: 2rem;
    left: 2rem;
    background: #c9a961;
    color: white;
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 999;
}

.scroll-top.visible {
    opacity: 1;
    visibility: visible;
}

.scroll-top:hover {
    background: #b8985a;
    transform: translateY(-3px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .hamburger {
        display: flex;
    }
    
    .nav-menu {
        position: fixed;
        left: -100%;
        top: 70px;
        flex-direction: column;
        background-color: rgba(255, 255, 255, 0.98);
        width: 100%;
        text-align: center;
        transition: 0.3s;
        box-shadow: 0 10px 27px rgba(0, 0, 0, 0.05);
        padding: 2rem 0;
        gap: 1rem;
    }
    
    .nav-menu.active {
        left: 0;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-large {
        width: 100%;
        max-width: 300px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .ofertas-grid {
        grid-template-columns: 1fr;
    }
    
    .rooms-grid {
        grid-template-columns: 1fr;
    }
    
    .room-card.featured {
        transform: none;
    }
    
    .experiences-grid {
        grid-template-columns: 1fr;
    }
    
    .services-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .stats-grid-main {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .gallery-grid {
        grid-template-columns: 1fr;
    }
    
    .gallery-item.large {
        grid-column: span 1;
        grid-row: span 1;
    }
    
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .map-popup {
        bottom: 70px;
        right: 10px;
        left: 10px;
        min-width: auto;
    }
    
    .map-options-btn {
        bottom: 15px;
        right: 15px;
        padding: 0.7rem 1rem;
    }
    
    .map-text {
        font-size: 0.9rem;
    }
    
    .map-popup-actions {
        padding: 0.5rem 1rem 1rem;
    }
    
    .map-nav-btn {
        padding: 0.7rem 0.8rem;
        font-size: 0.9rem;
    }
    
    .reservation-content {
        flex-direction: column;
        text-align: center;
    }
    
    .reservation-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-links {
        flex-direction: column;
        gap: 1rem;
    }
    
    .whatsapp-text {
        display: none;
    }
    
    .whatsapp-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        justify-content: center;
    }
    
    /* Modal responsive */
    .modal {
        padding: 10px;
    }
    
    .modal-content {
        max-height: 95vh;
        width: 95%;
    }
    
    .modal-header {
        padding: 1.5rem;
    }
    
    .tarifas-form {
        padding: 1.5rem;
        max-height: calc(95vh - 100px);
    }
    
    /* Scroll más delgado en móviles */
    .tarifas-form::-webkit-scrollbar {
        width: 4px;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 1rem;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .nav-container {
        padding: 1rem;
    }
    
    .stats-grid-main {
        grid-template-columns: 1fr;
    }
}

/* Animaciones de entrada */
.fade-in {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s ease;
}

.fade-in.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Loading spinner para imágenes */
.loading {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>

<script>
// Variables globales
let currentSlide = 0;
let currentTestimonialIndex = 1;

// Datos dinámicos desde PHP (con valores por defecto seguros)
const tiposHabitacion = <?= json_encode($tipos_habitacion) ?>;
const ofertasActivas = <?= json_encode($ofertas_activas) ?>;

// Inicialización cuando la página carga
document.addEventListener('DOMContentLoaded', function() {
    initializeSlider();
    initializeNavbar();
    initializeModal();
    initializeScrollEffects();
    initializePriceCalculator();
    initializeMobileMenu();
    initializeAnimations();
});

// Slider del hero
function initializeSlider() {
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length === 0) return;
    
    setInterval(() => {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }, 5000);
}

// Navbar scroll effect
function initializeNavbar() {
    const navbar = document.getElementById('navbar');
    const navLinks = document.querySelectorAll('.nav-link');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Active link highlighting
        let current = '';
        const sections = document.querySelectorAll('section[id]');
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 150;
            if (window.scrollY >= sectionTop) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    });
}

// Modal de tarifas
function initializeModal() {
    const modal = document.getElementById('modal-tarifas');
    const openButtons = document.querySelectorAll('#open-tarifas, #check-rates, #check-rates-2');
    const closeButton = document.getElementById('close-modal');
    
    openButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    });
    
    closeButton.addEventListener('click', () => {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
}

// Efectos de scroll
function initializeScrollEffects() {
    const scrollTopBtn = document.getElementById('scrollTop');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    });
    
    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Calculadora de precios con descuentos
function initializePriceCalculator() {
    const checkinInput = document.getElementById('checkin');
    const checkoutInput = document.getElementById('checkout');
    const habitacionSelect = document.getElementById('habitacion');
    const nochesTotalSpan = document.getElementById('noches-total');
    const precioNocheSpan = document.getElementById('precio-noche');
    const precioTotalSpan = document.getElementById('precio-total');
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    checkinInput.min = today;
    checkoutInput.min = today;
    
    function calculatePrice() {
        const checkin = new Date(checkinInput.value);
        const checkout = new Date(checkoutInput.value);
        const selectedOption = habitacionSelect.options[habitacionSelect.selectedIndex];
        
        if (checkin && checkout && checkin < checkout && selectedOption.value) {
            const nights = Math.ceil((checkout - checkin) / (1000 * 60 * 60 * 24));
            const pricePerNight = parseInt(selectedOption.dataset.precio);
            let total = nights * pricePerNight;
            
            // Aplicar descuento si hay ofertas activas
            if (ofertasActivas.length > 0) {
                const descuento = ofertasActivas[0].descuento;
                total = total * (1 - descuento / 100);
            }
            
            nochesTotalSpan.textContent = nights;
            precioNocheSpan.textContent = `${pricePerNight.toLocaleString()}`;
            precioTotalSpan.textContent = `${Math.round(total).toLocaleString()}`;
        } else {
            nochesTotalSpan.textContent = '0';
            precioNocheSpan.textContent = '$0';
            precioTotalSpan.textContent = '$0';
        }
    }
    
    checkinInput.addEventListener('change', () => {
        if (checkinInput.value) {
            const nextDay = new Date(checkinInput.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkoutInput.min = nextDay.toISOString().split('T')[0];
            calculatePrice();
        }
    });
    
    checkoutInput.addEventListener('change', calculatePrice);
    habitacionSelect.addEventListener('change', calculatePrice);
}

// Menu móvil
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

// Animaciones de entrada
function initializeAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    document.querySelectorAll('.room-card, .experience-card, .service-item, .gallery-item, .oferta-card').forEach(el => {
        el.classList.add('fade-in');
        observer.observe(el);
    });
}

// Funciones de interacción con habitaciones
function showRoomDetails(roomId) {
    const room = tiposHabitacion.find(t => t.id_tipo == roomId);
    if (room) {
        alert(`Detalles de ${room.nombre}:\n\nCapacidad: ${room.capacidad} personas\nPrecio: ${parseInt(room.precio_noche).toLocaleString()}/noche\nDisponibles: ${room.habitaciones_disponibles}\n\n${room.descripcion}`);
    }
}

function bookRoom(roomId) {
    window.location.href = `reservas.php?tipo=${roomId}`;
}

function continueReservation() {
    const checkin = document.getElementById('checkin').value;
    const checkout = document.getElementById('checkout').value;
    const habitacion = document.getElementById('habitacion').value;
    const huespedes = document.getElementById('huespedes').value;
    
    if (!checkin || !checkout || !habitacion || !huespedes) {
        alert('Por favor complete todos los campos antes de continuar.');
        return;
    }
    
    let url = `reservas.php?checkin=${checkin}&checkout=${checkout}&tipo=${habitacion}&huespedes=${huespedes}`;
    
    // Agregar oferta si está disponible
    if (ofertasActivas.length > 0) {
        url += `&oferta=${ofertasActivas[0].id_oferta}`;
    }
    
    window.location.href = url;
}

// Funciones globales
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Popup del mapa
function initializeMapPopup() {
    const mapOptionsBtn = document.getElementById('mapOptionsBtn');
    const mapPopup = document.getElementById('mapPopup');
    const mapPopupClose = document.getElementById('mapPopupClose');
    
    if (mapOptionsBtn && mapPopup && mapPopupClose) {
        mapOptionsBtn.addEventListener('click', () => {
            mapPopup.classList.add('active');
        });
        
        mapPopupClose.addEventListener('click', () => {
            mapPopup.classList.remove('active');
        });
        
        // Cerrar al hacer click fuera del popup
        document.addEventListener('click', (e) => {
            if (!mapPopup.contains(e.target) && !mapOptionsBtn.contains(e.target)) {
                mapPopup.classList.remove('active');
            }
        });
    }
}

// Inicializar popup del mapa
document.addEventListener('DOMContentLoaded', initializeMapPopup);

function currentTestimonial(n) {
    const testimonials = document.querySelectorAll('.testimonial');
    const dots = document.querySelectorAll('.dot');
    
    if (testimonials.length === 0) return;
    
    testimonials.forEach(testimonial => testimonial.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    if (testimonials[n - 1]) {
        testimonials[n - 1].classList.add('active');
    }
    if (dots[n - 1]) {
        dots[n - 1].classList.add('active');
    }
    
    currentTestimonialIndex = n;
}

// Auto-play testimonials
if (document.querySelectorAll('.testimonial').length > 0) {
    setInterval(() => {
        const totalTestimonials = document.querySelectorAll('.testimonial').length;
        if (totalTestimonials > 0) {
            currentTestimonialIndex = currentTestimonialIndex >= totalTestimonials ? 1 : currentTestimonialIndex + 1;
            currentTestimonial(currentTestimonialIndex);
        }
    }, 8000);
}

// Lazy loading de imágenes
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.classList.add('loading');
                
                img.onload = () => {
                    img.classList.remove('loading');
                };
                
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Inicializar lazy loading
document.addEventListener('DOMContentLoaded', initializeLazyLoading);

// Error handling para imágenes
document.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') {
        e.target.src = 'images/fondo_prueba.jpg'; // Imagen de respaldo
        e.target.alt = 'Imagen no disponible';
    }
}, true);

// Performance optimization
window.addEventListener('load', () => {
    // Preload critical images
    const criticalImages = [
        'images/fondo_prueba.jpg',
        'images/hotel-frente.jpg'
    ];
    
    criticalImages.forEach(src => {
        const img = new Image();
        img.src = src;
    });
});

console.log('🏨 Hotel Rivo - Index corregido cargado correctamente');
console.log('📊 Datos cargados:', {
    habitaciones: tiposHabitacion.length,
    ofertas: ofertasActivas.length,
    usuario_logueado: <?= $is_logged_in ? 'true' : 'false' ?>,
    es_admin: <?= $is_admin ? 'true' : 'false' ?>,
    conexion_bd: <?= $connection_error ? 'false' : 'true' ?>
});
</script>

</body>
</html>