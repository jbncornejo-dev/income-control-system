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
                <div class="stat-card border-gray">
                    <span class="stat-label">Exámenes hoy</span>
                    <span class="stat-value">{{ stats.hoy }}</span>
                </div>
                <div class="stat-card border-blue">
                    <span class="stat-label">En curso ahora</span>
                    <span class="stat-value">{{ stats.en_curso }}</span>
                </div>
                <div class="stat-card border-red">
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
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
    font-family: Georgia, serif;
}

/* Banner CTA */
.cta-banner {
    display: flex;
    align-items: center;
    background-color: #1e1b4b; /* Morado oscuro del mockup */
    color: white;
    padding: 2rem;
    border-radius: 0.5rem;
    text-decoration: none;
    margin-bottom: 2rem;
    transition: transform 0.2s, box-shadow 0.2s;
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
    color: #d1d5db;
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
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    border-top: 3px solid transparent;
}

.border-gray { border-top-color: #6b7280; }
.border-blue { border-top-color: #1e1b4b; }
.border-red { border-top-color: #b91c1c; }

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.stat-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: #111827;
    margin-top: 0.25rem;
    font-family: Georgia, serif;
}

/* Tabla */
.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
}

.table-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    text-transform: uppercase;
}

.data-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.font-bold { font-weight: 600; color: #1f2937; }
.text-gray { color: #6b7280; }
.mono { font-family: monospace; }

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.badge-curso {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}
</style>