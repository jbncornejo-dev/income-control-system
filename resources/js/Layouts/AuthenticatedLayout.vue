<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ToastContainer from '../components/ui/ToastContainer.vue';

const page = usePage();

// Guard: evita que la app truene si auth.user no llega (sesión expirada, etc.)
const user = computed(() => page.props.auth?.user ?? null);
// HU7: traducir el rol enviado por Laravel a los nombres del menú existente.
const role = computed(() => ({ administrador: 'admin', 'personal de control de ingreso': 'control' }[user.value?.rol] ?? user.value?.rol ?? null));

// Definición centralizada de navegación por rol.
// Agregar/quitar un link o un rol es cambiar este arreglo, no el template.
const navItems = [
  { label: 'Inicio', href: '/dashboard', roles: ['admin', 'docente', 'control'] },
  { label: 'Usuarios', href: '/usuarios', roles: ['admin'] },
  { label: 'Estudiantes', href: '/estudiantes', roles: ['admin'] },
  // HU7: acceso al catálogo real, exclusivo del administrador.
  { label: 'Asignaturas', href: '/asignaturas', roles: ['admin'] },
  { label: 'Ambientes', href: '/ambientes', roles: ['admin'] },
  { label: 'Gestión Exámenes', href: '/examenes', roles: ['admin', 'docente'] },
  { label: 'Grupos', href: '/grupos', roles: ['admin'] },
  { label: 'Panel Asistencia', href: '/panel-asistencia', roles: ['admin', 'docente', 'control'] },
  { label: 'Incidencias', href: '/incidencias', roles: ['admin', 'docente', 'control'] },
  { label: 'Registrar Ingreso', href: '/registro-ingreso', roles: ['control'], highlight: true },
  { label: 'Mi Código QR', href: '/estudiante/panel', roles: ['estudiante'] },
  { label: 'Mis Exámenes', href: '/mis-examenes', roles: ['estudiante'] },
];

// Solo se recalcula cuando cambia el rol, no re-evalúa condicionales en el template
const visibleNavItems = computed(() =>
  navItems.filter((item) => role.value && item.roles.includes(role.value))
);

const currentMenuLabel = computed(() => {
  const activeItem = navItems.find(item => page.url.startsWith(item.href));
  return activeItem ? activeItem.label : 'Panel Principal';
});

import { ref } from 'vue';

const isMobileMenuOpen = ref(false);

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

function logout() {
  router.post('/logout');
}
</script>

<template>
  <div class="app-layout">
    <!-- Overlay Móvil -->
    <div 
      v-if="isMobileMenuOpen" 
      class="sidebar-overlay" 
      @click="isMobileMenuOpen = false"
    ></div>

    <!-- NAVEGACIÓN LATERAL -->
    <aside class="sidebar" :class="{ 'sidebar-open': isMobileMenuOpen }">
      <div class="sidebar-header">
        <img src="/images/umss-logo.png" alt="UMSS Logo" class="sidebar-logo" />
        <div class="brand-text">
          <h1>CIE</h1>
          <span>{{ role ? role.toUpperCase() + ' PANEL' : 'PANEL' }}</span>
        </div>
      </div>
      <nav class="sidebar-nav">
        <ul>
          <!-- Iteramos sobre los <li> en lugar de los <Link> directamente -->
          <li
            v-for="item in visibleNavItems"
            :key="item.href"
            :class="{ active: $page.url.startsWith(item.href) }"
          >
            <Link :href="item.href" class="nav-link">
              {{ item.label }}
            </Link>
          </li>
        </ul>
      </nav>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <div class="main-wrapper">
      <header class="topbar">
        <div class="topbar-left">
          <button class="btn-mobile-toggle" @click="toggleMobileMenu" aria-label="Toggle Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
          </button>
          <div class="topbar-title">
          <span class="topbar-section">CIE</span>
          <span class="topbar-separator">/</span>
          <span class="topbar-current">{{ currentMenuLabel }}</span>
          </div>
        </div>

        <div class="topbar-actions">

          <nav class="topbar-nav">
            <Link :href="role === 'estudiante' ? '/estudiante/panel' : '/dashboard'" class="topbar-link">
              Inicio
            </Link>

            <a href="#" @click.prevent class="topbar-link">
              Tutorial
            </a>
          </nav>

          <div v-if="user" class="user-profile">
            <div class="user-info">
              <span class="user-name">{{ user.name }}</span>
              <span class="role-badge">{{ user.rol }}</span>
            </div>
          </div>

          <button class="btn-logout" @click="logout">
            Cerrar sesión
          </button>

        </div>

      </header>

      <main class="content-area">
        <slot />
      </main>

      <footer class="app-footer">
        <p>&copy; {{ new Date().getFullYear() }} CIE. Desarrollado por TexCorp. Todos los derechos reservados.</p>
      </footer>
    </div>
    <ToastContainer />
  </div>
</template>

<style scoped>
/* =========================
   LAYOUT BASE
   ========================= */
.app-layout {
  display: flex;
  min-height: 100vh;
  width: 100%;
  background-color: var(--bg-main);
}

.main-wrapper {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  min-width: 0; /* Evita que el contenido desborde horizontalmente */
}

.content-area {
  flex-grow: 1;
  /* El padding se delega a las vistas (.panel-container, .page-container) por ahora */
  display: flex;
  flex-direction: column;
}

/* =========================
   FIX LEGACY CONTAINERS
   Evitar scroll doble causado por vistas que definen min-height: 100vh
   ========================= */
.content-area :deep(.panel-container),
.content-area :deep(.page-container) {
  min-height: auto !important;
  background-color: transparent !important;
}

