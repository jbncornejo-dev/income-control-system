<!-- resources/js/components/ui/PublicNavbar.vue -->
<template>
    <nav class="navbar">
        <div class="logo">TIS • EVALUACIONES</div>
        <ul class="nav-links">
            <li><a href="#inicio">Inicio</a></li>
            <li><a href="#tutorial">Tutorial</a></li>
            <!-- Si usas Inertia, considera cambiar <a> por <Link> -->
            <li><a href="/login">Iniciar sesión</a></li>
        </ul>
    </nav>
</template>

<script setup>
// Si utilizas Inertia.js para la navegación, importa Link:
import { Link } from '@inertiajs/vue3';
</script>

<style scoped>
/* Estilos extraídos de index-styles_2.css */
.navbar {
    position: relative;
    width: 100%;
    height: 85px;
    flex-shrink: 0;
    padding: 0 4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--color-umss-dark); /* Usando el token global */
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
    z-index: 9999;
}

.logo {
    font-family: 'Orbitron', sans-serif;
    color: var(--color-cream, #FFFFFF); /* Fallback si el token no carga */
    font-weight: 700;
    letter-spacing: 2px;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 2rem;
}

.nav-links a {
    color: var(--color-cream, #FFFFFF);
    text-decoration: none;
    font-size: 1.4rem;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-family: 'Rajdhani', sans-serif;
}
.nav-links a { font-weight: 600; opacity: 0.9; transition: opacity 0.2s ease, color 0.2s ease; }
.nav-links a:hover { opacity: 1; color: #ffffff; }
</style>