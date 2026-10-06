<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    examenes: Array,
    examen: Object,
    ambientes: Array,
    pendientes: Array,
    resumen: Object,
});

const examenSeleccionado = ref(props.examen?.id_examen ?? '');
const busqueda = ref('');
const ultimaActualizacion = ref(null);
let temporizador = null;

const cerrado = computed(() => props.resumen?.cerrado === true);

const pendientesFiltrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    if (!q) return props.pendientes;
    return props.pendientes.filter((p) =>
        `${p.nombres} ${p.apellidos}`.toLowerCase().includes(q) ||
        String(p.codigo_universitario).toLowerCase().includes(q)
    );
});

function cambiarExamen() {
    router.get('/panel-asistencia', { id_examen: examenSeleccionado.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function refrescar() {
    router.reload({
        only: ['ambientes', 'pendientes', 'resumen'],
        preserveScroll: true,
        onSuccess: () => { ultimaActualizacion.value = new Date(); },
    });
}

function iniciarRefresco() {
    detenerRefresco();
    if (!props.examen || cerrado.value) return;
    temporizador = setInterval(refrescar, 3000);
}

function detenerRefresco() {
    if (temporizador) {
        clearInterval(temporizador);
        temporizador = null;
    }
}

function formatearHora(valor) {
    if (!valor) return '';
    return new Date(valor).toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
}

function formatearActualizacion(valor) {
    if (!valor) return 'sin actualizar aun';
    return valor.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

watch(() => [props.examen?.id_examen, cerrado.value], iniciarRefresco);

onMounted(iniciarRefresco);
onUnmounted(detenerRefresco);
</script>

<template>
    <Head title="Panel de asistencia" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <div class="header-section">
                <div>
                    <h1 class="panel-title">PANEL DE ASISTENCIA</h1>
                    <p class="subtitle">Estado de ingreso por ambiente durante el examen</p>
                </div>
                <div class="selector">
                    <label class="filtro-label">Examen</label>
                    <select v-model="examenSeleccionado" class="control" @change="cambiarExamen">
                        <option value="">Seleccione un examen</option>
                        <option v-for="e in examenes" :key="e.id_examen" :value="e.id_examen">
                            {{ e.asignatura?.nombre_asignatura }} · {{ e.fecha }} {{ e.hora_inicio?.slice(0, 5) }}
                        </option>
                    </select>
                </div>
            </div>

            <p v-if="!examen" class="aviso">Seleccione un examen en curso o proximo para ver su estado de asistencia.</p>

            <template v-else>
                <div class="estado-barra">
                    <span class="estado-chip" :class="cerrado ? 'chip-cerrado' : 'chip-vivo'">
                        {{ cerrado ? 'Examen cerrado' : 'Actualizacion automatica activa' }}
                    </span>
                    <span class="estado-detalle">
                        {{ cerrado
                            ? 'Se muestra el estado final de asistencia.'
                            : `Ultima actualizacion: ${formatearActualizacion(ultimaActualizacion)}` }}
                    </span>
                    <button v-if="!cerrado" class="btn-secundario" @click="refrescar">Actualizar ahora</button>
                </div>

                <div class="resumen-grid">
                    <div class="resumen-card">
                        <span class="resumen-valor">{{ resumen.habilitados }}</span>
                        <span class="resumen-label">Habilitados</span>
                    </div>
                    <div class="resumen-card resumen-exito">
                        <span class="resumen-valor">{{ resumen.ingresaron }}/{{ resumen.habilitados }}</span>
                        <span class="resumen-label">Ingresaron</span>
                    </div>
                    <div class="resumen-card resumen-alerta">
                        <span class="resumen-valor">{{ resumen.pendientes }}</span>
                        <span class="resumen-label">Pendientes</span>
                    </div>
                </div>

                <div class="ambientes-grid">
                    <div v-for="ambiente in ambientes" :key="ambiente.id_examen_ambiente" class="ambiente-card">
                        <div class="ambiente-header">
                            <h2 class="ambiente-nombre">{{ ambiente.nombre_ambiente }}</h2>
                            <span class="ambiente-contador">{{ ambiente.total_ingresaron }}/{{ ambiente.total_habilitados }} ingresados</span>
                        </div>
                        <div class="lista">
                            <div v-for="estudiante in ambiente.ingresaron" :key="estudiante.id_estudiante" class="lista-item">
                                <div class="item-datos">
                                    <span class="item-codigo">{{ estudiante.codigo_universitario }}</span>
                                    <span class="item-nombre">{{ estudiante.nombres }} {{ estudiante.apellidos }}</span>
                                </div>
                                <span class="item-hora">{{ formatearHora(estudiante.hora_ingreso) }}</span>
                            </div>
                            <p v-if="ambiente.ingresaron.length === 0" class="lista-vacia">Aun no hay ingresos en este ambiente.</p>
                        </div>
                    </div>
                    <p v-if="ambientes.length === 0" class="aviso">Este examen no tiene ambientes asignados.</p>
                </div>

                <div class="pendientes-card">
                    <div class="ambiente-header">
                        <h2 class="ambiente-nombre">No se han presentado</h2>
                        <span class="ambiente-contador">{{ pendientesFiltrados.length }} de {{ pendientes.length }}</span>
                    </div>
                    <input v-model="busqueda" type="text" class="control" placeholder="Buscar por nombre o codigo universitario" />
                    <div class="lista">
                        <div v-for="estudiante in pendientesFiltrados" :key="estudiante.id_estudiante" class="lista-item">
                            <div class="item-datos">
                                <span class="item-codigo">{{ estudiante.codigo_universitario }}</span>
                                <span class="item-nombre">{{ estudiante.nombres }} {{ estudiante.apellidos }}</span>
                            </div>
                        </div>
                        <p v-if="pendientesFiltrados.length === 0" class="lista-vacia">
                            {{ pendientes.length === 0 ? 'Todos los habilitados ya ingresaron.' : 'Sin coincidencias con esa busqueda.' }}
                        </p>
                    </div>
                    <p class="nota">Los estudiantes pendientes aun no tienen ambiente asignado, por eso se listan a nivel del examen.</p>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem 3rem; background-color: var(--bg-main); min-height: 100vh; font-family: var(--font-family); }
.header-section { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin: 0 0 4px; letter-spacing: 0.05em; font-family: var(--font-display); }
.subtitle { font-size: 0.95rem; color: var(--text-muted); margin: 0; }
.selector { display: flex; flex-direction: column; gap: 4px; min-width: 300px; }
.filtro-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.control { padding: 0.5rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.25rem; font-size: 0.95rem; background: #fff; color: var(--text-dark); font-family: inherit; width: 100%; box-sizing: border-box; }
.control:focus { outline: none; border-color: var(--color-primary); }
.aviso { background: #fff; border: 1px solid #e5e7eb; border-left: 3px solid var(--color-primary); border-radius: 0 0.25rem 0.25rem 0; padding: 1rem; color: var(--text-muted); font-size: 0.95rem; }
.estado-barra { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.estado-chip { padding: 0.35rem 0.9rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; }
.chip-vivo { background: #e6f4ea; color: #1e7a34; }
.chip-cerrado { background: #fdecea; color: var(--color-active); }
.estado-detalle { font-size: 0.9rem; color: var(--text-muted); }
.btn-secundario { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 0.4rem 1rem; border-radius: 0.25rem; font-size: 0.9rem; cursor: pointer; font-family: inherit; }
.resumen-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.resumen-card { background: #fff; border: 1px solid #e5e7eb; border-top: 4px solid var(--color-primary); border-radius: 0.5rem; padding: 1rem; text-align: center; }
.resumen-exito { border-top-color: #1e7a34; }
.resumen-alerta { border-top-color: var(--color-active); }
.resumen-valor { display: block; font-size: 1.75rem; font-weight: 700; color: var(--color-primary); font-family: var(--font-display); }
.resumen-label { font-size: 0.85rem; color: var(--text-muted); }
.ambientes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.ambiente-card, .pendientes-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.25rem; }
.ambiente-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0; padding-bottom: 0.75rem; margin-bottom: 0.75rem; gap: 1rem; }
.ambiente-nombre { font-size: 1rem; font-weight: 700; color: var(--color-primary); margin: 0; }
.ambiente-contador { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
.lista { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.5rem; max-height: 320px; overflow-y: auto; }
.lista-item { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.6rem 0.75rem; background: #f9fafb; border-left: 3px solid var(--color-primary); border-radius: 0 0.25rem 0.25rem 0; }
.item-datos { display: flex; flex-direction: column; }
.item-codigo { font-family: monospace; font-size: 0.8rem; color: var(--text-muted); }
.item-nombre { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
.item-hora { font-family: monospace; font-size: 0.9rem; color: #1e7a34; font-weight: 700; }
.lista-vacia { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
.nota { font-size: 0.8rem; color: var(--text-muted); margin: 0.75rem 0 0; }
@media (max-width: 820px) {
    .panel-container { padding: 1.25rem; }
    .header-section { flex-direction: column; align-items: stretch; }
    .selector { min-width: 0; }
    .resumen-grid { grid-template-columns: 1fr; }
    .ambientes-grid { grid-template-columns: 1fr; }
}
</style>
