<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    examenes: {
        type: Array,
        default: () => []
    },
    calendarioData: {
        type: Object,
        default: null
    }
});

const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const diasSemana = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
const hoy = new Date();
const mesActual = ref(props.calendarioData ? props.calendarioData.mes - 1 : hoy.getMonth());
const anioActual = ref(props.calendarioData ? props.calendarioData.anio : hoy.getFullYear());
const diaSeleccionado = ref(null);
const isLoading = ref(false);
const pad = (n) => String(n).padStart(2, '0');
const claveFecha = (a, m, d) => `${a}-${pad(m + 1)}-${pad(d)}`;

watch(() => props.calendarioData, (newData) => {
    if (newData) {
        mesActual.value = newData.mes - 1;
        anioActual.value = newData.anio;
        isLoading.value = false;
    }
}, { deep: true });

const examenesPorFecha = computed(() => {
    const mapa = {};
    (props.examenes || []).forEach((e) => {
        const f = String(e.fecha || '').slice(0, 10);
        if (f) (mapa[f] ||= []).push(e);
    });
    return mapa;
});

const celdas = computed(() => {
    const offset = (new Date(anioActual.value, mesActual.value, 1).getDay() + 6) % 7;
    const total = new Date(anioActual.value, mesActual.value + 1, 0).getDate();
    const lista = Array(offset).fill(null);
    for (let d = 1; d <= total; d++) lista.push(d);
    return lista;
});

const esHoy = (d) => d === hoy.getDate() && mesActual.value === hoy.getMonth() && anioActual.value === hoy.getFullYear();
const tieneExamen = (d) => !!examenesPorFecha.value[claveFecha(anioActual.value, mesActual.value, d)];
const examenesDelDia = computed(() => diaSeleccionado.value ? examenesPorFecha.value[claveFecha(anioActual.value, mesActual.value, diaSeleccionado.value)] || [] : []);

const cambiarMes = (delta) => {
    const f = new Date(anioActual.value, mesActual.value + delta, 1);
    const m = f.getMonth();
    const a = f.getFullYear();
    
    // Si estamos usando Inertia y tenemos datos del backend
    if (props.calendarioData) {
        isLoading.value = true;
        router.get(
            window.location.pathname, 
            { mes: m + 1, anio: a }, 
            { preserveState: true, preserveScroll: true, only: ['examenesCalendario', 'calendarioData'] }
        );
    } else {
        mesActual.value = m;
        anioActual.value = a;
    }
    diaSeleccionado.value = null;
};

const seleccionarDia = (d) => { if (d) diaSeleccionado.value = diaSeleccionado.value === d ? null : d; };
</script>

<template>
    <div class="cal-container">
        <div class="cal-nav" :class="{ 'cal-loading': isLoading }">
            <button class="cal-btn" @click="cambiarMes(-1)" aria-label="Mes anterior" :disabled="isLoading">&lsaquo;</button>
            <span class="cal-mes">{{ meses[mesActual] }} {{ anioActual }} <span v-if="isLoading" class="cal-spinner">⌛</span></span>
            <button class="cal-btn" @click="cambiarMes(1)" aria-label="Mes siguiente" :disabled="isLoading">&rsaquo;</button>
        </div>
        <div class="cal-grid" :class="{ 'cal-loading': isLoading }">
            <span v-for="(d, i) in diasSemana" :key="'h' + i" class="cal-head">{{ d }}</span>
            <button v-for="(dia, i) in celdas" :key="i" class="cal-day" :class="{ vacio: !dia, hoy: esHoy(dia), examen: dia && tieneExamen(dia), activo: dia && dia === diaSeleccionado }" :disabled="!dia || isLoading" @click="seleccionarDia(dia)">
                {{ dia || '' }}
                <div v-if="dia && tieneExamen(dia)" class="examen-dots">
                    <div v-for="e in Math.min(3, examenesPorFecha[claveFecha(anioActual, mesActual, dia)]?.length || 0)" :key="e" class="examen-dot"></div>
                    <div v-if="(examenesPorFecha[claveFecha(anioActual, mesActual, dia)]?.length || 0) > 3" class="examen-dot-plus">+</div>
                </div>
            </button>
        </div>
        <div v-if="diaSeleccionado" class="cal-detalle">
            <p v-if="!examenesDelDia.length" class="cal-vacio">Sin exámenes este día.</p>
            <div v-for="e in examenesDelDia" :key="e.id_examen || e.id" class="cal-examen">
                <strong>{{ e.asignatura?.nombre_asignatura || e.asignatura?.nombre || 'Examen' }}</strong>
                <span>{{ String(e.hora_inicio || e.hora || '').slice(0, 5) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cal-container { display: flex; flex-direction: column; }
.cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.cal-mes { font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.85rem; }
.cal-btn { background: none; border: 1px solid #e5e7eb; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; color: var(--color-primary); font-size: 1.1rem; line-height: 1; transition: background 0.2s ease, color 0.2s ease; }
.cal-btn:hover { background: var(--color-primary); color: #fff; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
.cal-head { font-size: 0.7rem; font-weight: 700; color: #6b7280; padding: 4px 0; }
.cal-day { position: relative; border: none; background: none; padding: 6px 0; border-radius: 4px; font-size: 0.85rem; color: #374151; cursor: pointer; transition: background 0.2s ease, color 0.2s ease, scale 0.2s ease, box-shadow 0.2s ease; }
.cal-day:hover:not(.vacio) { background: #f3f4f6; box-shadow: 0 0 8px rgba(29, 54, 83, 0.25); }
.cal-day.vacio { cursor: default; }
.cal-day.hoy { font-weight: 700; color: var(--color-primary); background: #eef2f7; box-shadow: inset 0 0 0 2px var(--color-primary); }
.cal-day.examen { color: var(--color-danger, #A12B33); font-weight: 600; }
.cal-day.activo { background: var(--color-primary); color: #fff; }

.examen-dots {
    display: flex;
    justify-content: center;
    gap: 2px;
    position: absolute;
    bottom: 4px;
    left: 0;
    right: 0;
}
.examen-dot {
    width: 4px;
    height: 4px;
    background-color: var(--color-danger, #A12B33);
    border-radius: 50%;
}
.examen-dot-plus {
    width: 4px;
    height: 4px;
    background-color: var(--color-danger, #A12B33);
    border-radius: 50%;
    opacity: 0.5;
    font-size: 8px;
    line-height: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.cal-loading { opacity: 0.5; }
.cal-spinner { display: inline-block; font-size: 0.75rem; margin-left: 4px; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.cal-detalle { margin-top: 0.75rem; border-top: 1px solid #f0f0f0; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 6px; }
.cal-examen { display: flex; justify-content: space-between; font-size: 0.85rem; color: #374151; padding: 0.5rem 0.75rem; border-left: 3px solid var(--color-danger, #d32f2f); background: #f9fafb; border-radius: 0 6px 6px 0; transition: background 0.2s ease, box-shadow 0.2s ease; }
.cal-examen:hover { box-shadow: 0 0 10px rgba(211, 47, 47, 0.18); }
.cal-vacio { font-size: 0.85rem; color: #6b7280; margin: 0; }
</style>
