<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useToastStore } from '@/stores/useToastStore';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';

const toast = useToastStore();
const page = usePage();

const nombreUsuarioActual = page.props.auth?.user?.name ?? '';

const props = defineProps({
    examenes: Object, // Objeto paginado de Laravel
    filters: Object,
    periodos: Array,
    tipos: Array,
    conteos: Object,
    esAdmin: Boolean
});

// Filtros
const busqueda = ref(props.filters?.asignatura ?? '');
const idPeriodo = ref(props.filters?.id_periodo ?? '');
const idTipo = ref(props.filters?.id_tipo_examen ?? '');
const fecha = ref(props.filters?.fecha ?? '');
const horaInicio = ref(props.filters?.hora_inicio ?? '');
const estado = ref(props.filters?.estado ?? '');
const compartido = ref(props.filters?.compartido === '1');
const cargando = ref(false);

const hayFiltrosActivos = computed(() =>
    !!(props.filters?.asignatura || props.filters?.id_periodo || props.filters?.id_tipo_examen || props.filters?.fecha || props.filters?.hora_inicio || props.filters?.estado || props.filters?.compartido)
);

// Pestañas de estado
const CHIPS = [
    { key: '', etiqueta: 'Todos' },
    { key: 'programado', etiqueta: 'Programados' },
    { key: 'en_curso', etiqueta: 'En curso' },
    { key: 'finalizado', etiqueta: 'Finalizados' },
    { key: 'cancelado', etiqueta: 'Cancelados' },
    { key: 'anulado', etiqueta: 'Anulados' },
    { key: 'suspendido', etiqueta: 'Suspendidos' },
    { key: 'compartidos', etiqueta: 'Compartidos' },
];

function conteoChip(key) {
    if (!key) return null;
    return props.conteos?.[key] ?? 0;
}

function chipActivo(key) {
    if (key === 'compartidos') return compartido.value;
    return (estado.value || '') === (key || '');
}

function seleccionarChip(key) {
    if (cargando.value) return;
    if (key === 'compartidos') {
        compartido.value = true;
        estado.value = '';
    } else {
        estado.value = key;
        compartido.value = false;
    }
    visitar('/examenes', busquedaParams(), {
        replace: true,
        only: ['examenes', 'filters', 'conteos'],
    });
}

watch(() => props.filters, (filtros) => {
    busqueda.value = filtros?.asignatura ?? '';
    idPeriodo.value = filtros?.id_periodo ?? '';
    idTipo.value = filtros?.id_tipo_examen ?? '';
    fecha.value = filtros?.fecha ?? '';
    horaInicio.value = filtros?.hora_inicio ?? '';
    estado.value = filtros?.estado ?? '';
    compartido.value = filtros?.compartido === '1';
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
        id_tipo_examen: idTipo.value || undefined,
        fecha: fecha.value || undefined,
        hora_inicio: horaInicio.value || undefined,
        estado: estado.value || undefined,
        compartido: compartido.value ? '1' : undefined,
    };
}

function buscar() {
    visitar('/examenes', busquedaParams(), {
        replace: true,
        only: ['examenes', 'filters', 'conteos'],
    });
}

function limpiar() {
    busqueda.value = '';
    idPeriodo.value = '';
    idTipo.value = '';
    fecha.value = '';
    horaInicio.value = '';
    estado.value = '';
    compartido.value = false;
    visitar('/examenes', {}, {
        replace: true,
        only: ['examenes', 'filters', 'conteos'],
    });
}

function visitarPagina(url) {
    visitar(url);
}

// Estados visuales de examen
const ESTADOS = {
    programado: { clase: 'badge-programado', etiqueta: 'Programado' },
    en_curso: { clase: 'badge-en-curso', etiqueta: 'En curso' },
    finalizado: { clase: 'badge-finalizado', etiqueta: 'Finalizado' },
    cancelado: { clase: 'badge-cancelado', etiqueta: 'Cancelado' },
    anulado: { clase: 'badge-anulado', etiqueta: 'Anulado' },
    suspendido: { clase: 'badge-suspendido', etiqueta: 'Suspendido' },
};

function estadoInfo(estadoActual) {
    return ESTADOS[estadoActual] ?? { clase: 'badge-pendiente', etiqueta: estadoActual ?? 'Sin estado' };
}

