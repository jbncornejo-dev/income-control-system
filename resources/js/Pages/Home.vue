<template>
  <AuthenticatedLayout>
    <div style="text-align:center; padding: 40px;">
      <button class="btn-primary">Verifica Estudiante</button>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
</script>

<style scoped>
.btn-primary { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); }
</style>
