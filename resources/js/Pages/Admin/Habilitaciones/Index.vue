
<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import { useToastStore } from '@/stores/useToastStore';
import Button from '@/components/ui/Button.vue';
import TextInput from '@/components/ui/TextInput.vue';

const props = defineProps({
    examen: Object,
    habilitaciones: Object,
    stats: Object,
    filtros: Object,
    coincidencias: Number,
    acciones: Object,
    personalAsignado: { type: Array, default: () => [] },
    personalDisponible: { type: Array, default: () => [] }
});

const personalSeleccionado = ref('');
const errorPersonal = ref('');
const procesandoPersonal = ref(false);
const toast = useToastStore();

function asignarPersonal() {
    if (!personalSeleccionado.value) {
        errorPersonal.value = 'Debe seleccionar un usuario para asignar';
        return;
    }
    errorPersonal.value = '';
    procesandoPersonal.value = true;

    router.post(`/examenes/${props.examen.id_examen}/personal-control`, { id_usuario: personalSeleccionado.value }, {
        preserveScroll: true,
        onSuccess: () => {
            personalSeleccionado.value = '';
            toast.success('Personal de control asignado correctamente.');
        },
        onError: (e) => {
            errorPersonal.value = e.id_usuario ?? 'No se pudo asignar el personal de control.';
        },
        onFinish: () => { procesandoPersonal.value = false; }
    });
}

