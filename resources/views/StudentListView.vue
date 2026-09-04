<template>
  <div class="student-list-container">
    <h1 style="color: var(--color-primary);">Listado de Estudiantes</h1>
    
    <div class="toolbar">
      <SearchInput v-model="searchQuery" placeholder="Buscar por CI o Apellido..." />
      <button class="btn-primary">Añadir Estudiante</button>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>CI</th>
            <th>Nombre Completo</th>
            <th>Carrera</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in filteredStudents" :key="student.id">
            <td>{{ student.ci }}</td>
            <td>{{ student.name }}</td>
            <td>{{ student.career }}</td>
            <td><StatusBadge :status="student.status" :text="student.statusText" /></td>
            <td><button class="btn-action">Editar</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :currentPage="page" :totalPages="5" @change-page="page = $event" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import SearchInput from '../components/SearchInput.vue';
import StatusBadge from '../components/StatusBadge.vue';
import Pagination from '../components/Pagination.vue';

const searchQuery = ref('');
const page = ref(1);

const students = ref([
  { id: 1, ci: '1234567', name: 'Ana Perez', career: 'Ing. Sistemas', status: 'active', statusText: 'Habilitada' },
  { id: 2, ci: '7654321', name: 'Carlos Gomez', career: 'Ing. Civil', status: 'inactive', statusText: 'Inhabilitado' }
]);

const filteredStudents = computed(() => {
  if (!searchQuery.value) return students.value;
  return students.value.filter(s => 
    s.ci.includes(searchQuery.value) || s.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.btn-primary { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); }
.table-responsive { background: var(--color-white); border-radius: var(--radius-md); box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-family: var(--font-main); }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background-color: #f8f9fa; color: var(--color-text-main); font-weight: bold; }
.btn-action { background: transparent; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 4px 8px; border-radius: 4px; cursor: pointer; }
</style>