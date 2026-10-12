<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Home, Users, UserSquare2, BookOpen, Building, FileText, Component, ClipboardCheck, AlertCircle, ScanLine, QrCode, ClipboardList, LogOut } from 'lucide-vue-next';
import ToastContainer from '../components/ui/ToastContainer.vue';

const page = usePage();

// Guard: evita que la app truene si auth.user no llega (sesión expirada, etc.)
const user = computed(() => page.props.auth?.user ?? null);
// HU7: traducir el rol enviado por Laravel a los nombres del menú existente.
const role = computed(() => ({ administrador: 'admin', 'personal de control de ingreso': 'control' }[user.value?.rol] ?? user.value?.rol ?? null));

// Definición centralizada de navegación por rol.
// Agregar/quitar un link o un rol es cambiar este arreglo, no el template.
const navItems = [
  { label: 'Inicio', href: '/dashboard', roles: ['admin', 'docente', 'control'], icon: Home },
  { label: 'Usuarios', href: '/usuarios', roles: ['admin'], icon: Users },
  { label: 'Estudiantes', href: '/estudiantes', roles: ['admin'], icon: UserSquare2 },
  { label: 'Asignaturas', href: '/asignaturas', roles: ['admin'], icon: BookOpen },
  { label: 'Ambientes', href: '/ambientes', roles: ['admin'], icon: Building },
  { label: 'Gestión Exámenes', href: '/examenes', roles: ['admin', 'docente'], icon: FileText },
  { label: 'Grupos', href: '/grupos', roles: ['admin'], icon: Component },
  { label: 'Panel Asistencia', href: '/panel-asistencia', roles: ['admin', 'docente', 'control'], icon: ClipboardCheck },
  { label: 'Incidencias', href: '/incidencias', roles: ['admin', 'docente', 'control'], icon: AlertCircle },
  { label: 'Registrar Ingreso', href: '/registro-ingreso', roles: ['control'], highlight: true, icon: ScanLine },
  { label: 'Mi Código QR', href: '/estudiante/panel', roles: ['estudiante'], icon: QrCode },
  { label: 'Mis Exámenes', href: '/mis-examenes', roles: ['estudiante'], icon: ClipboardList },
];

// Solo se recalcula cuando cambia el rol, no re-evalúa condicionales en el template
const visibleNavItems = computed(() =>
  navItems.filter((item) => role.value && item.roles.includes(role.value))
);

const currentMenuLabel = computed(() => {
  const activeItem = navItems.find(item => page.url.startsWith(item.href));
  return activeItem ? activeItem.label : 'Panel Principal';
});



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
        <div class="sidebar-logo-container">
            <span class="sidebar-logo-text">U<span class="text-gold">M</span>SS</span>
        </div>
        <div class="brand-text">
          <h1>CIE</h1>
          <span>{{ role ? role.toUpperCase() + ' PANEL' : 'ADMIN PANEL' }}</span>
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
              <component :is="item.icon" v-if="item.icon" class="sidebar-icon" :size="18" stroke-width="2" />
              <span>{{ item.label }}</span>
            </Link>
          </li>
        </ul>
      </nav>
      
      <div class="sidebar-footer">
        <span>v1.0.0 (Stable)</span>
      </div>
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



          <div v-if="user" class="user-profile">
            <div class="user-info">
              <span class="user-name">{{ user.name }}</span>
              <span class="role-badge">{{ user.rol }}</span>
            </div>
            <div class="user-avatar">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
          </div>

          <button class="btn-logout" @click="logout" title="Cerrar sesión">
            <LogOut :size="18" stroke-width="2" class="logout-icon" />
          </button>

        </div>

      </header>

      <main class="content-area">
        <slot />
      </main>

      <footer class="app-footer">
        <div class="footer-left">
          &copy; {{ new Date().getFullYear() }} <span class="footer-brand">CIE UMSS</span>. Todos los derechos reservados.
        </div>
        <div class="footer-right">
          <span>Desarrollado por</span>
          <div class="texcorp-brand">
            <img src="/images/TexCorp.png" alt="TexCorp" class="texcorp-logo">
            <span class="texcorp-text">TEXCORP</span>
          </div>
        </div>
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
  height: 100vh;
  width: 100%;
  background-color: var(--bg-main);
  overflow: hidden;
}

.main-wrapper {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  min-width: 0; /* Evita que el contenido desborde horizontalmente */
  height: 100vh;
  overflow: hidden;
}

.content-area {
  flex-grow: 1;
  /* El padding se delega a las vistas (.panel-container, .page-container) por ahora */
  display: flex;
  flex-direction: column;
  overflow-y: auto;
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
  width: 288px; /* w-72 */
  background-color: #0A192F; /* dark background from mockup */
  color: var(--text-white);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  height: 100vh;
  z-index: 40;
  transition: transform var(--transition-normal);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header {
  flex-shrink: 0;
  padding: 32px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.sidebar-logo-container {
  width: 64px;
  height: 64px;
  border: 2px solid rgba(197, 160, 89, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  background-color: #0A192F;
  box-shadow: 0 0 15px rgba(197, 160, 89, 0.2);
}

.sidebar-logo-text {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 1.25rem;
  color: #FFFFFF;
  letter-spacing: -0.05em;
}

.text-gold {
  color: #C5A059;
}

.brand-text h1 {
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 900;
  margin: 0;
  letter-spacing: 2px;
}

.brand-text span {
  font-size: 10px;
  font-weight: 500;
  color: #C5A059;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  display: block;
  margin-top: 4px;
}

.sidebar-nav {
  flex-grow: 1;
  min-height: 0;
  margin-top: 20px;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE/Edge */
}

.sidebar-nav::-webkit-scrollbar {
  display: none; /* Chrome/Safari */
}

.sidebar-nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
  padding-bottom: 20px;
}

.sidebar-nav li a {
  display: block;
  padding: 12px 20px 12px 24px;
  text-decoration: none;
  color: #9ca3af;
  font-size: 14px;
  transition: 0.2s ease;
  border-left: 4px solid transparent;
  margin-bottom: 4px;
}

/* Estado activo */
.sidebar-nav li.active a {
  background-color: rgba(255, 255, 255, 0.05);
  color: #ffffff;
  border-left: 4px solid #C5A059;
}

/* Hover para elementos no activos */
.sidebar-nav li:not(.active) a:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.05);
}

