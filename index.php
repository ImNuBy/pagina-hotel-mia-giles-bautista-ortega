<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Rivo | Punta Mogotes</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
    <div class="logo">HOTEL RIVO</div>
    <nav>
        <a href="#inicio">Inicio</a>
        <a href="#habitaciones">Habitaciones</a>
        <a href="#experiencias">Experiencias</a>
        <a href="#galeria">Galería</a>
        <a href="#" id="open-tarifas">Tarifas</a>
        <a href="logout.php" class="logout-btn">Cerrar sesión</a>
    </nav>
</header>

<section id="inicio" class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <h1>Bienvenido al Hotel Rivo</h1>
        <p class="hero-subtitle">Una experiencia de lujo frente al mar en Punta Mogotes</p>
        <div class="hero-divider"></div>
        <p class="hero-description">Donde la elegancia se encuentra con el océano</p>
    </div>
</section>

<section id="habitaciones" class="section">
    <div class="container">
        <div class="section-header">
            <h2>Habitaciones & Suites</h2>
            <div class="section-divider"></div>
            <p>Espacios diseñados para brindar el máximo confort y sofisticación</p>
        </div>
        
        <div class="rooms-grid">
            <div class="room-card luxury-card">
                <div class="room-image">
                    <img src="images/fondo_prueba.jpg" alt="Suite Deluxe">
                    <div class="room-overlay">
                        <div class="room-details">
                            <h4>Ver detalles</h4>
                        </div>
                    </div>
                </div>
                <div class="room-info">
                    <h3>Suite Deluxe</h3>
                    <p>Vista panorámica al mar • 45m² • Balcón privado</p>
                    <div class="room-amenities">
                        <span>Wi-Fi</span>
                        <span>Minibar</span>
                        <span>Aire acondicionado</span>
                    </div>
                </div>
            </div>
            
            <div class="room-card luxury-card">
                <div class="room-image">
                    <img src="images/fondo_prueba.jpg" alt="Habitación Estándar">
                    <div class="room-overlay">
                        <div class="room-details">
                            <h4>Ver detalles</h4>
                        </div>
                    </div>
                </div>
                <div class="room-info">
                    <h3>Habitación Estándar</h3>
                    <p>Comodidad y elegancia • 30m² • Vista jardín</p>
                    <div class="room-amenities">
                        <span>Wi-Fi</span>
                        <span>Escritorio</span>
                        <span>Aire acondicionado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="experiencias" class="section experiences-section">
    <div class="container">
        <div class="section-header">
            <h2>Experiencias</h2>
            <div class="section-divider"></div>
            <p>Momentos únicos que perdurarán en su memoria</p>
        </div>
        
        <div class="experiences-grid">
            <div class="experience-item">
                <div class="experience-icon">🍽️</div>
                <h3>Desayuno Gourmet</h3>
                <p>Comience su día con una selección de productos frescos y locales en nuestro comedor con vista al mar.</p>
            </div>
            
            <div class="experience-item">
                <div class="experience-icon">🌊</div>
                <h3>Acceso Privado a la Playa</h3>
                <p>Disfrute de la tranquilidad de Punta Mogotes con acceso exclusivo a nuestra zona de playa.</p>
            </div>
            
            <div class="experience-item">
                <div class="experience-icon">🌅</div>
                <h3>Atardeceres Únicos</h3>
                <p>Contemple los espectaculares atardeceres desde nuestras terrazas panorámicas.</p>
            </div>
        </div>
    </div>
</section>

<section id="galeria" class="section gallery-section">
    <div class="container">
        <div class="section-header">
            <h2>Galería</h2>
            <div class="section-divider"></div>
            <p>Descubra la belleza de nuestras instalaciones</p>
        </div>
        
        <div class="gallery-grid">
            <div class="gallery-item large">
                <img src="images/fondo_prueba.jpg" alt="Vista principal del hotel">
                <div class="gallery-overlay">
                    <h4>Vista Principal</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Habitación deluxe">
                <div class="gallery-overlay">
                    <h4>Suite Deluxe</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Comedor">
                <div class="gallery-overlay">
                    <h4>Comedor</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Vista al mar">
                <div class="gallery-overlay">
                    <h4>Vista al Mar</h4>
                </div>
            </div>
            <div class="gallery-item">
                <img src="images/fondo_prueba.jpg" alt="Terraza">
                <div class="gallery-overlay">
                    <h4>Terraza</h4>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Sección de reserva al final -->
<section class="reservation-section">
    <div class="reservation-content">
        <div class="reservation-text">
            <h2>Reserve su experiencia</h2>
            <p>Permítanos crear momentos inolvidables durante su estadía en Punta Mogotes</p>
        </div>
        <div class="reservation-actions">
            <button class="btn btn-primary btn-large" onclick="window.location.href='reservas.php'">
                Reservar ahora
            </button>
            <button class="btn btn-secondary btn-large" id="check-rates">
                Consultar tarifas
            </button>
        </div>
    </div>
