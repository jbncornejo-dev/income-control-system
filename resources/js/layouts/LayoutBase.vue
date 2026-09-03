<template>
  <div class="layout-wrapper">
    <nav class="sidebar">
      <h2 class="brand">Sistema de Ingreso</h2>
      <ul class="menu">
        <li><router-link to="/dashboard">Dashboard</router-link></li>
      </ul>
      <button class="btn-secondary">Salir</button>
    </nav>
    <main class="content">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
</script>