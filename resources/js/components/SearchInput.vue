<template>
  <div class="search-wrapper">
    <input 
      type="text" 
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :placeholder="placeholder"
      class="search-input"
    />
  </div>
</template>

<script setup>
defineProps({
  modelValue: String,
  placeholder: { type: String, default: 'Buscar...' }
});
defineEmits(['update:modelValue']);
</script>

<style scoped>
.search-wrapper { margin-bottom: 20px; width: 100%; max-width: 400px; }
.search-input {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #ccc;
  border-radius: var(--radius-md);
  font-family: var(--font-main);
  box-sizing: border-box;
}
.search-input:focus { outline: none; border-color: var(--color-primary); }
</style>