<div class="price-item total">
                        <span>Total estimado:</span>
                        <span id="precio-total">$0</span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-primary btn-full" onclick="window.location.href='reservas.php'">
                    Continuar con la Reserva
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Botón flotante de WhatsApp -->
<div class="whatsapp-float">
    <a href="https://wa.me/542231234567" target="_blank" class="whatsapp-btn">
        <span class="whatsapp-icon">📱</span>
        <span class="whatsapp-text">WhatsApp</span>
    </a>
</div>

<!-- Botón de scroll to top -->
<button class="scroll-top" id="scrollTop">↑</button>

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

.btn-login {
    background: transparent;
    border: 2px solid #c9a961;
    color: #c9a961;
    padding: 0.6rem 1.5rem;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-login:hover {
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
    margin-bottom: 3rem;
    font-style: italic;
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
}

.room-features span {
    background: #c9a961;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
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
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease;
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

/* Efectos de parallax suave */
.parallax {
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
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
let currentTestimonial = 1;

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
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
    
    closeButton.addEventListener('click', () => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// Efectos de scroll
function initializeScrollEffects() {
    // Scroll to top button
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

// Calculadora de precios
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
            const total = nights * pricePerNight;
            
            nochesTotalSpan.textContent = nights;
            precioNocheSpan.textContent = `${pricePerNight.toLocaleString()}`;
            precioTotalSpan.textContent = `${total.toLocaleString()}`;
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
    
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
    });
    
    // Cerrar menu al hacer click en un link
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        });
    });
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
    
    // Observer para elementos que deben animarse
    document.querySelectorAll('.room-card, .experience-card, .service-item, .gallery-item').forEach(el => {
        el.classList.add('fade-in');
        observer.observe(el);
    });
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

function currentTestimonial(n) {
    const testimonials = document.querySelectorAll('.testimonial');
    const dots = document.querySelectorAll('.dot');
    
    testimonials.forEach(testimonial => testimonial.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    testimonials[n - 1].classList.add('active');
    dots[n - 1].classList.add('active');
    
    currentTestimonial = n;
}

// Auto-play testimonials
setInterval(() => {
    const totalTestimonials = document.querySelectorAll('.testimonial').length;
    currentTestimonial = currentTestimonial >= totalTestimonials ? 1 : currentTestimonial + 1;
    currentTestimonial(currentTestimonial);
}, 8000);

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

// Error handling para imágenes
document.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') {
        e.target.src = 'images/placeholder.jpg'; // Imagen de respaldo
        e.target.alt = 'Imagen no disponible';
    }
}, true);

console.log('🏨 Hotel Rivo - Index mejorado cargado correctamente');
</script>

</body>
</html><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Rivo | Punta Mogotes - Experiencia de Lujo Frente al Mar</title>
    <meta name="description" content="Hotel Rivo en Punta Mogotes, Mar del Plata. Experiencia de lujo frente al mar con habitaciones elegantes, spa y gastronomía de primer nivel.">
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
            <a href="#galeria" class="nav-link">Galería</a>
            <a href="#contacto" class="nav-link">Contacto</a>
            <a href="#" class="nav-link special" id="open-tarifas">Tarifas</a>
            <a href="login.php" class="btn-login">Iniciar Sesión</a>
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
            <span class="hero-welcome">Bienvenido a</span>
            <h1 class="hero-title">Hotel Rivo</h1>
            <p class="hero-subtitle">Una experiencia de lujo frente al mar en Punta Mogotes</p>
            <div class="hero-divider"></div>
            <p class="hero-description">Donde la elegancia se encuentra con el océano</p>
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
                <div class="info-icon">🏆</div>
                <div class="info-content">
                    <h3>5 Estrellas</h3>
                    <p>Servicio de excelencia garantizado</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">🚗</div>
                <div class="info-content">
                    <h3>Valet Parking</h3>
                    <p>Servicio de estacionamiento incluido</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">🍽️</div>
                <div class="info-content">
                    <h3>Restaurante</h3>
                    <p>Gastronomía gourmet internacional</p>
                </div>
            </div>
        </div>
    </div>
