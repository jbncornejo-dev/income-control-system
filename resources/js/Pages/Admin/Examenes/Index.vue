<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useToastStore } from '@/stores/useToastStore';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';

const toast = useToastStore();

const page = usePage();
// Nombre del usuario autenticado: el docente identifica así "sus" grupos para
// pintarlos con el color por defecto en los badges de grupos compartidos.
const nombreUsuarioActual = page.props.auth?.user?.name ?? '';

const props = defineProps({
    examenes: Object, // Objeto paginado de Laravel
    filters: Object,
    periodos: Array,
    tipos: Array,
    conteos: Object,
    esAdmin: Boolean
});

// Filtros reales que soporta el backend: asignatura (coincidencia parcial,
// tolerante a acentos y mayúsculas), periodo, tipo de examen, fecha exacta,
// hora, estado y compartidos. La búsqueda se dispara con el botón Buscar;
// así no se traba la página mientras se escribe.
const busqueda = ref(props.filters?.asignatura ?? '');
const idPeriodo = ref(props.filters?.id_periodo ?? '');
const idTipo = ref(props.filters?.id_tipo_examen ?? '');
const fecha = ref(props.filters?.fecha ?? '');
const horaInicio = ref(props.filters?.hora_inicio ?? '');
const estado = ref(props.filters?.estado ?? '');
const compartido = ref(props.filters?.compartido === '1');
const cargando = ref(false);

// Si llegan filtros desde la URL, el estado vacío lo indica con otro mensaje.
const hayFiltrosActivos = computed(() =>
    !!(props.filters?.asignatura || props.filters?.id_periodo || props.filters?.id_tipo_examen || props.filters?.fecha || props.filters?.hora_inicio || props.filters?.estado || props.filters?.compartido)
);

// Pestañas de estado: atajos de filtro sobre la misma lista (no vistas
// separadas). "Todos" no envía estado; los demás usan el parámetro `estado`
// del backend. Cada pestaña lleva el color de su estado (ver class en el template).
const CHIPS = [
    { key: '', etiqueta: 'Todos' },
    { key: 'programado', etiqueta: 'Programados' },
    { key: 'en_curso', etiqueta: 'En curso' },
    { key: 'finalizado', etiqueta: 'Finalizados' },
    { key: 'cancelado', etiqueta: 'Cancelados' },
    { key: 'anulado', etiqueta: 'Anulados' },
    { key: 'suspendido', etiqueta: 'Suspendidos' },
    // Chip de dimensión propia: exámenes cuyos grupos cubren a varios docentes.
    { key: 'compartidos', etiqueta: 'Compartidos' },
];

function conteoChip(key) {
    if (!key) return null;
    return props.conteos?.[key] ?? 0;
}

// Un solo chip activo a la vez: los de estado y el de compartidos son
// excluyentes entre sí (seleccionar uno desactiva el otro).
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

// Mantiene los inputs en sincronía con la URL al navegar o volver con el historial.
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
    // replace=true evita acumular en el historial una entrada por cada búsqueda
    // y que al volver a la sección se restaure una URL con filtros obsoletos.
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

// Paginación: la URL generada por Laravel ya conserva los filtros aplicados.
function visitarPagina(url) {
    visitar(url);
}

// Información visual de cada estado del examen (gestión).
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

