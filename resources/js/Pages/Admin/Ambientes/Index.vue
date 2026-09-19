<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import SearchInput from '@/components/SearchInput.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    ambientes: Object,
    totalCapacidad: Number,
    filters: Object
});

const toast = useToastStore();

const busqueda        = ref(props.filters?.nombre_ambiente ?? '');
const modalAbierto    = ref(false);
const modalEliminar   = ref(false);
const modoEdicion     = ref(false);
const guardando       = ref(false);
const eliminando      = ref(false);
const errorEliminar   = ref('');
const ambienteEditando  = ref(null);
const ambienteAEliminar = ref(null);

const form   = ref({ nombre_ambiente: '', capacidad: '' });
const errores = ref({ nombre_ambiente: '', capacidad: '' });
const cargando = ref(false);
const errorBusqueda = ref('');
let debounceTimer = null;
let cancelarConsulta = null;
let consultaActual = 0;

function cancelarBusqueda() {
    clearTimeout(debounceTimer);
    debounceTimer = null;
    consultaActual++;
    cancelarConsulta?.();
    cancelarConsulta = null;
    cargando.value = false;
}

function visitar(url, filtros = {}, reemplazar = false) {
    if (!url) return;
    cancelarBusqueda();
    const consulta = consultaActual;
    errorBusqueda.value = '';
    router.get(url, filtros, {
        preserveState: true,
        preserveScroll: true,
        replace: reemplazar,
        only: ['ambientes', 'filters'],
        onCancelToken: (token) => { cancelarConsulta = () => token.cancel(); },
        onStart: () => { cargando.value = true; },
        onError: (errores) => {
            if (consulta === consultaActual) errorBusqueda.value = errores.nombre_ambiente ?? 'No se pudo realizar la búsqueda.';
        },
        onFinish: () => {
            if (consulta === consultaActual) {
                cargando.value = false;
                cancelarConsulta = null;
            }
        },
    });
}

function buscar() {
    visitar('/ambientes', { nombre_ambiente: busqueda.value.trim() || undefined }, true);
}

// Esperar una pausa al escribir y cancelar consultas que ya no corresponden al texto.
watch(busqueda, () => {
    cancelarBusqueda();
    errorBusqueda.value = '';
    if (busqueda.value.trim() === (props.filters?.nombre_ambiente ?? '')) return;
    cargando.value = true;
    debounceTimer = setTimeout(buscar, 300);
}, { flush: 'sync' });

// Recuperar el filtro al navegar sin sobrescribir una búsqueda pendiente.
watch(() => props.filters, (filters) => {
    if (!cargando.value && !debounceTimer) busqueda.value = filters?.nombre_ambiente ?? '';
});

onBeforeUnmount(cancelarBusqueda);

function abrirModalNuevo() {
    modoEdicion.value = false;
    form.value = { nombre_ambiente: '', capacidad: '' };
    errores.value = { nombre_ambiente: '', capacidad: '' };
    ambienteEditando.value = null;
    modalAbierto.value = true;
}

function abrirModalEditar(ambiente) {
    modoEdicion.value = true;
    ambienteEditando.value = ambiente;
    form.value = { nombre_ambiente: ambiente.nombre_ambiente, capacidad: ambiente.capacidad };
    errores.value = { nombre_ambiente: '', capacidad: '' };
    modalAbierto.value = true;
}

function cerrarModal() { modalAbierto.value = false; }

function validar() {
    let ok = true;
    errores.value = { nombre_ambiente: '', capacidad: '' };
    if (!form.value.nombre_ambiente.trim()) {
        errores.value.nombre_ambiente = 'El nombre es obligatorio.';
        ok = false;
    }
    if (!form.value.capacidad || form.value.capacidad <= 0) {
        errores.value.capacidad = 'La capacidad debe ser mayor a 0.';
        ok = false;
    }
    return ok;
}