</section>

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
            <div class="room-card" data-room="standard">
                <div class="room-image">
                    <img src="images/fondo_prueba.jpg" alt="Habitación Estándar" loading="lazy">
                    <div class="room-overlay">
                        <div class="room-badge">Más Popular</div>
                        <div class="room-actions">
                            <button class="btn-room-details">Ver Detalles</button>
                            <button class="btn-room-book">Reservar</button>
                        </div>
                    </div>
                </div>
                <div class="room-info">
                    <div class="room-header">
                        <h3>Habitación Estándar</h3>
                        <div class="room-price">
                            <span class="price-from">desde</span>
                            <span class="price-amount">$20.000</span>
                            <span class="price-period">/noche</span>
                        </div>
                    </div>
                    <p class="room-description">Elegante habitación con todas las comodidades esenciales y vista parcial al mar</p>
                    <div class="room-amenities">
                        <span class="amenity">📐 25m²</span>
                        <span class="amenity">👥 2 personas</span>
                        <span class="amenity">🌊 Vista parcial</span>
                        <span class="amenity">🛏️ King size</span>
                    </div>
                    <div class="room-features">
                        <span>WiFi gratuito</span>
                        <span>Aire acondicionado</span>
                        <span>Minibar</span>
                        <span>Balcón</span>
                    </div>
                </div>
            </div>

            <div class="room-card featured" data-room="deluxe">
                <div class="room-image">
                    <img src="images/fondo_prueba.jpg" alt="Suite Deluxe" loading="lazy">
                    <div class="room-overlay">
                        <div class="room-badge premium">Recomendado</div>
                        <div class="room-actions">
                            <button class="btn-room-details">Ver Detalles</button>
                            <button class="btn-room-book">Reservar</button>
                        </div>
                    </div>
                </div>
                <div class="room-info">
                    <div class="room-header">
                        <h3>Suite Deluxe</h3>
                        <div class="room-price">
                            <span class="price-from">desde</span>
                            <span class="price-amount">$35.000</span>
                            <span class="price-period">/noche</span>
                        </div>
                    </div>
                    <p class="room-description">Amplia suite con vista panorámica al mar y sala de estar independiente</p>
                    <div class="room-amenities">
                        <span class="amenity">📐 45m²</span>
                        <span class="amenity">👥 4 personas</span>
                        <span class="amenity">🌊 Vista al mar</span>
                        <span class="amenity">🛏️ King + Sofá</span>
                    </div>
                    <div class="room-features">
                        <span>Jacuzzi privado</span>
                        <span>Balcón amplio</span>
                        <span>Room service 24h</span>
                        <span>Champagne de bienvenida</span>
                    </div>
                </div>
            </div>

            <div class="room-card" data-room="presidential">
                <div class="room-image">
                    <img src="images/fondo_prueba.jpg" alt="Suite Presidencial" loading="lazy">
                    <div class="room-overlay">
                        <div class="room-badge luxury">Exclusiva</div>
                        <div class="room-actions">
                            <button class="btn-room-details">Ver Detalles</button>
                            <button class="btn-room-book">Reservar</button>
                        </div>
                    </div>
                </div>
                <div class="room-info">
                    <div class="room-header">
                        <h3>Suite Presidencial</h3>
                        <div class="room-price">
                            <span class="price-from">desde</span>
                            <span class="price-amount">$75.000</span>
                            <span class="price-period">/noche</span>
                        </div>
                    </div>
                    <p class="room-description">La experiencia más exclusiva con terraza privada y servicios premium</p>
                    <div class="room-amenities">
                        <span class="amenity">📐 80m²</span>
                        <span class="amenity">👥 6 personas</span>
                        <span class="amenity">🌊 Vista premium</span>
                        <span class="amenity">🏠 2 dormitorios</span>
                    </div>
                    <div class="room-features">
                        <span>Terraza privada</span>
                        <span>Mayordomo personal</span>
                        <span>Spa en suite</span>
                        <span>Cena gourmet incluida</span>
                    </div>
                </div>
            </div>
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
            <div class="service-item">
                <div class="service-icon">🏊‍♂️</div>
                <h3>Piscina Infinity</h3>
                <p>Piscina climatizada con vista al mar</p>
            </div>
            <div class="service-item">
                <div class="service-icon">🏋️‍♀️</div>
                <h3>Gimnasio 24h</h3>
                <p>Equipamiento de última generación</p>
            </div>
            <div class="service-item">
                <div class="service-icon">🚗</div>
                <h3>Valet Parking</h3>
                <p>Servicio de estacionamiento gratuito</p>
            </div>
            <div class="service-item">
                <div class="service-icon">🛎️</div>
                <h3>Concierge</h3>
                <p>Asistencia personalizada 24/7</p>
            </div>
            <div class="service-item">
                <div class="service-icon">🧳</div>
                <h3>Transfer</h3>
                <p>Traslado desde el aeropuerto</p>
            </div>
            <div class="service-item">
                <div class="service-icon">📶</div>
                <h3>WiFi Premium</h3>
                <p>Internet de alta velocidad gratuito</p>
            </div>
            <div class="service-item">
                <div class="service-icon">🧺</div>
                <h3>Lavandería</h3>
                <p>Servicio de lavado y planchado</p>
            </div>
            <div class="service-item">
                <div class="service-icon">☕</div>
                <h3>Room Service</h3>
                <p>Servicio a la habitación las 24h</p>
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
                <img src="images/fondo_prueba.jpg" alt="Habitación deluxe" loading="lazy">
                <div class="gallery-overlay">
                    <span>Suite Deluxe</span>
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

