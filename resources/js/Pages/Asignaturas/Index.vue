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

      <!-- Buscador -->
      <div class="toolbar">
        <div class="search-wrapper">
          <span class="search-icon">🔍</span>
          <input
            v-model="busqueda"
            type="text"
            placeholder="Buscar por nombre o ID..."
            class="search-input"
          />
          <button v-if="busqueda" class="clear-btn" @click="busqueda = ''">✕</button>
        </div>
        <span class="result-count">{{ filtradas.length }} asignatura(s)</span>
      </div>

      <!-- Tabla -->
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
            <tr v-for="asignatura in paginadas" :key="asignatura.id_asignatura">
              <td class="id-cell">{{ asignatura.id_asignatura }}</td>
              <td>{{ asignatura.nombre }}</td>
              <td class="actions-cell">
                <button class="btn-action btn-edit" @click="abrirModalEditar(asignatura)">
                  Editar
                </button>
                <button class="btn-action btn-delete" @click="confirmarEliminar(asignatura)">
                  Eliminar
                </button>
              </td>
            </tr>
            <tr v-if="filtradas.length === 0">
              <td colspan="3" class="empty-row">No se encontraron asignaturas.</td>
            </tr>
          </tbody>
        </table>

        <!-- Paginación -->
        <div class="pagination" v-if="totalPaginas > 1">
          <button :disabled="paginaActual === 1" @click="paginaActual--" class="btn-page">
            ← Anterior
          </button>
          <span class="page-info">Página {{ paginaActual }} de {{ totalPaginas }}</span>
          <button :disabled="paginaActual === totalPaginas" @click="paginaActual++" class="btn-page">
            Siguiente →
          </button>
        </div>
      </div>

      <!-- Modal Nueva / Editar -->
      <Modal
        :open="modalAbierto"
        :title="modoEdicion ? 'Editar Asignatura' : 'Nueva Asignatura'"
        @close="cerrarModal"
      >
        <div class="form-group">
          <label class="form-label">Nombre <span class="required">*</span></label>
          <input
            v-model="form.nombre"
            type="text"
            class="form-input"
            :class="{ 'input-error': errores.nombre }"
            placeholder="Ej: Cálculo I"
            @input="errores.nombre = ''"
          />
          <p v-if="errores.nombre" class="error-msg">{{ errores.nombre }}</p>
        </div>

        <div v-if="modoEdicion" class="form-group">
          <label class="form-label">ID</label>
          <input :value="asignaturaEditando?.id_asignatura" type="text" class="form-input input-readonly" readonly />
          <p class="help-text">El ID no es editable.</p>
        </div>

        <template #footer>
          <button @click="cerrarModal" class="btn-cancel">Cancelar</button>
          <button @click="guardar" class="btn-primary" :disabled="guardando">
            <LoadingSpinner v-if="guardando" size="small" />
            <span v-else>{{ modoEdicion ? 'Guardar cambios' : 'Crear asignatura' }}</span>
          </button>
        </template>
      </Modal>

      <!-- Modal Confirmar Eliminar -->
      <Modal :open="modalEliminar" title="Eliminar Asignatura" @close="modalEliminar = false">
        <p class="confirm-text">
          ¿Estás seguro de eliminar <strong>{{ asignaturaAEliminar?.nombre }}</strong>?
          Esta acción no se puede deshacer.
        </p>
        <template #footer>
          <button @click="modalEliminar = false" class="btn-cancel">Cancelar</button>
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
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { useToastStore } from '@/stores/useToastStore'

const props = defineProps({
  asignaturas: {
    type: Array,
    default: () => [],
  },
})

const toast = useToastStore()

// ── Estado ──────────────────────────────────────────────────────────────────
const busqueda      = ref('')
const paginaActual  = ref(1)
const porPagina     = 10
const cargando      = ref(false)
const modalAbierto  = ref(false)
const modalEliminar = ref(false)
const modoEdicion   = ref(false)
const guardando     = ref(false)
const eliminando    = ref(false)

const asignaturaEditando  = ref(null)
const asignaturaAEliminar = ref(null)

const form = ref({ nombre: '' })
const errores = ref({ nombre: '' })

// ── Computed ─────────────────────────────────────────────────────────────────
const filtradas = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  if (!q) return props.asignaturas
  return props.asignaturas.filter(a =>
    a.nombre.toLowerCase().includes(q) ||
    String(a.id_asignatura) === q
  )
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(filtradas.value.length / porPagina)))

const paginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina
  return filtradas.value.slice(inicio, inicio + porPagina)
})

// ── Métodos ──────────────────────────────────────────────────────────────────
function abrirModalNueva() {
  modoEdicion.value = false
  form.value = { nombre: '' }
  errores.value = { nombre: '' }
  asignaturaEditando.value = null
  modalAbierto.value = true
}

function abrirModalEditar(asignatura) {
  modoEdicion.value = true
  asignaturaEditando.value = asignatura
  form.value = { nombre: asignatura.nombre }
  errores.value = { nombre: '' }
  modalAbierto.value = true
}

function cerrarModal() {
  modalAbierto.value = false
}

function validar() {
  errores.value.nombre = ''
  if (!form.value.nombre.trim()) {
    errores.value.nombre = 'El nombre es obligatorio.'
    return false
  }
  return true
}

function guardar() {
  if (!validar()) return
  guardando.value = true

  if (modoEdicion.value) {
    router.put(`/asignaturas/${asignaturaEditando.value.id_asignatura}`, form.value, {
      preserveState: true,
      onSuccess: () => {
        toast.success('Asignatura actualizada correctamente.')
        cerrarModal()
      },
      onError: (e) => {
        if (e.nombre) errores.value.nombre = e.nombre
        else toast.error('Error al actualizar la asignatura.')
      },
      onFinish: () => { guardando.value = false },
    })
  } else {
    router.post('/asignaturas', form.value, {
      preserveState: true,
      onSuccess: () => {
        toast.success('Asignatura creada correctamente.')
        cerrarModal()
      },
      onError: (e) => {
        if (e.nombre) errores.value.nombre = e.nombre
        else toast.error('Error al crear la asignatura.')
      },
      onFinish: () => { guardando.value = false },
    })
  }
}

function confirmarEliminar(asignatura) {
  asignaturaAEliminar.value = asignatura
  modalEliminar.value = true
}

function eliminar() {
  eliminando.value = true
  router.delete(`/asignaturas/${asignaturaAEliminar.value.id_asignatura}`, {
    preserveState: true,
    onSuccess: () => {
      toast.success('Asignatura eliminada correctamente.')
      modalEliminar.value = false
    },
    onError: (e) => {
      if (e.message) toast.error(e.message)
      else toast.error('No se puede eliminar la asignatura porque tiene exámenes registrados.')
      modalEliminar.value = false
    },
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
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
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