<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Users, FileText, BookOpen, Building2, UserCog, Calendar, ExternalLink, Globe } from 'lucide-vue-next';

// Recibimos los datos enviados desde routes/web.php
const props = defineProps({
    stats: Object,
    proximosExamenes: Array,
    noticias: Array,
    calendarioData: Object,
    examenesCalendario: Array
});

import ExamenesCalendar from '@/components/ui/ExamenesCalendar.vue';
import StatCard from '@/components/ui/StatCard.vue';

const enlaces = [
    { titulo: 'Portal UMSS', desc: 'Sitio oficial de la universidad', url: 'https://www.umss.edu.bo', color: 'var(--color-primary)' },
    { titulo: 'FCyT', desc: 'Facultad de Ciencias y Tecnología', url: 'https://www.fcyt.umss.edu.bo', color: 'var(--color-gold, #C5A059)' },
    { titulo: 'Websis', desc: 'Sistema académico institucional', url: 'https://websis.umss.edu.bo', color: 'var(--color-danger, #A12B33)' },
];

const noticiasRender = computed(() => {
    return props.noticias && props.noticias.length > 0 
        ? props.noticias 
        : null;
});
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Fila de Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <StatCard label="Estudiantes" :value="stats.estudiantes" desc="registrados" color="var(--color-primary)">
                    <template #icon><Users :size="24" /></template>
                </StatCard>
                <StatCard label="Exámenes" :value="stats.examenes" desc="programados" color="var(--color-danger, #A12B33)">
                    <template #icon><FileText :size="24" /></template>
                </StatCard>
                <StatCard label="Asignaturas" :value="stats.asignaturas" desc="activas" color="var(--color-primary)">
                    <template #icon><BookOpen :size="24" /></template>
                </StatCard>
                <StatCard label="Ambientes" :value="stats.ambientes" desc="habilitados" color="var(--color-primary)">
                    <template #icon><Building2 :size="24" /></template>
                </StatCard>
                <StatCard label="Usuarios" :value="stats.usuarios" desc="personal" color="var(--color-gold, #C5A059)">
                    <template #icon><UserCog :size="24" /></template>
                </StatCard>
            </div>

            <!-- Sección Media: Tabla y Accesos Rápidos -->
            <div class="content-grid">
                
                <!-- Columna Izquierda: Tabla -->
                <div class="content-box">
                    <div class="box-header">
                        <h2>Próximos Exámenes</h2>
                        <Link href="/examenes" class="link-action">Ver todos &rarr;</Link>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ASIGNATURA</th>
                                    <th>FECHA</th>
                                    <th>HORA</th>
                                    <th>AMBIENTE</th>
                                    <th style="text-align: right">ESTADO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Iteración sobre datos reales -->
                                <tr v-for="examen in proximosExamenes" :key="examen.id_examen">
                                    <td class="td-strong">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                                    <td>{{ examen.fecha }}</td>
                                    <td>{{ String(examen.hora_inicio).slice(0, 5) }}</td>
                                    <td>
                                        <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                            {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).filter(Boolean).join(', ') }}
                                        </span>
                                        <span v-else>N/D</span>
                                    </td>
                                    <td style="text-align: right">
                                        <span class="status-badge" :class="examen.estado_actual">{{ examen.estado_actual ? examen.estado_actual.replace('_', ' ').toUpperCase() : 'PROGRAMADO' }}</span>
                                    </td>
                                </tr>
                                
                                <!-- Mensaje si no hay registros -->
                                <tr v-if="!proximosExamenes || proximosExamenes.length === 0">
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No hay exámenes próximos</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Columna Derecha: Accesos Rápidos -->
                <div class="content-box">
                    <div class="box-header">
                        <h2>Accesos Rápidos</h2>
                    </div>
                    <div class="quick-access-list">
                        <Link href="/estudiantes" class="qa-item">
                            <span>Gestión de Estudiantes</span>
                            <span class="qa-meta">{{ stats.estudiantes }} registros</span>
                        </Link>
                        <Link href="/examenes" class="qa-item">
                            <span>Gestión de Exámenes</span>
                            <span class="qa-meta">{{ stats.examenes }} prog.</span>
                        </Link>
                        <Link href="/asignaturas" class="qa-item">
                            <span>Asignaturas</span>
                            <span class="qa-meta">{{ stats.asignaturas }} activas</span>
                        </Link>
                        <Link href="/ambientes" class="qa-item">
                            <span>Ambientes</span>
                            <span class="qa-meta">{{ stats.ambientes }} hab.</span>
                        </Link>
                        <Link href="/usuarios" class="qa-item">
                            <span>Usuarios del sistema</span>
                            <span class="qa-meta">{{ stats.usuarios }} cuentas</span>
                        </Link>
                    </div>
                </div>

            </div>

            <!-- Grid Inferior: Widgets -->
            <div class="extras-grid">
                <!-- Widget Calendario -->
                <div class="content-box widget-box">
                    <div class="box-header"><h2>Calendario</h2></div>
                    <ExamenesCalendar :examenes="examenesCalendario" :calendario-data="calendarioData" />
                </div>

                <!-- Widget Enlaces -->
                <div class="content-box widget-box">
                    <div class="box-header"><h2>Enlaces Oficiales</h2></div>
                    <div class="link-list">
                        <a v-for="l in enlaces" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="link-item" :style="{ borderLeftColor: l.color }">
                            <span class="link-title">{{ l.titulo }}</span>
                            <span class="link-desc">{{ l.desc }}</span>
                        </a>
                    </div>
                </div>

                <!-- Widget Noticias UMSS -->
                <div class="content-box widget-box">
                    <div class="box-header">
                        <h2>Noticias del Sistema</h2>
                        <a href="https://www.umss.edu.bo/categoria/noticias/" target="_blank" class="news-link-external">UMSS <ExternalLink :size="12" /></a>
                    </div>
                    
                    <div v-if="noticiasRender" class="news-list">
                        <a v-for="(n, i) in noticiasRender" :key="i" :href="n.enlace" target="_blank" rel="noopener" class="news-item">
                            <span class="news-fecha">{{ n.fecha }}</span>
                            <span class="news-titulo">{{ n.titulo }}</span>
                            <p class="news-texto">{{ n.resumen }}</p>
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
<style scoped>
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
    padding: var(--spacing-md);
    min-width: 0;
}

