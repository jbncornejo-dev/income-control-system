<template>
    <Head title="Control de Ingresos | UMSS" />
    <div class="landing-page antialiased">
        <nav id="navbar" :class="['navbar-fixed', { 'navbar-scrolled': isScrolled }]">
            <div class="navbar-container">
                <div class="brand">
                    <img src="/images/LogoUMSS.png" alt="Logo UMSS" class="brand-logo" />
                    <div class="brand-text">
                        TIS <span class="brand-accent">•</span> EVALUACIONES
                    </div>
                </div>
                
                <ul class="nav-links desktop-nav">
                    <li><a href="#inicio" class="nav-link">Inicio</a></li>
                    <li><a href="#tutorial" class="nav-link">Guía de Uso</a></li>
                    <li>
                        <Link href="/login" class="nav-btn">
                            Acceso Personal
                        </Link>
                    </li>
                </ul>
                
                <button class="mobile-menu-btn" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Menú móvil" :aria-expanded="mobileMenuOpen">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>

            <!-- Menú móvil -->
            <div v-if="mobileMenuOpen" class="mobile-nav">
                <ul class="mobile-nav-links">
                    <li><a href="#inicio" @click="mobileMenuOpen = false">Inicio</a></li>
                    <li><a href="#tutorial" @click="mobileMenuOpen = false">Guía de Uso</a></li>
                    <li><Link href="/login" class="mobile-nav-btn" @click="mobileMenuOpen = false">Acceso Personal</Link></li>
                </ul>
            </div>
        </nav>

        <header id="inicio" class="hero-section">
            <div class="hero-bg">
                <img src="/paseoUniversitario.jpg" alt="Campus Universitario" class="hero-img" />
                <div class="hero-overlay-gradient"></div>
                <div class="hero-overlay-mobile"></div>
            </div>

            <div class="hero-content">
                <div class="hero-content-inner reveal">
                    <h1 class="hero-title">
                        Control de <br>
                        <span class="hero-title-accent">Ingresos</span>
                    </h1>
                    <h2 class="hero-subtitle">Estudiantes</h2>
                    
                    <div class="hero-description-box">
                        <div class="hero-description-accent"></div>
                        <p class="hero-description">
                            Gestión segura, validación en tiempo real y control riguroso para los procesos de evaluación de la universidad.
                        </p>
                    </div>
                    
                    <div class="hero-actions">
                        <Link href="/login" class="btn-tech shadow-lg">
                            <span>Ingresar al Sistema</span>
                            <svg class="icon-right" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <section id="tutorial" class="protocol-section">
            <div class="protocol-container">
                <div class="protocol-header reveal">
                    <h2 class="protocol-title">Protocolo de Acceso</h2>
                    <div class="protocol-divider"></div>
                    <p class="protocol-description">
                        Interfaz diseñada para alta demanda. Permite al personal registrar y verificar el flujo de ingresos de manera eficiente y sin cuellos de botella.
                    </p>
                </div>

                <div class="protocol-grid">
                    <div class="protocol-connector"></div>

                    <div class="step-card reveal delay-100">
                        <div class="step-icon-wrapper">
                            <span class="step-number">01</span>
                        </div>
                        <h3 class="step-title">Autenticación</h3>
                        <p class="step-text">
                            Acceso protegido mediante credenciales institucionales. El sistema valida la identidad del evaluador antes de habilitar el terminal.
                        </p>
                    </div>

                    <div class="step-card reveal delay-200">
                        <div class="step-icon-wrapper">
                            <span class="step-number">02</span>
                        </div>
                        <h3 class="step-title">Escaneo Rápido</h3>
                        <p class="step-text">
                            Lectura inmediata de códigos QR o credenciales. Optimizado para procesar grandes volúmenes de estudiantes en segundos.
                        </p>
                    </div>

                    <div class="step-card reveal delay-300">
                        <div class="step-icon-wrapper">
                            <span class="step-number">03</span>
                        </div>
                        <h3 class="step-title">Confirmación</h3>
                        <p class="step-text">
                            Registro automático en la base de datos central. Guarda un log exacto de fecha, hora y puerta de ingreso para auditorías.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="public-footer">
            <div class="footer-container">
                <div class="footer-brand">
                    TIS <span class="brand-accent">•</span> EVALUACIONES
                </div>
                <p class="footer-copy">
                    &copy; 2026 Universidad Mayor de San Simón. Todos los derechos reservados.
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, Head } from '@inertiajs/vue3';

const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
let observer = null;

