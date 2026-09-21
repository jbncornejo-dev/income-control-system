


Habilitaciones index · VUE
<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import { useToastStore } from '@/stores/useToastStore';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    examen: Object,
    habilitaciones: Object,
    stats: Object
});
 
const toast = useToastStore();
 
const listaLocal = ref(props.habilitaciones?.data ?? []);
 
const busqueda     = ref('');
const filtroActivo = ref('todos');
 
const listaFiltrada = computed(() => {
    let lista = listaLocal.value;
    if (filtroActivo.value === 'habilitados')
        lista = lista.filter(h => h.estado_habilitado);
    if (filtroActivo.value === 'inhabilitados')
        lista = lista.filter(h => !h.estado_habilitado);
    if (busqueda.value.trim()) {
        const q = busqueda.value.toLowerCase();
        lista = lista.filter(h =>
            getNombreCompleto(h.estudiante).toLowerCase().includes(q) ||
            h.estudiante?.codigo_universitario?.toLowerCase().includes(q)
        );
    }
    return lista;
});
 
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
            const item = listaLocal.value.find(h => h.id_habilitacion === hab.id_habilitacion);
            if (item) {
                item.estado_habilitado = nuevoEstado;
                item.motivo_inhabilitacion = motivoTexto;
            }
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
                <div style="display: flex; gap: 1rem; flex: 1;">
                    <input
                        v-model="busqueda"
                        type="text"
                        placeholder="Buscar estudiante..."
                        class="search-input"
                    >
                    <div class="filter-group">
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'todos' ? 'active' : '']" @click="filtroActivo = 'todos'">Todos</Button>
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'habilitados' ? 'active' : '']" @click="filtroActivo = 'habilitados'">Habilitados</Button>
                        <Button variant="action" :class="['filter-btn', filtroActivo === 'inhabilitados' ? 'active' : '']" @click="filtroActivo = 'inhabilitados'">Inhabilitados</Button>
                    </div>
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
                        <tr v-for="hab in listaFiltrada" :key="hab.id_habilitacion">
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
                                <textarea v-model="hab.normas_particulares" rows="1" style="width:100%; font-size:0.75rem; border:1px solid #d1d5db; border-radius:3px; padding:2px 6px; resize:vertical;" @change="guardarNormas(hab)"></textarea>
                            </td>
                            <td class="col-mono text-purple">&mdash;</td>
                            <td>
                                <Button
                                    :variant="hab.estado_habilitado ? 'delete' : 'action'"
                                    :disabled="procesando || !!hab.registro_ingreso"
                                    :title="hab.registro_ingreso ? 'No se puede modificar: el estudiante ya ingresó al examen' : ''"
                                    @click="clickToggle(hab)"
                                >
                                    {{ hab.estado_habilitado ? 'Inhabilitar' : 'Habilitar' }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="listaFiltrada.length === 0">
                            <td colspan="5" class="empty-state">No hay estudiantes registrados en este examen.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
 
        <!-- Modal motivo inhabilitación -->
        <Modal :open="modalMotivo" title="Motivo de Inhabilitación" @close="cancelarMotivo">
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">
                    Motivo <span style="color:#d32f2f;">*</span>
                </label>
                <textarea
                    v-model="motivo"
                    rows="3"
                    placeholder="Ej: Documentación incompleta..."
                    style="width:100%; padding:10px; border-radius:6px; font-size:14px; box-sizing:border-box;"
                    :style="{ border: errorMotivo ? '1px solid #d32f2f' : '1px solid #d1d5db' }"
                    @input="errorMotivo = ''"
                ></textarea>
                <p v-if="errorMotivo" style="color:#d32f2f; font-size:12px; margin-top:4px;">{{ errorMotivo }}</p>
            </div>
            <template #footer>
                <Button variant="action" class="btn-modal" @click="cancelarMotivo">
                    Cancelar
                </Button>
                <Button 
                    variant="primary" 
                    class="btn-modal btn-peligro" 
                    @click="confirmarInhabilitar"
                    :disabled="procesando || !!habPendiente?.registro_ingreso"
                >
                    Confirmar inhabilitación
                </Button>
            </template>
        </Modal>
 
    </AuthenticatedLayout>
</template>
 
<style scoped>
.panel-container {
    padding: 2rem;
    background-color: var(--bg-main);
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}
.back-link-container { margin-bottom: 1rem; }
.back-link { color: var(--color-primary); text-decoration: none; font-size: 0.875rem; font-weight: 500; }
.exam-header-card {
    background: #ffffff;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    border-top: 4px solid #4f46e5;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.eyebrow { font-size: 0.75rem; color: #9ca3af; letter-spacing: 0.05em; text-transform: uppercase; }
.exam-title { font-size: 1.75rem; font-weight: 700; color: #1f2937; margin: 0.25rem 0 0 0; }
.exam-meta-row { display: flex; gap: 1.5rem; font-size: 0.875rem; color: #6b7280; margin-bottom: 1.5rem; }
.exam-meta-row strong { color: #374151; }
.mono { font-family: monospace; }
.stats-row { display: flex; gap: 1rem; }
.stat-box { flex: 1; background-color: #f9fafb; border-radius: 0.375rem; padding: 1rem; text-align: center; border: 1px solid #f3f4f6; }
.stat-number { display: block; font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.75rem; color: #9ca3af; }
.text-blue { color: #1e3a8a; }
.text-red { color: #b91c1c; }
.text-gray { color: #4b5563; }
.text-purple { color: var(--color-primary); }
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; gap: 1rem; }
.search-input { flex: 1; max-width: 500px; padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; }
.filter-group { display: flex; border: 1px solid #d1d5db; border-radius: 0.25rem; overflow: hidden; }
.filter-btn { background: white; border: none; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; cursor: pointer; border-right: 1px solid #d1d5db; }
.filter-btn:last-child { border-right: none; }
.filter-btn.active { background: #1d3653; color: white; }
.table-container { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { background-color: var(--color-primary); color: white; text-align: left; padding: 0.75rem 1rem; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
.data-table td { padding: 1rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.col-mono { font-family: monospace; font-size: 0.8rem; }
.student-name { font-weight: 500; color: #1f2937; }
.student-reason { font-size: 0.75rem; margin-top: 0.25rem; }
.badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; display: inline-block; }
.badge-hab { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-inhab { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-confirmado { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
.empty-state { text-align: center; color: #6b7280; padding: 2rem !important; }
/* Estandarización de Modales y Peligro */
.btn-modal {
  padding: 10px 20px !important;
  font-size: 14px !important;
}

.btn-peligro {
  background-color: #dc3545 !important;
  color: #ffffff !important;
}
.btn-peligro:hover:not(:disabled) {
  background-color: #b02a37 !important;
}

/* Fix para agrupar botones como solapas (Tabs) */
.filter-group {
    display: flex;
    gap: 0;
}

/* Modificamos la variante "action" cuando se usa en filtros */
.filter-btn {
    border-radius: 0 !important;
    margin-left: -1px; /* Solapa los bordes adyacentes */
}

.filter-btn:first-child {
    border-top-left-radius: 4px !important;
    border-bottom-left-radius: 4px !important;
}

.filter-btn:last-child {
    border-top-right-radius: 4px !important;
    border-bottom-right-radius: 4px !important;
}

.filter-btn.active {
    background-color: var(--color-primary) !important;
    color: white !important;
    border-color: var(--color-primary) !important;
    z-index: 2;
}

/* Permitir scroll en tablas pequeñas */
.table-container { 
    background: white; 
    border: 1px solid #e5e7eb; 
    border-radius: 0.5rem; 
    overflow-x: auto; 
}
</style>
 