// Distinción de exámenes compartidos
const PALETA_GRUPOS = [
    { fondo: '#e0f2fe', texto: '#0369a1', borde: '#7dd3fc' },
    { fondo: '#fef3c7', texto: '#92400e', borde: '#fcd34d' },
    { fondo: '#ede9fe', texto: '#5b21b6', borde: '#c4b5fd' },
    { fondo: '#d1fae5', texto: '#065f46', borde: '#6ee7b7' },
    { fondo: '#ffe4e6', texto: '#9f1239', borde: '#fda4af' },
    { fondo: '#cffafe', texto: '#155e75', borde: '#67e8f9' },
];

function estiloBadgeGrupo(examen, grupo) {
    const indice = (examen.docentes ?? []).indexOf(grupo.nombre_docente);
    const color = PALETA_GRUPOS[(indice >= 0 ? indice : 0) % PALETA_GRUPOS.length];
    return { backgroundColor: color.fondo, color: color.texto, borderColor: color.borde };
}

function tituloGrupo(grupo) {
    return grupo.nombre_docente ? `Grupo ${grupo.nombre} · ${grupo.nombre_docente}` : `Grupo ${grupo.nombre}`;
}

function tituloCompartido(examen) {
    const cantidad = examen.docentes?.length ?? 2;
    return `Examen compartido entre ${cantidad} docentes`;
}

// Confirmación modal
const confirmacion = ref({
    abierta: false,
    examen: null,
    accion: null,
    titulo: '',
    descripcion: '',
    boton: '',
});

