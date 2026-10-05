<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    examenes: Array
});

import StatCard from '@/components/ui/StatCard.vue';

// Mapa de estado_actual (del backend) a etiqueta visible
const ESTADOS = {
    programado:  { etiqueta: 'Programado',  clase: 'badge-programado' },
    en_curso:    { etiqueta: 'En curso',     clase: 'badge-en-curso' },
    finalizado:  { etiqueta: 'Finalizado',   clase: 'badge-finalizado' },
    suspendido:  { etiqueta: 'Suspendido',   clase: 'badge-suspendido' },
    cancelado:   { etiqueta: 'Cancelado',    clase: 'badge-cancelado' },
    anulado:     { etiqueta: 'Anulado',      clase: 'badge-anulado' },
};

function estadoEtiqueta(estado) {
    return ESTADOS[estado]?.etiqueta ?? (estado ?? 'Sin estado');
}

function estadoBadgeClass(estado) {
    return ESTADOS[estado]?.clase ?? 'badge-pendiente';
}
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL DE CONTROL</h1>

            <!-- CTA Registrar Ingreso -->
            <Link href="/registro-ingreso" class="cta-banner">
                <div class="cta-icon">
                    <span>&crarr;</span>
                </div>
                <div class="cta-text">
                    <h2>Registrar Ingreso de Estudiante</h2>
                    <p>Acceder a la terminal de control y verificación de estudiantes.</p>
                </div>
            </Link>

            <Link href="/incidencias" class="acceso-incidencias">
                <div class="acceso-texto">
                    <span class="acceso-titulo">Reportar incidencia</span>
                    <span class="acceso-desc">Expulsiones, problemas de identificación u otras situaciones</span>
                </div>
                <span class="acceso-flecha">&rarr;</span>
            </Link>

            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <StatCard label="Exámenes hoy" :value="stats.hoy" desc="programados" color="var(--color-primary)" />
                <StatCard label="En curso ahora" :value="stats.en_curso" desc="activos" color="var(--color-success)" />
                <StatCard label="Ingresos" :value="stats.ingresos" desc="registrados" color="var(--color-active)" />
            </div>

            <!-- Tabla de Exámenes del Día -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Exámenes Activos y Próximos</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ASIGNATURA</th>
                            <th>HORARIO</th>
                            <th>AMBIENTES</th>
                            <th>ESTADO</th>
                            <th>REGISTRADOS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="examen in examenes" :key="examen.id_examen">
                            <td class="font-bold">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            <td class="mono text-gray">{{ examen.hora_inicio }}</td>
                            <td class="text-gray">
                                <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                    {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                </span>
                                <span v-else>N/D</span>
                            </td>
                            <td>
                                <span :class="['badge', estadoBadgeClass(examen.estado_actual)]">
                                    {{ estadoEtiqueta(examen.estado_actual) }}
                                </span>
                            </td>
                            <td class="mono text-gray">--/--</td>
                        </tr>
                        <tr v-if="!examenes || examenes.length === 0">
                            <td colspan="5" class="empty-state">No hay exámenes programados para hoy.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

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

/* Banner CTA */
.cta-banner {
    display: flex;
    align-items: center;
    background-color: var(--color-primary);
    color: var(--color-white);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    text-decoration: none;
    margin-bottom: var(--spacing-lg);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    box-shadow: var(--shadow-card);
}

.cta-banner:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-elevated);
}

.cta-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 3rem;
    height: 3rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    font-size: 1.5rem;
    margin-right: var(--spacing-md);
    flex-shrink: 0;
}

.cta-text h2 {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.cta-text p {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
}

/* Acceso Incidencias */
.acceso-incidencias { 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    gap: var(--spacing-md); 
    background: var(--color-white); 
    border: 1px solid var(--border-light); 
    border-left: 4px solid var(--color-active); 
    border-radius: var(--radius-md); 
    padding: var(--spacing-md) var(--spacing-lg); 
    text-decoration: none; 
    margin-bottom: var(--spacing-xl); 
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}
.acceso-incidencias:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}
.acceso-texto { display: flex; flex-direction: column; }
.acceso-titulo { font-weight: 600; color: var(--color-primary); font-size: 1rem; margin-bottom: 2px; }
.acceso-desc { font-size: 0.85rem; color: var(--text-muted); }
.acceso-flecha { color: var(--color-active); font-size: 1.25rem; }

/* Estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

/* Tabla */
.table-container {
    background: var(--color-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    overflow-x: auto;
    box-shadow: var(--shadow-card);
}

.table-header {
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: 1px solid var(--border-light);
}

.table-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--color-primary);
    font-family: var(--font-display);
}

/* Elementos de tabla locales */
.font-bold { font-weight: 600; color: var(--text-dark); }
.text-gray { color: var(--text-muted); }
.mono { font-family: var(--font-mono); font-size: 0.85rem; }

.badge {
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Badges de estado alineados con los valores reales de estado_actual del backend */
.badge-programado  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-en-curso    { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.badge-finalizado  { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.badge-suspendido  { background: #fefce8; color: #a16207; border: 1px solid #fde047; }
.badge-cancelado   { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
.badge-anulado     { background: #fdf4ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.badge-pendiente   { background: #f9fafb; color: #6b7280; border: 1px solid #e5e7eb; }

/* Responsive Dashboard */
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); gap: var(--spacing-md); }
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(1, 1fr); }
    .cta-banner { flex-direction: column; align-items: flex-start; gap: var(--spacing-md); }
    .cta-icon { margin-right: 0; }
    .acceso-incidencias { flex-direction: column; align-items: flex-start; }
}
</style>