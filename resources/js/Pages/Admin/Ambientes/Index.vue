<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
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
                <Button variant="primary" @click="abrirModalNuevo">+ Nuevo Ambiente</Button>
            </div>

            <div class="search-section">
                <div class="filter-field">
                    <TextInput v-model="busqueda" placeholder="Buscar ambientes por nombre..." style="width: 100%; max-width: 400px;" />
                </div>
                <span class="result-count" aria-live="polite">{{ ambientes.total }} ambiente(s)</span>
            </div>
            <p v-if="errorBusqueda" class="error-msg" role="alert">{{ errorBusqueda }}</p>

            <div class="table-container" :aria-busy="cargando">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NOMBRE</th>
                            <th>CAPACIDAD</th>
                            <th class="actions-col">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ambiente in ambientes.data" :key="ambiente.id_ambiente">
                            <td class="col-id">{{ ambiente.id_ambiente }}</td>
                            <td>{{ ambiente.nombre_ambiente }}</td>
                            <td><span class="capacity-badge">{{ ambiente.capacidad }} personas</span></td>
                            <td class="actions-cell">
                                <Button variant="action" @click="abrirModalEditar(ambiente)">Editar</Button>
                                <Button variant="delete" @click="confirmarEliminar(ambiente)">Eliminar</Button>
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
                <Button variant="primary" :disabled="cargando || !ambientes.prev_page_url" @click="visitar(ambientes.prev_page_url)">Anterior</Button>
                <span class="page-info">Página {{ ambientes.current_page }} de {{ ambientes.last_page }}</span>
                <Button variant="primary" :disabled="cargando || !ambientes.next_page_url" @click="visitar(ambientes.next_page_url)">Siguiente</Button>
            </div>

        </div>

        <!-- Modal Nuevo / Editar -->
        <Modal :open="modalAbierto" :title="modoEdicion ? 'Editar Ambiente' : 'Nuevo Ambiente'" @close="cerrarModal">
            <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <TextInput v-model="form.nombre_ambiente" type="text" :class="{ 'input-error': errores.nombre_ambiente }" placeholder="Ej: Aula A-3" @input="errores.nombre_ambiente = ''" />
                <p v-if="errores.nombre_ambiente" class="error-msg">{{ errores.nombre_ambiente }}</p>
            </div>
            <div class="form-group">
                <label class="form-label">Capacidad <span class="required">*</span></label>
                <TextInput v-model.number="form.capacidad" type="number" min="1" :class="{ 'input-error': errores.capacidad }" placeholder="Ej: 80" @input="errores.capacidad = ''" />
                <p v-if="errores.capacidad" class="error-msg">{{ errores.capacidad }}</p>
                <p class="help-text">Debe ser un número entero mayor a 0.</p>
            </div>
            <div v-if="modoEdicion" class="form-group">
                <label class="form-label">ID</label>
                <TextInput :value="ambienteEditando?.id_ambiente" type="text" class="input-readonly" readonly />
                <p class="help-text">El ID no es editable.</p>
            </div>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="cerrarModal" :disabled="guardando">Cancelar</Button>
                <Button variant="primary" class="btn-modal" @click="guardar" :disabled="guardando">
                    <LoadingSpinner v-if="guardando" size="small" />
                    <span v-else>{{ modoEdicion ? 'Guardar cambios' : 'Crear ambiente' }}</span>
                </Button>
            </template>
        </Modal>

        <!-- Modal Confirmar Eliminar -->
        <Modal :open="modalEliminar" title="Eliminar Ambiente" @close="modalEliminar = false">
            <p class="confirm-text">¿Estás seguro de eliminar <strong>{{ ambienteAEliminar?.nombre_ambiente }}</strong>? Esta acción no se puede deshacer.</p>
            <p v-if="errorEliminar" class="error-msg" role="alert">{{ errorEliminar }}</p>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="modalEliminar = false" :disabled="eliminando">Cancelar</Button>
                <Button variant="danger" class="btn-modal" @click="eliminar" :disabled="eliminando">
                    <LoadingSpinner v-if="eliminando" size="small" />
                    <span v-else>Sí, eliminar</span>
                </Button>
            </template>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal */
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-lg);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0 0 4px;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

.subtitle {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.highlight-number {
    font-weight: 700;
    color: var(--color-primary);
}

.search-section {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.filter-field {
    flex: 1;
    min-width: 250px;
}

.result-count {
    font-size: 0.85rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.capacity-badge {
    background: var(--color-white-soft);
    color: var(--color-primary);
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid var(--border-light);
}

.empty-state {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem !important;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border-top: 1px solid var(--border-light);
    background-color: var(--color-white);
}

.page-info {
    font-size: 0.85rem;
    color: var(--text-muted);
}


.confirm-text {
    font-size: 0.9rem;
    color: var(--text-dark);
    line-height: 1.6;
}

/* Tabla */
.table-container { 
    background: var(--color-white); 
    border: 1px solid var(--border-light); 
    border-radius: var(--radius-md); 
    overflow-x: auto; 
    width: 100%; 
    box-shadow: var(--shadow-card);
}

.col-id { 
    color: var(--text-muted); 
    font-family: var(--font-mono); 
    font-size: 0.85rem; 
    width: 60px; 
}

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

/* Responsive */
@media (max-width: 768px) {
    .header-section {
        flex-direction: column;
        align-items: stretch;
    }
    .header-section > button {
        width: 100%;
    }
    .search-section {
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
