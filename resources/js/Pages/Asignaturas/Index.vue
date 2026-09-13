<template>
  <AuthenticatedLayout>
    <div class="page-container">

      <!-- Encabezado -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Gestión de Asignaturas</h1>
          <p class="page-subtitle">Administra el catálogo de materias del sistema</p>
        </div>
        <button class="btn-primary" @click="abrirModalNueva">
          + Nueva Asignatura
        </button>
      </div>

      <!-- HU7: filtros enviados al servidor; se conservan al paginar. -->
      <form class="toolbar" @submit.prevent="buscar">
        <div class="filter-field">
          <label for="filtro-id">ID</label>
          <input id="filtro-id" v-model="filtroId" type="number" min="1" step="1" class="form-input" placeholder="ID exacto" />
          <p v-if="erroresBusqueda.id_asignatura" class="error-msg">{{ erroresBusqueda.id_asignatura }}</p>
        </div>
        <div class="filter-field">
          <label for="filtro-nombre">Nombre</label>
          <input id="filtro-nombre" v-model="filtroNombre" type="text" maxlength="150" class="form-input" placeholder="Buscar por nombre" />
          <p v-if="erroresBusqueda.nombre_asignatura" class="error-msg">{{ erroresBusqueda.nombre_asignatura }}</p>
        </div>
        <button type="submit" class="btn-primary" :disabled="cargando">Buscar</button>
        <button type="button" class="btn-cancel" :disabled="cargando" @click="limpiar">Limpiar</button>
        <span class="result-count">{{ asignaturas.total }} asignatura(s)</span>
      </form>

      <!-- HU7: filas y total procedentes del paginador de Laravel. -->
      <div class="table-card">
        <div v-if="cargando" class="loading-center">
          <LoadingSpinner size="large" />
        </div>

        <table v-else class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="asignatura in asignaturas.data" :key="asignatura.id_asignatura">
              <td class="id-cell">{{ asignatura.id_asignatura }}</td>
              <td>{{ asignatura.nombre_asignatura }}</td>
              <td class="actions-cell">
                <button class="btn-action btn-edit" @click="abrirModalEditar(asignatura)">
                  Editar
                </button>
                <button class="btn-action btn-delete" @click="confirmarEliminar(asignatura)">
                  Eliminar
                </button>
              </td>
            </tr>
            <tr v-if="asignaturas.data.length === 0">
              <td colspan="3" class="empty-row">No se encontraron asignaturas.</td>
            </tr>
          </tbody>
        </table>

        <!-- HU7: los enlaces del servidor mantienen los filtros aplicados. -->
        <div class="pagination" v-if="asignaturas.last_page > 1">
          <button :disabled="cargando || !asignaturas.prev_page_url" @click="visitar(asignaturas.prev_page_url)" class="btn-page">
            ← Anterior
          </button>
          <span class="page-info">Página {{ asignaturas.current_page }} de {{ asignaturas.last_page }}</span>
          <button :disabled="cargando || !asignaturas.next_page_url" @click="visitar(asignaturas.next_page_url)" class="btn-page">
            Siguiente →
          </button>
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
          <input
            id="nombre-asignatura"
            v-model="form.nombre_asignatura"
            maxlength="150"
            :disabled="form.processing"
            @keydown.enter.prevent="guardar"
            type="text"
            class="form-input"
            :class="{ 'input-error': form.errors.nombre_asignatura }"
            placeholder="Ej: Cálculo I"
            @input="form.clearErrors('nombre_asignatura')"
          />
          <p v-if="form.errors.nombre_asignatura" class="error-msg">{{ form.errors.nombre_asignatura }}</p>
        </div>

        <div v-if="modoEdicion" class="form-group">
          <label class="form-label">ID</label>
          <input :value="asignaturaEditando?.id_asignatura" type="text" class="form-input input-readonly" readonly />
          <p class="help-text">El ID no es editable.</p>
        </div>

        <template #footer>
          <button @click="cerrarModal" :disabled="form.processing" class="btn-cancel">Cancelar</button>
          <button @click="guardar" class="btn-primary" :disabled="form.processing">
            <LoadingSpinner v-if="form.processing" size="small" />
            <span v-else>{{ modoEdicion ? 'Guardar cambios' : 'Crear asignatura' }}</span>
          </button>
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
          <button @click="cerrarEliminar" :disabled="eliminando" class="btn-cancel">Cancelar</button>
          <button @click="eliminar" class="btn-danger" :disabled="eliminando">
            <LoadingSpinner v-if="eliminando" size="small" />
            <span v-else>Sí, eliminar</span>
          </button>
        </template>
      </Modal>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { useToastStore } from '@/stores/useToastStore'

