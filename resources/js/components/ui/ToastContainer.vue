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
import { useToastStore } from '../../stores/useToastStore'
const toastStore = useToastStore()
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.toast {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 6px;
  min-width: 280px;
  font-family: var(--font-main);
  font-size: 14px;
  box-shadow: var(--shadow-card);
  animation: slideIn 0.3s ease;
}
.toast.success {
  background-color: var(--color-primary);
  color: var(--color-white);
}
.toast.error {
  background-color: #d32f2f;
  color: var(--color-white);
}
.toast button {
  background: none;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 16px;
}
@keyframes slideIn {
  from { opacity: 0; transform: translateX(50px); }
  to   { opacity: 1; transform: translateX(0); }
}
</style>