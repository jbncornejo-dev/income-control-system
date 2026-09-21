<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    examenes: Array
});
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL DE CONTROL</h1>

            <!-- Call to Action Principal -->
            <Link href="/registro-ingreso" class="cta-banner">
                <div class="cta-icon">
                    <span>&crarr;</span>
                </div>
                <div class="cta-text">
                    <h2>Registrar Ingreso de Estudiante</h2>
                    <p>Verificar habilitación y confirmar entrada al examen</p>
                </div>
            </Link>

            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top-color: var(--color-primary);">
                    <span class="stat-label">Exámenes hoy</span>
                    <span class="stat-value">{{ stats.hoy }}</span>
                </div>
                <div class="stat-card" style="border-top-color: var(--color-primary);">
                    <span class="stat-label">En curso ahora</span>
                    <span class="stat-value">{{ stats.en_curso }}</span>
                </div>
                <div class="stat-card" style="border-top-color: var(--color-danger);">
                    <span class="stat-label">Ingresos registrados</span>
                    <span class="stat-value">{{ stats.ingresos }}</span>
                </div>
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
                                <!-- Estado temporal estático para el frontend -->
                                <span class="badge badge-curso">En curso</span>
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

/* Banner CTA */
.cta-banner {
    display: flex;
    align-items: center;
    background-color: var(--color-primary);
    color: var(--text-white, #ffffff);
    padding: 2rem;
    border-radius: 0.5rem;
    text-decoration: none;
    margin-bottom: 2rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.cta-banner:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
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
    margin-right: 1.5rem;
}

.cta-text h2 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.cta-text p {
    margin: 0;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.8);
}

/* Estadísticas */
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
    display: flex;
    flex-direction: column;
    border-top: 4px solid var(--color-primary);
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
    margin-top: 0.25rem;
}

/* Tabla */
.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow-x: auto;
}

.table-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    text-transform: uppercase;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.font-bold { font-weight: 600; color: #1f2937; }
.text-gray { color: #6b7280; }
.mono { font-family: monospace; font-size: 0.8rem; }

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.badge-curso {
    background-color: #f9fafb;
    color: var(--color-primary);
    border: 1px solid var(--color-primary);
}

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}
</style>