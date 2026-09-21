<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useToastStore } from '@/stores/useToastStore';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';

const toast = useToastStore();

const props = defineProps({
    examenes: Object, // Objeto paginado de Laravel
    filters: Object,
    periodos: Array,
    esAdmin: Boolean
});

// Filtros reales que soporta el backend: asignatura (coincidencia parcial,
// tolerante a acentos y mayúsculas), periodo, fecha exacta y hora. La búsqueda
// se dispara con el botón Buscar; así no se traba la página mientras se escribe.
const busqueda = ref(props.filters?.asignatura ?? '');
const idPeriodo = ref(props.filters?.id_periodo ?? '');
const fecha = ref(props.filters?.fecha ?? '');
const horaInicio = ref(props.filters?.hora_inicio ?? '');
const cargando = ref(false);

// Si llegan filtros desde la URL, el estado vacío lo indica con otro mensaje.
const hayFiltrosActivos = computed(() =>
    !!(props.filters?.asignatura || props.filters?.id_periodo || props.filters?.fecha || props.filters?.hora_inicio)
);

// Mantiene los inputs en sincronía con la URL al navegar o volver con el historial.
watch(() => props.filters, (filtros) => {
    busqueda.value = filtros?.asignatura ?? '';
    idPeriodo.value = filtros?.id_periodo ?? '';
    fecha.value = filtros?.fecha ?? '';
    horaInicio.value = filtros?.hora_inicio ?? '';
});

function visitar(url, datos = {}, opciones = {}) {
    if (!url || cargando.value) return;
    router.get(url, datos, {
        preserveState: true,
        preserveScroll: true,
        replace: false,
        onStart: () => { cargando.value = true; },
        onFinish: () => { cargando.value = false; },
        ...opciones,
    });
}

function busquedaParams() {
    return {
        asignatura: busqueda.value.trim() || undefined,
        id_periodo: idPeriodo.value || undefined,
        fecha: fecha.value || undefined,
        hora_inicio: horaInicio.value || undefined,
    };
}

function buscar() {
    // replace=true evita acumular en el historial una entrada por cada búsqueda
    // y que al volver a la sección se restaure una URL con filtros obsoletos.
    visitar('/examenes', busquedaParams(), {
        replace: true,
        only: ['examenes', 'filters'],
    });
}

function limpiar() {
    busqueda.value = '';
    idPeriodo.value = '';
    fecha.value = '';
    horaInicio.value = '';
    visitar('/examenes', {}, {
        replace: true,
        only: ['examenes', 'filters'],
    });
}

// Paginación: la URL generada por Laravel ya conserva los filtros aplicados.
function visitarPagina(url) {
    visitar(url);
}

// Información visual de cada estado del examen (ciclo de vida).
const ESTADOS = {
    programado: { clase: 'badge-programado', etiqueta: 'Programado' },
    en_curso: { clase: 'badge-en-curso', etiqueta: 'En curso' },
    finalizado: { clase: 'badge-finalizado', etiqueta: 'Finalizado' },
    cancelado: { clase: 'badge-cancelado', etiqueta: 'Anulado' },
};

function estadoInfo(estadoActual) {
    return ESTADOS[estadoActual] ?? { clase: 'badge-pendiente', etiqueta: estadoActual ?? 'Sin estado' };
}

// Confirmación de acciones manuales (anular/suspender/reanudar/eliminar) mediante
// un modal, en lugar del confirm nativo del navegador.
const confirmacion = ref({
    abierta: false,
    examen: null,
    accion: null,
    titulo: '',
    descripcion: '',
    boton: '',
});

const CONTENIDO_ACCIONES = {
    anular: {
        titulo: 'Anular examen',
        descripcion: 'Esta acción es DEFINITIVA y quedará registrada en la auditoría. El examen dejará de considerarse y no podrá reanudarse.',
        boton: 'Anular',
    },
    suspender: {
        titulo: 'Suspender examen',
        descripcion: 'Se pausa el registro de nuevos ingresos. El examen continúa en curso según su horario (la duración no cambia) y podrás reanudarlo al resolver el incidente.',
        boton: 'Suspender',
    },
    reanudar: {
        titulo: 'Reanudar examen',
        descripcion: 'Vuelve a permitir el registro de ingresos. El estado del examen volverá a derivarse automáticamente de su horario.',
        boton: 'Reanudar',
    },
    eliminar: {
        titulo: 'Eliminar examen',
        descripcion: 'Se eliminará el examen y sus ambientes asociados. No se puede eliminar si tiene inscripciones o registros de ingreso.',
        boton: 'Eliminar',
    },
};

