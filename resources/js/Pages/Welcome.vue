<!-- resources/js/Pages/Welcome.vue -->
<template>
    <div class="landing-page">
        <!-- Uso del Navbar reutilizable -->
        <PublicNavbar />

        <!-- HERO SECTION -->
        <header class="hero-section">
            <div class="hero-container">
                <!-- Letras gigantes de fondo -->
                <div class="background-letters">
                    <span class="letter-top-left">U</span>
                    <span class="letter-top-right">M</span>
                    <span class="letter-bottom-left">S</span>
                    <span class="letter-bottom-right">S</span>
                </div>

                <!-- Círculo de acento central -->
                <div class="circle-accent">
                    <div class="circle-content">
                        <h2>Gestión segura.<br>Validación en tiempo real.<br>Control riguroso.</h2>
                        
                        <!-- Uso de nuestro componente atómico Button -->
                        <!-- Usamos la variante 'hero' si queremos un estilo específico, o customizamos el slot -->
                        <Button variant="outline" class="btn-hero-custom" @click="goToLogin">
                            Ingresar al Sistema
                        </Button>
                    </div>
                </div>
            </div>
        </header>

        <!-- SECCIÓN TUTORIAL -->
        <section id="tutorial" class="tutorial-section">
            <div class="tutorial-header">
                <h2>Tutorial de Uso</h2>
                <p>La interfaz debe ser muy sencilla e intuitiva, permitiendo que el personal pueda registrar y verificar ingresos rápidamente.</p>
            </div>
            
            <div class="tutorial-grid">
                <div class="step-card">
                    <span class="step-number">01</span>
                    <h3>Autenticación</h3>
                    <p>Acceso protegido mediante mecanismos de autenticación y autorización para el personal.</p>
                </div>
                <div class="step-card">
                    <span class="step-number">02</span>
                    <h3>Escaneo</h3>
                    <p>Lectura inmediata del código del estudiante. El sistema debe procesar un gran número de registros de ingreso en periodos cortos.</p>
                </div>
                <div class="step-card">
                    <span class="step-number">03</span>
                    <h3>Confirmación</h3>
                    <p>Registro automático de la fecha y hora en que cada estudiante ingresa al examen.</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import PublicNavbar from '@/components/ui/PublicNavbar.vue';
import Button from '@/components/ui/Button.vue';
//import { router } from '@inertiajs/vue3'; // Descomentar si usas Inertia