@media (min-width: 640px) {
    .panel-container { padding: var(--spacing-lg); }
}
@media (min-width: 1024px) {
    .panel-container { padding: var(--spacing-xl); }
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--spacing-xl);
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 640px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(5, 1fr); }
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

@media (min-width: 1024px) {
    .content-grid { grid-template-columns: 2fr 1fr; }
}

.content-box {
    background: var(--color-white);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    min-width: 0;
}


.box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-sm);
    border-bottom: 1px solid var(--border-light);
}

.box-header h2 {
    font-size: 1.125rem;
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
.link-action:hover { text-decoration: underline; }

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}
.data-table th {
    padding: 0.75rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--bg-main);
}
.data-table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--text-dark);
    border-bottom: 1px solid var(--border-light);
}
.data-table tr:hover td { background: var(--color-white-soft); }
.td-strong { font-weight: 600; color: #111827; }

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid transparent;
}
.status-badge.programado { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.status-badge.finalizado { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.status-badge.en_curso { background: #fffbeb; color: #b45309; border-color: #fde68a; }

.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    flex: 1;
}
.qa-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-dark);
    font-size: 0.95rem;
    font-weight: 500;
    background: var(--color-white);
    transition: all var(--transition-fast);
}
.qa-item:hover {
    background-color: var(--bg-main);
    border-color: var(--color-primary);
    color: var(--color-primary);
}
.qa-meta {
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 400;
    transition: color var(--transition-fast);
}
.qa-item:hover .qa-meta { color: var(--color-primary); }

.extras-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-lg);
}
@media (min-width: 768px) {
    .extras-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .extras-grid { grid-template-columns: repeat(3, 1fr); }
}

.link-list, .news-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.link-item {
    display: block;
    padding: 0.75rem;
    background: var(--bg-main);
    border-left: 4px solid var(--color-primary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-dark);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.link-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
    background: var(--color-white);
}
.link-title { display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 2px; }
.link-desc { display: block; font-size: 0.75rem; color: var(--text-muted); }

.news-list {
    display: flex;
    flex-direction: column;
}

.news-item {
    display: block;
    padding: var(--spacing-md) 0;
    text-decoration: none;
    border-bottom: 1px solid var(--border-light);
    transition: background var(--transition-fast);
}
.news-item:first-child { padding-top: 0; }
.news-item:last-child { border-bottom: none; }
.news-item:hover { background: rgba(0,0,0,0.02); }

.news-fecha { 
    display: block; 
    font-size: 0.75rem; 
    color: var(--color-active); 
    font-weight: 700; 
    margin-bottom: 4px; 
    text-transform: uppercase; 
    letter-spacing: 0.05em; 
}
.news-titulo { 
    display: block; 
    font-weight: 600; 
    font-size: 0.875rem; 
    color: var(--text-dark); 
    margin-bottom: 4px; 
}
.news-texto { 
    font-size: 0.8rem; 
    color: var(--text-muted); 
    margin: 0; 
    line-height: 1.5; 
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
.news-link-external:hover { text-decoration: underline; }

.news-fallback {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-xl) 0;
    text-align: center;
}
.news-fallback-icon { color: #d1d5db; margin-bottom: var(--spacing-sm); }
.news-fallback-text { font-size: 0.875rem; color: var(--text-muted); margin-bottom: var(--spacing-sm); }
.news-fallback-link { font-size: 0.75rem; font-weight: 600; color: var(--color-primary); text-decoration: none; }
.news-fallback-link:hover { text-decoration: underline; }
</style>