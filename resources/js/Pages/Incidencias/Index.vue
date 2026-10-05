<script setup>
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    incidencias: Object,
    examenes: Array,
    estudiantes: Array,
    tipos: Array,
    filters: Object,
});

const toast = useToastStore();

const filtroExamen = ref(props.filters?.id_examen ?? '');
const filtroTipo = ref(props.filters?.tipo_incidencia ?? '');
const filtroDesde = ref(props.filters?.desde ?? '');
const filtroHasta = ref(props.filters?.hasta ?? '');

const modalAbierto = ref(false);
const guardando = ref(false);
const busquedaEstudiante = ref('');

const form = ref({ id_examen: '', id_estudiante: '', tipo_incidencia: '', descripcion_motivo: '' });
const errores = ref({ id_examen: '', tipo_incidencia: '', descripcion_motivo: '' });

const estudiantesFiltrados = computed(() => {
    const q = busquedaEstudiante.value.trim().toLowerCase();
    if (!q) return props.estudiantes.slice(0, 30);
    return props.estudiantes.filter((e) =>
        `${e.nombres} ${e.apellidos}`.toLowerCase().includes(q) ||
        String(e.codigo_universitario).toLowerCase().includes(q) ||
        String(e.documento_identidad).toLowerCase().includes(q)
    ).slice(0, 30);
});

const nombreExamen = (examen) => examen?.asignatura?.nombre_asignatura ?? 'Sin asignatura';

const nombreEstudiante = (estudiante) =>
    estudiante ? `${estudiante.nombres} ${estudiante.apellidos}` : 'Sin estudiante';

function formatearFecha(valor) {
    if (!valor) return '';
    const fecha = new Date(valor);
    return fecha.toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' });
}