const goToLogin = () => {
    // Si usas Inertia:
    // router.visit('/login');
    window.location.href = '/login'; // Fallback estándar
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&display=swap");
/* Importa las fuentes necesarias solo para esta vista, o muévelas a tu layout principal */
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Playfair+Display:wght@600;700;900&family=Rajdhani:wght@300;400;600;700&display=swap');

.landing-page {
    /* Nos aseguramos de que herede las fuentes correctas */
    font-family: 'Rajdhani', sans-serif;
    color: var(--color-umss-dark);
}

/* --- HERO SECTION (Estilos de index-styles_2.css) --- */
.hero-section {
    position: relative;
    height: calc(100vh - 85px); /* Ajuste: 100vh menos la altura del navbar */
    color: var(--color-cream);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.hero-container {
    position: relative;
    width: 100%;
    flex: 1;
    /* Asegúrate de que la ruta de la imagen sea correcta según tu carpeta public/ */
    background-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.6) 30%, rgba(0, 0, 0, 0.6) 100%), url('/paseoUniversitario.jpg');
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.background-letters {
    position: absolute;
    width: 100%;
    height: 100%;
    pointer-events: none;
    user-select: none;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
    padding: 2vh 4vw;
    z-index: 1;
    opacity: 0.8;
}

.background-letters span {
    font-family: 'Playfair Display', serif;
    font-size: 22vw;
    font-weight: 900;
    color: var(--color-cream);
}

.letter-top-left { justify-self: start; align-self: start; line-height: 0.8; }
.letter-top-right { justify-self: end; align-self: start; line-height: 0.8; }
.letter-bottom-left { justify-self: start; align-self: end; line-height: 0.8; }
.letter-bottom-right { justify-self: end; align-self: end; line-height: 0.8; }

.circle-accent {
    position: relative;
    width: 450px;
    height: 450px;
    background-color: rgba(227, 6, 19, 0.6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 20px 50px rgba(227, 6, 19, 0.25);
    z-index: 2;
}

.circle-content {
    padding: 3rem;
}

.circle-content h2 {
    font-family: 'Rajdhani', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 2rem;
    line-height: 1.3;
    color: var(--color-cream); /* <-- Agregamos explícitamente el color blanco marfil */
}

/* Sobrescribiendo estilos específicos para el botón en el hero */
.btn-hero-custom {
    padding: 12px 30px;
    background-color: var(--color-cream, #fff) !important;
    color: #000000 !important;
    border-radius: 30px;
    transition: transform 0.3s, box-shadow 0.3s;
}

.btn-hero-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
    background-color: var(--color-cream, #fff) !important; /* Mantiene el fondo blanco */
    opacity: 1 !important;
}

/* --- SECCIÓN TUTORIAL --- */
.tutorial-section {
    padding: 100px 4rem;
    background-color: var(--color-gray-light);
}

.tutorial-header {
    text-align: center;
    margin-bottom: 4rem;
}

.tutorial-header h2 {
    font-family: 'Orbitron', sans-serif;
    font-size: 2.5rem;
    color: var(--color-umss-dark);
    margin-bottom: 1rem;
}

.tutorial-header p {
    color: #334155;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

.tutorial-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 3rem;
    max-width: 1200px;
    margin: 0 auto;
}

.step-card {
    background-color: var(--color-cream);
    padding: 3rem 2rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border-bottom: 5px solid var(--color-umss-accent);
    transition: transform 0.3s;
}

.step-card:hover {
    transform: translateY(-10px);
}

.step-number {
    display: inline-block;
    font-family: 'Orbitron', sans-serif;
    font-size: 3rem;
    font-weight: 900;
    color: var(--color-umss-taupe);
    margin-bottom: 1rem;
}

.step-card h3 {
    color: var(--color-umss-dark);
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.step-card p {
    color: #334155;
    font-size: 0.95rem;
    line-height: 1.6;
}
.step-card { transition: transform 0.25s ease, box-shadow 0.25s ease; }
.step-card:hover { transform: scale(1.06); box-shadow: 0 12px 30px rgba(0, 33, 67, 0.18); }
.btn-hero-custom:hover { color: #000000 !important; }
.background-letters { pointer-events: none; }
.background-letters span { transition: scale 0.4s ease, text-shadow 0.4s ease, opacity 0.4s ease; }
.circle-accent { transition: scale 0.4s ease, background-color 0.4s ease; }
.circle-accent:hover { scale: 1.06; background-color: rgba(227, 6, 19, 0.7); }
.hero-container:has(.circle-accent:hover) .background-letters span { scale: 1.08; }
.letter-bottom-left, .letter-bottom-right { translate: 0 -40px; transform-origin: bottom center; }
.letter-top-left, .letter-top-right { transform-origin: top center; }
.circle-content h2 { font-family: "Orbitron", var(--font-display), sans-serif !important; font-weight: 700; font-size: 1.55rem; line-height: 1.5; letter-spacing: 0.06em; text-transform: uppercase; text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35); }
.btn-hero-custom { font-family: "Orbitron", var(--font-display), sans-serif !important; font-weight: 700 !important; font-size: 0.8rem !important; letter-spacing: 0.18em !important; text-transform: uppercase !important; padding: 0.9rem 2.2rem !important; border-radius: 0 !important; border: 2px solid #ffffff !important; background: rgba(255, 255, 255, 0.92) !important; clip-path: polygon(12px 0, 100% 0, calc(100% - 12px) 100%, 0 100%); transition: background 0.3s ease, letter-spacing 0.3s ease, scale 0.3s ease !important; }
.btn-hero-custom:hover { background: #ffffff !important; letter-spacing: 0.24em !important; scale: 1.05; }
.tutorial-header p { font-size: 1.2rem; line-height: 1.6; color: #1f2937; }
.step-card p { font-size: 1.1rem; line-height: 1.7; color: #1f2937; }
.step-number { transition: color 0.25s ease; }
.step-card:hover .step-number { color: var(--color-umss-dark); }
</style>