<template>
  <AuthenticatedLayout>
    <div class="student-list-container">
      <h1 style="color: var(--color-primary);">Listado de Estudiantes</h1>

      <div v-if="pageProps.props.flash?.success" class="flash-success" role="status">
        {{ pageProps.props.flash.success }}
      </div>
      <div v-if="pageProps.props.flash?.error" class="flash-error" role="alert">
        {{ pageProps.props.flash.error }}
      </div>

      <div class="toolbar">
        <SearchInput v-model="searchQuery" placeholder="Buscar por código, documento, nombres o apellidos..." />
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
          <button type="submit" :disabled="csvSubiendo" class="btn-upload">
            Cargar CSV
          </button>
          <LoadingSpinner v-if="csvSubiendo" class="spinner-inline" />
        </form>
        <p v-if="csvError" class="csv-error" role="alert">{{ csvError }}</p>
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
              <!-- Campos canónicos del backend (tabla `estudiante`) -->
              <td>{{ student.codigo_universitario }}</td>
              <td>{{ student.documento_identidad }}</td>
              <td>{{ student.nombres }}</td>
              <td>{{ student.apellidos }}</td>
              <td>
                <button class="btn-action" @click="abrirModalEditar(student)">Editar</button>
                <button class="btn-action btn-danger" @click="eliminarEstudiante(student.id)">Eliminar</button>
              </td>
            </tr>
            <tr v-if="filteredStudents.length === 0">
              <td colspan="5" class="empty-cell">
                {{ students.length === 0
                  ? 'No hay estudiantes registrados.'
                  : 'No se encontraron resultados para la búsqueda.' }}
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
          @success="mostrarModal = false"
        />

        <template #footer>
          <button class="btn-cancelar" @click="mostrarModal = false">Cancelar</button>
          <button class="btn-primary" @click="refFormulario?.emitirGuardado()">
            {{ estudianteSeleccionado ? 'Actualizar Registro' : 'Guardar Registro' }}
          </button>
        </template>
      </Modal>

      <Modal :open="showModal" @close="cerrarModalImportacion">
        <div class="modal-content">
          <h3>Reporte de Carga Masiva</h3>
          <div class="summary-stats">
            <p>✅ Registros creados: <strong>{{ uploadResults.creados }}</strong></p>
            <p>❌ Registros rechazados: <strong>{{ uploadResults.rechazados }}</strong></p>
          </div>

          <div v-if="uploadResults.detalles_rechazos.length > 0" class="error-container">
            <h4>Motivos de rechazo:</h4>
            <ul class="error-list">
              <li v-for="(error, index) in uploadResults.detalles_rechazos" :key="index">
                <strong>Fila {{ error.fila }}:</strong> {{ error.motivo }}
              </li>
            </ul>
          </div>

          <button @click="cerrarModalImportacion" class="btn-close">Entendido</button>
        </div>
      </Modal>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchInput from '@/components/SearchInput.vue';
import Pagination from '@/components/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';

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

const pageProps = usePage();

const searchQuery = ref(props.filtros?.search ?? '');
const mostrarModal = ref(false);
const estudianteSeleccionado = ref(null);
const refFormulario = ref(null);

// --- Tabla ---
const students = computed(() => {
  const raw = props.estudiantes?.data ?? props.estudiantes;
  return Array.isArray(raw) ? raw : [];
});

const filteredStudents = computed(() => {
  if (!searchQuery.value) return students.value;
  const termino = searchQuery.value.toLowerCase();
  return students.value.filter((s) =>
    [s.codigo_universitario, s.documento_identidad, s.nombres, s.apellidos]
      .some((campo) => String(campo ?? '').toLowerCase().includes(termino))
  );
});

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

// --- Crear / Editar ---
const abrirModalCrear = () => {
  estudianteSeleccionado.value = null; // Limpiamos datos previos
  mostrarModal.value = true;
};

const abrirModalEditar = (estudiante) => {
  estudianteSeleccionado.value = estudiante; // Cargamos los datos del estudiante
  mostrarModal.value = true;
};

const eliminarEstudiante = (id) => {
  if (confirm('¿Estás seguro de que deseas eliminar este registro?')) {
    router.delete(`/estudiantes/${id}`, {
      preserveScroll: true,
    });
  }
};

// --- Importación CSV ---
const showModal = ref(false);
const uploadResults = ref({ creados: 0, rechazados: 0, detalles_rechazos: [] });
const csvFile = ref(null);
const csvSubiendo = ref(false);
const csvError = ref('');

const handleFileChange = (event) => {
  csvFile.value = event.target.files[0] ?? null;
  csvError.value = '';
};

const submitCsv = async () => {
  if (!csvFile.value) return;

  csvSubiendo.value = true;
  csvError.value = '';

  const formData = new FormData();
  // El backend valida el campo `file` (mimes:csv,txt, max:10240).
  formData.append('file', csvFile.value);

  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const response = await fetch('/estudiantes/importar', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      csvError.value = data?.errors?.file?.[0] ?? data?.mensaje ?? 'Error al importar el archivo.';
      return;
    }

    uploadResults.value = {
      creados: data?.exitosos ?? 0,
      rechazados: (data?.rechazados ?? []).length,
      detalles_rechazos: (data?.rechazados ?? []).flatMap((rechazo) =>
        (rechazo.motivos ?? []).map((motivo) => ({ fila: rechazo.fila, motivo }))
      ),
    };

    showModal.value = true;
    csvFile.value = null;
    // Refresca el listado para que los estudiantes importados aparezcan.
    router.reload({ only: ['estudiantes', 'filtros'] });
  } catch (error) {
    csvError.value = 'Error de conexión al importar el archivo.';
  } finally {
    csvSubiendo.value = false;
  }
};

const cerrarModalImportacion = () => {
  showModal.value = false;
  uploadResults.value = { creados: 0, rechazados: 0, detalles_rechazos: [] };
};
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.btn-primary { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); font-size: 14px; font-weight: 600; transition: background-color 0.15s ease; }
.btn-primary:hover { background-color: var(--color-primary-hover); }
.table-responsive { background: var(--color-white); border-radius: var(--radius-md); box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-family: var(--font-main); }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background-color: #f8f9fa; color: var(--color-text-main); font-weight: bold; }
.empty-cell { text-align: center; color: #6c757d; padding: 24px 15px; }
.btn-action { background: transparent; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 4px 8px; border-radius: 4px; cursor: pointer; }
.flash-success { background: #e6f4ea; color: #1e7a34; border: 1px solid #b6e2c0; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
.flash-error { background: #fdecea; color: #b3261e; border: 1px solid #f5c2b9; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
.actions-container { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 6px; }
.csv-upload-form { display: flex; align-items: center; gap: 1rem; }
.file-input { border: 1px solid #ddd; padding: 0.3rem; border-radius: 4px; background: white; }
.btn-upload { padding: 0.5rem 1rem; background-color: #198754; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-upload:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner-inline { width: 24px; height: 24px; }
.csv-error { color: #b3261e; margin-top: 0.5rem; font-size: 0.9rem; }
.btn-cancelar { margin-right: 15px; background: transparent; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 14px; padding: 10px 16px; }
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