function abrirConfirmacion(examen, accion) {
    const contenido = CONTENIDO_ACCIONES[accion];
    if (!contenido) return;
    confirmacion.value = {
        abierta: true,
        examen,
        accion,
        titulo: contenido.titulo,
        descripcion: contenido.descripcion,
        boton: contenido.boton,
    };
}

function cerrarConfirmacion() {
    confirmacion.value.abierta = false;
}

function confirmarAccion() {
    const { examen, accion } = confirmacion.value;
    cerrarConfirmacion();
    if (!examen) return;

    const opciones = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash?.success) toast.success(page.props.flash.success);
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'No se pudo realizar la operación.');
        },
    };

    if (accion === 'eliminar') {
        router.delete(`/examenes/${examen.id_examen}`, opciones);
        return;
    }

    router.patch(`/examenes/${examen.id_examen}/estado`, { accion }, opciones);
}

// Clase del botón de confirmación según la acción.
const claseBotonConfirmacion = computed(() => {
    const clases = {
        anular: 'btn-anular',
        eliminar: 'btn-delete',
        suspender: 'btn-suspender',
        reanudar: 'btn-reanudar',
    };
    return clases[confirmacion.value.accion] ?? '';
});
</script>

<template>
    <Head title="Gestión de Exámenes" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">GESTIÓN DE EXÁMENES</h1>

            <!-- Barra de Acciones: buscador con lupa + botón de registro + filtros seleccionables.
                 Los filtros se aplican al pulsar Buscar para no trabar la página mientras se escribe. -->
            <div class="action-bar">
                <div class="action-bar-main">
                    <form class="search-row" @submit.prevent="buscar">
                        <div class="search-wrapper">
                            <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input
                                v-model="busqueda"
                                type="text"
                                placeholder="Buscar por asignatura..."
                                class="search-input"
                                :disabled="cargando"
                            >
                        </div>
                        <div class="search-actions">
                            <Button type="submit" variant="primary" class="btn-toolbar" :disabled="cargando">Buscar</Button>
                            <Button type="button" variant="action" class="btn-toolbar" :disabled="cargando" @click="limpiar">Limpiar</Button>
                        </div>
                        </form>
                        <Button as="a" variant="primary" class="btn-create" @click.prevent="router.visit('/examenes/crear')">
                            + Registrar Examen
                        </Button>
                    </div>

                <div class="filter-row">
                    <label class="filter-field">
                        <span class="filter-label">Periodo</span>
                        <select v-model="idPeriodo" class="filter-input" :disabled="cargando">
                            <option value="">Todos</option>
                            <option v-for="periodo in periodos" :key="periodo.id_periodo" :value="String(periodo.id_periodo)" :title="periodo.nombre">
                                {{ periodo.codigo }}
                            </option>
                        </select>
                    </label>
                    <label class="filter-field">
                        <span class="filter-label">Fecha</span>
                        <input v-model="fecha" type="date" class="filter-input" :disabled="cargando" />
                    </label>
                    <label class="filter-field">
                        <span class="filter-label">Hora</span>
                        <input v-model="horaInicio" type="time" class="filter-input" :disabled="cargando" />
                    </label>
                </div>
            </div>

            <!-- Tabla de Datos -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ASIGNATURA</th>
                            <th>PERIODO</th>
                            <th v-if="esAdmin">DOCENTE</th>
                            <th>GRUPOS</th>
                            <th>FECHA</th>
                            <th>HORA</th>
                            <th>HORA FIN</th>
                            <th>AMBIENTES</th>
                            <th>ESTADO</th>
                            <th class="actions-col">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="examen in examenes.data" :key="examen.id_examen">
                            <!-- Ajusta las propiedades (ej: asignatura.nombre) según tu BD -->
                            <td class="col-asignatura">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            <td :title="examen.periodo?.nombre || ''">{{ examen.periodo_codigo || '—' }}</td>
                            <td v-if="esAdmin">{{ examen.docentes?.join(', ') || 'N/D' }}</td>
                            <!-- Grupos de la asignatura -->
                            <td>
                                <span v-if="examen.grupos && examen.grupos.length > 0" class="group-badges">
                                    <span v-for="grupo in examen.grupos" :key="grupo" class="badge badge-grupo">{{ grupo }}</span>
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            
                            <!-- Uso de una fuente monoespaciada para fechas y horas si lo deseas -->
                            <td class="col-fecha">{{ examen.fecha }}</td>
                            <td class="col-hora">{{ (examen.hora_inicio || '').slice(0, 5) }}</td>
                            <td class="col-hora">{{ examen.hora_fin || '—' }}</td>
                            
                            <!-- Procesamiento de ambientes (array a string separado por comas) -->
                            <td>
                                <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                    {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                </span>
                                <span v-else class="text-muted">Sin ambiente</span>
                            </td>
                            
                            <td>
                                <div class="estado-cell">
                                    <span :class="['badge', estadoInfo(examen.estado_actual).clase]">
                                        {{ estadoInfo(examen.estado_actual).etiqueta }}
                                    </span>
                                    <span v-if="examen.estado === 'suspendido' && ['programado', 'en_curso'].includes(examen.estado_actual)" class="tag-ingreso-suspendido">
                                        Ingreso suspendido
                                    </span>
                                </div>
                            </td>
                            
                            <td class="actions-cell">
                                <Button variant="action" @click="router.visit(`/examenes/${examen.id_examen}/habilitaciones`)">Ver</Button>
                                <Button v-if="examen.estado_actual !== 'cancelado' && examen.estado_actual !== 'finalizado'" variant="action" @click="router.visit(`/examenes/${examen.id_examen}/editar`)">Editar</Button>

                                <template v-if="examen.estado === 'suspendido'">
                                    <Button variant="action" class="btn-reanudar" @click="abrirConfirmacion(examen, 'reanudar')">Reanudar</Button>
                                    <Button v-if="examen.estado_actual !== 'finalizado'" variant="action" class="btn-anular" @click="abrirConfirmacion(examen, 'anular')">Anular</Button>
                                </template>

                                <template v-else-if="examen.estado !== 'cancelado' && examen.estado_actual !== 'finalizado'">
                                    <Button v-if="examen.estado_actual === 'en_curso'" variant="action" class="btn-suspender" @click="abrirConfirmacion(examen, 'suspender')">Suspender</Button>
                                    <Button variant="action" class="btn-anular" @click="abrirConfirmacion(examen, 'anular')">Anular</Button>
                                </template>

                                <!-- Exclusivo del Admin (Si está presente en Docente, ignora) -->
                                <Button v-if="esAdmin" variant="delete" @click="abrirConfirmacion(examen, 'eliminar')">Eliminar</Button>
                            </td>
                        </tr>
                        
                        <tr v-if="!examenes.data || examenes.data.length === 0">
                            <td :colspan="esAdmin ? 10 : 9" class="empty-state">
                                {{ hayFiltrosActivos ? 'No hay exámenes que coincidan con los filtros aplicados.' : 'No hay exámenes registrados.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pie de tabla -->
                <div class="table-footer">
                    <span>{{ cargando ? 'Cargando…' : (examenes.to || 0) + ' de ' + (examenes.total || 0) + ' exámenes' }}</span>
                </div>

                <!-- Navegación: los enlaces del servidor conservan los filtros aplicados -->
                <div v-if="examenes.last_page > 1" class="pagination-bar">
                    <button class="btn-page" :disabled="cargando || !examenes.prev_page_url" @click="visitarPagina(examenes.prev_page_url)">
                        ← Anterior
                    </button>
                    <span class="page-info">Página {{ examenes.current_page }} de {{ examenes.last_page }}</span>
                    <button class="btn-page" :disabled="cargando || !examenes.next_page_url" @click="visitarPagina(examenes.next_page_url)">
                        Siguiente →
                    </button>
                </div>
            </div>
        </div>
    <!-- Modal de confirmación para acciones manuales sobre exámenes -->
        <Modal :open="confirmacion.abierta" :title="confirmacion.titulo" @close="cerrarConfirmacion">
            <p class="modal-desc">{{ confirmacion.descripcion }}</p>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="cerrarConfirmacion">Cancelar</Button>
                <Button variant="primary" class="btn-modal" :class="claseBotonConfirmacion" @click="confirmarAccion">{{ confirmacion.boton }}</Button>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor y Título */
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
}

/* Barra de Acciones */
.action-bar {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

/* Fila principal: buscador a la izquierda y botón de registro a la derecha */
.action-bar-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Fila del buscador (lupa + botones) */
.search-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-wrapper {
    position: relative;
    flex: 1;
    max-width: 500px;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 0.5rem 1rem 0.5rem 2.25rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    box-sizing: border-box;
}

.search-actions {
    display: flex;
    gap: 0.5rem;
}

/* Fila de filtros seleccionables */
.filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 1rem;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 140px;
}

.filter-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
}

.filter-input {
    padding: 0.35rem 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    background-color: white;
    box-sizing: border-box;
}

/* Tabla de Datos */
.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow-x: auto; /* Permite visualizar las columnas completas */
    width: 100%;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 0.85rem; /* Ajustado para ganar espacio */
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    text-transform: uppercase;
    font-size: 0.75rem;
    white-space: nowrap;
}

