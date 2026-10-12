<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    CalendarCheck, 
    UserCheck, 
    UserX, 
    CalendarX, 
    ExternalLink, 
    Globe 
} from 'lucide-vue-next';
import ExamenesCalendar from '@/components/ui/ExamenesCalendar.vue';
import StatCard from '@/components/ui/StatCard.vue';

const props = defineProps({
    stats: Object,
    proximosExamenes: Array,
    calendarioData: Object,
    examenesCalendario: Array,
    noticias: Array
});

const enlaces = [
    { titulo: 'Websis', desc: 'Notas y listas de estudiantes', url: 'https://websis.umss.edu.bo', color: 'var(--color-primary)' },
    { titulo: 'Portal UMSS', desc: 'Sitio oficial de la universidad', url: 'https://www.umss.edu.bo', color: 'var(--color-gold, #C5A059)' },
    { titulo: 'FCyT', desc: 'Facultad de Ciencias y Tecnología', url: 'https://www.fcyt.umss.edu.bo', color: 'var(--color-danger, #A12B33)' },
];

const fallbackNoticias = [
    { fecha: 'Sep 2026', titulo: 'Revisa tus habilitaciones', resumen: 'Confirma el estado de tus estudiantes antes de cada examen programado.' },
    { fecha: 'Sep 2026', titulo: 'Normas particulares', resumen: 'Registra condiciones especiales por estudiante en el detalle del examen.' },
    { fecha: 'Sep 2026', titulo: 'Motivo obligatorio', resumen: 'Toda inhabilitación debe incluir un motivo para quedar registrada en el sistema.' },
];

const noticiasRender = computed(() => {
    return props.noticias && props.noticias.length > 0 
        ? props.noticias 
        : fallbackNoticias;
});
</script>

