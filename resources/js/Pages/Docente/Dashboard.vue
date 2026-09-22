<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link,router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    stats: Object,
    proximosExamenes: Array
});

const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const diasSemana = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
const hoy = new Date();
const mesActual = ref(hoy.getMonth());
const anioActual = ref(hoy.getFullYear());
const diaSeleccionado = ref(null);
const pad = (n) => String(n).padStart(2, '0');
const claveFecha = (a, m, d) => `${a}-${pad(m + 1)}-${pad(d)}`;
const examenesPorFecha = computed(() => {
    const mapa = {};
    (props.proximosExamenes || []).forEach((e) => {
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
    mesActual.value = f.getMonth();
    anioActual.value = f.getFullYear();
    diaSeleccionado.value = null;
};
const seleccionarDia = (d) => { if (d) diaSeleccionado.value = diaSeleccionado.value === d ? null : d; };
const enlaces = [
    { titulo: 'Websis', desc: 'Notas y listas de estudiantes', url: 'https://websis.umss.edu.bo' },
    { titulo: 'Portal UMSS', desc: 'Sitio oficial de la universidad', url: 'https://www.umss.edu.bo' },
    { titulo: 'FCyT', desc: 'Facultad de Ciencias y Tecnología', url: 'https://www.fcyt.umss.edu.bo' },
];
const noticias = [
    { fecha: 'Sep 2026', titulo: 'Revisa tus habilitaciones', texto: 'Confirma el estado de tus estudiantes antes de cada examen.' },
    { fecha: 'Sep 2026', titulo: 'Normas particulares', texto: 'Registra condiciones especiales por estudiante en el detalle del examen.' },
    { fecha: 'Sep 2026', titulo: 'Motivo obligatorio', texto: 'Toda inhabilitación debe incluir un motivo para quedar registrada.' },
];
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top-color: var(--color-primary);">
                    <span class="stat-label">Mis exámenes</span>
                    <span class="stat-value">{{ stats.examenes }}</span>
                    <span class="stat-desc">próximos</span>
                </div>
                <div class="stat-card" style="border-top-color: var(--color-primary);">
                    <span class="stat-label">Habilitados</span>
                    <span class="stat-value">{{ stats.habilitados }}</span>
                    <span class="stat-desc">estudiantes</span>
                </div>
                <div class="stat-card" style="border-top-color: var(--color-danger);">
                    <span class="stat-label">Inhabilitados</span>
                    <span class="stat-value">{{ stats.inhabilitados }}</span>
                    <span class="stat-desc">requieren revisión</span>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="content-grid">
                
                <!-- Columna Izquierda: Mis Exámenes Próximos -->
                <div class="content-box">
                    <div class="box-header">
                        <h2>Mis Exámenes Próximos</h2>
                        <Link href="/examenes" class="link-action">Ver todos &rarr;</Link>
                    </div>
                    
                    <div class="exam-list">
                        <div v-for="examen in proximosExamenes" :key="examen.id_examen" class="exam-item">
                            <div class="exam-info">
                                <h3 class="exam-title">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</h3>
                                <p class="exam-meta">
                                    {{ examen.fecha }} &middot; {{ examen.hora_inicio }} &middot; 
                                    <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                        {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                    </span>
                                </p>
                                <p class="exam-counts">
                                    <span class="count-hab"><strong>{{ examen.hab_count || 0 }}</strong> hab.</span>
                                    <span class="count-inhab"><strong>{{ examen.inhab_count || 0 }}</strong> inhab.</span>
                                </p>
                            </div>
                            <!-- Ajusta la ruta del botón según tu enrutamiento -->
                            <Button variant="action" @click="router.visit(`/examenes/${examen.id_examen}`)">
                                Ver detalle
                            </Button>
                        </div>

                        <!-- Estado vacío -->
                        <div v-if="!proximosExamenes || proximosExamenes.length === 0" class="empty-state">
                            No tienes exámenes próximos programados.
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Accesos Rápidos -->
                <div class="content-box h-fit">
                    <div class="box-header">
                        <h2>Accesos Rápidos</h2>
                    </div>
                    <div class="quick-access-list">
                        <Link href="/examenes" class="qa-item">
                            <div class="qa-content">
                                <span class="qa-title">Mis exámenes</span>
                                <span class="qa-desc">Lista y gestión</span>
                            </div>
                        </Link>
                        <Link href="/habilitaciones" class="qa-item">
                            <div class="qa-content">
                                <span class="qa-title">Habilitaciones</span>
                                <span class="qa-desc">Estado de estudiantes</span>
                            </div>
                        </Link>
                    </div>
                </div>

            </div>
            <div class="extras-grid">
                <div class="content-box">
                    <div class="box-header"><h2>Calendario de exámenes</h2></div>
                    <div class="cal-nav">
                        <button class="cal-btn" @click="cambiarMes(-1)" aria-label="Mes anterior">&lsaquo;</button>
                        <span class="cal-mes">{{ meses[mesActual] }} {{ anioActual }}</span>
                        <button class="cal-btn" @click="cambiarMes(1)" aria-label="Mes siguiente">&rsaquo;</button>
                    </div>
                    <div class="cal-grid">
                        <span v-for="(d, i) in diasSemana" :key="'h' + i" class="cal-head">{{ d }}</span>
                        <button v-for="(dia, i) in celdas" :key="i" class="cal-day" :class="{ vacio: !dia, hoy: esHoy(dia), examen: dia && tieneExamen(dia), activo: dia && dia === diaSeleccionado }" :disabled="!dia" @click="seleccionarDia(dia)">{{ dia || '' }}</button>
                    </div>
                    <div v-if="diaSeleccionado" class="cal-detalle">
                        <p v-if="!examenesDelDia.length" class="cal-vacio">Sin exámenes este día.</p>
                        <div v-for="e in examenesDelDia" :key="e.id_examen" class="cal-examen">
                            <strong>{{ e.asignatura?.nombre_asignatura || 'Examen' }}</strong>
                            <span>{{ String(e.hora_inicio || '').slice(0, 5) }}</span>
                        </div>
                    </div>
                </div>
                <div class="content-box">
                    <div class="box-header"><h2>Enlaces</h2></div>
                    <div class="link-list">
                        <a v-for="l in enlaces" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="link-item">
                            <span class="link-title">{{ l.titulo }}</span>
                            <span class="link-desc">{{ l.desc }}</span>
                        </a>
                    </div>
                </div>
                <div class="content-box">
                    <div class="box-header"><h2>Noticias</h2></div>
                    <div class="news-list">
                        <div v-for="(n, i) in noticias" :key="i" class="news-item">
                            <span class="news-fecha">{{ n.fecha }}</span>
                            <span class="news-titulo">{{ n.titulo }}</span>
                            <p class="news-texto">{{ n.texto }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal */
.panel-container {
    padding: 2rem;
    font-family: var(--font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif);
    background-color: var(--bg-main, #f3f4f6);
    min-height: 100vh;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 1.5rem;
    text-transform: uppercase;
}

/* Tarjetas de Estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #ffffff;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-top: 4px solid var(--color-primary);
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0.25rem 0;
}

.stat-desc {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Cuadrícula Inferior */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.content-box {
    background: #ffffff;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    padding: 1.5rem;
}

.h-fit {
    height: fit-content;
}

.box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.box-header h2 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.link-action {
    color: var(--color-primary);
    font-size: 0.875rem;
    text-decoration: none;
    font-weight: 600;
}

.link-action:hover {
    text-decoration: underline;
}

/* Lista de Exámenes */
.exam-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.exam-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid var(--color-primary);
    padding-left: 1rem;
}

.exam-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
}

.exam-meta {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
}

.exam-counts {
    font-size: 0.875rem;
    margin: 0;
}

.count-hab { color: var(--color-primary); margin-right: 1rem; }
.count-inhab { color: var(--color-danger); }

/* Accesos Rápidos */
.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.qa-item {
    display: flex;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    text-decoration: none;
    color: #1f2937;
    transition: all 0.2s ease;
}

.qa-item:hover {
    background-color: #f9fafb;
    border-color: var(--color-primary);
    transform: translateX(4px);
}

.qa-content {
    display: flex;
    flex-direction: column;
}

.qa-title {
    font-weight: 600;
    font-size: 0.875rem;
}

.qa-desc {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.empty-state {
    color: #6b7280;
    font-size: 0.875rem;
    text-align: center;
    padding: 2rem;
}
.extras-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 1.5rem; }
@media (max-width: 1100px) { .extras-grid { grid-template-columns: 1fr; } }
.cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.cal-mes { font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.85rem; }
.cal-btn { background: none; border: 1px solid #e5e7eb; border-radius: 4px; width: 28px; height: 28px; cursor: pointer; color: var(--color-primary); font-size: 1.1rem; line-height: 1; }
.cal-btn:hover { background: #f3f4f6; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
.cal-head { font-size: 0.7rem; font-weight: 700; color: #6b7280; padding: 4px 0; }
.cal-day { position: relative; border: none; background: none; padding: 6px 0; border-radius: 4px; font-size: 0.85rem; color: #374151; cursor: pointer; }
.cal-day:hover:not(.vacio) { background: #f3f4f6; }
.cal-day.vacio { cursor: default; }
.cal-day.hoy { font-weight: 700; color: var(--color-primary); background: #eef2f7; }
.cal-day.examen::after { content: ''; position: absolute; bottom: 2px; left: 50%; translate: -50% 0; width: 5px; height: 5px; border-radius: 50%; background: var(--color-danger, #d32f2f); }
.cal-day.activo { background: var(--color-primary); color: #fff; }
.cal-detalle { margin-top: 0.75rem; border-top: 1px solid #f0f0f0; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 6px; }
.cal-examen { display: flex; justify-content: space-between; font-size: 0.85rem; color: #374151; }
.cal-vacio { font-size: 0.85rem; color: #6b7280; margin: 0; }
.link-list, .news-list { display: flex; flex-direction: column; gap: 0.5rem; }
.link-item { display: flex; flex-direction: column; padding: 0.75rem; border: 1px solid #f0f0f0; border-radius: 6px; text-decoration: none; transition: background 0.2s ease, border-color 0.2s ease; }
.link-item:hover { background: #f9fafb; border-color: var(--color-primary); }
.link-title { font-weight: 600; color: var(--color-primary); font-size: 0.9rem; }
.link-desc { font-size: 0.8rem; color: #6b7280; }
.news-item { padding: 0.75rem; border-left: 3px solid var(--color-primary); background: #f9fafb; border-radius: 0 6px 6px 0; }
.news-fecha { display: block; font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em; }
.news-titulo { display: block; font-weight: 600; color: var(--color-primary); font-size: 0.9rem; margin: 2px 0; }
.news-texto { font-size: 0.8rem; color: #4b5563; margin: 0; }
.extras-grid .content-box { border-top: 4px solid var(--color-primary); }
.extras-grid .content-box:nth-child(3) { border-top-color: var(--color-danger, #d32f2f); }
.cal-btn { transition: background 0.2s ease, color 0.2s ease; }
.cal-btn:hover { background: var(--color-primary); color: #fff; }
.cal-day { transition: background 0.2s ease, color 0.2s ease, scale 0.2s ease; }
.cal-day.hoy { box-shadow: inset 0 0 0 2px var(--color-primary); }
.cal-day.examen { color: #b91c1c; font-weight: 600; }
.cal-day.activo { background: var(--color-primary); color: #fff; }
.cal-examen { padding: 0.5rem 0.75rem; border-left: 3px solid var(--color-danger, #d32f2f); background: #f9fafb; border-radius: 0 6px 6px 0; }
.link-item { border-left: 3px solid transparent; }
.cal-day, .cal-examen, .link-item, .news-item { transition: background 0.2s ease, box-shadow 0.2s ease; }
.cal-day:hover:not(.vacio) { box-shadow: 0 0 8px rgba(29, 54, 83, 0.25); }
.cal-examen:hover { box-shadow: 0 0 10px rgba(211, 47, 47, 0.18); }
.link-item:hover { box-shadow: 0 0 12px rgba(29, 54, 83, 0.15); }
.news-item:hover { background: #eef2f7; box-shadow: 0 0 12px rgba(29, 54, 83, 0.12); }
</style>