function aplicarFiltros() {
    router.get('/incidencias', {
        id_examen: filtroExamen.value || undefined,
        tipo_incidencia: filtroTipo.value || undefined,
        desde: filtroDesde.value || undefined,
        hasta: filtroHasta.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function limpiarFiltros() {
    filtroExamen.value = '';
    filtroTipo.value = '';
    filtroDesde.value = '';
    filtroHasta.value = '';
    router.get('/incidencias', {}, { preserveState: true, preserveScroll: true });
}

function abrirModal() {
    form.value = { id_examen: '', id_estudiante: '', tipo_incidencia: '', descripcion_motivo: '' };
    errores.value = { id_examen: '', tipo_incidencia: '', descripcion_motivo: '' };
    busquedaEstudiante.value = '';
    modalAbierto.value = true;
}

function cerrarModal() {
    modalAbierto.value = false;
}

function validar() {
    let valido = true;
    errores.value = { id_examen: '', tipo_incidencia: '', descripcion_motivo: '' };
    const mensaje = 'Debe rellenar este campo para proceder';

    if (!form.value.id_examen) {
        errores.value.id_examen = mensaje;
        valido = false;
    }
    if (!form.value.tipo_incidencia) {
        errores.value.tipo_incidencia = mensaje;
        valido = false;
    }
    if (!form.value.descripcion_motivo.trim()) {
        errores.value.descripcion_motivo = mensaje;
        valido = false;
    }
    return valido;
}

function guardar() {
    if (!validar()) return;
    guardando.value = true;

    router.post('/incidencias', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Incidencia registrada correctamente.');
            cerrarModal();
        },
        onError: (e) => {
            errores.value.id_examen = e.id_examen ?? '';
            errores.value.tipo_incidencia = e.tipo_incidencia ?? '';
            errores.value.descripcion_motivo = e.descripcion_motivo ?? '';
            toast.error('No se pudo registrar la incidencia.');
        },
        onFinish: () => { guardando.value = false; },
    });
}

function irAPagina(url) {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Incidencias" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <div class="header-section">
                <div>
                    <h1 class="panel-title">INCIDENCIAS</h1>
                    <p class="subtitle">Historial de situaciones ocurridas durante los exámenes</p>
                </div>
                <button class="btn-primary" @click="abrirModal">Reportar incidencia</button>
            </div>

            <div class="filtros">
                <div class="filtro-campo">
                    <label class="filtro-label">Examen</label>
                    <select v-model="filtroExamen" class="control" @change="aplicarFiltros">
                        <option value="">Todos</option>
                        <option v-for="examen in examenes" :key="examen.id_examen" :value="examen.id_examen">
                            {{ nombreExamen(examen) }} · {{ examen.fecha }}
                        </option>
                    </select>
                </div>
                <div class="filtro-campo">
                    <label class="filtro-label">Tipo</label>
                    <select v-model="filtroTipo" class="control" @change="aplicarFiltros">
                        <option value="">Todos</option>
                        <option v-for="tipo in tipos" :key="tipo" :value="tipo">{{ tipo }}</option>
                    </select>
                </div>
                <div class="filtro-campo">
                    <label class="filtro-label">Desde</label>
                    <input v-model="filtroDesde" type="date" class="control" @change="aplicarFiltros" />
                </div>
                <div class="filtro-campo">
                    <label class="filtro-label">Hasta</label>
                    <input v-model="filtroHasta" type="date" class="control" @change="aplicarFiltros" />
                </div>
                <button class="btn-secundario" @click="limpiarFiltros">Limpiar</button>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Examen</th>
                            <th>Estudiante</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="incidencia in incidencias.data" :key="incidencia.id_incidencia">
                            <td data-label="Fecha y hora" class="col-fecha">{{ formatearFecha(incidencia.fecha_hora) }}</td>
                            <td data-label="Examen">{{ nombreExamen(incidencia.examen) }}</td>
                            <td data-label="Estudiante">{{ nombreEstudiante(incidencia.estudiante) }}</td>
                            <td data-label="Tipo">
                                <span class="badge" :class="incidencia.tipo_incidencia === 'Expulsión' ? 'badge-critico' : 'badge-normal'">
                                    {{ incidencia.tipo_incidencia }}
                                </span>
                            </td>
                            <td data-label="Descripción" class="col-descripcion">{{ incidencia.descripcion_motivo }}</td>
                            <td data-label="Registrado por">{{ incidencia.user?.name ?? 'N/D' }}</td>
                        </tr>
                        <tr v-if="!incidencias.data || incidencias.data.length === 0">
                            <td colspan="6" class="empty-state">No hay incidencias registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="paginacion" v-if="incidencias.last_page > 1">
                <button class="btn-page" :disabled="!incidencias.prev_page_url" @click="irAPagina(incidencias.prev_page_url)">Anterior</button>
                <span class="page-info">Página {{ incidencias.current_page }} de {{ incidencias.last_page }}</span>
                <button class="btn-page" :disabled="!incidencias.next_page_url" @click="irAPagina(incidencias.next_page_url)">Siguiente</button>
            </div>
        </div>

        <Modal :open="modalAbierto" title="Reportar incidencia" @close="cerrarModal">
            <div class="form-group">
                <label class="form-label">Examen <span class="required">*</span></label>
                <select v-model="form.id_examen" class="control" :class="{ 'control-error': errores.id_examen }">
                    <option value="">Seleccione un examen</option>
                    <option v-for="examen in examenes" :key="examen.id_examen" :value="examen.id_examen">
                        {{ nombreExamen(examen) }} · {{ examen.fecha }}
                    </option>
                </select>
                <p v-if="errores.id_examen" class="error-msg">{{ errores.id_examen }}</p>
            </div>

            <div class="form-group">
                <label class="form-label">Estudiante</label>
                <input v-model="busquedaEstudiante" type="text" class="control" placeholder="Buscar por nombre, código o documento" />
                <select v-model="form.id_estudiante" class="control select-lista">
                    <option value="">Sin estudiante asociado</option>
                    <option v-for="estudiante in estudiantesFiltrados" :key="estudiante.id_estudiante" :value="estudiante.id_estudiante">
                        {{ estudiante.codigo_universitario }} · {{ estudiante.nombres }} {{ estudiante.apellidos }}
                    </option>
                </select>
                <p class="help-text">Opcional: una incidencia puede no estar asociada a un estudiante.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Tipo de incidencia <span class="required">*</span></label>
                <select v-model="form.tipo_incidencia" class="control" :class="{ 'control-error': errores.tipo_incidencia }">
                    <option value="">Seleccione un tipo</option>
                    <option v-for="tipo in tipos" :key="tipo" :value="tipo">{{ tipo }}</option>
                </select>
                <p v-if="errores.tipo_incidencia" class="error-msg">{{ errores.tipo_incidencia }}</p>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción <span class="required">*</span></label>
                <textarea v-model="form.descripcion_motivo" rows="4" class="control" :class="{ 'control-error': errores.descripcion_motivo }" placeholder="Describa lo ocurrido"></textarea>
                <p v-if="errores.descripcion_motivo" class="error-msg">{{ errores.descripcion_motivo }}</p>
            </div>

            <p class="aviso">La fecha, la hora y el usuario se registran automáticamente. Una vez guardada, la incidencia no puede editarse ni eliminarse.</p>

            <template #footer>
                <button class="btn-cancelar" @click="cerrarModal">Cancelar</button>
                <button class="btn-primary" :disabled="guardando" @click="guardar">
                    <LoadingSpinner v-if="guardando" size="small" />
                    <span v-else>Registrar incidencia</span>
                </button>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem 3rem; background-color: var(--bg-main); min-height: 100vh; font-family: var(--font-family); }
.header-section { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin: 0 0 4px; letter-spacing: 0.05em; font-family: var(--font-display); }
.subtitle { font-size: 0.95rem; color: var(--text-muted); margin: 0; }
.filtros { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem; }
.filtro-campo { display: flex; flex-direction: column; gap: 4px; }
.filtro-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.control { padding: 0.5rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.25rem; font-size: 0.95rem; background: #fff; color: var(--text-dark); font-family: inherit; width: 100%; box-sizing: border-box; }
.control:focus { outline: none; border-color: var(--color-primary); }
.control-error { border-color: var(--color-active); }
.select-lista { margin-top: 6px; }
.btn-primary { background: var(--color-primary); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 0.25rem; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-secundario { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 0.5rem 1rem; border-radius: 0.25rem; font-size: 0.95rem; cursor: pointer; font-family: inherit; }
.btn-cancelar { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.95rem; padding: 0.6rem 1rem; font-family: inherit; }
.table-container { background: #fff; border: 1px solid var(--border-light); border-radius: var(--radius-md); overflow: hidden; margin-top: var(--spacing-md); }
.col-fecha { white-space: nowrap; color: var(--text-muted); }
.col-descripcion { max-width: 320px; }
.badge { padding: 3px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
.badge-normal { background: #eff6ff; color: var(--color-primary); }
.badge-critico { background: #fdecea; color: var(--color-active); }
.paginacion { display: flex; justify-content: center; align-items: center; gap: 1rem; padding: 1rem; }
.btn-page { background: var(--color-primary); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; color: var(--text-muted); }
.aviso { font-size: 0.85rem; color: var(--text-muted); background: #f9fafb; border-left: 3px solid var(--color-primary); padding: 0.6rem 0.75rem; border-radius: 0 4px 4px 0; margin: 0; }
@media (max-width: 820px) {
    .panel-container { padding: 1.25rem; }
    .header-section { flex-direction: column; align-items: stretch; }
    .btn-primary { justify-content: center; }
    .filtros { flex-direction: column; align-items: stretch; }
    .data-table thead { display: none; }
    .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
    .data-table tr { border-bottom: 1px solid #e5e7eb; padding: 0.5rem 0; }
    .data-table td { border: none; padding: 0.35rem 1rem; }
    .data-table td::before { content: attr(data-label); display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; margin-bottom: 2px; }
    .col-descripcion { max-width: none; }
}
</style>
