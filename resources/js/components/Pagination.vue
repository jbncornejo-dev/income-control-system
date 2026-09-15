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
.pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-family: var(--font-main); }
.btn-page { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 8px 16px; border-radius: var(--radius-md); cursor: pointer; }
.btn-page:disabled { background-color: #ccc; cursor: not-allowed; }
.page-info { color: var(--color-text-main); font-size: 14px; }
</style>