<template>
    <Head title="Panel Principal Docente" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Grid de Tarjetas (Stats) para Docentes -->
            <div class="stats-grid">
                <StatCard 
                    label="Mis exámenes" 
                    :value="stats.examenes" 
                    desc="próximos" 
                    color="var(--color-primary)"
                >
                    <template #icon><CalendarCheck :size="24" /></template>
                </StatCard>

                <StatCard 
                    label="Habilitados" 
                    :value="stats.habilitados" 
                    desc="estudiantes" 
                    color="#10b981"
                >
                    <template #icon><UserCheck :size="24" /></template>
                </StatCard>

                <StatCard 
                    label="Inhabilitados" 
                    :value="stats.inhabilitados" 
                    desc="requieren revisión" 
                    color="#f59e0b"
                >
                    <template #icon><UserX :size="24" /></template>
                </StatCard>
            </div>

            <!-- Grid Medio: Mis Exámenes Próximos y Accesos Rápidos -->
            <div class="content-grid">
                
                <!-- Columna Izquierda: Mis Exámenes Próximos -->
                <div class="content-box">
                    <div class="box-header">
                        <h2 class="box-title">Mis Exámenes Próximos</h2>
                        <Link href="/examenes" class="link-action">Ver todos &rarr;</Link>
                    </div>
                    
                    <div v-if="proximosExamenes && proximosExamenes.length > 0" class="exam-list">
                        <div v-for="examen in proximosExamenes" :key="examen.id_examen" class="exam-card-item">
                            <div class="exam-card-info">
                                <h3 class="exam-subject-name">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</h3>
                                <p class="exam-meta-line">
                                    <span>{{ examen.fecha }}</span> &middot; 
                                    <span>{{ String(examen.hora_inicio || '').slice(0, 5) }}</span>
                                    <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                        &middot; {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).filter(Boolean).join(', ') }}
                                    </span>
                                </p>
                                <div class="exam-counts-line">
                                    <span class="count-pill count-hab"><strong>{{ examen.hab_count || 0 }}</strong> hab.</span>
                                    <span class="count-pill count-inhab"><strong>{{ examen.inhab_count || 0 }}</strong> inhab.</span>
                                </div>
                            </div>
                            <Link 
                                :href="`/examenes/${examen.id_examen}/habilitaciones`"
                                class="btn-exam-detail"
                            >
                                Ver detalle
                            </Link>
                        </div>
                    </div>

                    <!-- Estado Vacío Fiel al Mockup -->
                    <div v-else class="empty-exams-placeholder">
                        <div class="empty-icon-circle">
                            <CalendarX :size="32" class="empty-icon-svg" />
                        </div>
                        <p class="empty-primary-text">No tienes exámenes próximos programados.</p>
                        <p class="empty-secondary-text">Los exámenes que se te asignen o registres aparecerán en este panel de control.</p>
                    </div>
                </div>

                <!-- Columna Derecha: Accesos Rápidos -->
                <div class="content-box h-fit">
                    <div class="box-header">
                        <h2 class="box-title">Accesos Rápidos</h2>
                    </div>
                    
                    <div class="quick-access-stack">
                        <Link href="/examenes" class="qa-card-link">
                            <div class="qa-card-header">
                                <span class="qa-title-text">Mis exámenes</span>
                                <span class="qa-badge-meta">Gestionar &rarr;</span>
                            </div>
                            <span class="qa-desc-text">Lista y gestión de evaluaciones</span>
                        </Link>
                        <Link href="/habilitaciones" class="qa-card-link">
                            <div class="qa-card-header">
                                <span class="qa-title-text">Habilitaciones</span>
                                <span class="qa-badge-meta">Revisar &rarr;</span>
                            </div>
                            <span class="qa-desc-text">Control del estado de estudiantes</span>
                        </Link>
                    </div>
                </div>

            </div>

            <!-- Grid Inferior: Widgets (Calendario, Enlaces, Noticias) -->
            <div class="extras-grid">
                
                <!-- Widget Calendario -->
                <div class="content-box widget-box">
                    <div class="box-header">
                        <h2 class="box-title">Calendario</h2>
                    </div>
                    <ExamenesCalendar :examenes="examenesCalendario" :calendario-data="calendarioData" />
                </div>

                <!-- Widget Enlaces -->
                <div class="content-box widget-box">
                    <div class="box-header">
                        <h2 class="box-title">Enlaces</h2>
                    </div>
                    <div class="link-list">
                        <a 
                            v-for="l in enlaces" 
                            :key="l.url" 
                            :href="l.url" 
                            target="_blank" 
                            rel="noopener" 
                            class="official-link-card"
                            :style="{ borderLeftColor: l.color }"
                        >
                            <span class="official-link-title">{{ l.titulo }}</span>
                            <span class="official-link-desc">{{ l.desc }}</span>
                        </a>
                    </div>
                </div>

                <!-- Widget Noticias (Funcional y conectado con UMSS) -->
                <div class="content-box widget-box">
                    <div class="box-header">
                        <h2 class="box-title">Noticias</h2>
                        <a href="https://www.umss.edu.bo/categoria/noticias/" target="_blank" rel="noopener" class="news-link-external">
                            UMSS <ExternalLink :size="12" />
                        </a>
                    </div>
                    
                    <div v-if="noticiasRender" class="news-list">
                        <a 
                            v-for="(n, i) in noticiasRender" 
                            :key="i" 
                            :href="n.enlace || 'https://www.umss.edu.bo/categoria/noticias/'" 
                            target="_blank" 
                            rel="noopener" 
                            class="news-card-item"
                        >
                            <span class="news-card-date">{{ n.fecha }}</span>
                            <span class="news-card-title">{{ n.titulo }}</span>
                            <p class="news-card-summary">{{ n.resumen }}</p>
                        </a>
                    </div>
                    
                    <div v-else class="news-fallback">
                        <Globe :size="32" class="news-fallback-icon" />
                        <p class="news-fallback-text">No se pudieron cargar las noticias en este momento.</p>
                        <a href="https://www.umss.edu.bo/categoria/noticias/" target="_blank" class="news-fallback-link">Ver noticias en UMSS &rarr;</a>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal con padding responsivo */
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