const handleScroll = () => {
    isScrolled.value = window.scrollY > 50;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    
    // Intersection Observer
    const revealElements = document.querySelectorAll('.reveal');
    const revealOptions = {
        threshold: 0.15,
        rootMargin: "0px 0px -50px 0px"
    };

    observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                obs.unobserve(entry.target);
            }
        });
    }, revealOptions);

    revealElements.forEach(el => observer.observe(el));
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    if (observer) {
        observer.disconnect();
    }
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;600;700;900&display=swap');

.landing-page {
    font-family: 'Inter', sans-serif;
    background-color: #F8F9FA;
    color: #1f2937;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

h1, h2, h3, h4, .font-display {
    font-family: 'Orbitron', sans-serif;
    letter-spacing: 0.05em;
}

/* NAVBAR */
.navbar-fixed {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 50;
    transition: all 0.3s ease;
    padding: 1.5rem 1.5rem;
    color: #ffffff;
}
@media (min-width: 1024px) {
    .navbar-fixed { padding: 1.5rem 3rem; }
}
.navbar-scrolled {
    background-color: rgba(10, 25, 47, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.navbar-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1400px;
    margin: 0 auto;
}
.brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.brand-logo {
    width: 48px;
    height: 48px;
    object-fit: contain;
}
.brand-text {
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    letter-spacing: 0.1em;
}
.brand-accent {
    color: #C5A059;
}
.desktop-nav {
    display: none;
    gap: 2rem;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 500;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    list-style: none;
    margin: 0;
    padding: 0;
}
@media (min-width: 768px) {
    .desktop-nav { display: flex; }
}
.nav-link {
    color: #ffffff;
    transition: color 0.2s ease;
    text-decoration: none;
}
.nav-link:hover {
    color: #C5A059;
}
.nav-btn {
    display: inline-block;
    padding: 0.625rem 1.25rem;
    background-color: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    backdrop-filter: blur(4px);
    transition: all 0.3s ease;
    color: #ffffff;
    text-decoration: none;
}
.nav-btn:hover {
    background-color: rgba(255, 255, 255, 0.2);
}
.mobile-menu-btn {
    display: block;
    color: #ffffff;
    background: transparent;
    border: none;
    cursor: pointer;
}
@media (min-width: 768px) {
    .mobile-menu-btn { display: none; }
}
.mobile-menu-btn svg { width: 24px; height: 24px; }
.mobile-nav {
    background-color: rgba(10, 25, 47, 0.98);
    padding: 1rem;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.mobile-nav-links {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    list-style: none;
    padding: 0;
    margin: 0;
}
.mobile-nav-links a {
    color: #ffffff;
    text-decoration: none;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.875rem;
    display: block;
    padding: 0.5rem;
}
.mobile-nav-btn {
    background-color: rgba(255,255,255,0.1);
    text-align: center;
    border-radius: 4px;
}

/* HERO */
.hero-section {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 5rem 1.5rem 0;
    overflow: hidden;
    background-color: #0A192F;
}
.hero-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
}
.hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    opacity: 0.8;
}
.hero-overlay-gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(10, 25, 47, 0.75) 0%, rgba(10, 25, 47, 0.5) 40%, rgba(10, 25, 47, 0.2) 70%, transparent 100%);
}
/*.hero-overlay-mobile {
    position: absolute;
    inset: 0;
    background-color: rgba(10, 25, 47, 0.6);
    display: none;
}*/
@media (max-width: 767px) {
    .hero-overlay-mobile { display: block; }
}
.hero-content {
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    position: relative;
    z-index: 10;
}
.hero-content-inner {
    max-width: 48rem;
}
.hero-title {
    font-size: 3rem;
    line-height: 1.1;
    font-family: 'Orbitron', sans-serif;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: -0.025em;
}
@media (min-width: 768px) { .hero-title { font-size: 3.75rem; } }
@media (min-width: 1024px) { .hero-title { font-size: 4.5rem; } }
.hero-title-accent {
    color: #A12B33;
    text-shadow: 0 0 40px rgba(161, 43, 51, 0.5);
}
.hero-subtitle {
    font-size: 1.875rem;
    font-family: 'Orbitron', sans-serif;
    font-weight: 300;
    color: #d1d5db;
    letter-spacing: 0.15em;
    margin-bottom: 2.5rem;
    text-transform: uppercase;
}
@media (min-width: 768px) { .hero-subtitle { font-size: 2.25rem; } }
@media (min-width: 1024px) { .hero-subtitle { font-size: 3rem; } }