// ---------------------------------------------------------------------------
// Distinción visual de exámenes compartidos (sin columna extra):
// - Los badges de grupo se pintan con el color estable del docente dueño
//   (el backend ordena `docentes` con el nombre del usuario autenticado al
//   inicio, así "sus" grupos usan el azul por defecto y los ajenos brillan
//   con otros tonos). El primer color es el azul del badge-grupo actual.
// - Un examen compartido lleva además un ícono de dos personas junto a la
//   asignatura, con el detalle de cuántos docentes lo gestionan.
// ---------------------------------------------------------------------------
const PALETA_GRUPOS = [
    { fondo: '#e0f2fe', texto: '#0369a1', borde: '#7dd3fc' }, // azul (por defecto)
    { fondo: '#fef3c7', texto: '#92400e', borde: '#fcd34d' }, // ámbar
    { fondo: '#ede9fe', texto: '#5b21b6', borde: '#c4b5fd' }, // violeta
    { fondo: '#d1fae5', texto: '#065f46', borde: '#6ee7b7' }, // verde
    { fondo: '#ffe4e6', texto: '#9f1239', borde: '#fda4af' }, // rosa
    { fondo: '#cffafe', texto: '#155e75', borde: '#67e8f9' }, // cian
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

// Clase del botón de confirmación según la acción.
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

// ---------------------------------------------------------------------------
// Menú contextual por examen (⋮): concentra las acciones válidas según el
// estado, evitando la columna ACCIONES (que se veía mal en móvil). La fila
// lleva a "Ver" (habilitaciones) con doble clic en escritorio o un toque en
// móvil; el menú cubre el resto.
// ---------------------------------------------------------------------------

// Acciones disponibles para un examen según su estado de gestión.
// Las reglas coinciden con las del backend (cambiarEstado/edit):
// - Cancelar: solo programado. Anular: solo en curso (o suspendido con ventana activa).
// - Editar: bloqueado si es terminal (cancelado/anulado) o la ventana ya terminó.
// - "Ver" siempre en el menú ⋮. En el del clic derecho (conVer=false) solo se
//   agrega cuando no hay ninguna otra acción (p. ej. un examen finalizado o
//   terminal sin permisos de admin): así el menú nunca queda vacío y feo.
function construirItemsMenu(examen, { conVer = true } = {}) {
    const esTerminal = examen.estado_actual === 'cancelado' || examen.estado_actual === 'anulado';
    const ventanaFinalizada = examen.estado_horario === 'finalizado';
    // Editar estructura y cambiar estado solo si el backend lo permite: el admin
    // siempre; el docente solo en exámenes cuyos grupos le pertenecen por completo.
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

    // Suspendido: siempre se puede reanudar; anular solo con la ventana activa.
    if (puedeGestionar && examen.estado === 'suspendido') {
        items.push({ clave: 'reanudar', etiqueta: 'Reanudar', icono: 'reanudar', accion: () => abrirConfirmacion(examen, 'reanudar') });
        if (examen.estado_horario === 'en_curso') {
            items.push({ clave: 'anular', etiqueta: 'Anular', icono: 'anular', accion: () => abrirConfirmacion(examen, 'anular') });
        }
    }

    if (props.esAdmin) {
        items.push({ clave: 'eliminar', etiqueta: 'Eliminar', icono: 'eliminar', accion: () => abrirConfirmacion(examen, 'eliminar') });
    }

    // El menú nunca debe quedar vacío: si no hay ninguna acción aplicable,
    // "Ver" actúa de respaldo (unshift para que siga siendo la primera opción).
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

// Fila seleccionada con un clic (escritorio): queda resaltada como feedback
// visual. El doble clic es el que navega a "Ver".
const filaSeleccionadaId = ref(null);

// Cierre por clic fuera del menú o con Escape. Referencias de función estables
// para registrar/desregistrar los listeners de document sin fugas ni bucles.
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

// Abre el menú anclado a un punto de la pantalla (botón ⋮ o clic derecho
// sobre la fila). Se usa position: fixed, así que el menú se clava contra la
// ventana para no salirse de la pantalla ni hacia la derecha ni hacia abajo.
function abrirMenuEn(x, y, examen, items) {
    cerrarMenu(); // descarta un menú abierto en otra fila y limpia listeners
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

// Botón ⋮ de la fila: ancla el menú bajo el botón. En móvil es la única vía
// a "Ver" (no hay doble clic), así que el menú sí incluye la acción.
function abrirMenuExamen(event, examen) {
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    abrirMenuEn(rect.right, rect.bottom, examen, construirItemsMenu(examen));
}

// Clic derecho sobre la fila: menú contextual en la posición del puntero.
// Así las acciones se abren desde cualquier parte de la fila, sin tener que
// llegar al extremo (pensado para escritorio; en móvil se usan las tarjetas).
// No incluye "Ver": al existir el doble clic sería redundante.
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

// Clic izquierdo sobre la fila: en escritorio un clic selecciona/resalta la
// fila (el doble clic navega a "Ver"); en pantallas táctiles el toque navega
// directo, porque no hay doble clic fiable.
function clicFila(examen) {
    if (window.matchMedia('(pointer: fine)').matches) {
        filaSeleccionadaId.value = examen.id_examen;
    } else {
        verExamen(examen);
    }
}

// Ver: doble clic (escritorio) o toque (móvil) sobre la fila navega a las
// habilitaciones del examen.
function verExamen(examen) {
    router.visit(`/examenes/${examen.id_examen}/habilitaciones`);
}

// Si la página se desmonta con el menú abierto, no dejar listeners colgando.
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

            <!-- Pestañas por estado: filtros con conteos sobre la misma lista.
                 Cada pestaña lleva el color de su estado (coherente con los badges). -->
            <nav class="state-tabs" aria-label="Filtrar exámenes por estado">
                <button
                    v-for="chip in CHIPS"
                    :key="chip.key || 'todos'"
                    type="button"
                    class="state-tab"
                    :class="[`state-tab--${chip.key || 'todos'}`, { 'state-tab--active': chipActivo(chip.key) }]"
                    :disabled="cargando"
                    @click="seleccionarChip(chip.key)"
                >
                    {{ chip.etiqueta }}
                    <span v-if="chip.key" class="state-tab-count">{{ conteoChip(chip.key) }}</span>
                </button>
            </nav>

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
                        <span class="filter-label">Tipo</span>
                        <select v-model="idTipo" class="filter-input" :disabled="cargando">
                            <option value="">Todos</option>
                            <option v-for="tipo in tipos" :key="tipo.id_tipo_examen" :value="String(tipo.id_tipo_examen)" :title="tipo.codigo">
                                {{ tipo.nombre }}
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
                            <th>TIPO</th>
                            <th>PERIODO</th>
                            <th v-if="esAdmin">DOCENTE</th>
                            <th>GRUPOS</th>
                            <th>FECHA</th>
                            <th>HORA</th>
                            <th>HORA FIN</th>
                            <th>AMBIENTES</th>
                            <th>ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- La fila navega a "Ver" (habilitaciones) con doble clic en escritorio o
                             un toque en móvil. Clic derecho en cualquier parte de la fila:
                             abre el menú contextual con las acciones según el estado. -->
                        <tr
                            v-for="examen in examenes.data"
                            :key="examen.id_examen"
                            :class="['row-examen', { 'row-examen--seleccionada': filaSeleccionadaId === examen.id_examen }]"
                            @click="clicFila(examen)"
                            @dblclick="verExamen(examen)"
                            @contextmenu.prevent="abrirMenuContextual($event, examen)"
                        >
                            <!-- Ajusta las propiedades (ej: asignatura.nombre) según tu BD -->
                            <td class="col-asignatura" data-label="Asignatura">
                                <span class="asignatura-celda">
                                    <span>{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</span>
                                    <!-- Ícono sutil de examen compartido entre docentes (sin columna extra). -->
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
                            <td data-label="Tipo">
                                <span v-if="examen.tipo">{{ examen.tipo.nombre }}</span>
                                <span v-else class="text-muted">Sin tipo</span>
                            </td>
                            <td data-label="Periodo" :title="examen.periodo?.nombre || ''">{{ examen.periodo_codigo || '—' }}</td>
                            <td v-if="esAdmin" data-label="Docente">{{ examen.docentes?.join(', ') || 'N/D' }}</td>
                            <!-- Grupos de la asignatura -->
                            <td data-label="Grupos">
                                <span v-if="examen.grupos && examen.grupos.length > 0" class="group-badges">
                                    <span
                                        v-for="grupo in examen.grupos"
                                        :key="grupo.nombre"
                                        class="badge badge-grupo"
                                        :style="estiloBadgeGrupo(examen, grupo)"
                                        :title="tituloGrupo(grupo)"
                                    >{{ grupo.nombre }}</span>
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            
                            <!-- Uso de una fuente monoespaciada para fechas y horas si lo deseas -->
                            <td class="col-fecha" data-label="Fecha">{{ examen.fecha }}</td>
                            <td class="col-hora" data-label="Hora">{{ (examen.hora_inicio || '').slice(0, 5) }}</td>
                            <td class="col-hora" data-label="Hora fin">{{ examen.hora_fin || '—' }}</td>
                            
                            <!-- Procesamiento de ambientes (array a string separado por comas) -->
                            <td data-label="Ambientes">
                                <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                    {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                </span>
                                <span v-else class="text-muted">Sin ambiente</span>
                            </td>
                            
                            <td class="td-estado" data-label="Estado">
                                <div class="estado-cell">
                                    <span :class="['badge', estadoInfo(examen.estado_actual).clase]">
                                        {{ estadoInfo(examen.estado_actual).etiqueta }}
                                    </span>
                                </div>
                                <!-- Menú de acciones por estado (ver/editar/cancelar/anular/suspender/reanudar/eliminar) -->
                                <button
                                    type="button"
                                    class="row-menu-btn"
                                    aria-haspopup="menu"
                                    :aria-label="`Acciones del examen de ${examen.asignatura?.nombre_asignatura || 'la asignatura'}`"
                                    @click.stop="abrirMenuExamen($event, examen)"
                                >
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                                        <circle cx="5" cy="12" r="1.7"></circle>
                                        <circle cx="12" cy="12" r="1.7"></circle>
                                        <circle cx="19" cy="12" r="1.7"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        
                        <tr v-if="!examenes.data || examenes.data.length === 0" class="empty-row">
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

        <!-- Menú contextual de la fila (⋮). Se renderiza fuera del contenedor de
             la tabla (que tiene overflow-x: auto) para que no quede recortado. -->
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
                    <!-- Icono de la acción (en lugar del punto de color) -->
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
                            <!-- ojo -->
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </template>
                        <template v-else-if="item.icono === 'editar'">
                            <!-- lápiz -->
                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                        </template>
                        <template v-else-if="item.icono === 'cancelar'">
                            <!-- círculo tachado -->
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </template>
                        <template v-else-if="item.icono === 'anular'">
                            <!-- octágono de alerta -->
                            <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </template>
                        <template v-else-if="item.icono === 'suspender'">
                            <!-- pausa -->
                            <rect x="6" y="4" width="4" height="16"></rect>
                            <rect x="14" y="4" width="4" height="16"></rect>
                        </template>
                        <template v-else-if="item.icono === 'reanudar'">
                            <!-- play -->
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </template>
                        <template v-else-if="item.icono === 'eliminar'">
                            <!-- papelera -->
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

/* Pestañas por estado: filtro con conteos, colores coherentes con los badges.
   El color de cada pestaña se define con variables CSS por variante. */
.state-tabs {
    display: flex;
    align-items: stretch;
    gap: 0.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    overflow-x: auto;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.state-tab {
    --tab-color: #4b5563;
    --tab-tint: transparent;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 1rem;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0.5rem 0.5rem 0 0;
    background-color: transparent;
    color: #4b5563;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
    cursor: pointer;
    transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
}

.state-tab:hover:not(:disabled) {
    color: var(--tab-color);
    background-color: #f3f4f6;
}

.state-tab--active {
    color: var(--tab-color);
    background-color: var(--tab-tint);
    border-bottom-color: var(--tab-color);
}

.state-tab--active:hover:not(:disabled) {
    background-color: var(--tab-tint);
}

.state-tab:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Colores por estado (armonizados con los badges del listado) */
.state-tab--todos { --tab-color: var(--color-primary); --tab-tint: #eff6ff; }
.state-tab--programado { --tab-color: #0369a1; --tab-tint: #e0f2fe; }
.state-tab--en_curso { --tab-color: #15803d; --tab-tint: #dcfce7; }
.state-tab--finalizado { --tab-color: #6d28d9; --tab-tint: #ede9fe; }
.state-tab--cancelado { --tab-color: #4b5563; --tab-tint: #f3f4f6; }
.state-tab--anulado { --tab-color: #b91c1c; --tab-tint: #fee2e2; }
.state-tab--suspendido { --tab-color: #b45309; --tab-tint: #fffbeb; }
.state-tab--compartidos { --tab-color: #0f766e; --tab-tint: #f0fdfa; }

/* Contador de la pestaña: gris en reposo, del color del estado al estar activa */
.state-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.4rem;
    border-radius: 9999px;
    background-color: rgba(0, 0, 0, 0.08);
    color: #6b7280;
    font-size: 0.7rem;
    font-weight: 700;
}

.state-tab:hover:not(:disabled) .state-tab-count {
    background-color: var(--tab-tint);
    color: var(--tab-color);
}

.state-tab--active .state-tab-count {
    background-color: var(--tab-color);
    color: #ffffff;
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

/* Contenido de la celda de asignatura: nombre + ícono de compartido en línea */
.asignatura-celda {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.icon-compartido {
    flex: 0 0 auto;
    width: 14px;
    height: 14px;
    color: #0f766e;
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
    background-color: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
}

.badge-anulado {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

.badge-suspendido {
    background-color: #fffbeb;
    color: #92400e;
    border: 1px solid #fbbf24;
}

/* Contenedor del badge */
.estado-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Celda ESTADO: aloja el badge y el botón ⋮ del menú (flotante a la derecha,
   sin columna adicional). La fila completa es clicable para ir a "Ver". */
.td-estado {
    position: relative;
    padding-right: 2.5rem;
}

.row-examen {
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.row-examen:hover,
.row-examen:focus-within {
    background-color: #f8fafc;
}

/* Fila seleccionada con un clic izquierdo (escritorio): resaltado azul
   persistente hasta seleccionar otra fila. Va después de los :hover para
   ganar el empate de especificidad cuando la fila está seleccionada y el
   cursor pasa por encima. */
.row-examen--seleccionada,
.row-examen--seleccionada:hover,
.row-examen--seleccionada:focus-within {
    background-color: #eef2ff;
}

.row-examen--seleccionada td:first-child {
    box-shadow: inset 3px 0 0 var(--color-primary);
}

.row-menu-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 0.375rem;
    background-color: #ffffff;
    color: #6b7280;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.12s ease, background-color 0.12s ease, color 0.12s ease;
}

.row-examen:hover .row-menu-btn,
.row-examen:focus-within .row-menu-btn {
    opacity: 1;
}

.row-menu-btn:hover {
    background-color: #eef2ff;
    color: var(--color-primary);
}

.row-menu-btn:focus-visible {
    opacity: 1;
    outline: 2px solid var(--color-primary);
    outline-offset: 1px;
}

/* En pantallas táctiles no hay hover: el menú queda siempre visible. */
@media (hover: none) {
    .row-menu-btn {
        opacity: 1;
    }
}

/* Dropdown del menú por examen */
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
}

.row-menu-item:hover,
.row-menu-item:focus-visible {
    background-color: #f3f4f6;
    color: var(--color-primary-hover);
    outline: none;
}

.row-menu-icon {
    flex: 0 0 auto;
    color: var(--color-primary);
    transition: color 0.1s ease;
}

.row-menu-item:hover .row-menu-icon,
.row-menu-item:focus-visible .row-menu-icon {
    color: var(--color-primary-hover);
}

.modal-desc {
    margin: 0;
    color: #4b5563;
    line-height: 1.5;
}

/* Botones del modal de confirmación por acción */
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

/* ============================================================
   Móvil: la lista de exámenes se muestra como tarjetas.
   Elimina el scroll horizontal de la tabla en pantallas chicas;
   el botón ⋮ queda siempre visible en la esquina superior de
   cada tarjeta. En escritorio se mantiene la tabla con la
   fila clicable (Ver) y el clic derecho para las acciones.
   ============================================================ */
@media (max-width: 768px) {
    .table-container {
        background: transparent;
        border: none;
        overflow: visible;
    }

    .data-table,
    .data-table thead,
    .data-table tbody {
        display: block;
    }

    .data-table thead {
        display: none;
    }

    .data-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    /* Cada fila se convierte en una tarjeta */
    .data-table tr.row-examen,
    .data-table tbody tr.empty-row {
        display: block;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.5rem 0.9rem;
        box-shadow: 0 1px 3px rgba(17, 24, 39, 0.06);
        position: relative;
    }

    .data-table td {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 1rem;
        padding: 0.3rem 0;
        border-bottom: none;
    }

    /* Etiqueta de cada valor dentro de la tarjeta (viene de data-label) */
    .data-table td::before {
        content: attr(data-label);
        flex: 0 0 auto;
        min-width: 6rem;
        color: #6b7280;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    /* La asignatura (primera línea) deja sitio al botón ⋮ */
    .col-asignatura {
        padding-left: 0;
        padding-right: 2.75rem;
        font-size: 0.95rem;
    }

    .group-badges {
        justify-content: flex-end;
    }

    /* El badge de estado se alinea a la derecha de su etiqueta */
    .estado-cell {
        justify-content: flex-end;
    }

    /* El botón ⋮ se ancla a la esquina superior derecha de la tarjeta
       (la tarjeta es el contenedor posicionado, no la celda ESTADO) */
    .td-estado {
        position: static;
        padding-right: 0;
    }

    .row-menu-btn {
        position: absolute;
        top: 0.6rem;
        right: 0.6rem;
        transform: none;
        width: 1.9rem;
        height: 1.9rem;
        opacity: 1;
        background-color: #f9fafb;
        color: var(--color-primary);
        z-index: 1;
    }

    /* La fila vacía también es una tarjeta, sin etiqueta */
    .data-table tbody tr.empty-row {
        border-style: dashed;
        text-align: center;
        color: #6b7280;
    }

    .data-table tbody tr.empty-row td::before {
        content: none;
    }

    .data-table td.empty-state {
        display: block;
        text-align: center;
        padding: 1.5rem !important;
    }
}
</style>