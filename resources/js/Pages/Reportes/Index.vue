<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const props = defineProps({
    reportes: { type: Object, required: true },
    asignaturas: { type: Array, required: true },
    periodos: { type: Array, required: true },
});

const idPeriodo = ref(props.periodos[0]?.id_periodo ?? '');
const idAsignatura = ref('');
const rutas = { 'reportes.examenes': '/reportes/examenes', 'reportes.incidencias': '/reportes/incidencias' };

function url(permiso, formato) {
    const filtros = new URLSearchParams({ id_periodo: idPeriodo.value, formato });
    if (idAsignatura.value) filtros.set('id_asignatura', idAsignatura.value);
    return rutas[permiso] + '?' + filtros;
}
</script>

<template>
    <Head title="Reportes" />
    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">Reportes</h1>
            <p v-if="!Object.keys(reportes).length">Tu rol todavía no tiene permisos de exportación.</p>
            <p v-else-if="!periodos.length">Todavía no hay periodos registrados.</p>
            <p v-else-if="!asignaturas.length">No hay asignaturas disponibles para generar reportes.</p>
            <template v-else>
                <div class="report-filters">
                    <SelectInput id="periodo" v-model="idPeriodo" label="Periodo" :options="periodos.map(p => ({ value: p.id_periodo, label: p.nombre }))" />
                    <SelectInput id="asignatura" v-model="idAsignatura" label="Asignatura" placeholder="Todas las asignaturas disponibles" :options="asignaturas.map(a => ({ value: a.id_asignatura, label: a.nombre_asignatura }))" />
                </div>
                <div v-for="(etiqueta, permiso) in reportes" :key="permiso" class="report-card">
                    <h2>{{ etiqueta }}</h2>
                    <div v-if="idPeriodo" class="report-actions">
                        <a :href="url(permiso, 'pdf')">Descargar PDF</a>
                        <a :href="url(permiso, 'excel')">Descargar Excel</a>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped src="../../../css/panels.css"></style>
<style scoped>
.report-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); gap: 1rem; margin-bottom: 1rem; }
.report-card { margin-bottom: 0.75rem; padding: 1rem; background: white; border: 1px solid var(--border-light); border-radius: 0.5rem; }
.report-card h2 { margin: 0 0 0.5rem; color: var(--color-primary); font-size: 1rem; }
.report-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.report-actions a { color: var(--color-primary); text-decoration: underline; }
</style>
