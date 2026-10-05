<template>
  <AuthenticatedLayout>
    <div class="page-container">

      <!-- Encabezado -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Gestión de Asignaturas</h1>
          <p class="page-subtitle">Administra el catálogo de materias del sistema</p>
        </div>
        <Button variant="primary" @click="abrirModalNueva">
          + Nueva Asignatura
        </Button>
      </div>

      <!-- La búsqueda consulta todo el catálogo antes de paginar. -->
      <div class="toolbar">
        <div class="filter-field">
          <TextInput v-model="filtroNombre" placeholder="Buscar asignaturas por nombre..." style="width: 100%; max-width: 400px;" />
          <p v-if="erroresBusqueda.nombre_asignatura" class="error-msg">{{ erroresBusqueda.nombre_asignatura }}</p>
        </div>
        <span class="result-count" aria-live="polite">{{ asignaturas.total }} asignatura(s)</span>
      </div>

      <!-- HU7: filas y total procedentes del paginador de Laravel. -->
      <div class="table-container">
        <div v-if="cargando" class="loading-center">
          <LoadingSpinner size="large" />
        </div>

        <table v-else class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>NOMBRE</th>
              <th class="actions-col">ACCIONES</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="asignatura in asignaturas.data" :key="asignatura.id_asignatura">
              <td class="id-cell">{{ asignatura.id_asignatura }}</td>
              <td>{{ asignatura.nombre_asignatura }}</td>
              <td class="actions-cell">
                <Button variant="action" @click="abrirModalEditar(asignatura)">
                  Editar
                </Button>
                <Button variant="delete" @click="confirmarEliminar(asignatura)">
                  Eliminar
                </Button>
              </td>
            </tr>
            <tr v-if="asignaturas.data.length === 0">
              <td colspan="3" class="empty-row">No se encontraron asignaturas.</td>
            </tr>
          </tbody>
        </table>

        <!-- HU7: los enlaces del servidor mantienen los filtros aplicados. -->
        <div class="pagination" v-if="asignaturas.last_page > 1">
          <Button variant="primary" :disabled="cargando || !asignaturas.prev_page_url" @click="visitar(asignaturas.prev_page_url)">
            Anterior
          </Button>
          <span class="page-info">Página {{ asignaturas.current_page }} de {{ asignaturas.last_page }}</span>
          <Button variant="primary" :disabled="cargando || !asignaturas.next_page_url" @click="visitar(asignaturas.next_page_url)">
            Siguiente
          </Button>
        </div>
      </div>

      <!-- HU7: formulario real con nombre_asignatura y errores de Laravel. -->
      <Modal
        :open="modalAbierto"
        :title="modoEdicion ? 'Editar Asignatura' : 'Nueva Asignatura'"
        @close="cerrarModal"
      >
        <div class="form-group">
          <label for="nombre-asignatura" class="form-label">Nombre <span class="required">*</span></label>
          <TextInput
            id="nombre-asignatura"
            v-model="form.nombre_asignatura"
            maxlength="150"
            :disabled="form.processing"
            @keydown.enter.prevent="guardar"
            type="text"
            :class="{ 'input-error': form.errors.nombre_asignatura }"
            placeholder="Ej: Cálculo I"
            @input="form.clearErrors('nombre_asignatura')"
          />
          <p v-if="form.errors.nombre_asignatura" class="error-msg">{{ form.errors.nombre_asignatura }}</p>
        </div>

        <div v-if="modoEdicion" class="form-group">
          <label class="form-label">ID</label>
          <TextInput :value="asignaturaEditando?.id_asignatura" type="text" class="input-readonly" readonly />
          <p class="help-text">El ID no es editable.</p>
        </div>

        <template #footer>
          <Button variant="action" class="btn-modal" @click="cerrarModal" :disabled="form.processing">Cancelar</Button>
          <Button variant="primary" class="btn-modal" @click="guardar" :disabled="form.processing">
            <LoadingSpinner v-if="form.processing" size="small" />
            <span v-else>{{ modoEdicion ? 'Guardar cambios' : 'Crear asignatura' }}</span>
          </Button>
        </template>
      </Modal>

      <!-- HU7: el bloqueo por exámenes se muestra sin anunciar una eliminación. -->
      <Modal :open="modalEliminar" title="Eliminar Asignatura" @close="cerrarEliminar">
        <p class="confirm-text">
          ¿Estás seguro de eliminar <strong>{{ asignaturaAEliminar?.nombre_asignatura }}</strong>?
          Esta acción no se puede deshacer.
        </p>
        <p v-if="errorEliminar" class="error-msg" role="alert">{{ errorEliminar }}</p>
        <template #footer>
          <Button variant="action" class="btn-modal" @click="cerrarEliminar" :disabled="eliminando">Cancelar</Button>
          <Button variant="danger" class="btn-modal" @click="eliminar" :disabled="eliminando">
            <LoadingSpinner v-if="eliminando" size="small" />
            <span v-else>Sí, eliminar</span>
          </Button>
        </template>
      </Modal>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import TextInput from '@/components/ui/TextInput.vue'
import Button from '@/components/ui/Button.vue'
import { useToastStore } from '@/stores/useToastStore'

// HU7: sustituir el arreglo temporal por el paginador y filtros de Laravel.
const props = defineProps({
  asignaturas: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
})
const toast = useToastStore()
const filtroNombre = ref(props.filtros.nombre_asignatura ?? '')
const erroresBusqueda = ref({})
const cargando = ref(false)
const modalAbierto = ref(false)
const modalEliminar = ref(false)
const modoEdicion = ref(false)
const eliminando = ref(false)
const errorEliminar = ref('')
const asignaturaEditando = ref(null)
const asignaturaAEliminar = ref(null)
const form = useForm({ nombre_asignatura: '' })