// HU7: sustituir el arreglo temporal por el paginador y filtros de Laravel.
const props = defineProps({
  asignaturas: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
})
const toast = useToastStore()
const filtroId = ref(props.filtros.id_asignatura ?? '')
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

// HU7: sincronizar filtros al navegar o volver con el historial del navegador.
watch(() => props.filtros, (filtros) => {
  filtroId.value = filtros.id_asignatura ?? ''
  filtroNombre.value = filtros.nombre_asignatura ?? ''
})

function visitar(url, filtros = {}) {
  if (!url || cargando.value) return
  erroresBusqueda.value = {}
  router.get(url, filtros, {
    preserveState: true,
    preserveScroll: true,
    onStart: () => { cargando.value = true },
    onError: (errores) => { erroresBusqueda.value = errores },
    onFinish: () => { cargando.value = false },
  })
}

function buscar() {
  visitar('/asignaturas', {
    id_asignatura: filtroId.value || undefined,
    nombre_asignatura: filtroNombre.value.trim() || undefined,
  })
}

function limpiar() {
  filtroId.value = ''
  filtroNombre.value = ''
  visitar('/asignaturas')
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
.page-container { padding: 24px; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-primary);
  margin: 0 0 4px;
}
.page-subtitle { font-size: 13px; color: var(--color-text-secondary); margin: 0; }

.toolbar {
  flex-wrap: wrap;
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
.filter-field { flex: 1; min-width: 150px; }
.filter-field label { display: block; margin-bottom: 4px; font-size: 13px; }

.search-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--color-bg-input);
  border: 1px solid var(--color-white-soft);
  border-radius: 6px;
  padding: 8px 12px;
  flex: 1;
  max-width: 400px;
}
.search-input {
  border: none;
  background: transparent;
  outline: none;
  flex: 1;
  font-size: 14px;
  color: var(--color-text-main);
}
.clear-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-secondary);
  font-size: 13px;
}
.result-count { font-size: 13px; color: var(--color-text-secondary); white-space: nowrap; }

.table-card {
  background: var(--color-white);
  border-radius: 8px;
  box-shadow: var(--shadow-card);
  overflow: hidden;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th {
  background: var(--color-primary);
  color: var(--color-white);
  padding: 12px 16px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
}
.data-table td { padding: 12px 16px; border-bottom: 1px solid var(--color-white-soft); color: var(--color-text-main); }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--color-bg-base); }
.id-cell { color: var(--color-text-secondary); font-size: 13px; width: 80px; }
.actions-cell { display: flex; gap: 8px; }
.empty-row { text-align: center; color: var(--color-text-secondary); padding: 32px !important; }

.btn-action {
  padding: 5px 12px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}
.btn-edit {
  background: var(--color-bg-input);
  color: var(--color-primary);
  border: 1px solid var(--color-primary);
}
.btn-delete { background: #fdecea; color: #d32f2f; border: 1px solid #d32f2f; }

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid var(--color-white-soft);
}
.btn-page {
  background: var(--color-primary);
  color: var(--color-white);
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
}
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 13px; color: var(--color-text-secondary); }

.loading-center { display: flex; justify-content: center; padding: 48px; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: var(--color-text-main); margin-bottom: 6px; }
.required { color: #d32f2f; }
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-white-soft);
  border-radius: 6px;
  font-size: 14px;
  background: var(--color-bg-input);
  color: var(--color-text-main);
  box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--shadow-input-focus); }
.input-error { border-color: #d32f2f !important; }
.input-readonly { opacity: 0.6; cursor: not-allowed; }
.error-msg { color: #d32f2f; font-size: 12px; margin-top: 4px; }
.help-text { color: var(--color-text-secondary); font-size: 12px; margin-top: 4px; }

.btn-primary {
  background: var(--color-primary);
  color: var(--color-white);
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-cancel {
  background: transparent;
  border: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  font-size: 14px;
  padding: 10px 16px;
}
.btn-danger {
  background: #d32f2f;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
.confirm-text { font-size: 14px; color: var(--color-text-main); line-height: 1.6; }
</style>