.sidebar-footer {
  padding: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
  flex-shrink: 0;
}

.sidebar-footer span {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.3);
  font-weight: 500;
  letter-spacing: 0.05em;
}

/* =========================
   TOPBAR
   ========================= */

.topbar {
  height: 64px;
  padding: 0 var(--spacing-xl);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-white);
  border-bottom: 1px solid var(--border-light);
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  z-index: 30;
  flex-shrink: 0;
}

/* Título / breadcrumb */
.topbar-left {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.topbar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.btn-mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #4b5563;
  cursor: pointer;
  padding: 6px;
  border-radius: var(--radius-sm);
  align-items: center;
  justify-content: center;
}

.topbar-section {
  color: #1f2937;
  font-weight: 700;
  font-family: var(--font-display);
  letter-spacing: 0.05em;
  font-size: 0.875rem;
}

.topbar-separator {
  color: #9ca3af;
  margin: 0 8px;
}

.topbar-current {
  color: var(--color-primary);
  font-weight: 500;
  font-size: 0.875rem;
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
  color: #4b5563;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  transition: color 0.2s ease;
}

.topbar-link:hover {
  color: var(--color-primary);
}

/* Perfil */

.user-profile {
  display: flex;
  align-items: center;
  padding-left: 24px;
  border-left: 1px solid #e5e7eb;
  gap: 12px;
}

.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.user-name {
  color: #111827;
  font-size: 0.875rem;
  font-weight: 700;
}

.role-badge {
  color: #C5A059;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.user-avatar {
  height: 36px;
  width: 36px;
  border-radius: 50%;
  background-color: var(--color-primary);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-family: var(--font-display);
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

/* Cerrar sesión */

.btn-logout {
  padding: 8px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  transition: color 0.2s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.logout-icon {
  flex-shrink: 0;
}

.btn-logout::before {
  content: "Cerrar sesión";
  font-size: 0.875rem;
  font-weight: 500;
}

.btn-logout:hover, .btn-logout:hover::before {
  color: #dc2626;
}

/* =========================
   FOOTER
   ========================= */
.app-footer {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 15px 30px;
  background-color: var(--color-white);
  border-top: 1px solid var(--border-light, #d1d5db);
  flex-shrink: 0;
  box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.02);
  z-index: 20;
}

@media (min-width: 640px) {
  .app-footer {
    flex-direction: row;
  }
}

.footer-left {
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 500;
}

.footer-brand {
  font-family: var(--font-display, 'Orbitron', sans-serif);
  font-weight: 700;
  color: var(--color-primary);
  font-size: 13px;
}

.footer-right {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
  font-size: 12px;
  font-weight: 500;
}

.texcorp-brand {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #374151;
  transition: color 0.2s ease;
  cursor: default;
}

.texcorp-brand:hover {
  color: var(--color-primary);
}

.texcorp-logo {
  height: 16px;
  width: auto;
  object-fit: contain;
}

.texcorp-text {
  font-family: var(--font-display, 'Orbitron', sans-serif);
  font-weight: 700;
  letter-spacing: 0.1em;
  font-size: 11px;
}

.nav-link {
  color: #9ca3af !important;
  font-size: 0.875rem !important;
  font-weight: 500 !important;
  letter-spacing: 0.02em;
  transition: color 0.2s ease;
  display: flex;
  align-items: center;
  gap: 16px;
}
.sidebar-icon {
  opacity: 0.7;
  transition: opacity 0.2s ease, color 0.2s ease;
  flex-shrink: 0;
  margin-top: -1px;
}
.sidebar-nav li:hover .nav-link, .sidebar-nav li.active .nav-link {
  color: #ffffff !important;
}
.sidebar-nav li:hover .sidebar-icon, .sidebar-nav li.active .sidebar-icon {
  opacity: 1;
  color: #C5A059;
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
    background: rgba(10, 25, 47, 0.5); /* Brand dark transparent */
    backdrop-filter: blur(2px);
    z-index: 900;
  }
  
  .btn-mobile-toggle {
    display: flex;
  }
  
  .topbar {
    padding: 0 16px;
  }

  .topbar-title {
    font-size: 13px;
    gap: 6px;
  }

  .user-profile {
    padding-left: 0;
    border-left: none;
    gap: 8px;
  }
  
  .user-info {
    display: none; /* Hide name/role on mobile */
  }

  .user-avatar {
    height: 32px;
    width: 32px;
    font-size: 14px;
  }

  .btn-logout::before {
    display: none; /* Only show icon on mobile */
  }
}
</style>