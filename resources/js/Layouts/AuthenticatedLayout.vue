<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ToastContainer from '../components/ui/ToastContainer.vue';
import '../../css/layout.css';

const page = usePage();

// Guard: evita que la app truene si auth.user no llega (sesión expirada, etc.)
const user = computed(() => page.props.auth?.user ?? null);
const role = computed(() => user.value?.role ?? null);

// Definición centralizada de navegación por rol.
// Agregar/quitar un link o un rol es cambiar este arreglo, no el template.
const navItems = [
  { label: 'Inicio', href: '/dashboard', roles: ['admin', 'docente', 'control', 'estudiante'] },
  { label: 'Usuarios', href: '/usuarios', roles: ['admin'] },
  { label: 'Estudiantes', href: '/estudiantes', roles: ['admin'] },
  { label: 'Ambientes', href: '/ambientes', roles: ['admin'] },
  { label: 'Gestión Exámenes', href: '/examenes', roles: ['admin', 'docente'] },
  { label: 'Registrar Ingreso', href: '/ingreso/registrar', roles: ['control'], highlight: true },
  { label: 'Mis Exámenes', href: '/mis-examenes', roles: ['estudiante'] },
];

// Solo se recalcula cuando cambia el rol, no re-evalúa condicionales en el template
const visibleNavItems = computed(() =>
  navItems.filter((item) => role.value && item.roles.includes(role.value))
);

function logout() {
  router.post('/logout');
}
</script>

<template>
  
  <div class="app-layout">
    <!-- NAVEGACIÓN LATERAL -->
    <aside class="sidebar">
      <div class="brand">
        <h2>Control CIE</h2>
      </div>
      <nav class="nav-menu">
        <Link
          v-for="item in visibleNavItems"
          :key="item.href"
          :href="item.href"
          class="nav-link"
          :class="{ highlight: item.highlight }"
        >
          {{ item.label }}
        </Link>
      </nav>
    </aside>

    <!-- ÁREA PRINCIPAL -->
    <div class="main-wrapper">
      <header class="topbar">
        <div v-if="user" class="user-profile">
          <span class="user-name">{{ user.name }}</span>
          <span class="role-badge">{{ user.role }}</span>
        </div>

        <button class="btn-logout" @click="logout">
          Cerrar sesión
        </button>
      </header>

      <main class="content-area">
        <slot />
      </main>
    </div>
    <ToastContainer />
  </div>
</template>