const CONTENIDO_ACCIONES = {
    cancelar: {
        titulo: 'Cancelar examen',
        descripcion: 'El examen programado no se llevará a cabo y quedará registrado en la auditoría. Es una decisión definitiva.',
        boton: 'Cancelar',
    },
    anular: {
        titulo: 'Anular examen',
        descripcion: 'El examen está en curso y se invalidará lo ocurrido. Es una acción DEFINITIVA que quedará registrada en la auditoría.',
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

const claseBotonConfirmacion = computed(() => {
    const clases = {
        cancelar: 'btn-cancelar',
        anular: 'btn-anular',
        eliminar: 'btn-delete',
        suspender: 'btn-suspender',
        reanudar: 'btn-reanudar',
    };
    return clases[confirmacion.value.accion] ?? '';
});

// Menú contextual
function construirItemsMenu(examen, { conVer = true } = {}) {
    const esTerminal = examen.estado_actual === 'cancelado' || examen.estado_actual === 'anulado';
    const ventanaFinalizada = examen.estado_horario === 'finalizado';
    const puedeGestionar = Boolean(examen.puede_gestionar);
    const items = [];

    if (puedeGestionar && !esTerminal && !ventanaFinalizada) {
        items.push({ clave: 'editar', etiqueta: 'Editar', icono: 'editar', accion: () => router.visit(`/examenes/${examen.id_examen}/editar`) });
    }

    if (puedeGestionar && examen.estado_actual === 'programado') {
        items.push({ clave: 'cancelar', etiqueta: 'Cancelar', icono: 'cancelar', accion: () => abrirConfirmacion(examen, 'cancelar') });
    }

    if (puedeGestionar && examen.estado_actual === 'en_curso') {
        items.push({ clave: 'suspender', etiqueta: 'Suspender', icono: 'suspender', accion: () => abrirConfirmacion(examen, 'suspender') });
        items.push({ clave: 'anular', etiqueta: 'Anular', icono: 'anular', accion: () => abrirConfirmacion(examen, 'anular') });
    }

    if (puedeGestionar && examen.estado === 'suspendido') {
        items.push({ clave: 'reanudar', etiqueta: 'Reanudar', icono: 'reanudar', accion: () => abrirConfirmacion(examen, 'reanudar') });
        if (examen.estado_horario === 'en_curso') {
            items.push({ clave: 'anular', etiqueta: 'Anular', icono: 'anular', accion: () => abrirConfirmacion(examen, 'anular') });
        }
    }

    if (props.esAdmin) {
        items.push({ clave: 'eliminar', etiqueta: 'Eliminar', icono: 'eliminar', accion: () => abrirConfirmacion(examen, 'eliminar') });
    }

    if (conVer || items.length === 0) {
        items.unshift({ clave: 'ver', etiqueta: 'Ver', icono: 'ver', accion: () => verExamen(examen) });
    }

    return items;
}

const menu = ref({
    abierto: false,
    examen: null,
    x: 0,
    y: 0,
    items: [],
});

const filaSeleccionadaId = ref(null);

function alClicFuera(evento) {
    if (!evento.target.closest('.row-menu') && !evento.target.closest('.row-menu-btn')) {
        cerrarMenu();
    }
}

function alEscapar(evento) {
    if (evento.key === 'Escape') {
        cerrarMenu();
    }
}

function abrirMenuEn(x, y, examen, items) {
    cerrarMenu();
    const anchoMenu = 220;
    const altoMenu = items.length * 40 + 28;
    menu.value = {
        abierto: true,
        examen,
        x: Math.min(x, window.innerWidth - anchoMenu - 8),
        y: Math.min(y + 4, Math.max(8, window.innerHeight - altoMenu - 8)),
        items,
    };
    document.addEventListener('click', alClicFuera);
    document.addEventListener('keydown', alEscapar);
}

function abrirMenuExamen(event, examen) {
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    abrirMenuEn(rect.right, rect.bottom, examen, construirItemsMenu(examen));
}

function abrirMenuContextual(event, examen) {
    event.preventDefault();
    abrirMenuEn(event.clientX, event.clientY, examen, construirItemsMenu(examen, { conVer: false }));
}

function cerrarMenu() {
    if (menu.value.abierto) {
        document.removeEventListener('click', alClicFuera);
        document.removeEventListener('keydown', alEscapar);
    }
    menu.value.abierto = false;
}

function ejecutarAccion(item) {
    cerrarMenu();
    item.accion();
}

function clicFila(examen) {
    if (window.matchMedia('(pointer: fine)').matches) {
        filaSeleccionadaId.value = examen.id_examen;
    } else {
        verExamen(examen);
    }
}

function verExamen(examen) {
    router.visit(`/examenes/${examen.id_examen}/habilitaciones`);
}

onBeforeUnmount(() => {
    document.removeEventListener('click', alClicFuera);
    document.removeEventListener('keydown', alEscapar);
});
</script>

<template>
    <Head title="Gestión de Exámenes" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">GESTIÓN DE EXÁMENES</h1>

            <!-- Pestañas de Estado -->
            <nav class="state-tabs" aria-label="Filtrar exámenes por estado">
                <button
                    v-for="chip in CHIPS"
                    :key="chip.key || 'todos'"
                    type="button"
                    class="state-tab"
                    :class="{ 'state-tab--active': chipActivo(chip.key) }"
                    :disabled="cargando"
                    @click="seleccionarChip(chip.key)"
                >
                    <span>{{ chip.etiqueta }}</span>
                    <span 
                        v-if="chip.key" 
                        class="state-tab-count"
                        :class="{ 'state-tab-count--active': chipActivo(chip.key) }"
                    >
                        {{ conteoChip(chip.key) }}
                    </span>
                </button>
            </nav>

            <!-- Barra de Búsqueda y Botón Principal -->
            <div class="action-bar-top">
                <form class="search-form-group" @submit.prevent="buscar">
                    <div class="search-input-wrapper">
                        <div class="search-input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            v-model="busqueda"
                            placeholder="Buscar por asignatura..." 
                            class="search-input-field"
                            :disabled="cargando"
                        />
                    </div>
                    <div class="search-buttons-group">
                        <button type="submit" class="btn-search" :disabled="cargando">
                            Buscar
                        </button>
                        <button type="button" class="btn-clean" :disabled="cargando" @click="limpiar">
                            Limpiar
                        </button>
                    </div>
                </form>

                <button 
                    type="button" 
                    class="btn-register-exam" 
                    @click="router.visit('/examenes/crear')"
                >
                    <svg class="btn-plus-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Registrar Examen</span>
                </button>
            </div>

            <!-- Filtros Secundarios -->
            <div class="secondary-filters-box">
                <div class="filter-col">
                    <label class="filter-col-label">Periodo</label>
                    <div class="select-field-wrapper">
                        <select v-model="idPeriodo" class="filter-select-field" :disabled="cargando" @change="buscar">
                            <option value="">Todos</option>
                            <option v-for="p in periodos" :key="p.id_periodo" :value="String(p.id_periodo)">
                                {{ p.codigo }}
                            </option>
                        </select>
                        <div class="select-chevron-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="filter-col">
                    <label class="filter-col-label">Tipo</label>
                    <div class="select-field-wrapper">
                        <select v-model="idTipo" class="filter-select-field" :disabled="cargando" @change="buscar">
                            <option value="">Todos</option>
                            <option v-for="t in tipos" :key="t.id_tipo_examen" :value="String(t.id_tipo_examen)">
                                {{ t.nombre }}
                            </option>
                        </select>
                        <div class="select-chevron-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="filter-col">
                    <label class="filter-col-label">Fecha</label>
                    <input v-model="fecha" type="date" class="filter-input-field" :disabled="cargando" @change="buscar" />
                </div>

                <div class="filter-col">
                    <label class="filter-col-label">Hora</label>
                    <input v-model="horaInicio" type="time" class="filter-input-field" :disabled="cargando" @change="buscar" />
                </div>
            </div>

            <!-- Tabla de Exámenes -->
            <div class="table-card">
                <div class="table-scroll-container">
                    <table class="exams-table">
                        <thead>
                            <tr>
                                <th>Asignatura</th>
                                <th>Tipo</th>
                                <th>Periodo</th>
                                <th v-if="esAdmin">Docente</th>
                                <th>Grupos</th>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Ambientes</th>
                                <th class="th-actions">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="examen in examenes.data"
                                :key="examen.id_examen"
                                :class="['row-examen', { 'row-examen--seleccionada': filaSeleccionadaId === examen.id_examen }]"
                                @click="clicFila(examen)"
                                @dblclick="verExamen(examen)"
                                @contextmenu.prevent="abrirMenuContextual($event, examen)"
                            >
                                <!-- Asignatura -->
                                <td>
                                    <span class="col-asignatura-container">
                                        <span class="col-asignatura-text">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</span>
                                        <svg
                                            v-if="examen.es_compartido"
                                            class="icon-compartido"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            role="img"
                                            :aria-label="tituloCompartido(examen)"
                                            :title="tituloCompartido(examen)"
                                        >
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </span>
                                </td>

                                <!-- Tipo -->
                                <td>
                                    <span class="col-tipo-text">{{ examen.tipo?.nombre || 'Sin tipo' }}</span>
                                </td>

                                <!-- Periodo -->
                                <td>
                                    <span 
                                        class="col-periodo-code" 
                                        :title="examen.periodo?.nombre || ('Gestión ' + (examen.periodo_codigo || ''))"
                                    >
                                        {{ examen.periodo_codigo || '—' }}
                                    </span>
                                </td>

                                <!-- Docente (si es admin) -->
                                <td v-if="esAdmin">
                                    <span class="col-docente-text">{{ examen.docentes?.join(', ') || 'N/D' }}</span>
                                </td>

                                <!-- Grupos -->
                                <td>
                                    <div v-if="examen.grupos && examen.grupos.length > 0" class="group-badges-list">
                                        <span
                                            v-for="grupo in examen.grupos"
                                            :key="grupo.nombre"
                                            class="group-badge-box"
                                            :style="examen.es_compartido ? estiloBadgeGrupo(examen, grupo) : {}"
                                            :title="tituloGrupo(grupo)"
                                        >
                                            {{ grupo.nombre }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted">—</span>
                                </td>

                                <!-- Fecha -->
                                <td>
                                    <span class="col-fecha-text">{{ examen.fecha }}</span>
                                </td>

                                <!-- Horario -->
                                <td>
                                    <span v-if="examen.hora_inicio" class="col-horario-text">
                                        <span class="time-bold">{{ String(examen.hora_inicio).slice(0, 5) }}</span>
                                        <span v-if="examen.hora_fin"> a <span class="time-bold">{{ String(examen.hora_fin).slice(0, 5) }}</span></span>
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>

                                <!-- Ambientes -->
                                <td>
                                    <span 
                                        v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0"
                                        class="col-ambientes-text"
                                        :title="examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ')"
                                    >
                                        {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                    </span>
                                    <span v-else class="text-muted">Sin ambiente</span>
                                </td>

                                <!-- Estado y Menú de Acciones -->
                                <td class="th-actions">
                                    <div class="estado-actions-wrapper">
                                        <span class="status-badge" :class="estadoInfo(examen.estado_actual).clase">
                                            {{ estadoInfo(examen.estado_actual).etiqueta }}
                                        </span>
                                        <button
                                            type="button"
                                            class="row-menu-btn"
                                            aria-haspopup="menu"
                                            :aria-label="`Acciones del examen de ${examen.asignatura?.nombre_asignatura || 'la asignatura'}`"
                                            @click.stop="abrirMenuExamen($event, examen)"
                                        >
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                                <circle cx="5" cy="12" r="2"></circle>
                                                <circle cx="12" cy="12" r="2"></circle>
                                                <circle cx="19" cy="12" r="2"></circle>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!examenes.data || examenes.data.length === 0">
                                <td :colspan="esAdmin ? 9 : 8" class="empty-table-state">
                                    {{ hayFiltrosActivos ? 'No hay exámenes que coincidan con los filtros aplicados.' : 'No hay exámenes registrados.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación de Tabla -->
                <div class="pagination-container">
                    <span class="pagination-text">
                        {{ (examenes.to || 0) }} de {{ (examenes.total || 0) }} exámenes
                    </span>
                    <div class="pagination-controls-group">
                        <button 
                            type="button"
                            class="btn-page-nav" 
                            :disabled="cargando || !examenes.prev_page_url" 
                            @click="visitarPagina(examenes.prev_page_url)"
                        >
                            ← Anterior
                        </button>
                        <span class="page-indicator-text">
                            Página {{ examenes.current_page || 1 }} de {{ examenes.last_page || 1 }}
                        </span>
                        <button 
                            type="button"
                            class="btn-page-nav" 
                            :disabled="cargando || !examenes.next_page_url" 
                            @click="visitarPagina(examenes.next_page_url)"
                        >
                            Siguiente →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para acciones manuales -->
        <Modal :open="confirmacion.abierta" :title="confirmacion.titulo" @close="cerrarConfirmacion">
            <p class="modal-desc">{{ confirmacion.descripcion }}</p>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="cerrarConfirmacion">Cancelar</Button>
                <Button variant="primary" class="btn-modal" :class="claseBotonConfirmacion" @click="confirmarAccion">{{ confirmacion.boton }}</Button>
            </template>
        </Modal>

        <!-- Menú contextual de la fila (⋮) con Teleport -->
        <Teleport to="body">
            <div
                v-if="menu.abierto"
                class="row-menu"
                role="menu"
                :style="{ left: menu.x + 'px', top: menu.y + 'px' }"
            >
                <button
                    v-for="item in menu.items"
                    :key="item.clave"
                    type="button"
                    role="menuitem"
                    class="row-menu-item"
                    @click="ejecutarAccion(item)"
                >
                    <svg
                        class="row-menu-icon"
                        viewBox="0 0 24 24"
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <template v-if="item.icono === 'ver'">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </template>
                        <template v-else-if="item.icono === 'editar'">
                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                        </template>
                        <template v-else-if="item.icono === 'cancelar'">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </template>
                        <template v-else-if="item.icono === 'anular'">
                            <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </template>
                        <template v-else-if="item.icono === 'suspender'">
                            <rect x="6" y="4" width="4" height="16"></rect>
                            <rect x="14" y="4" width="4" height="16"></rect>
                        </template>
                        <template v-else-if="item.icono === 'reanudar'">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </template>
                        <template v-else-if="item.icono === 'eliminar'">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </template>
                    </svg>
                    {{ item.etiqueta }}
                </button>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor Principal y Título */
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
    padding: 1rem; /* p-4 móvil */
    flex: 1 1 0%;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

@media (min-width: 640px) {
    .panel-container {
        padding: 1.5rem; /* sm:p-6 */
    }
}

@media (min-width: 1024px) {
    .panel-container {
        padding: 2rem; /* lg:p-8 */
    }
}

.panel-title {
    font-size: 1.5rem; /* text-2xl */
    line-height: 2rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 2rem; /* mb-8 */
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 0.05em;
}

/* Pestañas de Estado */
.state-tabs {
    display: flex;
    overflow-x: auto;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    padding-bottom: 1px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.state-tabs::-webkit-scrollbar {
    display: none;
}

.state-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
    font-family: var(--font-family);
}

.state-tab:hover:not(:disabled) {
    color: #374151;
    border-bottom-color: #d1d5db;
}

.state-tab--active {
    border-bottom: 2px solid var(--color-primary);
    color: var(--color-primary);
    font-weight: 600;
}

.state-tab:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.state-tab-count {
    background-color: #f3f4f6;
    color: #4b5563;
    padding: 0.125rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1rem;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.state-tab-count--active {
    background-color: var(--color-primary);
    color: #ffffff;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

/* Barra de Búsqueda y Botón Principal */
.action-bar-top {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (min-width: 1024px) {
    .action-bar-top {
        flex-direction: row;
        align-items: center;
    }
}

.search-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
    flex: 1;
}

@media (min-width: 640px) {
    .search-form-group {
        flex-direction: row;
        align-items: center;
    }
}

.search-input-wrapper {
    position: relative;
    width: 100%;
}

@media (min-width: 640px) {
    .search-input-wrapper {
        max-width: 28rem; /* sm:max-w-md */
    }
}

.search-input-icon {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    padding-left: 0.75rem;
    display: flex;
    align-items: center;
    pointer-events: none;
    color: #9ca3af;
}

.search-input-icon svg {
    width: 1.25rem;
    height: 1.25rem;
}

.search-input-field {
    display: block;
    width: 100%;
    padding-left: 2.5rem;
    padding-right: 0.75rem;
    padding-top: 0.625rem;
    padding-bottom: 0.625rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #1f2937;
    font-family: var(--font-family);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    outline: none;
}

.search-input-field::placeholder {
    color: #9ca3af;
}

.search-input-field:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15);
}

.search-buttons-group {
    display: flex;
    gap: 0.5rem;
}

.btn-search {
    background-color: var(--color-primary);
    color: #ffffff;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.025em;
    border: none;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s ease;
    font-family: var(--font-family);
    white-space: nowrap;
}

.btn-search:hover:not(:disabled) {
    background-color: var(--color-primary-hover, #3A5A7E);
}

.btn-clean {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.025em;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    font-family: var(--font-family);
    white-space: nowrap;
}

.btn-clean:hover:not(:disabled) {
    background-color: #f9fafb;
    color: #1f2937;
}

.btn-register-exam {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background-color: var(--color-primary);
    color: #ffffff;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.025em;
    border: none;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s ease;
    width: 100%;
    white-space: nowrap;
    font-family: var(--font-family);
}

@media (min-width: 1024px) {
    .btn-register-exam {
        width: auto;
    }
}

.btn-register-exam:hover {
    background-color: var(--color-primary-hover, #3A5A7E);
}

.btn-plus-icon {
    width: 1rem;
    height: 1rem;
    stroke-width: 2;
}

/* Filtros Secundarios */
.secondary-filters-box {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    background-color: #ffffff;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.filter-col {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    flex: 1 1 150px;
    min-width: 150px;
}

.filter-col-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: var(--font-family);
}

.select-field-wrapper {
    position: relative;
    width: 100%;
}

.filter-select-field {
    display: block;
    width: 100%;
    padding-left: 0.75rem;
    padding-right: 2.25rem;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #374151;
    background-color: #ffffff;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    outline: none;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.filter-select-field:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15);
}

.select-chevron-icon {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    color: #6b7280;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.select-chevron-icon svg {
    width: 100%;
    height: 100%;
}

.filter-input-field {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #374151;
    background-color: #ffffff;
    outline: none;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.filter-input-field:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15);
}

/* Tabla de Exámenes */
.table-card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.table-scroll-container {
    overflow-x: auto;
    width: 100%;
    scrollbar-width: thin;
}

.exams-table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.exams-table thead tr {
    background-color: #f9fafb;
    border-bottom: 1px solid #f3f4f6;
    color: #6b7280;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.exams-table th {
    padding: 1rem 1.25rem;
    white-space: nowrap;
}

.exams-table th.th-actions {
    text-align: right;
}

.exams-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.15s ease;
    cursor: pointer;
}

.exams-table tbody tr:hover {
    background-color: rgba(29, 54, 83, 0.04);
}

.exams-table tbody tr.row-examen--seleccionada {
    background-color: #eef2ff;
}

.exams-table tbody tr:last-child {
    border-bottom: none;
}

.exams-table td {
    padding: 1rem 1.25rem;
    vertical-align: middle;
}

.col-asignatura-container {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.col-asignatura-text {
    font-weight: 700;
    color: var(--color-primary);
}

.icon-compartido {
    flex: 0 0 auto;
    width: 15px;
    height: 15px;
    color: #0f766e;
}

.col-tipo-text {
    color: #6b7280;
    white-space: nowrap;
}

.col-periodo-code {
    border-bottom: 1px dashed #9ca3af;
    cursor: help;
    white-space: nowrap;
}

.col-docente-text {
    color: #374151;
    white-space: nowrap;
}

.group-badges-list {
    display: flex;
    gap: 0.375rem;
    flex-wrap: nowrap;
}

.group-badge-box {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid #d1d5db;
    font-size: 0.75rem;
    font-weight: 600;
    color: #4b5563;
    background-color: #f9fafb;
    white-space: nowrap;
}

.col-fecha-text {
    font-weight: 500;
    color: #1f2937;
    white-space: nowrap;
}

.col-horario-text {
    color: #4b5563;
    white-space: nowrap;
}

.time-bold {
    font-weight: 500;
    color: #111827;
}

.col-ambientes-text {
    color: #4b5563;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.text-muted {
    color: #9ca3af;
}

/* Estado y Acciones */
.estado-actions-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge-finalizado { background-color: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
.badge-programado { background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
.badge-en-curso   { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-cancelado  { background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
.badge-anulado    { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-suspendido { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.badge-pendiente  { background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

.row-menu-btn {
    color: #9ca3af;
    background: transparent;
    border: none;
    border-radius: 0.25rem;
    padding: 0.375rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.15s ease;
}

.row-examen:hover .row-menu-btn,
.row-menu-btn:focus-visible {
    opacity: 1;
}

.row-menu-btn:hover {
    color: var(--color-primary);
    background-color: rgba(29, 54, 83, 0.1);
}

@media (hover: none) {
    .row-menu-btn {
        opacity: 1;
    }
}

.empty-table-state {
    text-align: center;
    padding: 3rem 1.5rem !important;
    color: #6b7280;
    font-size: 0.875rem;
}

/* Paginación */
.pagination-container {
    padding: 1rem;
    border-top: 1px solid #f3f4f6;
    background-color: rgba(249, 250, 251, 0.6);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

@media (min-width: 640px) {
    .pagination-container {
        flex-direction: row;
    }
}

.pagination-text {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.pagination-controls-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-indicator-text {
    font-size: 0.875rem;
    color: #4b5563;
    font-weight: 600;
}

.btn-page-nav {
    padding: 0.375rem 0.75rem;
    border: 1px solid #e5e7eb;
    background-color: #ffffff;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.btn-page-nav:hover:not(:disabled) {
    background-color: #f9fafb;
    border-color: #d1d5db;
    color: var(--color-primary);
}

.btn-page-nav:disabled {
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.6;
    background-color: #ffffff;
    border-color: #e5e7eb;
}

/* Menú Contextual Flotante */
.row-menu {
    position: fixed;
    z-index: 50;
    min-width: 210px;
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-shadow: 0 10px 30px rgba(17, 24, 39, 0.14);
    padding: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.row-menu-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.7rem;
    border: none;
    border-radius: 0.375rem;
    background-color: transparent;
    color: var(--color-primary);
    font-size: 0.85rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.1s ease, color 0.1s ease;
    font-family: var(--font-family);
}

.row-menu-item:hover,
.row-menu-item:focus-visible {
    background-color: #f3f4f6;
    color: var(--color-primary-hover, #152a42);
    outline: none;
}

.row-menu-icon {
    flex: 0 0 auto;
    color: var(--color-primary);
    transition: color 0.1s ease;
}

.row-menu-item:hover .row-menu-icon,
.row-menu-item:focus-visible .row-menu-icon {
    color: var(--color-primary-hover, #152a42);
}

.modal-desc {
    margin: 0;
    color: #4b5563;
    line-height: 1.5;
}

.btn-modal {
    padding: 0.625rem 1.25rem !important;
    font-size: 0.875rem !important;
}

.btn-cancelar {
    background-color: #6b7280 !important;
    color: #ffffff !important;
    border: none !important;
}

.btn-cancelar:hover {
    background-color: #4b5563 !important;
}

.btn-anular {
    background-color: var(--color-danger) !important;
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
</style>