<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="emit('close')">
      <div class="modal-container" :class="{ 'modal-container--wide': wide }">
        <div class="modal-header">
          <h2>{{ title }}</h2>
          <button class="modal-close" @click="emit('close')" aria-label="Cerrar" title="Cerrar">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M1 1l12 12M13 1L1 13" />
          </svg>
        </button>
        </div>
        <div class="modal-body">
          <slot />
        </div>
        <div class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
  open: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: ''
  },
  wide: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

function handleEscape(e) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => window.addEventListener('keydown', handleEscape))
onUnmounted(() => window.removeEventListener('keydown', handleEscape))
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}
.modal-container {
  background: white;
  border-radius: 8px;
  padding: 24px;
  width: 100%;
  max-width: 640px;
  max-height: 90dvh;
  overflow-y: auto;
  box-sizing: border-box;
}
.modal-container--wide {
  max-width: 860px;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.modal-close {
  background: transparent;
  border: none;
  color: var(--color-text-secondary);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.modal-close:hover {
  background: var(--color-bg-input);
  color: var(--color-active);
}
.modal-footer {
  margin-top: 16px;
}
</style>