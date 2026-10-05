<template>
  <AuthenticatedLayout>
    <div class="panel-container">
      <h1 class="panel-title">ESTUDIANTES</h1>

      <div class="action-bar">
        <TextInput v-model="searchQuery" placeholder="Buscar por código, documento, correo, nombres o apellidos..." style="width: 100%; max-width: 500px;" />
        <div class="action-controls">
          <Button variant="action" @click="abrirModalCsv">Importar CSV</Button>
          <Button variant="primary" @click="abrirModalCrear">Añadir Estudiante</Button>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ESTUDIANTE</th>
              <th>CÓDIGO UNIV.</th>
              <th>DOCUMENTO IDENTIDAD</th>
              <th>CORREO</th>
              <th class="actions-col">ACCIONES</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in students" :key="student.id">
              <td class="name-cell">
                <div class="avatar">{{ getInitial(student.nombres) }}</div>
                <span>{{ student.nombres }} {{ student.apellidos }}</span>
              </td>
              <td>{{ student.codigo_universitario }}</td>
              <td>{{ student.documento_identidad }}</td>
              <td>{{ student.email || '—' }}</td>
              <td class="actions-cell">
                <Button variant="action" @click="abrirQr(student)">QR</Button>
                <Button variant="action" @click="abrirModalEditar(student)">Editar</Button>
                <Button variant="delete" @click="confirmarEliminar(student)">Eliminar</Button>
              </td>
            </tr>
            <tr v-if="students.length === 0">
              <td colspan="5" class="empty-state">
                {{ searchQuery
                  ? 'No se encontraron resultados para la búsqueda.'
                  : 'No hay estudiantes registrados.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :currentPage="currentPage" :totalPages="totalPages" @change-page="changePage" />

      <!-- Modal: registrar / editar estudiante -->
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
          <Button variant="action" class="btn-modal" @click="mostrarModal = false">Cancelar</Button>
          <Button variant="primary" @click="refFormulario?.emitirGuardado()">
            {{ estudianteSeleccionado ? 'Actualizar Registro' : 'Guardar Registro' }}
          </Button>
        </template>
      </Modal>

      <!-- Modal: importación CSV -->
      <EstudiantesCsvModal :open="csvModal" @close="csvModal = false" />

      <!-- Modal: confirmar eliminación de estudiante -->
      <Modal
        :open="modalEliminar"
        title="Eliminar Estudiante"
        @close="modalEliminar = false"
      >
        <div class="confirm-box">
          <div class="confirm-icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </div>
          <div class="confirm-content">
            <p class="confirm-text">
              ¿Está seguro de que desea eliminar al estudiante
              <strong v-if="estudianteAEliminar">
                {{ estudianteAEliminar.nombres }} {{ estudianteAEliminar.apellidos }}
              </strong>?
            </p>
            <p class="confirm-note">Esta acción no se puede deshacer.</p>
          </div>
        </div>

        <template #footer>
          <Button variant="action" :disabled="eliminando" @click="modalEliminar = false">Cancelar</Button>
          <Button variant="danger" :disabled="eliminando" @click="eliminarEstudiante">
            {{ eliminando ? 'Eliminando...' : 'Sí, eliminar' }}
          </Button>
        </template>
      </Modal>

      <!-- Modal: código QR del estudiante -->
      <Modal
        :open="qrModal"
        :title="estudianteQr ? `QR de ${estudianteQr.nombres} ${estudianteQr.apellidos}` : 'Código QR'"
        @close="qrModal = false"
      >
        <div v-if="estudianteQr" class="qr-content">
          <img :src="`/estudiantes/${estudianteQr.id}/qr`" alt="Código QR del estudiante" class="qr-image" />
          <p class="qr-help">
            El personal de control puede escanear este código al momento del ingreso al examen
            para identificar al estudiante.
          </p>
        </div>

        <template #footer>
          <Button variant="action" class="btn-modal" @click="qrModal = false">Cerrar</Button>
          <a
            v-if="estudianteQr"
            :href="`/estudiantes/${estudianteQr.id}/qr`"
            download
            class="btn-descargar"
          >
            Descargar QR
          </a>
        </template>
      </Modal>
    </div>
  </AuthenticatedLayout>

</template>

<script setup>
import Button from '@/components/ui/Button.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/components/ui/TextInput.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';
import EstudiantesCsvModal from '@/components/forms/EstudiantesCsvModal.vue';
import { useToastStore } from '@/stores/useToastStore';

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

// --- Eliminación ---
const toast = useToastStore();
const modalEliminar = ref(false);
const estudianteAEliminar = ref(null);
const eliminando = ref(false);

const confirmarEliminar = (estudiante) => {
  estudianteAEliminar.value = estudiante;
  modalEliminar.value = true;
};

const eliminarEstudiante = () => {
  if (!estudianteAEliminar.value) return;

  eliminando.value = true;

  router.delete(`/estudiantes/${estudianteAEliminar.value.id}`, {
    preserveScroll: true,
    onSuccess: (page) => {
      // El backend responde con flash de éxito o de error (bloqueo por registros asociados).
      const flash = page?.props?.flash ?? {};
      if (flash.error) toast.error(flash.error);
      else if (flash.success) toast.success(flash.success);
      modalEliminar.value = false;
    },
    onError: () => {
      toast.error('No se pudo eliminar el estudiante.');
      modalEliminar.value = false;
    },
    onFinish: () => {
      eliminando.value = false;
      estudianteAEliminar.value = null;
    },
  });
};

// --- QR del estudiante ---
const qrModal = ref(false);
const estudianteQr = ref(null);

const abrirQr = (estudiante) => {
  estudianteQr.value = estudiante;
  qrModal.value = true;
};

// --- Tabla ---
const students = computed(() => {
  const raw = props.estudiantes?.data ?? props.estudiantes;
  return Array.isArray(raw) ? raw : [];
});

// --- Búsqueda (server-side) ---
// El backend filtra sobre TODAS las páginas antes de paginar
// (`StudentController::index`); aquí solo disparamos la consulta con debounce
// para no saturar el servidor con cada tecla. El filtrado client-side se eliminó
// porque solo veía la página actual (15 registros).
let debounceTimer = null;

const buscarEstudiantes = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const termino = searchQuery.value.trim();

    router.get('/estudiantes', { search: termino || null }, {
      // preserveState mantiene el texto del buscador mientras llega la respuesta.
      preserveState: true,
      // replace evita llenar el historial del navegador con cada búsqueda.
      replace: true,
      // Solo se recargan las props que cambian con la búsqueda.
      only: ['estudiantes', 'filtros'],
    });
  }, 300);
};

