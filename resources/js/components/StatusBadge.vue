<template>
  <span :class="['status-badge', statusClass]">
    {{ text }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, required: true },
  text: { type: String, required: true }
});

const statusClass = computed(() => {
  return props.status === 'active' ? 'badge-active' : 'badge-inactive';
});
</script>

<style scoped>
.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: bold;
  font-family: var(--font-main);
  text-transform: uppercase;
}
.badge-active {
  background-color: #e6f4ea;
  color: #1e8e3e;
  border: 1px solid #1e8e3e;
}
.badge-inactive {
  background-color: #fce8e6;
  color: var(--color-secondary);
  border: 1px solid var(--color-secondary);
}
</style>