function guardar() {
    if (!validar()) return;
    guardando.value = true;
    if (modoEdicion.value) {
        router.patch(`/ambientes/${ambienteEditando.value.id_ambiente}`, form.value, {
            preserveState: true,
            onSuccess: () => { toast.success('Ambiente actualizado correctamente.'); cerrarModal(); },
            onError: (e) => {
                if (e.nombre_ambiente) errores.value.nombre_ambiente = e.nombre_ambiente;
                else toast.error('Error al actualizar el ambiente.');
            },
            onFinish: () => { guardando.value = false; }
        });
    } else {
        router.post('/ambientes', form.value, {
            preserveState: true,
            onSuccess: () => { toast.success('Ambiente creado correctamente.'); cerrarModal(); },
            onError: (e) => {
                if (e.nombre_ambiente) errores.value.nombre_ambiente = e.nombre_ambiente;
                else toast.error('Error al crear el ambiente.');
            },
            onFinish: () => { guardando.value = false; }
        });
    }
}

function confirmarEliminar(ambiente) {
    ambienteAEliminar.value = ambiente;
    errorEliminar.value = '';
    modalEliminar.value = true;
}

function eliminar() {
    if (eliminando.value) return;
    eliminando.value = true;
    errorEliminar.value = '';
    router.delete(`/ambientes/${ambienteAEliminar.value.id_ambiente}`, {
        preserveState: true,
        preserveScroll: true,
        // Una redirección con flash.error también ejecuta onSuccess en Inertia.
        onSuccess: (page) => {
            if (page.props.flash?.error) {
                errorEliminar.value = page.props.flash.error;
                toast.error(errorEliminar.value);
                return;
            }
            if (page.props.flash?.success) {
                toast.success(page.props.flash.success);
                modalEliminar.value = false;
            }
        },
        onError: () => {
            errorEliminar.value = 'No se pudo eliminar el ambiente.';
            toast.error(errorEliminar.value);
        },
        onFinish: () => { eliminando.value = false; }
    });
}
</script>

<template>
    <Head title="Ambientes" />
    <AuthenticatedLayout>
        <div class="panel-container">

            <div class="header-section">
                <div>
                    <h1 class="panel-title">AMBIENTES</h1>
                    <p class="subtitle">Capacidad total: <span class="highlight-number">{{ totalCapacidad }}</span> personas</p>
                </div>
                <button class="btn-primary" @click="abrirModalNuevo">+ Nuevo Ambiente</button>
            </div>

            <div class="search-section">
                <div class="filter-field">
                    <SearchInput v-model="busqueda" placeholder="Buscar ambientes por nombre..." />
                </div>
                <span class="result-count" aria-live="polite">{{ ambientes.total }} ambiente(s)</span>
            </div>
            <p v-if="errorBusqueda" class="error-msg" role="alert">{{ errorBusqueda }}</p>

            <div class="table-container" :aria-busy="cargando">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Capacidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ambiente in ambientes.data" :key="ambiente.id_ambiente">
                            <td class="col-id">{{ ambiente.id_ambiente }}</td>
                            <td>{{ ambiente.nombre_ambiente }}</td>
                            <td><span class="capacity-badge">{{ ambiente.capacidad }} personas</span></td>
                            <td class="col-actions">
                                <button class="btn-action btn-edit" @click="abrirModalEditar(ambiente)">Editar</button>
                                <button class="btn-action btn-delete" @click="confirmarEliminar(ambiente)">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="!ambientes.data || ambientes.data.length === 0">
                            <td colspan="4" class="empty-state">No se encontraron ambientes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="pagination" v-if="ambientes.last_page > 1">
                <button :disabled="cargando || !ambientes.prev_page_url" @click="visitar(ambientes.prev_page_url)" class="btn-page">← Anterior</button>
                <span class="page-info">Página {{ ambientes.current_page }} de {{ ambientes.last_page }}</span>
                <button :disabled="cargando || !ambientes.next_page_url" @click="visitar(ambientes.next_page_url)" class="btn-page">Siguiente →</button>
            </div>

        </div>

        <!-- Modal Nuevo / Editar -->
        <Modal :open="modalAbierto" :title="modoEdicion ? 'Editar Ambiente' : 'Nuevo Ambiente'" @close="cerrarModal">
            <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <input v-model="form.nombre_ambiente" type="text" class="form-input" :class="{ 'input-error': errores.nombre_ambiente }" placeholder="Ej: Aula A-3" @input="errores.nombre_ambiente = ''" />
                <p v-if="errores.nombre_ambiente" class="error-msg">{{ errores.nombre_ambiente }}</p>
            </div>
            <div class="form-group">
                <label class="form-label">Capacidad <span class="required">*</span></label>
                <input v-model.number="form.capacidad" type="number" min="1" class="form-input" :class="{ 'input-error': errores.capacidad }" placeholder="Ej: 80" @input="errores.capacidad = ''" />
                <p v-if="errores.capacidad" class="error-msg">{{ errores.capacidad }}</p>
                <p class="help-text">Debe ser un número entero mayor a 0.</p>
            </div>
            <div v-if="modoEdicion" class="form-group">
                <label class="form-label">ID</label>
                <input :value="ambienteEditando?.id_ambiente" type="text" class="form-input input-readonly" readonly />
                <p class="help-text">El ID no es editable.</p>
            </div>
            <template #footer>
                <button @click="cerrarModal" class="btn-cancel">Cancelar</button>
                <button @click="guardar" class="btn-primary" :disabled="guardando">
                    <LoadingSpinner v-if="guardando" size="small" />
                    <span v-else>{{ modoEdicion ? 'Guardar cambios' : 'Crear ambiente' }}</span>
                </button>
            </template>
        </Modal>

        <!-- Modal Confirmar Eliminar -->
        <Modal :open="modalEliminar" title="Eliminar Ambiente" @close="modalEliminar = false">
            <p class="confirm-text">¿Estás seguro de eliminar <strong>{{ ambienteAEliminar?.nombre_ambiente }}</strong>? Esta acción no se puede deshacer.</p>
            <p v-if="errorEliminar" class="error-msg" role="alert">{{ errorEliminar }}</p>
            <template #footer>
                <button @click="modalEliminar = false" class="btn-cancel">Cancelar</button>
                <button @click="eliminar" class="btn-danger" :disabled="eliminando">
                    <LoadingSpinner v-if="eliminando" size="small" />
                    <span v-else>Sí, eliminar</span>
                </button>
            </template>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem 3rem; background-color: var(--bg-main); min-height: 100vh; font-family: var(--font-family); }