</section>

<!-- Modal de tarifas -->
<div id="modal-tarifas" class="modal">
    <div class="modal-content luxury-modal">
        <span class="close" id="close-modal">&times;</span>
        <div class="modal-header">
            <h2>Consultar Tarifas</h2>
            <div class="modal-divider"></div>
        </div>
        
        <form id="form-tarifas" class="tarifas-form">
            <div class="form-group">
                <label for="habitacion">Tipo de habitación</label>
                <select id="habitacion" name="habitacion" required>
                    <option value="estandar" data-precio="20000">Habitación Estándar - $20.000 por noche</option>
                    <option value="deluxe" data-precio="35000">Suite Deluxe - $35.000 por noche</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="noches">Cantidad de noches</label>
                <input type="number" id="noches" name="noches" min="1" placeholder="Número de noches" required>
            </div>
            
            <div class="price-summary">
                <div class="price-breakdown">
                    <span>Total estimado</span>
                    <span id="precio-total" class="total-amount">$0</span>
                </div>
            </div>
            
            <button type="button" class="btn btn-primary btn-full" onclick="window.location.href='reservas.php'">
                Continuar con la reserva
            </button>
        </form>
    </div>
</div>

<footer>
    <div class="footer-content">
        <div class="footer-logo">HOTEL RIVO</div>
        <p>&copy; 2025 Hotel Rivo - Punta Mogotes. Todos los derechos reservados.</p>
        <div class="footer-contact">
            <span>Punta Mogotes, Mar del Plata</span>
            <span>+54 223-XXX-XXXX</span>
        </div>
    </div>
</footer>

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
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* Navbar estilo Four Seasons */
.navbar {
    position: fixed;
    width: 100%;
    top: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.2rem 3rem;
    z-index: 999;
    transition: all 0.3s ease;
}

.navbar .logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 700;
    color: #1a1a1a;
    letter-spacing: 2px;
}

.navbar nav {
    display: flex;
    align-items: center;
}

.navbar nav a {
    margin-left: 2.5rem;
    text-decoration: none;
    color: #4a4a4a;
    font-weight: 400;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    position: relative;
}

.navbar nav a:hover {
    color: #c9a961;
}

.navbar nav a::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -5px;
    left: 50%;
    background-color: #c9a961;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.navbar nav a:hover::after {
    width: 100%;
}

.logout-btn {
    background: #c9a961 !important;
    color: white !important;
    padding: 0.5rem 1.2rem !important;
    border-radius: 25px !important;
    margin-left: 2rem !important;
}

.logout-btn:hover {
    background: #b8985a !important;
    transform: translateY(-1px);
}

/* Hero Section estilo Four Seasons */
.hero {
    background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.4)), url('images/hotel-frente.jpg') no-repeat center center/cover;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    position: relative;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.4));
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 800px;
    padding: 0 2rem;
    color: white;
}

.hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 4rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    letter-spacing: 1px;
}

.hero-subtitle {
    font-size: 1.4rem;
    margin-bottom: 2rem;
    font-weight: 300;
    opacity: 0.95;
}

.hero-divider {
    width: 80px;
    height: 2px;
    background: #c9a961;
    margin: 2rem auto;
}

.hero-description {
    font-size: 1.1rem;
    font-style: italic;
    opacity: 0.9;
}

/* Secciones */
.section {
    padding: 8rem 0;
}

.section-header {
    text-align: center;
    margin-bottom: 5rem;
}

.section-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 3rem;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 1rem;
}

.section-divider {
    width: 60px;
    height: 2px;
    background: #c9a961;
    margin: 1.5rem auto 2rem;
}

.section-header p {
    font-size: 1.2rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Habitaciones */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 3rem;
    max-width: 1000px;
    margin: 0 auto;
}

.room-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: all 0.4s ease;
}

.room-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.room-image {
    position: relative;
    overflow: hidden;
    height: 280px;
}

.room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.room-card:hover .room-image img {
    transform: scale(1.05);
}

.room-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.room-card:hover .room-overlay {
    opacity: 1;
}

.room-details h4 {
    color: white;
    font-size: 1.1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.room-info {
    padding: 2rem;
}

.room-info h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
}

.room-info p {
    color: #666;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.room-amenities {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.room-amenities span {
    background: #f8f8f8;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    color: #666;
}

/* Experiencias */
.experiences-section {
    background: #f8f8f8;
}

.experiences-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 3rem;
    max-width: 1000px;
    margin: 0 auto;
}

.experience-item {
    text-align: center;
    padding: 2rem;
}