.hero-description-box {
    position: relative;
    padding-left: 1.5rem;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    margin-bottom: 3rem;
    border-left: 4px solid rgba(29, 54, 83, 0.5);
}
.hero-description-accent {
    position: absolute;
    left: -4px;
    top: 0;
    width: 4px;
    height: 100%;
    background-color: #C5A059;
}
.hero-description {
    color: #d1d5db;
    font-size: 1.125rem;
    font-weight: 300;
    line-height: 1.625;
    max-width: 42rem;
    margin: 0;
}
@media (min-width: 768px) { .hero-description { font-size: 1.25rem; } }

.hero-actions {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    align-items: center;
}
@media (min-width: 640px) {
    .hero-actions { flex-direction: row; align-items: flex-start; }
}
.btn-tech {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    z-index: 1;
    background-color: #A12B33;
    color: #ffffff;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 1rem 2.5rem;
    border-radius: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    width: 100%;
    text-decoration: none;
    border: none;
    cursor: pointer;
}
@media (min-width: 640px) {
    .btn-tech { width: auto; }
}
.btn-tech::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 0%; height: 100%;
    background-color: #8B0000;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: -1;
}
.btn-tech:hover::before {
    width: 100%;
}
.btn-tech:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(161, 43, 51, 0.5);
    color: #ffffff;
}
.icon-right {
    width: 1.25rem;
    height: 1.25rem;
}
.shadow-lg {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* PROTOCOLO DE ACCESO */
.protocol-section {
    padding: 6rem 0;
    background-color: #F8F9FA;
    position: relative;
}
.protocol-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}
@media (min-width: 1024px) {
    .protocol-container { padding: 0 3rem; }
}
.protocol-header {
    text-align: center;
    max-width: 48rem;
    margin: 0 auto 5rem;
}
.protocol-title {
    font-size: 1.875rem;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    color: #0A192F;
    margin-bottom: 1rem;
}
@media (min-width: 768px) { .protocol-title { font-size: 2.25rem; } }
.protocol-divider {
    width: 5rem;
    height: 0.25rem;
    background-color: #C5A059;
    margin: 0 auto 1.5rem;
    border-radius: 9999px;
}
.protocol-description {
    color: #4b5563;
    font-size: 1.125rem;
    line-height: 1.625;
}

.protocol-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    position: relative;
}
@media (min-width: 768px) {
    .protocol-grid { grid-template-columns: repeat(3, 1fr); gap: 2rem; }
}
.protocol-connector {
    display: none;
    position: absolute;
    top: 3rem;
    left: 16.666%;
    right: 16.666%;
    height: 2px;
    background-color: #e5e7eb;
    z-index: 0;
}
@media (min-width: 768px) {
    .protocol-connector { display: block; }
}
.step-card {
    background-color: #ffffff;
    border-radius: 1rem;
    padding: 2rem;
    text-align: center;
    position: relative;
    z-index: 10;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid #f3f4f6;
    border-top: 4px solid transparent;
    transition: all 0.4s ease;
}
.step-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 30px -5px rgba(10, 25, 47, 0.1);
    border-top-color: #C5A059;
}
.step-icon-wrapper {
    width: 6rem;
    height: 6rem;
    margin: 0 auto 1.5rem;
    background-color: #F8F9FA;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
.step-number {
    font-family: 'Orbitron', sans-serif;
    font-size: 2.25rem;
    font-weight: 900;
    background: linear-gradient(135deg, #1D3653, #0A192F);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.step-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0A192F;
    margin-bottom: 0.75rem;
}
.step-text {
    color: #6b7280;
    font-size: 0.875rem;
    line-height: 1.625;
}

/* FOOTER */
.public-footer {
    background-color: #0A192F;
    color: rgba(255, 255, 255, 0.7);
    padding: 2rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}
.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
@media (min-width: 768px) {
    .footer-container { 
        flex-direction: row; 
        justify-content: space-between; 
        padding: 0 3rem;
    }
}
.footer-brand {
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 1rem;
}
@media (min-width: 768px) {
    .footer-brand { margin-bottom: 0; }
}
.footer-copy {
    font-size: 0.875rem;
    margin: 0;
}

/* ANIMACIONES */
.reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
}
.reveal.active {
    opacity: 1;
    transform: translateY(0);
}
.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
.delay-300 { transition-delay: 300ms; }

@media (prefers-reduced-motion: reduce) {
    .reveal {
        transition: none;
        opacity: 1;
        transform: none;
    }
}
</style>