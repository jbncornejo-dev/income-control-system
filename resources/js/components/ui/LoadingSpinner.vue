<template>
  <div class="spinner-wrapper" :class="size">
    <div class="spinner"></div>
  </div>
</template>

<script setup>
defineProps({
  size: {
    type: String,
    default: 'medium',
    validator: (v) => ['small', 'medium', 'large'].includes(v)
  }
})
</script>

<style scoped>
.spinner-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
}
.spinner {
  border-radius: 50%;
  border: 3px solid var(--color-bg-input);
  border-top-color: var(--color-primary);
  animation: spin 0.7s linear infinite;
}
/* Tamaños */
.small .spinner  { width: 16px; height: 16px; border-width: 2px; }
.medium .spinner { width: 32px; height: 32px; border-width: 3px; }
.large .spinner  { width: 56px; height: 56px; border-width: 4px; }

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>