<template>
  <div style="text-align: center; padding: 50px;">
    <h1 style="color: var(--color-secondary);">Acceso Denegado</h1>
    <p>Tu rol actual no tiene permisos para acceder a esta función.</p>
    <Link href="/dashboard">Volver al panel principal</Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>