/* =========================
   SIDEBAR
   ========================= */
.sidebar {
  width: 260px;
  background-color: var(--bg-sidebar); 
  color: var(--text-white);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  z-index: 40;
  transition: transform var(--transition-normal);
}

.sidebar-header {
  padding: 30px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.sidebar-logo {
  width: 90px;
  height: auto;
  margin-bottom: 15px;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
  transition: transform 0.3s ease;
}

.sidebar-logo:hover {
  transform: scale(1.05);
}

.brand-text h1 {
  font-family: 'Orbitron', sans-serif;
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 5px 0;
  letter-spacing: 2px;
}

.brand-text span {
  font-size: 11px;
  font-weight: 400;
  color: var(--text-muted);
  letter-spacing: 1px;
}

.sidebar-nav {
  flex-grow: 1;
  margin-top: 20px;
}

.sidebar-nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-nav li a {
  display: block;
  padding: 15px 20px 15px 30px;
  text-decoration: none;
  color: var(--text-muted);
  font-size: 15px;
  transition: 0.2s ease;
}

/* Estado activo: Fondo rojo y borde izquierdo */
.sidebar-nav li.active a {
  background-color: var(--color-active);
  color: var(--text-white);
  border-left: 4px solid var(--text-white);
  padding-left: 26px; /* Se restan los 4px del borde para mantener la alineación de 30px */
}

/* Hover para elementos no activos */
.sidebar-nav li:not(.active) a:hover {
  color: var(--text-white);
  background-color: rgba(255, 255, 255, 0.05);
}

/* =========================
   TOPBAR
   ========================= */

.topbar {
  height: 72px;
  padding: 0 var(--spacing-xl);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(115deg, rgba(255, 255, 255, 0) 55%, rgba(255, 255, 255, 0.08) 70%, rgba(255, 255, 255, 0) 85%), linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary) 20%, #2a4f7c 55%, #4d77a3 100%);
  border-bottom: 2px solid var(--color-active);
  box-shadow: 0 2px 0 rgba(77, 119, 163, 0.35), 0 6px 20px rgba(15, 34, 56, 0.3);
  position: sticky;
  top: 0;
  z-index: 30;
  flex-shrink: 0;
}

/* Título / breadcrumb */
.topbar-left {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.btn-mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: var(--color-white);
  cursor: pointer;
  padding: 8px;
  border-radius: var(--radius-sm);
}

.btn-mobile-toggle:hover {
  background: rgba(255, 255, 255, 0.1);
}

.topbar-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
}

.topbar-section {
  color: rgba(255, 255, 255, 0.8);
  font-weight: 700;
  letter-spacing: 0.1em;
  font-size: 1rem;
}

.topbar-separator {
  color: rgba(255, 255, 255, 0.55);
}

.topbar-current {
  color: #ffffff;
  font-weight: 700;
  font-size: 1.15rem;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
}

/* Elementos de la derecha */

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 24px;
}

/* Navegación superior */

.topbar-nav {
  display: flex;
  align-items: center;
  gap: 20px;
}

.topbar-link {
  color: #ffffff;
  text-decoration: none;
  font-size: 1rem;
  font-weight: 600;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
  opacity: 0.9;
  transition: opacity 0.2s ease;
}

.topbar-link:hover {
  opacity: 1;
}

/* Perfil */

.user-profile {
  display: flex;
  align-items: center;
  padding-left: 24px;
  /* Separador adaptado a fondo claro */
  border-left: 1px solid var(--border-light, #d1d5db);
}

.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}

.user-name {
  color: #ffffff;
  font-size: 1rem;
  font-weight: 700;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
}

.role-badge {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.45);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Cerrar sesión */

.btn-logout {
  padding: 8px 14px;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.6);
  border-radius: 6px;
  color: #ffffff;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease;
}

.btn-logout:hover {
  color: #ffffff;
  border-color: var(--color-active, #a12b33);
  background-color: var(--color-active, #a12b33);
}

/* =========================
   FOOTER
   ========================= */
.app-footer {
  text-align: center;
  padding: 15px 30px;
  background-color: #ffffff;
  color: var(--text-muted, #7b93ab);
  font-size: 12px;
  border-top: 1px solid var(--border-light, #d1d5db);
  flex-shrink: 0;
}

.app-footer p {
  margin: 0;
  font-weight: 500;
  letter-spacing: 0.5px;
}
.nav-link {
  color: rgba(255, 255, 255, 0.75) !important;
  font-size: 1.1rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.02em;
  transition: color 0.2s ease;
}
.nav-link:hover, .active .nav-link {
  color: #ffffff !important;
}
.brand-text span {
  color: rgba(255, 255, 255, 0.75);
}

/* =========================
   RESPONSIVE BASE
   ========================= */
@media (max-width: 1024px) {
  .topbar {
    padding: 0 var(--spacing-md);
  }
  
  .content-area {
    padding: var(--spacing-md);
  }
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    transform: translateX(-100%);
    z-index: 910;
  }
  
  .sidebar.sidebar-open {
    transform: translateX(0);
  }
  
  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 900;
  }
  
  .btn-mobile-toggle {
    display: flex;
  }
  
  .topbar-nav {
    display: none; /* Ocultamos los links del topbar en móvil para ahorrar espacio */
  }
  
  .user-profile {
    padding-left: 0;
    border-left: none;
  }
  
  .user-name {
    display: none; /* Ocultamos el nombre para ahorrar espacio, solo mostramos el rol o nada */
  }
}
</style>