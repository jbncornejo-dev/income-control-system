<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import Pagination from '@/components/ui/Pagination.vue';

const props = defineProps({
    periodos: Array,
    idPeriodoSeleccionado: [Number, String],
    examenes: Object,
    incidencias: Object,
    esDocente: Boolean,
});

const periodoActual = ref(props.idPeriodoSeleccionado ? String(props.idPeriodoSeleccionado) : '');

// Formato de opciones para SelectInput
const opcionesPeriodos = computed(() => {
    return props.periodos?.map(p => ({
        value: String(p.id_periodo),
        label: p.codigo ? `${p.codigo} (${p.nombre || p.gestion})` : `Gestión ${p.gestion}`,
    })) || [];
});

watch(() => props.idPeriodoSeleccionado, (nuevoId) => {
    periodoActual.value = nuevoId ? String(nuevoId) : '';
});

function cambiarPeriodo() {
    router.get('/historico', { id_periodo: periodoActual.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function cambiarPaginaExamenes(pagina) {
    router.get('/historico', {
        id_periodo: periodoActual.value,
        examenes_page: pagina,
        incidencias_page: props.incidencias?.current_page || 1,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function cambiarPaginaIncidencias(pagina) {
    router.get('/historico', {
        id_periodo: periodoActual.value,
        examenes_page: props.examenes?.current_page || 1,
        incidencias_page: pagina,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Histórico Académico" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">HISTÓRICO POR SEMESTRE</h1>

            <!-- Selector de Periodo -->
            <div class="filter-wrapper">
                <SelectInput
                    v-model="periodoActual"
                    label="Seleccione el Periodo Académico"
                    :options="opcionesPeriodos"
                    @change="cambiarPeriodo"
                />
            </div>

            <!-- TABLA 1: EXÁMENES -->
            <div class="table-section">
                <h2 class="section-title">Exámenes del Periodo</h2>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asignatura</th>
                            <th>Tipo</th>
                            <th>Grupos</th>
                            <th>Fecha</th>
                            <th class="text-center">Habilitados</th>
                            <th class="text-center">Ingresados</th>
                            <th class="text-center">Incidencias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="examen in examenes.data" :key="examen.id_examen">
                            <td class="font-bold">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            <td>{{ examen.tipo?.nombre || 'Sin tipo' }}</td>
                            <td>
                                <span v-if="examen.grupos && examen.grupos.length > 0">
                                    {{ examen.grupos.map(g => g.nombre_grupo).join(', ') }}
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>{{ examen.fecha }}</td>
                            <td class="text-center font-bold">{{ examen.habilitaciones_count }}</td>
                            <td class="text-center font-bold text-success">{{ examen.registros_ingreso_count }}</td>
                            <td class="text-center font-bold text-danger">{{ examen.incidencias_count }}</td>
                        </tr>
                        <tr v-if="!examenes.data || examenes.data.length === 0">
                            <td colspan="7" class="empty-state">No hay exámenes registrados para este periodo.</td>
                        </tr>
                    </tbody>
                </table>

                <Pagination
                    v-if="examenes.last_page > 1"
                    :current-page="examenes.current_page"
                    :total-pages="examenes.last_page"
                    @change-page="cambiarPaginaExamenes"
                />
            </div>

            <!-- TABLA 2: INCIDENCIAS -->
            <div class="table-section">
                <h2 class="section-title">Incidencias del Periodo</h2>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Examen (Asignatura)</th>
                            <th>Estudiante</th>
                            <th>Tipo Incidencia</th>
                            <th>Reportado por</th>
                            <th>Detalle / Motivo</th>
                            <th>Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="incidencia in incidencias.data" :key="incidencia.id_incidencia">
                            <td class="font-bold">{{ incidencia.examen?.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            <td>
                                <div>{{ incidencia.estudiante?.nombres }} {{ incidencia.estudiante?.apellidos }}</div>
                                <small class="text-muted">{{ incidencia.estudiante?.codigo_universitario }}</small>
                            </td>
                            <td>{{ incidencia.tipo_incidencia || 'Incidencia' }}</td>
                            <td>{{ incidencia.user?.name || 'Sistema' }}</td>
                            <td>{{ incidencia.descripcion_motivo || 'Sin detalles' }}</td>
                            <td>{{ incidencia.fecha_hora }}</td>
                        </tr>
                        <tr v-if="!incidencias.data || incidencias.data.length === 0">
                            <td colspan="6" class="empty-state">No se registraron incidencias en este periodo.</td>
                        </tr>
                    </tbody>
                </table>

                <Pagination
                    v-if="incidencias.last_page > 1"
                    :current-page="incidencias.current_page"
                    :total-pages="incidencias.last_page"
                    @change-page="cambiarPaginaIncidencias"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container {
    padding: var(--spacing-lg);
    font-family: var(--font-main);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
    font-family: var(--font-display);
    letter-spacing: 0.05em;
    margin: 0;
}

.filter-wrapper {
    max-width: 340px;
}

.table-section {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
}

.text-center {
    text-align: center;
}

.font-bold {
    font-weight: 600;
}

.text-muted {
    color: var(--text-muted);
}

.text-success {
    color: #16a34a;
}

.text-danger {
    color: #dc2626;
}
</style>