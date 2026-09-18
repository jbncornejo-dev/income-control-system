<template>
  <AuthenticatedLayout>
    <div class="student-list-container">
      <h1 style="color: var(--color-primary);">Listado de Estudiantes</h1>

      <div class="toolbar">
        <SearchInput v-model="searchQuery" placeholder="Buscar por CI o Apellido..." />
        <button class="btn-primary" @click="abrirModalCrear">Añadir Estudiante</button>
      </div>

      <div class="actions-container">
      <form @submit.prevent="submitCsv" class="csv-upload-form">
        <input 
          type="file" 
          accept=".csv" 
          @change="handleFileChange" 
          required 
          class="file-input"
        />
        <button type="submit" :disabled="csvForm.processing" class="btn-upload">
          Cargar CSV
        </button>
        <LoadingSpinner v-if="csvForm.processing" class="spinner-inline" />
      </form>
    </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Código Univ.</th>
              <th>Documento Identidad</th>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in filteredStudents" :key="student.id">
              <!-- Mapeamos las propiedades reales de tu backend -->
              <td>{{ student.codigo_universitario }}</td>
              <td>{{ student.documento_identidad }}</td>
              <td>{{ student.nombres }}</td>
              <td>{{ student.apellidos }}</td>
              <td>
                <!-- Botones de Acción -->
                <button class="btn-action" @click="abrirModalEditar(student)">Editar</button>
                <button class="btn-action btn-danger" @click="eliminarEstudiante(student.id)">Eliminar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :currentPage="currentPage" :totalPages="totalPages" @change-page="changePage" />
      <Modal 
  :open="mostrarModal" 
  :title="estudianteSeleccionado ? 'Editar Estudiante' : 'Registrar Estudiante'" 
  @close="mostrarModal = false"
>
  <EstudianteForm 
    ref="refFormulario" 
    :estudiante="estudianteSeleccionado" 
    @success="onEstudianteCreado" 
  />
  
  <template #footer>
    <button @click="mostrarModal = false" style="margin-right: 15px; background: transparent; border: none; cursor: pointer;">Cancelar</button>
    <button class="btn-primary" @click="refFormulario.emitirGuardado()">
      {{ estudianteSeleccionado ? 'Actualizar Registro' : 'Guardar Registro' }}
    </button>
  </template>
</Modal>
    </div>
  </AuthenticatedLayout>
  <Modal :show="showModal" @close="showModal = false">
    <div class="modal-content">
      <h3>Reporte de Carga Masiva</h3>
      <div class="summary-stats">
        <p>Registros creados: <strong>{{ uploadResults.creados }}</strong></p>
        <p>Registros rechazados: <strong>{{ uploadResults.rechazados }}</strong></p>
      </div>

      <div v-if="uploadResults.detalles_rechazos && uploadResults.detalles_rechazos.length > 0" class="error-container">
        <h4>Motivos de rechazo:</h4>
        <ul class="error-list">
          <li v-for="(error, index) in uploadResults.detalles_rechazos" :key="index">
            <strong>Fila {{ error.fila }}:</strong> {{ error.motivo }}
          </li>
        </ul>
      </div>

      <button @click="showModal = false" class="btn-close">Entendido</button>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchInput from '@/components/SearchInput.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';

const showModal = ref(false);
const uploadResults = ref({ creados: 0, rechazados: 0, detalles_rechazos: [] });
const csvForm = useForm({
  archivo_csv: null,
});

const handleFileChange = (event) => {
  csvForm.archivo_csv = event.target.files[0];
};

const props = defineProps({
  estudiantes: {
    type: Object,
    default: () => ({ data: [] }),
  },
  filtros: {
    type: Object,
    default: () => ({}),
  },
})

const searchQuery = ref(props.filtros?.search ?? '');
const mostrarModal = ref(false);
const estudianteSeleccionado = ref(null);
// Función para abrir el modal en modo "Creación"
const abrirModalCrear = () => {
  estudianteSeleccionado.value = null; // Limpiamos datos previos
  mostrarModal.value = true;
};

// Función para abrir el modal en modo "Edición"
const abrirModalEditar = (estudiante) => {
  estudianteSeleccionado.value = estudiante; // Cargamos los datos del estudiante
  mostrarModal.value = true;
};

// Función para eliminar
const eliminarEstudiante = (id) => {
  if (confirm('¿Estás seguro de que deseas eliminar este registro?')) {
    // Usamos router.delete de Inertia para llamar al backend
    router.delete(`/estudiantes/${id}`, {
      preserveScroll: true,
    });
  }
};

const refFormulario = ref(null);
const pageProps = usePage();

const currentPage = computed(() => props.estudiantes?.current_page ?? 1);
const totalPages = computed(() => props.estudiantes?.last_page ?? 1);

const changePage = (newPage) => {
  if (newPage < 1 || newPage > totalPages.value || newPage === currentPage.value) return;
  const url = newPage > currentPage.value
    ? props.estudiantes.next_page_url
    : props.estudiantes.prev_page_url;
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

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

const submitCsv = () => {
  csvForm.post('/estudiantes/importar', {
    preserveScroll: true,
    onSuccess: (page) => {
      // Capturamos la respuesta del backend enviada mediante Inertia flash data
      const results = page.props.flash.import_results;
      if (results) {
        uploadResults.value = results;
        showModal.value = true;
      }
      csvForm.reset('archivo_csv');
    },
  });
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
.actions-container { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 6px; }
.csv-upload-form { display: flex; align-items: center; gap: 1rem; }
.file-input { border: 1px solid #ddd; padding: 0.3rem; border-radius: 4px; background: white; }
.btn-upload { padding: 0.5rem 1rem; background-color: #198754; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-upload:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner-inline { width: 24px; height: 24px; }
.modal-content { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.summary-stats p { margin: 0.2rem 0; font-size: 1.1rem; }
.error-container { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 4px; }
.error-list { max-height: 200px; overflow-y: auto; padding-left: 1.5rem; margin-top: 0.5rem; font-size: 0.9rem; }
.btn-close { align-self: flex-end; padding: 0.5rem 1rem; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-danger {
  color: #dc3545;
  border-color: #dc3545;
  margin-left: 8px;
}
.btn-danger:hover {
  background-color: #dc3545;
  color: white;
}
</style>