<!-- Testimonios Section -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Opiniones</span>
            <h2>Huéspedes Satisfechos</h2>
            <div class="section-divider"></div>
            <p>Lo que dicen nuestros huéspedes sobre su experiencia</p>
        </div>
        
        <div class="testimonials-carousel">
            <div class="testimonial active">
                <div class="testimonial-content">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p>"Una experiencia extraordinaria. El servicio es impecable y las vistas son simplemente espectaculares. Definitivamente volveremos."</p>
                    <div class="testimonial-author">
                        <img src="images/fondo_prueba.jpg" alt="Cliente" loading="lazy">
                        <div>
                            <h4>María González</h4>
                            <span>Buenos Aires</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial">
                <div class="testimonial-content">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p>"Hotel Rivo superó todas nuestras expectativas. La atención al detalle y el profesionalismo del staff es excepcional."</p>
                    <div class="testimonial-author">
                        <img src="images/fondo_prueba.jpg" alt="Cliente" loading="lazy">
                        <div>
                            <h4>Carlos Mendoza</h4>
                            <span>Córdoba</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="testimonial">
                <div class="testimonial-content">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p>"El spa es increíble y la gastronomía del restaurante es de nivel mundial. Una estadía perfecta para relajarse."</p>
                    <div class="testimonial-author">
                        <img src="images/fondo_prueba.jpg" alt="Cliente" loading="lazy">
                        <div>
                            <h4>Ana Silva</h4>
                            <span>Rosario</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="testimonial-dots">
            <span class="dot active" onclick="currentTestimonial(1)"></span>
            <span class="dot" onclick="currentTestimonial(2)"></span>
            <span class="dot" onclick="currentTestimonial(3)"></span>
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
                <div class="map-placeholder">
                    <div class="map-content">
                        <h3>🗺️ Mapa Interactivo</h3>
                        <p>Hotel Rivo - Punta Mogotes</p>
                        <button class="btn btn-outline">Ver en Google Maps</button>
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
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 Hotel Rivo - Punta Mogotes. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="#">Términos y Condiciones</a>
                <a href="#">Política de Privacidad</a>
                <a href="login.php">Área de Administración</a>
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
                        <option value="standard" data-precio="20000">Habitación Estándar - $20.000/noche</option>
                        <option value="deluxe" data-precio="35000">Suite Deluxe - $35.000/noche</option>
                        <option value="presidential" data-precio="75000">Suite Presidencial - $75.000/noche</option>
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
                    <div class="price-item total">
                        <span>Total estimado:</span>
                        <span id="precio-total">$0</span>
                    </div>