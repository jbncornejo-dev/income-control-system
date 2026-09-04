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
  <div class="form-group">
      <label>Carrera</label>
      <select v-model="carrera" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <option value="Ing. Sistemas">Ingeniería de Sistemas</option>
        <option value="Ing. Civil">Ingeniería Civil</option>
      </select>
    </div>
</template>

<script setup>
import { ref } from 'vue'
const nombre = ref('')
const carnet = ref('')
const carrera = ref('')
const emit = defineEmits(['guardar'])

// Esta función empaqueta los datos y los envía al componente padre
const emitirGuardado = () => {
  emit('guardar', { nombre: nombre.value, carnet: carnet.value, carrera: carrera.value })
}

// Expone la función para que el botón del Modal pueda ejecutarla
defineExpose({ emitirGuardado })
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