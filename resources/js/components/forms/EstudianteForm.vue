<template>
  <div>
    <div class="form-group">
      <label>Nombre</label>
      <input type="text" v-model="nombre" placeholder="Nombre del estudiante" />
    </div>
    <div class="form-group">
      <label>Carnet</label>
      <input type="text" v-model="carnet" placeholder="Carnet de identidad" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const nombre = ref('')
const carnet = ref('')
</script>

<style scoped>
.form-group {
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
input {
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}
</style>