function quitarPersonal(usuario) {
    procesandoPersonal.value = true;

    router.delete(`/examenes/${props.examen.id_examen}/personal-control/${usuario.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Personal de control desasignado correctamente.'),
        onError: () => toast.error('No se pudo quitar al personal de control.'),
        onFinish: () => { procesandoPersonal.value = false; }
    });
}
 
const listaLocal = ref(props.habilitaciones?.data ?? []);
watch(() => props.habilitaciones, value => { listaLocal.value = value?.data ?? []; });
const busqueda = ref(props.filtros?.busqueda ?? '');
const filtroActivo = ref(props.filtros?.estado ?? 'todos');
const modalBloque = ref(false);
const accionBloque = ref(null);
const cantidadBloque = computed(() => accionBloque.value === 'habilitar' ? props.acciones?.habilitar ?? 0 : props.acciones?.inhabilitar ?? 0);

function cargarFiltro(estado = filtroActivo.value, termino = busqueda.value) {
    filtroActivo.value = estado;
    modalBloque.value = false;
    router.get(`/examenes/${props.examen.id_examen}/habilitaciones`, {
        estado,
        busqueda: termino.trim(),
    }, { preserveState: true, preserveScroll: true, replace: true });
}
let temporizador;
watch(busqueda, valor => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => cargarFiltro(filtroActivo.value, valor), 350);
});

function abrirBloque(accion) {
    accionBloque.value = accion;
    motivo.value = '';
    errorMotivo.value = '';
    modalBloque.value = true;
}

function confirmarBloque() {
    if (accionBloque.value === 'inhabilitar' && !motivo.value.trim()) {
        errorMotivo.value = 'Debe especificar el motivo de la inhabilitación.';
        return;
    }
    procesando.value = true;
    router.patch(`/examenes/${props.examen.id_examen}/habilitaciones`, {
        estado_habilitado: accionBloque.value === 'habilitar',
        motivo_inhabilitacion: accionBloque.value === 'inhabilitar' ? motivo.value.trim() : null,
        estado: filtroActivo.value,
        busqueda: busqueda.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: page => {
            modalBloque.value = false;
            toast.success(page.props.flash?.success ?? 'Cambio por bloque completado.');
        },
        onError: errores => { errorMotivo.value = errores.motivo_inhabilitacion ?? ''; toast.error(errores.motivo_inhabilitacion ?? 'No se pudo completar el cambio.'); },
        onFinish: () => { procesando.value = false; },
    });
}
 
const getNombreCompleto = (estudiante) => {
    if (!estudiante) return 'N/D';
    return `${estudiante.nombres || ''} ${estudiante.apellidos || ''}`.trim();
};
 
const modalMotivo  = ref(false);
const motivo       = ref('');
const errorMotivo  = ref('');
const habPendiente = ref(null);
const procesando   = ref(false);
 
function clickToggle(hab) {
    if (hab.estado_habilitado) {
        habPendiente.value = hab;
        motivo.value = '';
        errorMotivo.value = '';
        modalMotivo.value = true;
    } else {
        enviarCambio(hab, true, null);
    }
}
 
function cancelarMotivo() {
    modalMotivo.value = false;
    habPendiente.value = null;
    motivo.value = '';
    errorMotivo.value = '';
}
 
function confirmarInhabilitar() {
    if (!motivo.value.trim()) {
        errorMotivo.value = 'Debe especificar el motivo de la inhabilitación.';
        return;
    }
    enviarCambio(habPendiente.value, false, motivo.value.trim());
    modalMotivo.value = false;
}
 
function enviarCambio(hab, nuevoEstado, motivoTexto) {
    procesando.value = true;
    router.patch(`/habilitaciones/${hab.id_habilitacion}`, {
        estado_habilitado: nuevoEstado,
        motivo_inhabilitacion: motivoTexto,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success(nuevoEstado ? 'Estudiante habilitado correctamente.' : 'Estudiante inhabilitado correctamente.');
            habPendiente.value = null;
        },
        onError: (e) => {
            if (e.motivo_inhabilitacion) toast.error(e.motivo_inhabilitacion);
            else if (e.message) toast.error(e.message);
            else toast.error('No se pudo cambiar el estado.');
        },
        onFinish: () => { procesando.value = false; },
    });
}

function guardarNormas(hab) {
    router.patch(`/habilitaciones/${hab.id_habilitacion}`, {
        estado_habilitado: hab.estado_habilitado,
        motivo_inhabilitacion: hab.motivo_inhabilitacion,
        normas_particulares: hab.normas_particulares,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Normas particulares guardadas.'),
        onError: () => toast.error('Error al guardar las normas particulares.'),
    });
}
</script>
 
<template>
    <Head title="Detalle de Examen" />
 
    <AuthenticatedLayout>
        <div class="panel-container">
 
            <div class="back-link-container">
                <Link href="/examenes" class="back-link">&larr; Volver a Exámenes</Link>
            </div>
 
            <div class="exam-header-card">
                <div class="header-top">
                    <div>
                        <span class="eyebrow">EXAMEN PROGRAMADO</span>
                        <h1 class="exam-title">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</h1>
                    </div>
                    <span class="badge badge-confirmado">Confirmado</span>
                </div>
 
                <div class="exam-meta-row">
                    <span><strong>Docente:</strong> {{ examen.docente?.name || 'N/D' }}</span>
                    <span><strong>Fecha:</strong> <span class="mono">{{ examen.fecha }}</span></span>
                    <span><strong>Hora:</strong> <span class="mono">{{ examen.hora_inicio }}</span></span>
                    <span>
                        <strong>Ambientes:</strong>
                        <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                            {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                        </span>
                        <span v-else>N/D</span>
                    </span>
                </div>
 
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-number text-blue">{{ stats.habilitados }}</span>
                        <span class="stat-label">Habilitados</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number text-red">{{ stats.inhabilitados }}</span>
                        <span class="stat-label">Inhabilitados</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number text-gray">{{ stats.ingresaron }}</span>
                        <span class="stat-label">Ingresaron</span>
                    </div>
                </div>
            </div>
 
            <!-- Barra de Búsqueda y Filtros -->
            <div class="action-bar">
                <div style="display: flex; gap: var(--spacing-md); flex: 1; flex-wrap: wrap;">
                    <TextInput
                        v-model="busqueda"
                        placeholder="Buscar estudiante..."
                        style="flex: 1; min-width: 200px; max-width: 500px;"
                    />
                    <div class="filter-group">
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'todos' ? 'active' : '']" @click="cargarFiltro('todos')">Todos</Button>
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'habilitados' ? 'active' : '']" @click="cargarFiltro('habilitados')">Habilitados</Button>
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'inhabilitados' ? 'active' : '']" @click="cargarFiltro('inhabilitados')">Inhabilitados</Button>
                    </div>
                </div>
            </div>
            <div v-if="filtroActivo !== 'todos'" class="bulk-bar">
                <span><strong>{{ coincidencias }}</strong> estudiantes coinciden con el filtro. La acción incluye todas las páginas.</span>
                <div class="bulk-buttons">
                    <Button v-if="filtroActivo === 'inhabilitados'" variant="primary" class="bulk-action" :disabled="!acciones?.habilitar || procesando" @click="abrirBloque('habilitar')">Habilitar a los {{ acciones?.habilitar ?? 0 }} resultados <span aria-hidden="true">→</span></Button>
                    <Button v-if="filtroActivo === 'habilitados'" variant="danger" class="bulk-action" :disabled="!acciones?.inhabilitar || procesando" @click="abrirBloque('inhabilitar')">Inhabilitar a los {{ acciones?.inhabilitar ?? 0 }} resultados <span aria-hidden="true">→</span></Button>
                </div>
            </div>
 
            <!-- Tabla de Estudiantes -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>CÓDIGO</th>
                            <th>NOMBRE</th>
                            <th>ESTADO</th>
                            <th>INGRESO</th>
                            <th>NORMAS PARTICULARES</th>
                            <th>HABILITACIÓN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="hab in listaLocal" :key="hab.id_habilitacion">
                            <td class="col-mono text-purple">{{ hab.estudiante?.codigo_universitario || 'N/D' }}</td>
                            <td>
                                <div class="student-name">{{ getNombreCompleto(hab.estudiante) }}</div>
                                <div v-if="!hab.estado_habilitado && hab.motivo_inhabilitacion" class="student-reason text-red">
                                    {{ hab.motivo_inhabilitacion }}
                                </div>
                            </td>
                            <td>
                                <span :class="['badge', hab.estado_habilitado ? 'badge-hab' : 'badge-inhab']">
                                    {{ hab.estado_habilitado ? 'Habilitado' : 'Inhabilitado' }}
                                </span>
                            </td>
                            <td>
                                {{ hab.estudiante?.ya_ingreso ? 'Registrado' : 'Pendiente' }}
                            </td>
                            <td>
                                <textarea v-model="hab.normas_particulares" rows="1" class="form-input" style="font-size:0.8rem; padding:4px 8px; resize:vertical;" @change="guardarNormas(hab)"></textarea>
                            </td>
                            <td>
                                <Button
                                    :variant="hab.estado_habilitado ? 'danger' : 'primary'"
                                    :disabled="procesando || !!hab.estudiante?.ya_ingreso"
                                    :title="hab.estudiante?.ya_ingreso ? 'No se puede modificar: el estudiante ya ingresó al examen' : ''"
                                    @click="clickToggle(hab)"
                                >
                                    {{ hab.estado_habilitado ? 'Inhabilitar' : 'Habilitar' }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="listaLocal.length === 0">
                            <td colspan="6" class="empty-state">No hay estudiantes que coincidan con este filtro.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav v-if="habilitaciones?.last_page > 1" class="pagination" aria-label="Páginas de estudiantes">
                <Link v-for="link in habilitaciones.links" :key="link.label" :href="link.url || '#'" :class="['page-link', { active: link.active, disabled: !link.url }]" preserve-scroll v-html="link.label" />
            </nav>

            <div class="personal-seccion">
                <div class="personal-header">
                    <h2 class="personal-titulo">Personal de control asignado</h2>
                    <span class="personal-contador">{{ personalAsignado.length }} asignado(s)</span>
                </div>

                <div class="personal-form">
                    <select v-model="personalSeleccionado" class="personal-select" :class="{ 'personal-select-error': errorPersonal }">
                        <option value="">Seleccione personal de control</option>
                        <option v-for="persona in personalDisponible" :key="persona.id" :value="persona.id">
                            {{ persona.name }} ({{ persona.username }})
                        </option>
                    </select>
                    <button class="personal-btn" :disabled="procesandoPersonal || personalDisponible.length === 0" @click="asignarPersonal">Asignar</button>
                </div>
                <p v-if="errorPersonal" class="personal-error">{{ errorPersonal }}</p>
                <p v-if="personalDisponible.length === 0" class="personal-nota">No hay mas usuarios de control disponibles para este examen.</p>

                <div class="personal-lista">
                    <div v-for="persona in personalAsignado" :key="persona.id" class="personal-item">
                        <div class="personal-datos">
                            <span class="personal-nombre">{{ persona.name }}</span>
                            <span class="personal-usuario">{{ persona.username }}</span>
                        </div>
                        <button class="personal-quitar" :disabled="procesandoPersonal" @click="quitarPersonal(persona)">Quitar</button>
                    </div>
                    <p v-if="personalAsignado.length === 0" class="personal-vacio">Aun no se asigno personal de control a este examen.</p>
                </div>
            </div>

        </div>
 
        <!-- Modal motivo inhabilitación -->
        <Modal :open="modalMotivo" title="Motivo de Inhabilitación" @close="cancelarMotivo">
            <div class="form-group">
                <label class="form-label">
                    Motivo <span class="required">*</span>
                </label>
                <textarea
                    v-model="motivo"
                    rows="3"
                    placeholder="Ej: Documentación incompleta..."
                    class="form-input"
                    :class="{ 'input-error': errorMotivo }"
                    @input="errorMotivo = ''"
                ></textarea>
                <p v-if="errorMotivo" class="error-msg">{{ errorMotivo }}</p>
            </div>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="cancelarMotivo">
                    Cancelar
                </Button>
                <Button 
                    variant="danger" 
                    class="btn-modal" 
                    @click="confirmarInhabilitar"
                    :disabled="procesando || !!habPendiente?.estudiante?.ya_ingreso"
                >
                    Confirmar inhabilitación
                </Button>
            </template>
        </Modal>

        <Modal :open="modalBloque" :title="accionBloque === 'habilitar' ? 'Habilitar en bloque' : 'Inhabilitar en bloque'" @close="modalBloque = false">
            <p class="confirm-text" style="margin-bottom: var(--spacing-sm);">Se {{ accionBloque === 'habilitar' ? 'habilitarán' : 'inhabilitarán' }} <strong>{{ cantidadBloque }}</strong> estudiantes del examen {{ examen.asignatura?.nombre_asignatura }} que coinciden con el filtro y la búsqueda actuales, en todas las páginas.</p>
            <p class="confirm-text" style="margin-bottom: var(--spacing-md);">Los estudiantes que ya registraron ingreso quedan excluidos.</p>
            <div v-if="accionBloque === 'inhabilitar'" class="form-group">
                <label for="motivo-bloque" class="form-label">Motivo común de inhabilitación <span class="required">*</span></label>
                <textarea id="motivo-bloque" v-model="motivo" rows="3" class="form-input" @input="errorMotivo = ''" :class="{ 'input-error': errorMotivo }"></textarea>
                <p v-if="errorMotivo" class="error-msg">{{ errorMotivo }}</p>
            </div>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="modalBloque = false">Cancelar</Button>
                <Button :variant="accionBloque === 'habilitar' ? 'primary' : 'danger'" class="bulk-action btn-modal" :disabled="procesando || !cantidadBloque" @click="confirmarBloque"><span v-if="procesando" class="bulk-spinner" aria-hidden="true"></span>{{ procesando ? 'Procesando cambios...' : `Confirmar ${accionBloque} a ${cantidadBloque}` }}</Button>
            </template>
        </Modal>
 
    </AuthenticatedLayout>
</template>
 
<style scoped>
.panel-container {
    padding: var(--spacing-lg) var(--spacing-xl);
    background-color: transparent;
    font-family: var(--font-family);
}
.back-link-container { margin-bottom: var(--spacing-md); }
.back-link { color: var(--color-primary); text-decoration: none; font-size: 0.875rem; font-weight: 500; }
.exam-header-card {
    background: var(--color-white);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    border-top: 4px solid var(--color-primary);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-card);
}
.header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-md); }
.eyebrow { font-size: 0.75rem; color: var(--text-muted); letter-spacing: 0.05em; text-transform: uppercase; }
.exam-title { font-size: 1.75rem; font-weight: 700; color: var(--color-primary); margin: 0.25rem 0 0 0; font-family: var(--font-display); }
.exam-meta-row { display: flex; gap: var(--spacing-lg); font-size: 0.875rem; color: var(--text-muted); margin-bottom: var(--spacing-lg); }
.exam-meta-row strong { color: var(--text-dark); }
.mono { font-family: var(--font-mono); }
.stats-row { display: flex; gap: var(--spacing-md); }
.stat-box { flex: 1; background-color: var(--color-bg-input); border-radius: var(--radius-sm); padding: var(--spacing-md); text-align: center; border: 1px solid var(--border-light); }
.stat-number { display: block; font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.75rem; color: var(--text-muted); }
.text-blue { color: var(--color-primary); }
.text-red { color: var(--color-danger); }
.text-gray { color: var(--text-muted); }
.text-purple { color: var(--color-primary); }
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md); gap: var(--spacing-md); }
.bulk-bar { display: flex; justify-content: space-between; align-items: center; gap: var(--spacing-md); background: var(--color-white); border: 1px solid var(--color-primary); border-radius: var(--radius-md); padding: var(--spacing-sm) var(--spacing-md); margin-bottom: var(--spacing-md); color: var(--text-dark); font-size: 0.875rem; box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.1); }
.bulk-buttons { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
.bulk-action { gap: 0.55rem; min-height: 2.7rem; }
.bulk-spinner { width: 0.9rem; height: 0.9rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: bulk-spin 700ms linear infinite; }
@keyframes bulk-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .bulk-action { transition: none !important; } .bulk-spinner { animation: none; } }
.pagination { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; margin-top: var(--spacing-lg); }
.page-link { padding: 0.45rem 0.7rem; background: var(--color-white); border: 1px solid var(--border-light); border-radius: var(--radius-sm); color: var(--color-primary); text-decoration: none; }
.page-link.active { background: var(--color-primary); color: var(--color-white); }
.page-link.disabled { pointer-events: none; opacity: 0.5; }
@media (max-width: 720px) { .action-bar, .bulk-bar, .action-bar > div { flex-direction: column; align-items: stretch !important; } .bulk-buttons > * { flex: 1; } .exam-meta-row, .stats-row { flex-wrap: wrap; } }
.filter-group { display: flex; border: 1px solid var(--border-light); border-radius: var(--radius-md); overflow: hidden; }
.filter-btn { background: var(--color-white); border: none; padding: 0.5rem 1rem; font-size: 0.875rem; color: var(--text-dark); cursor: pointer; border-right: 1px solid var(--border-light); }
.filter-btn:last-child { border-right: none; }
.filter-btn.active { background: var(--color-primary); color: var(--color-white); border-color: var(--color-primary); }
.table-container { background: var(--color-white); border: 1px solid var(--border-light); border-radius: var(--radius-md); overflow-x: auto; box-shadow: var(--shadow-card); }
.col-mono { font-family: var(--font-mono); font-size: 0.85rem; }
.student-name { font-weight: 500; color: var(--text-dark); }
.student-reason { font-size: 0.75rem; margin-top: 0.25rem; }
.badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; display: inline-block; }
.badge-hab { background-color: rgba(79, 70, 229, 0.1); color: var(--color-primary); border: 1px solid rgba(79, 70, 229, 0.2); }
.badge-inhab { background-color: rgba(220, 53, 69, 0.1); color: var(--color-danger); border: 1px solid rgba(220, 53, 69, 0.2); }
.badge-confirmado { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
.empty-state { text-align: center; color: var(--text-muted); padding: 2rem !important; }

/* Estandarización de Modales */
.btn-modal { padding: 10px 20px !important; font-size: 14px !important; }
.confirm-text { color: var(--text-dark); font-size: 0.9rem; line-height: 1.6; }

/* Fix para agrupar botones como solapas (Tabs) */
.filter-group { display: flex; gap: 0; }
.filter-btn { border-radius: 0 !important; margin-left: -1px; }
.filter-btn:first-child { border-top-left-radius: 4px !important; border-bottom-left-radius: 4px !important; }
.filter-btn:last-child { border-top-right-radius: 4px !important; border-bottom-right-radius: 4px !important; }
.filter-btn.active { background-color: var(--color-primary) !important; color: var(--color-white) !important; border-color: var(--color-primary) !important; z-index: 2; }

/* Sección Personal de Control */
.personal-seccion { background: var(--color-white); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: var(--spacing-lg); margin-top: var(--spacing-lg); box-shadow: var(--shadow-card); }
.personal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: var(--spacing-sm); margin-bottom: var(--spacing-md); }
.personal-titulo { font-size: 1rem; font-weight: 700; color: var(--color-primary); margin: 0; font-family: var(--font-display); }
.personal-contador { font-size: 0.85rem; color: var(--text-muted); }
.personal-form { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
.personal-select { flex: 1; min-width: 240px; padding: var(--spacing-sm) 12px; border: 1px solid var(--border-light); border-radius: var(--radius-md); font-size: 0.95rem; font-family: inherit; background: var(--color-bg-input); }
.personal-select-error { border-color: var(--color-danger); }
.personal-btn { background: var(--color-primary); color: var(--color-white); border: none; padding: 0.5rem 1.25rem; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
.personal-btn:hover:not(:disabled) { background: var(--color-secondary); }
.personal-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.personal-error { color: var(--color-danger); font-size: 0.85rem; margin: var(--spacing-xs) 0 0; }
.personal-nota { color: var(--text-muted); font-size: 0.85rem; margin: var(--spacing-xs) 0 0; }
.personal-lista { margin-top: var(--spacing-md); display: flex; flex-direction: column; gap: var(--spacing-sm); }
.personal-item { display: flex; justify-content: space-between; align-items: center; gap: var(--spacing-md); padding: var(--spacing-md); border: 1px solid var(--border-light); border-left: 3px solid var(--color-primary); border-radius: 0 var(--radius-sm) var(--radius-sm) 0; background: var(--color-bg-input); }
.personal-datos { display: flex; flex-direction: column; }
.personal-nombre { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
.personal-usuario { font-size: 0.85rem; color: var(--text-muted); }
.personal-quitar { background: var(--color-white); color: var(--color-danger); border: 1px solid var(--color-danger); padding: 0.35rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.personal-quitar:hover:not(:disabled) { background: var(--color-danger); color: var(--color-white); }
.personal-quitar:disabled { opacity: 0.5; cursor: not-allowed; }
.personal-vacio { color: var(--text-muted); font-size: 0.9rem; margin: 0; text-align: center; padding: var(--spacing-md); }
</style>
 
