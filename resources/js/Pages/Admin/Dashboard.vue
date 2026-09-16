<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Recibimos los datos enviados desde routes/web.php
const props = defineProps({
    stats: Object,
    proximosExamenes: Array
});
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Fila de Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top-color: #3b82f6;">
                    <span class="stat-label">Estudiantes</span>
                    <span class="stat-value">{{ stats.estudiantes }}</span>
                    <span class="stat-desc">registrados</span>
                </div>
                <div class="stat-card" style="border-top-color: #ef4444;">
                    <span class="stat-label">Exámenes</span>
                    <span class="stat-value">{{ stats.examenes }}</span>
                    <span class="stat-desc">programados</span>
                </div>
                <div class="stat-card" style="border-top-color: #9ca3af;">
                    <span class="stat-label">Asignaturas</span>
                    <span class="stat-value">{{ stats.asignaturas }}</span>
                    <span class="stat-desc">activas</span>
                </div>
                <div class="stat-card" style="border-top-color: #a855f7;">
                    <span class="stat-label">Ambientes</span>
                    <span class="stat-value">{{ stats.ambientes }}</span>
                    <span class="stat-desc">habilitados</span>
                </div>
                <div class="stat-card" style="border-top-color: #b45309;">
                    <span class="stat-label">Usuarios</span>
                    <span class="stat-value">{{ stats.usuarios }}</span>
                    <span class="stat-desc">personal</span>
                </div>
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
                            <tr v-for="examen in proximosExamenes" :key="examen.id">
                                <!-- Ajusta 'nombre' según las propiedades reales de tus modelos -->
                                <td>{{ examen.asignatura?.nombre || 'N/D' }}</td>
                                <td>{{ examen.fecha }}</td>
                                <td>{{ examen.hora }}</td>
                                <td>
                                    <!-- Si tienes múltiples ambientes por examen -->
                                    <span v-if="examen.ambientes && examen.ambientes.length > 0">
                                        {{ examen.ambientes.map(a => a.nombre).join(', ') }}
                                    </span>
                                    <span v-else>{{ examen.ambiente?.nombre || 'N/D' }}</span>
                                </td>
                                <td>
                                    <span class="status-badge">{{ examen.estado || 'Pendiente' }}</span>
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
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal */
.panel-container {
    padding: 2rem;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: #f3f4f6;
    min-height: 100vh;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
}

/* Cuadrícula de Tarjetas Superiores */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #ffffff;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-top: 4px solid #ccc;
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin: 0.5rem 0;
}

.stat-desc {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Cuadrícula Inferior (Tabla + Accesos Rápidos) */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

/* Cajas de Contenido (Paneles blancos) */
.content-box {
    background: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 1.5rem;
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
    color: #3b82f6;
    font-size: 0.875rem;
    text-decoration: none;
}

/* Estilos de Tabla */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    text-align: left;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.data-table td {
    padding: 1rem 0;
    color: #4b5563;
    border-bottom: 1px solid #f3f4f6;
}

.status-badge {
    background-color: #eff6ff;
    color: #2563eb;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #bfdbfe;
}

/* Estilos de Accesos Rápidos */
.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.qa-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    text-decoration: none;
    color: #374151;
    font-size: 0.875rem;
    transition: background-color 0.2s;
}

.qa-item:hover {
    background-color: #f9fafb;
}

.qa-meta {
    color: #9ca3af;
    font-size: 0.75rem;
}
</style>