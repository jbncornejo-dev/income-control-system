<template>
  <AuthenticatedLayout>
    <div class="student-list-container">
      <h1 style="color: var(--color-primary);">Listado de Estudiantes</h1>

      <div v-if="pageProps.props.flash?.success" class="flash-success" role="status">
        {{ pageProps.props.flash.success }}
      </div>

      <div class="toolbar">
        <SearchInput v-model="searchQuery" placeholder="Buscar por CI o Apellido..." />
        <button class="btn-primary" @click="mostrarModal = true">Añadir Estudiante</button>
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
      <Modal :open="mostrarModal" title="Registrar Estudiante" @close="mostrarModal = false">
        <EstudianteForm ref="refFormulario" @success="onEstudianteCreado" />
        
        <template #footer>
          <button @click="mostrarModal = false" style="margin-right: 15px; background: transparent; border: none; cursor: pointer;">Cancelar</button>
          <button class="btn-primary" @click="refFormulario.emitirGuardado()">Guardar Registro</button>
        </template>
      </Modal>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchInput from '@/components/SearchInput.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';

const props = defineProps({
  estudiantes: {
    type: Object,
    default: () => ({ data: [] }),
  },
})

const searchQuery = ref('');
const page = ref(1);
const mostrarModal = ref(false);
const refFormulario = ref(null);
const pageProps = usePage();

// Soporta tanto paginator {data:[]} como array plano; fallback demo solo si no hay datos
const students = computed(() => {
  const raw = props.estudiantes?.data ?? props.estudiantes;
  if (Array.isArray(raw) && raw.length) return raw;
  return [
    { id: 1, ci: '1234567', name: 'Ana Perez', career: 'Ing. Sistemas', status: 'active', statusText: 'Habilitada' },
    { id: 2, ci: '7654321', name: 'Carlos Gomez', career: 'Ing. Civil', status: 'inactive', statusText: 'Inhabilitado' }
  ];
});

const filteredStudents = computed(() => {
  if (!searchQuery.value) return students.value;
  return students.value.filter(s =>
    s.ci.includes(searchQuery.value) || s.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

const onEstudianteCreado = () => {
  mostrarModal.value = false;
};
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.btn-primary { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); }
.table-responsive { background: var(--color-white); border-radius: var(--radius-md); box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-family: var(--font-main); }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background-color: #f8f9fa; color: var(--color-text-main); font-weight: bold; }
.btn-action { background: transparent; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 4px 8px; border-radius: 4px; cursor: pointer; }
.flash-success { background: #e6f4ea; color: #1e7a34; border: 1px solid #b6e2c0; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
</style>
