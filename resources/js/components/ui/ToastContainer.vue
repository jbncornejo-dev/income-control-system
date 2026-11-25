<template>
  <div class="toast-container">
    <div
      v-for="toast in toastStore.toasts"
      :key="toast.id"
      :class="['toast', toast.type]"
    >
      <span>{{ toast.msg }}</span>
      <button @click="toastStore.remove(toast.id)">✕</button>
    </div>
  </div>
</template>

<script setup>
import { useToastStore } from '../../stores/useToastStore';
const toastStore = useToastStore();
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: var(--spacing-lg);
  right: var(--spacing-lg);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}
.toast {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-md);
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-md);
  min-width: 280px;
  font-family: var(--font-main);
  font-size: 14px;
  box-shadow: var(--shadow-elevated);
  animation: slideIn var(--transition-normal);
}
.toast.success {
  background-color: var(--color-success);
  color: var(--color-white);
}
.toast.error {
  background-color: var(--color-danger);
  color: var(--color-white);
}
.toast button {
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
  border-radius: 50%;
  transition: background-color var(--transition-fast);
}
.toast button:hover {
  background-color: rgba(255, 255, 255, 0.2);
}
.toast button:focus-visible {
  outline: 2px solid var(--color-white);
  outline-offset: 2px;
}
@keyframes slideIn {
  from { opacity: 0; transform: translateX(50px); }
  to   { opacity: 1; transform: translateX(0); }
}
</style>