.data-table td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
}

/* Tipografías específicas de celdas */
.col-asignatura {
    color: var(--color-primary); /* Azul oscuro característico */
    font-weight: 600;
}

.text-muted {
    color: #9ca3af;
}

.group-badges {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.badge-grupo {
    background-color: #e0f2fe;
    color: #0369a1;
    border: 1px solid #7dd3fc;
}

/* Badges (Estados) */
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.badge-confirmado {
    background-color: #f3e8ff;
    color: #6b21a8;
    border: 1px solid #d8b4fe;
}

.badge-pendiente {
    background-color: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
}

.badge-borrador {
    background-color: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde047;
}

/* Badges de estados de exámenes */
.badge-programado {
    background-color: #e0f2fe;
    color: #0369a1;
    border: 1px solid #7dd3fc;
}

.badge-en-curso {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}

.badge-finalizado {
    background-color: #ede9fe;
    color: #6d28d9;
    border: 1px solid #c4b5fd;
}

.badge-cancelado {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

/* Contenedor del badge + tag secundario de ingreso suspendido */
.estado-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.tag-ingreso-suspendido {
    font-size: 0.7rem;
    font-weight: 600;
    color: #b45309;
    background-color: #fffbeb;
    border: 1px solid #fde047;
    border-radius: 999px;
    padding: 0.1rem 0.5rem;
    white-space: nowrap;
}

.modal-desc {
    margin: 0;
    color: #4b5563;
    line-height: 1.5;
}

/* Botones de Acción (Ver, Eliminar) */
.actions-col {
    text-align: center !important;
    min-width: 220px;
}

.actions-cell {
    display: flex;
    gap: 0.4rem;
    justify-content: left;
    align-items: center;
    white-space: nowrap;
    min-width: 220px;
}

.btn-anular {
    background-color: var(--color-danger) !important; /* Usar variable global */
    color: #ffffff !important;
    border: none !important;
}

.btn-anular:hover {
    background-color: var(--color-danger-hover) !important;
}

.btn-suspender {
    background-color: #f59e0b !important;
    color: #ffffff !important;
    border: none !important;
}

.btn-suspender:hover {
    background-color: #d97706 !important;
}

.btn-reanudar {
    background-color: #10b981 !important;
    color: #ffffff !important;
    border: none !important;
}

.btn-reanudar:hover {
    background-color: #059669 !important;
}

/* Pie de Tabla */
.table-footer {
    padding: 1rem;
    background-color: #ffffff;
    border-top: 1px solid #e5e7eb;
    color: #9ca3af;
    font-size: 0.75rem;
}

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}

/* Botones y estados deshabilitados */
.search-input:disabled,
.filter-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Paginación */
.pagination-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    background-color: #ffffff;
}

.page-info {
    font-size: 0.8rem;
    color: #6b7280;
}

/* Estandarización geométrica global */
.btn-toolbar,
.btn-modal {
  padding: 10px 20px !important;
  font-size: 14px !important;
}

/* Mantenemos los colores semánticos locales, pero añadimos !important 
   para sobreescribir el color azul base de variant="primary" */
.btn-anular {
    background-color: #dc3545 !important;
    color: #ffffff !important;
    border: none !important;
}
.btn-anular:hover {
    background-color: #b02a37 !important;
}

.btn-suspender {
    background-color: #f59e0b !important;
    color: #ffffff !important;
    border: none !important;
}
.btn-suspender:hover {
    background-color: #d97706 !important;
}

.btn-reanudar {
    background-color: #10b981 !important;
    color: #ffffff !important;
    border: none !important;
}
.btn-reanudar:hover {
    background-color: #059669 !important;
}
</style>