/* Grid de Tarjetas (Stats) */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (min-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Grid Medio (Exámenes y Accesos Rápidos) */
.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (min-width: 1024px) {
    .content-grid {
        grid-template-columns: 2fr 1fr;
    }
}

/* Cajas de Contenido */
.content-box {
    background: #ffffff;
    border-radius: 0.75rem; /* rounded-xl (12px) */
    border: 1px solid #e5e7eb;
    padding: 1.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    min-width: 0;
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
    border-bottom: 1px solid #f3f4f6;
}

.box-title {
    font-size: 1.125rem; /* text-lg */
    font-weight: 700;
    color: var(--color-primary);
    margin: 0;
    font-family: var(--font-display);
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

/* Lista de Exámenes Próximos */
.exam-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.exam-card-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    background-color: #ffffff;
    transition: all 0.2s ease;
    gap: 1rem;
}

.exam-card-item:hover {
    border-color: var(--color-primary);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05);
}

.exam-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.exam-subject-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0;
}

.exam-meta-line {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0;
}

.exam-counts-line {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.25rem;
}

.count-pill {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-weight: 500;
}

.count-hab {
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.count-inhab {
    background-color: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.btn-exam-detail {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 0.375rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    color: #374151;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.btn-exam-detail:hover {
    background-color: #f9fafb;
    border-color: var(--color-primary);
    color: var(--color-primary);
}

/* Estado Vacío de Exámenes */
.empty-exams-placeholder {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    border: 2px dashed #e5e7eb;
    border-radius: 0.5rem;
    background-color: rgba(249, 250, 251, 0.5);
    text-align: center;
}

.empty-icon-circle {
    width: 4rem;
    height: 4rem;
    background-color: #ffffff;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    margin-bottom: 1rem;
}

.empty-icon-svg {
    color: #d1d5db;
}

.empty-primary-text {
    color: #6b7280;
    font-weight: 500;
    font-size: 0.875rem;
    margin: 0;
}

.empty-secondary-text {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.5rem;
    max-width: 24rem;
}

/* Accesos Rápidos */
.quick-access-stack {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    flex: 1;
}

.qa-card-link {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background-color: #ffffff;
    text-decoration: none;
    transition: all 0.2s ease;
}

.qa-card-link:hover {
    border-color: var(--color-primary);
    background-color: rgba(29, 54, 83, 0.04);
}

.qa-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.qa-title-text {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    transition: color 0.2s ease;
}

.qa-card-link:hover .qa-title-text {
    color: var(--color-primary);
}

.qa-badge-meta {
    font-size: 0.75rem;
    font-weight: 500;
    color: #9ca3af;
    transition: color 0.2s ease;
}

.qa-card-link:hover .qa-badge-meta {
    color: var(--color-primary);
}

.qa-desc-text {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

/* Grid Inferior: Widgets */
.extras-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .extras-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .extras-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.widget-box {
    padding: 1.5rem;
}

/* Enlaces Oficiales */
.link-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.official-link-card {
    display: block;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid var(--color-primary);
    background-color: #f9fafb;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
}

.official-link-card:hover {
    background-color: #f3f4f6;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05);
}

.official-link-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    transition: color 0.2s ease;
}

.official-link-card:hover .official-link-title {
    color: var(--color-primary);
}

.official-link-desc {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.125rem;
}

/* Noticias */
.news-list {
    display: flex;
    flex-direction: column;
}

.news-card-item {
    display: block;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f3f4f6;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.news-card-item:first-child {
    padding-top: 0;
}

.news-card-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.news-card-date {
    display: block;
    font-size: 0.625rem; /* text-[10px] */
    font-weight: 700;
    color: #A12B33;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.news-card-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.25rem;
    transition: color 0.15s ease;
}

.news-card-item:hover .news-card-title {
    color: var(--color-primary);
}

.news-card-summary {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.news-link-external {
    font-size: 0.75rem;
    font-weight: 600;
    color: #C5A059;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}

.news-link-external:hover {
    text-decoration: underline;
}

.news-fallback {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 0;
    text-align: center;
}

.news-fallback-icon {
    color: #d1d5db;
    margin-bottom: 0.5rem;
}

.news-fallback-text {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.news-fallback-link {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-primary);
    text-decoration: none;
}

.news-fallback-link:hover {
    text-decoration: underline;
}
</style>