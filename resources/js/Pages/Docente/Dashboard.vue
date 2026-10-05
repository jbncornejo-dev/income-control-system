<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link,router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    stats: Object,
    proximosExamenes: Array
});

import ExamenesCalendar from '@/components/ui/ExamenesCalendar.vue';
import StatCard from '@/components/ui/StatCard.vue';

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
                <StatCard label="Mis exámenes" :value="stats.examenes" desc="próximos" color="var(--color-primary)" />
                <StatCard label="Habilitados" :value="stats.habilitados" desc="estudiantes" color="var(--color-success)" />
                <StatCard label="Inhabilitados" :value="stats.inhabilitados" desc="requieren revisión" color="var(--color-warning)" />
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
                            <Button variant="action" @click="router.visit(`/examenes/${examen.id_examen}/habilitaciones`)">
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

/* Tarjetas de Estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

/* Cuadrícula Inferior */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--spacing-lg);
}

.content-box {
    background: var(--color-white);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-card);
}

.h-fit {
    height: fit-content;
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

/* Lista de Exámenes */
.exam-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.exam-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--color-white-soft);
    border-left: 4px solid var(--color-primary);
    border-radius: var(--radius-sm);
    padding: var(--spacing-md) var(--spacing-lg);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.exam-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.exam-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0 0 4px 0;
}

.exam-meta {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.exam-counts {
    font-size: 0.9rem;
    margin: 0;
}

.count-hab { color: var(--color-success); margin-right: 1rem; }
.count-inhab { color: var(--color-warning); }

/* Accesos Rápidos */
.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.qa-item {
    display: flex;
    padding: var(--spacing-md);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-dark);
    background: var(--color-white);
    transition: all var(--transition-fast);
}

.qa-item:hover {
    background-color: var(--color-white-soft);
    border-color: var(--color-primary);
    transform: translateX(4px);
    box-shadow: var(--shadow-subtle);
}

.qa-content {
    display: flex;
    flex-direction: column;
}

.qa-title {
    font-weight: 600;
    font-size: 0.95rem;
}

.qa-desc {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-top: 4px;
}

.empty-state {
    color: var(--text-muted);
    font-size: 0.875rem;
    text-align: center;
    padding: var(--spacing-xl);
    font-style: italic;
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
@media (max-width: 992px) {
    .content-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(1, 1fr); }
    .exam-item { flex-direction: column; align-items: flex-start; gap: var(--spacing-md); }
    .content-box { padding: var(--spacing-md); }
}
</style>