.experience-icon {
    font-size: 3rem;
    margin-bottom: 1.5rem;
}

.experience-item h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    margin-bottom: 1rem;
    color: #1a1a1a;
}

.experience-item p {
    color: #666;
    line-height: 1.6;
}

/* Galería */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: repeat(3, 200px);
    gap: 1rem;
    max-width: 1000px;
    margin: 0 auto;
}

.gallery-item {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    cursor: pointer;
}

.gallery-item.large {
    grid-column: span 2;
    grid-row: span 2;
}

.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.gallery-item:hover img {
    transform: scale(1.1);
}

.gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: white;
    padding: 1.5rem;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.gallery-item:hover .gallery-overlay {
    transform: translateY(0);
}

/* Sección de reserva */
.reservation-section {
    background: linear-gradient(135deg, #1a1a1a, #2c2c2c);
    color: white;
    padding: 6rem 2rem;
    text-align: center;
}

.reservation-content {
    max-width: 800px;
    margin: 0 auto;
}

.reservation-text h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    margin-bottom: 1rem;
}

.reservation-text p {
    font-size: 1.2rem;
    margin-bottom: 3rem;
    opacity: 0.9;
}

.reservation-actions {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Botones */
.btn {
    display: inline-block;
    padding: 1rem 2rem;
    text-decoration: none;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-primary {
    background: #c9a961;
    color: white;
}

.btn-primary:hover {
    background: #b8985a;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(201, 169, 97, 0.3);
}

.btn-secondary {
    background: transparent;
    color: white;
    border: 2px solid white;
}

.btn-secondary:hover {
    background: white;
    color: #1a1a1a;
}

.btn-large {
    padding: 1.2rem 3rem;
    font-size: 1.1rem;
}

.btn-full {
    width: 100%;
    padding: 1.2rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: white;
    margin: 3% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 12px;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 2.5rem 2.5rem 1rem;
    text-align: center;
}

.modal-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: #1a1a1a;
    margin-bottom: 1rem;
}

.modal-divider {
    width: 50px;
    height: 2px;
    background: #c9a961;
    margin: 0 auto;
}

.close {
    position: absolute;
    top: 1rem;
    right: 1.5rem;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
    transition: color 0.3s ease;
}

.close:hover {
    color: #333;
}

.tarifas-form {
    padding: 1rem 2.5rem 2.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

.form-group select,
.form-group input {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group select:focus,
.form-group input:focus {
    outline: none;
    border-color: #c9a961;
}

.price-summary {
    background: #f8f8f8;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 2rem 0;
}

.price-breakdown {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-amount {
    font-size: 1.5rem;
    font-weight: 600;
    color: #c9a961;
}

/* Footer */
footer {
    background: #1a1a1a;
    color: white;
    padding: 3rem 2rem 2rem;
    text-align: center;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 2px;
    margin-bottom: 1rem;
    color: #c9a961;
}

.footer-contact {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.footer-contact span {
    color: #999;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .navbar {
        padding: 1rem 1.5rem;
    }
    
    .navbar nav a {
        margin-left: 1.5rem;
        font-size: 0.9rem;
    }
    
    .hero h1 {
        font-size: 2.5rem;
    }
    
    .section-header h2 {
        font-size: 2.2rem;
    }
    
    .rooms-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .experiences-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .gallery-grid {
        grid-template-columns: repeat(2, 1fr);
        grid-template-rows: repeat(4, 150px);
    }
    
    .gallery-item.large {
        grid-column: span 2;
        grid-row: span 1;
    }
    
    .reservation-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
    // Abrir y cerrar el modal
    const modal = document.getElementById('modal-tarifas');
    const openModalLinks = [document.getElementById('open-tarifas'), document.getElementById('check-rates')];
    const closeModal = document.getElementById('close-modal');

    openModalLinks.forEach(link => {
        link.onclick = function(e) {
            e.preventDefault();
            modal.style.display = 'block';
        }
    });

    closeModal.onclick = () => modal.style.display = 'none';

    window.onclick = function(event) {
        if (event.target === modal) modal.style.display = 'none';
    }

    // Calcular el total
    function calcularTotal() {
        const habitacion = document.getElementById('habitacion');
        const noches = document.getElementById('noches').value;
        const precio = habitacion.options[habitacion.selectedIndex].getAttribute('data-precio');

        if (noches && precio) {
            const total = parseInt(precio) * parseInt(noches);
            document.getElementById('precio-total').textContent = '$' + total.toLocaleString('es-AR');
        } else {
            document.getElementById('precio-total').textContent = '$0';
        }
    }

    document.getElementById('habitacion').addEventListener('change', calcularTotal);
    document.getElementById('noches').addEventListener('input', calcularTotal);

    // Cambiar navbar al hacer scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
</script>


</body>
</html>