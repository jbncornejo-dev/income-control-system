<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Recibimos los datos enviados desde routes/web.php
const props = defineProps({
    stats: Object,
    proximosExamenes: Array
});

import ExamenesCalendar from '@/components/ui/ExamenesCalendar.vue';
import StatCard from '@/components/ui/StatCard.vue';

const enlaces = [
    { titulo: 'Portal UMSS', desc: 'Sitio oficial de la universidad', url: 'https://www.umss.edu.bo' },
    { titulo: 'FCyT', desc: 'Facultad de Ciencias y Tecnología', url: 'https://www.fcyt.umss.edu.bo' },
    { titulo: 'Websis', desc: 'Sistema académico institucional', url: 'https://websis.umss.edu.bo' },
];
const noticias = [
    { fecha: 'Sep 2026', titulo: 'Periodo 2/2026 activo', texto: 'Verifica que los ambientes y asignaturas estén registrados antes de programar exámenes.' },
    { fecha: 'Sep 2026', titulo: 'Carga masiva de estudiantes', texto: 'Puedes importar estudiantes desde un archivo CSV en la gestión de estudiantes.' },
    { fecha: 'Sep 2026', titulo: 'Control de accesos', texto: 'Revisa periódicamente los usuarios del sistema y sus roles asignados.' },
];
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Fila de Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <StatCard label="Estudiantes" :value="stats.estudiantes" desc="registrados" color="var(--color-primary)" />
                <StatCard label="Exámenes" :value="stats.examenes" desc="programados" color="var(--color-danger)" />
                <StatCard label="Asignaturas" :value="stats.asignaturas" desc="activas" color="var(--color-primary)" />
                <StatCard label="Ambientes" :value="stats.ambientes" desc="habilitados" color="var(--color-primary)" />
                <StatCard label="Usuarios" :value="stats.usuarios" desc="personal" color="var(--color-primary)" />
            </div>

            <!-- Sección Inferior: Tabla y Accesos Rápidos -->
            <div class="content-grid">
                
                <!-- Columna Izquierda: Tabla -->
                <div class="content-box">
                    <div class="box-header">
                        <h2>Próximos Exámenes</h2>
                        <Link href="/examenes" class="link-action">Ver todos &rarr;</Link>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ASIGNATURA</th>
                                <th>FECHA</th>
                                <th>HORA</th>
                                <th>AMBIENTE</th>
                                <th>ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Iteración sobre datos reales -->
                            <tr v-for="examen in proximosExamenes" :key="examen.id_examen">
                                <td>{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                                <td>{{ examen.fecha }}</td>
                                <td>{{ examen.hora_inicio }}</td>
                                <td>
                                    <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                        {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).filter(Boolean).join(', ') }}
                                    </span>
                                    <span v-else>N/D</span>
                                </td>
                                <td>
                                    <span class="status-badge">{{ examen.estado_actual || 'Pendiente' }}</span>
                                </td>
                            </tr>
                            
                            <!-- Mensaje si no hay registros -->
                            <tr v-if="!proximosExamenes || proximosExamenes.length === 0">
                                <td colspan="5" style="text-align: center; color: #6b7280;">No hay exámenes próximos</td>
                            </tr>
                        </tbody>
                    </table>
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
                            <span class="qa-meta">{{ stats.examenes }} programados</span>
                        </Link>
                        <Link href="/asignaturas" class="qa-item">
                            <span>Asignaturas</span>
                            <span class="qa-meta">{{ stats.asignaturas }} activas</span>
                        </Link>
                        <Link href="/ambientes" class="qa-item">
                            <span>Ambientes</span>
                            <span class="qa-meta">{{ stats.ambientes }} habilitados</span>
                        </Link>
                        <Link href="/usuarios" class="qa-item">
                            <span>Usuarios del sistema</span>
                            <span class="qa-meta">{{ stats.usuarios }} cuentas</span>
                        </Link>
                    </div>
                </div>

            </div>
            <div class="extras-grid">
                <div class="content-box">
                    <div class="box-header"><h2>Calendario de exámenes</h2></div>
                    <ExamenesCalendar :examenes="proximosExamenes" />
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
<style scoped>
/* Contenedor principal */
.panel-container {
    /* El padding y min-height globales ahora los maneja AuthenticatedLayout */
    font-family: var(--font-family);
    background-color: transparent;
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--spacing-lg);
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

/* Cuadrícula de Tarjetas Superiores */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

/* Cuadrícula Inferior (Tabla + Accesos Rápidos) */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--spacing-lg);
}

/* Cajas de Contenido (Paneles blancos) */
.content-box {
    background: var(--color-white);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-card);
    overflow-x: auto; /* Para tablas en resoluciones menores */
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
    font-size: 1.25rem;
    font-weight: 600;
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


.status-badge {
    background-color: var(--color-white-soft);
    color: var(--color-primary);
    padding: 4px 12px;
    border-radius: var(--radius-pill);
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid var(--color-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Estilos de Accesos Rápidos */
.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.qa-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
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
    background-color: var(--color-white-soft);
    border-color: var(--color-primary);
    transform: translateX(4px);
    box-shadow: var(--shadow-subtle);
}

.qa-meta {
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 400;
}

/* Bloque Inferior */
.extras-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
    gap: var(--spacing-lg); 
    margin-top: var(--spacing-xl); 
}

/* Listas Informativas */
.link-list, .news-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.link-item { 
    display: flex;
    flex-direction: column;
    padding: var(--spacing-md);
    background: var(--color-white-soft);
    border-left: 4px solid var(--color-primary); 
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-dark);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.link-item:hover { 
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle); 
}
.link-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 2px; }
.link-desc { font-size: 0.85rem; color: var(--text-muted); }

.news-item { 
    display: flex;
    flex-direction: column;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--border-light);
    transition: background var(--transition-fast); 
}
.news-item:last-child { border-bottom: none; padding-bottom: 0; }
.news-item:first-child { padding-top: 0; }
.news-item:hover { background: rgba(0,0,0,0.02); }
.news-fecha { font-size: 0.75rem; color: var(--color-active); font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.news-titulo { font-weight: 600; font-size: 1rem; color: var(--color-primary); margin-bottom: 4px; }
.news-texto { font-size: 0.9rem; color: var(--text-muted); margin: 0; line-height: 1.5; }

/* Responsive Dashboard */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 992px) {
    .content-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .stats-grid { grid-template-columns: 1fr; }
    .content-box { padding: var(--spacing-md); }
}
</style>