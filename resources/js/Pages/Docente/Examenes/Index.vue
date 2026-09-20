<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    examenes: Object,
    filters: Object,
});

// Filtros reales que soporta el backend: asignatura (coincidencia parcial,
// tolerante a acentos y mayúsculas), fecha exacta y hora. La búsqueda se
// dispara con el botón Buscar; así no se traba la página mientras se escribe.
const busqueda = ref(props.filters?.asignatura ?? '');
const fecha = ref(props.filters?.fecha ?? '');
const horaInicio = ref(props.filters?.hora_inicio ?? '');
const cargando = ref(false);

// Si llegan filtros desde la URL, el estado vacío lo indica con otro mensaje.
const hayFiltrosActivos = computed(() =>
    !!(props.filters?.asignatura || props.filters?.fecha || props.filters?.hora_inicio)
);

// Mantiene los inputs en sincronía con la URL al navegar o volver con el historial.
watch(() => props.filters, (filtros) => {
    busqueda.value = filtros?.asignatura ?? '';
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

const getStatusClass = (estado) => {
    const status = estado ? estado.toLowerCase() : '';
    if (status === 'confirmado') return 'badge-confirmado';
    if (status === 'borrador') return 'badge-borrador';
    return 'badge-pendiente';
};
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
                            <button type="submit" class="btn-primary" :disabled="cargando">Buscar</button>
                            <button type="button" class="btn-cancel" :disabled="cargando" @click="limpiar">Limpiar</button>
                        </div>
                    </form>

                    <Link href="/examenes/crear" class="btn-primary btn-create">+ Registrar Examen</Link>
                </div>

                <div class="filter-row">
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
                            <th>GRUPOS</th>
                            <th>FECHA</th>
                            <th>HORA</th>
                            <th>HORA FIN</th>
                            <th>AMBIENTES</th>
                            <th>ESTADO</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="examen in examenes.data" :key="examen.id_examen">
                            
                            <td class="col-asignatura">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            
                            <!-- Grupos del docente en esa asignatura -->
                            <td>
                                <span v-if="examen.grupos && examen.grupos.length > 0" class="group-badges">
                                    <span v-for="grupo in examen.grupos" :key="grupo" class="badge badge-grupo">{{ grupo }}</span>
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            
                            <td class="col-fecha">{{ examen.fecha }}</td>
                            <td class="col-hora">{{ (examen.hora_inicio || '').slice(0, 5) }}</td>
                            <td class="col-hora">{{ examen.hora_fin || '—' }}</td>
                            
                            <td>
                                <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                    {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                </span>
                                <span v-else class="text-muted">Sin ambiente</span>
                            </td>
                            
                            <td>
                                <span :class="['badge', getStatusClass(examen.estado)]">
                                    {{ examen.estado || 'Pendiente' }}
                                </span>
                            </td>
                            
                            <td class="actions-cell">
                                <button class="btn-action" @click="router.visit(`/examenes/${examen.id_examen}/editar`)">Editar</button>
                            </td>
                        </tr>
                        
                        <tr v-if="!examenes.data || examenes.data.length === 0">
                            <td colspan="8" class="empty-state">
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
    </AuthenticatedLayout>
</template>

<style scoped>
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
    font-family: Georgia, serif;
}

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

.btn-create {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    white-space: nowrap;
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

.btn-primary {
    background-color: #3b0707; /* Tono oscuro adaptado al panel docente */
    color: white;
    padding: 0.5rem 1.5rem;
    border: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    cursor: pointer;
}

.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    text-transform: uppercase;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
}

.col-asignatura {
    color: #1e1b4b;
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

.actions-cell {
    display: flex;
    justify-content: flex-end;
}

.btn-action {
    background: transparent;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    color: #374151;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-action:hover {
    background-color: #f9fafb;
}

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
.btn-cancel {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: #374151;
    background-color: white;
    cursor: pointer;
}

.btn-cancel:hover {
    background-color: #f9fafb;
}

.btn-primary:disabled,
.btn-cancel:disabled,
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

.btn-page {
    background-color: #3b0707;
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    cursor: pointer;
}

.btn-page:hover:not(:disabled) {
    opacity: 0.85;
}

.btn-page:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-info {
    font-size: 0.8rem;
    color: #6b7280;
}
</style>