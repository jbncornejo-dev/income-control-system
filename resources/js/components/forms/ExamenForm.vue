<template>
  <div>
    <div class="form-group">
      <label>Materia</label>
      <input type="text" v-model="materia" placeholder="Nombre de la materia" />
    </div>
    <div class="form-group">
      <label>Fecha</label>
      <input type="date" v-model="fecha" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const materia = ref('')
const fecha = ref('')
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