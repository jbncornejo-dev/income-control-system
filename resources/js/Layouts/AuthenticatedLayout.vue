<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ToastContainer from '../components/ui/ToastContainer.vue';
import '../../css/layout.css';

const page = usePage();

// Guard: evita que la app truene si auth.user no llega (sesión expirada, etc.)
const user = computed(() => page.props.auth?.user ?? null);
// HU7: traducir el rol enviado por Laravel a los nombres del menú existente.
const role = computed(() => ({ administrador: 'admin', 'personal de control de ingreso': 'control' }[user.value?.rol] ?? user.value?.rol ?? null));

// Definición centralizada de navegación por rol.
// Agregar/quitar un link o un rol es cambiar este arreglo, no el template.
const navItems = [
  { label: 'Inicio', href: '/dashboard', roles: ['admin', 'docente', 'control', 'estudiante'] },
  { label: 'Usuarios', href: '/usuarios', roles: ['admin'] },
  { label: 'Estudiantes', href: '/estudiantes', roles: ['admin'] },
  // HU7: acceso al catálogo real, exclusivo del administrador.
  { label: 'Asignaturas', href: '/asignaturas', roles: ['admin'] },
  { label: 'Ambientes', href: '/ambientes', roles: ['admin'] },
  { label: 'Gestión Exámenes', href: '/examenes', roles: ['admin', 'docente'] },
  { label: 'Registrar Ingreso', href: '/ingreso/registrar', roles: ['control'], highlight: true },
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

function logout() {
  router.post('/logout');
}
</script>

<template>
  <div class="app-layout">
    <!-- NAVEGACIÓN LATERAL -->
    <aside class="sidebar">
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
      <div class="sidebar-footer">
          <p>&copy; {{ new Date().getFullYear() }} TextCorp.<br>Todos los derechos reservados.</p>
      </div>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <div class="main-wrapper">
      <header class="topbar">
        <div class="topbar-title">
          <span class="topbar-section">CIE</span>
          <span class="topbar-separator">/</span>
          <span class="topbar-current">{{ currentMenuLabel }}</span>
        </div>

        <div class="topbar-actions">

          <nav class="topbar-nav">
            <Link href="/dashboard" class="topbar-link">
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
        <p>&copy; 2026 CEI. Desarrollado por Texcorp. Todos los derechos reservados.</p>
      </footer>
    </div>
    <ToastContainer />
  </div>
</template>

<style scoped>
.sidebar {
  width: 260px;
  background-color: var(--bg-sidebar); 
  color: var(--text-white);
  display: flex;
  flex-direction: column;
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
  padding: 0 30px;
  display: flex;
  align-items: center;
  justify-content: space-between;

  /* Adaptado a fondo claro */
  background-color: #ffffff;
  border-bottom: 1px solid var(--border-light, #d1d5db);
}

/* Título / breadcrumb */

.topbar-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
}

.topbar-section {
  color: var(--text-muted, #7b93ab);
  font-weight: 500;
}

.topbar-separator {
  color: #9ca3af;
}

.topbar-current {
  color: var(--text-dark, #333333);
  font-weight: 600;
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
  color: #4b5563; /* Elimina el morado por defecto de los enlaces */
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s ease;
}

.topbar-link:hover {
  color: var(--color-primary, #1d3653);
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
  color: var(--text-dark, #333333);
  font-size: 14px;
  font-weight: 600;
}

.role-badge {
  color: var(--text-muted, #7b93ab);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Cerrar sesión */

.btn-logout {
  padding: 8px 14px;
  background: transparent;
  border: 1px solid var(--border-light, #d1d5db);
  border-radius: 6px;
  color: #4b5563;
  font-size: 13px;
  cursor: pointer;
  transition: 0.2s ease;
}

.btn-logout:hover {
  color: #ef4444; /* Rojo para indicar una acción de salida */
  border-color: #fca5a5;
  background-color: #fef2f2;
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
</style>