<template>
  <div class="layout-wrapper">
    <nav class="sidebar">
      <h2 class="brand">Sistema de Ingreso</h2>
      <ul class="menu">
        <li><Link href="/dashboard">Dashboard</Link></li>
        <li><Link href="/estudiantes">Estudiantes</Link></li>
      </ul>
      <button class="btn-secondary" @click="logout">Salir</button>
    </nav>
    <main class="content">
      <slot />
    </main>
    <ToastContainer />
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import ToastContainer from '../components/ui/ToastContainer.vue'

function logout() {
  router.post('/logout')
}
</script>