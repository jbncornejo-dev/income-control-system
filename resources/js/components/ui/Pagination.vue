<template>
  <div class="pagination">
    <button class="btn-page" :disabled="currentPage <= 1" @click="$emit('change-page', currentPage - 1)">Anterior</button>
    <span class="page-info">Página {{ currentPage }} de {{ totalPages }}</span>
    <button class="btn-page" :disabled="currentPage >= totalPages" @click="$emit('change-page', currentPage + 1)">Siguiente</button>
  </div>
</template>

<script setup>
defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true }
});
defineEmits(['change-page']);
</script>

<style scoped>
.pagination { display: flex; justify-content: center; align-items: center; gap: var(--spacing-md); margin-top: var(--spacing-lg); font-family: var(--font-main); }
.btn-page { background-color: var(--color-primary); color: var(--color-white); border: 1px solid transparent; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-md); cursor: pointer; transition: background-color var(--transition-fast); font-family: var(--font-main); }
.btn-page:hover:not(:disabled) { background-color: var(--color-primary-hover); }
.btn-page:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-page:focus-visible { outline: none; box-shadow: var(--shadow-input-focus); }
.page-info { color: var(--color-text-main); font-size: 14px; font-weight: 600; }
</style>