watch(searchQuery, buscarEstudiantes);
onBeforeUnmount(() => clearTimeout(debounceTimer));

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

// --- Importación CSV ---
const csvModal = ref(false);

const abrirModalCsv = () => {
  csvModal.value = true;
};

// Función auxiliar para obtener la inicial del nombre para el avatar
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?';
};
</script>

<style scoped>
.action-bar { display: flex; justify-content: space-between; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg); flex-wrap: wrap; }
.action-controls { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }

.confirm-text { color: var(--text-dark); font-size: 0.9rem; line-height: 1.6; margin: 0; }
.confirm-note { color: var(--color-danger); font-size: 0.85rem; margin: 0; }
.confirm-box {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-md);
  background: var(--color-white);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  padding: var(--spacing-md);
}
.confirm-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(220, 53, 69, 0.1);
  color: var(--color-danger);
}
.confirm-content { display: flex; flex-direction: column; gap: 6px; }
.confirm-content strong { color: var(--color-danger); }

/* Estandarización de botones secundarios en toolbar y modales */
.btn-toolbar,
.btn-modal {
  padding: 10px 20px !important;
  font-size: 14px !important;
}

/* Contenedor principal */
.panel-container {
    padding: var(--spacing-lg) var(--spacing-xl);
    background-color: transparent;
    font-family: var(--font-family);
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--spacing-lg);
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

/* Tabla de Datos */
.table-container {
    background: var(--color-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    overflow-x: auto;
    box-shadow: var(--shadow-card);
    margin-bottom: var(--spacing-lg);
}

/* Celdas específicas */
.name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.avatar {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    background-color: var(--color-primary);
    color: var(--color-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
}

.actions-cell {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: center;
    align-items: center;
    white-space: nowrap;
    min-width: 180px;
}

.actions-col {
    text-align: center !important;
}

.empty-state {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem !important;
}

/* ---- Modal QR ---- */
.qr-content { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.qr-image {
  width: 220px;
  height: 220px;
  object-fit: contain;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  padding: 8px;
  background: var(--color-white);
}
.qr-help { color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; text-align: center; margin: 0; }
.btn-descargar {
  display: inline-flex;
  align-items: center;
  padding: 10px 20px;
  background: var(--color-primary);
  color: var(--color-white);
  border-radius: var(--radius-md);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 0.2s ease;
}
.btn-descargar:hover { background: var(--color-secondary); }

/* Responsive */
@media (max-width: 768px) {
    .panel-container {
        padding: var(--spacing-md);
    }
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .action-controls {
        width: 100%;
        display: flex;
        flex-direction: column;
    }
    .action-controls > button {
        width: 100%;
    }
    .action-bar > :first-child {
        max-width: 100% !important;
    }
}
</style>