let debounceTimer = null
let cancelarConsulta = null
let consultaActual = 0

// Sincronizar la navegación sin sobrescribir texto que aún se está buscando.
watch(() => props.filtros, (filtros) => {
  if (!cargando.value && !debounceTimer) {
    filtroNombre.value = filtros.nombre_asignatura ?? ''
  }
})

function cancelarBusqueda() {
  clearTimeout(debounceTimer)
  debounceTimer = null
  consultaActual++
  cancelarConsulta?.()
  cancelarConsulta = null
  cargando.value = false
}

function visitar(url, filtros = {}, reemplazar = false) {
  if (!url) return
  cancelarBusqueda()
  const consulta = consultaActual
  erroresBusqueda.value = {}
  router.get(url, filtros, {
    preserveState: true,
    preserveScroll: true,
    replace: reemplazar,
    only: ['asignaturas', 'filtros'],
    onCancelToken: (token) => { cancelarConsulta = () => token.cancel() },
    onStart: () => { cargando.value = true },
    onError: (errores) => {
      if (consulta === consultaActual) erroresBusqueda.value = errores
    },
    onFinish: () => {
      if (consulta === consultaActual) {
        cargando.value = false
        cancelarConsulta = null
      }
    },
  })
}

function buscar() {
  visitar('/asignaturas', {
    nombre_asignatura: filtroNombre.value.trim() || undefined,
  }, true)
}

watch(filtroNombre, () => {
  cancelarBusqueda()
  erroresBusqueda.value = {}
  if (filtroNombre.value.trim() === (props.filtros.nombre_asignatura ?? '') && !props.filtros.id_asignatura) return
  debounceTimer = setTimeout(buscar, 300)
}, { flush: 'sync' })

onBeforeUnmount(cancelarBusqueda)

function limpiar() {
  filtroNombre.value = ''
  buscar()
}

function abrirModalNueva() {
  modoEdicion.value = false
  form.reset()
  form.clearErrors()
  asignaturaEditando.value = null
  modalAbierto.value = true
}

function abrirModalEditar(asignatura) {
  modoEdicion.value = true
  asignaturaEditando.value = asignatura
  form.nombre_asignatura = asignatura.nombre_asignatura
  form.clearErrors()
  modalAbierto.value = true
}

function cerrarModal() {
  if (!form.processing) modalAbierto.value = false
}

// HU7: POST registra y PATCH edita; useForm conserva errores y estado de envío.
function guardar() {
  if (form.processing) return
  const opciones = {
    preserveScroll: true,
    onSuccess: (page) => {
      if (page.props.flash?.success) toast.success(page.props.flash.success)
      modalAbierto.value = false
      form.reset()
    },
  }
  if (modoEdicion.value) {
    form.patch(`/asignaturas/${asignaturaEditando.value.id_asignatura}`, opciones)
  } else {
    form.post('/asignaturas', opciones)
  }
}

function confirmarEliminar(asignatura) {
  asignaturaAEliminar.value = asignatura
  errorEliminar.value = ''
  modalEliminar.value = true
}

function cerrarEliminar() {
  if (!eliminando.value) modalEliminar.value = false
}

// HU7: una redirección con flash.error indica que el borrado fue bloqueado.
function eliminar() {
  if (eliminando.value) return
  eliminando.value = true
  errorEliminar.value = ''
  router.delete(`/asignaturas/${asignaturaAEliminar.value.id_asignatura}`, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      if (page.props.flash?.error) {
        errorEliminar.value = page.props.flash.error
        toast.error(page.props.flash.error)
        return
      }
      if (page.props.flash?.success) toast.success(page.props.flash.success)
      modalEliminar.value = false
    },
    onError: () => { errorEliminar.value = 'No se pudo eliminar la asignatura.' },
    onFinish: () => { eliminando.value = false },
  })
}
</script>

<style scoped>
.page-container {
    padding: var(--spacing-lg) var(--spacing-xl);
    background-color: transparent;
    font-family: var(--font-family);
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--spacing-lg);
  flex-wrap: wrap;
  gap: var(--spacing-md);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-primary);
  margin: 0 0 4px;
  font-family: var(--font-display);
  letter-spacing: 1px;
}

.page-subtitle { font-size: 0.875rem; color: var(--text-muted); margin: 0; }

.toolbar {
  flex-wrap: wrap;
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-md);
}
.filter-field { flex: 1; min-width: 250px; }
.result-count { font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; }

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-md);
  border-top: 1px solid var(--border-light);
  background-color: var(--color-white);
}

.page-info { font-size: 0.85rem; color: var(--text-muted); }

.loading-center { display: flex; justify-content: center; padding: 48px; }


.confirm-text { font-size: 0.9rem; color: var(--text-dark); line-height: 1.6; }

/* Estandarización de Tabla */
.table-container { 
    background: var(--color-white); 
    border: 1px solid var(--border-light); 
    border-radius: var(--radius-md); 
    overflow-x: auto; 
    width: 100%; 
    box-shadow: var(--shadow-card);
}

.id-cell { 
    color: var(--text-muted); 
    font-family: var(--font-mono); 
    font-size: 0.85rem; 
    width: 60px; 
}

/* Columna de Acciones Centralizadas */
.actions-col {
    text-align: center !important;
}

.actions-cell {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: center;
    align-items: center;
    white-space: nowrap;
}

.empty-row { 
    text-align: center; 
    color: var(--text-muted); 
    padding: 2rem !important; 
}

/* Responsive */
@media (max-width: 768px) {
    .page-container {
        padding: var(--spacing-md);
    }
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    .page-header > button {
        width: 100%;
    }
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-field {
        width: 100%;
    }
    .filter-field > * {
        max-width: 100% !important;
    }
}
</style>