.header-section { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin: 0 0 4px; letter-spacing: 0.05em; }
.subtitle { font-size: 0.875rem; color: #6b7280; }
.highlight-number { font-weight: 700; color: var(--color-primary); }
.search-section { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 16px; }
.filter-field { flex: 1; min-width: 150px; }
.search-section :deep(.search-wrapper) { margin-bottom: 0; }
.result-count { font-size: 13px; color: var(--color-text-secondary); white-space: nowrap; }
.table-container { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { background-color: var(--color-primary); color: white; text-align: left; padding: 0.75rem 1rem; font-weight: 600; }
.data-table td { padding: 1rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #f9fafb; }
.col-id { color: #6b7280; font-size: 0.8rem; width: 60px; }
.col-actions { display: flex; gap: 8px; }
.capacity-badge { background: #eff6ff; color: var(--color-primary); padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.empty-state { text-align: center; color: #6b7280; padding: 2rem !important; }
.btn-action { padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; }
.btn-edit { background: #eff6ff; color: var(--color-primary); border: 1px solid var(--color-primary); }
.btn-delete { background: #fdecea; color: #d32f2f; border: 1px solid #d32f2f; }
.pagination { display: flex; justify-content: center; align-items: center; gap: 16px; padding: 16px; }
.btn-page { background: var(--color-primary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 13px; color: #6b7280; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.required { color: #d32f2f; }
.form-input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background: #f9fafb; color: #374151; box-sizing: border-box; }
.form-input:focus { outline: none; border-color: var(--color-primary); }
.input-error { border-color: #d32f2f !important; }
.input-readonly { opacity: 0.6; cursor: not-allowed; }
.error-msg { color: #d32f2f; font-size: 12px; margin-top: 4px; }
.help-text { color: #6b7280; font-size: 12px; margin-top: 4px; }
.btn-primary { background: var(--color-primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.btn-cancel { background: transparent; border: none; color: #6b7280; cursor: pointer; font-size: 14px; padding: 10px 16px; }
.btn-danger { background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.confirm-text { font-size: 14px; color: #374151; line-height: 1.6; }
</style>
