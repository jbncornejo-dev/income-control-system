<template>
  <AuthenticatedLayout>
    <div>
      <h1 style="color: var(--color-primary);">Panel Principal</h1>
      <p>Bienvenido al Sistema de Control de Ingreso a Exámenes Masivos.</p>

      <div style="display: flex; gap: 12px; margin-top: 20px;">
        <button class="btn-primary" @click="toastStore.success('Guardado correctamente')">
          Probar Toast Éxito
        </button>
        <button class="btn-danger" @click="toastStore.error('Ocurrió un error')">
          Probar Toast Error
        </button>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useToastStore } from '@/stores/useToastStore'

const toastStore = useToastStore()
</script>