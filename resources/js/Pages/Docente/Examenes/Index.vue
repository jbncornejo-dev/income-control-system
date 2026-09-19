<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    examenes: Object, 
    filters: Object
});

const getStatusClass = (estado) => {
    const status = estado ? estado.toLowerCase() : '';
    if (status === 'confirmado') return 'badge-confirmado';
    if (status === 'borrador') return 'badge-borrador';
    return 'badge-pendiente';
};
</script>

<template>
    <Head title="Gestión de Exámenes" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">GESTIÓN DE EXÁMENES</h1>

            <!-- Barra de Acciones -->
            <div class="action-bar">
                <input 
                    type="text" 
                    placeholder="Buscar por asignatura o docente..." 
                    class="search-input"
                >
                <div class="action-controls">
                    <select class="state-filter">
                        <option value="">Todos los estados</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="borrador">Borrador</option>
                    </select>
                    <!-- Botón solicitado (sin funcionalidad por ahora) -->
                    <button class="btn-primary">+ Nuevo examen</button>
                </div>
            </div>

            <!-- Tabla de Datos -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ASIGNATURA</th>
                            <th>GRUPOS</th>
                            <th>FECHA</th>
                            <th>HORA</th>
                            <th>AMBIENTES</th>
                            <th>ESTADO</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="examen in examenes.data" :key="examen.id_examen">
                            
                            <td class="col-asignatura">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</td>
                            
                            <!-- Grupos del docente en esa asignatura -->
                            <td>
                                <span v-if="examen.grupos && examen.grupos.length > 0" class="group-badges">
                                    <span v-for="grupo in examen.grupos" :key="grupo" class="badge badge-grupo">{{ grupo }}</span>
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            
                            <td class="col-fecha">{{ examen.fecha }}</td>
                            <td class="col-hora">{{ examen.hora_inicio }}</td>
                            
                            <td>
                                <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                    {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                </span>
                                <span v-else class="text-muted">Sin ambiente</span>
                            </td>
                            
                            <td>
                                <span :class="['badge', getStatusClass(examen.estado)]">
                                    {{ examen.estado || 'Pendiente' }}
                                </span>
                            </td>
                            
                            <td class="actions-cell">
                                <button class="btn-action">Ver</button>
                            </td>
                        </tr>
                        
                        <tr v-if="!examenes.data || examenes.data.length === 0">
                            <td colspan="7" class="empty-state">No hay exámenes registrados.</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pie de tabla -->
                <div class="table-footer">
                    <span>{{ examenes.to || 0 }} de {{ examenes.total || 0 }} exámenes</span>
                </div>
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

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.search-input {
    flex: 1;
    max-width: 500px;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.action-controls {
    display: flex;
    gap: 1rem;
}

.state-filter {
    padding: 0.5rem 2rem 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    background-color: white;
}

.btn-primary {
    background-color: #3b0707; /* Tono oscuro adaptado al panel docente */
    color: white;
    padding: 0.5rem 1.5rem;
    border: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    cursor: pointer;
}

.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
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
    color: #374151;
    vertical-align: middle;
}

.col-asignatura {
    color: #1e1b4b;
    font-weight: 600;
}

.text-muted {
    color: #9ca3af;
}

.group-badges {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.badge-grupo {
    background-color: #e0f2fe;
    color: #0369a1;
    border: 1px solid #7dd3fc;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.badge-confirmado {
    background-color: #f3e8ff;
    color: #6b21a8;
    border: 1px solid #d8b4fe;
}

.badge-pendiente {
    background-color: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
}

.badge-borrador {
    background-color: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde047;
}

.actions-cell {
    display: flex;
    justify-content: flex-end;
}

.btn-action {
    background: transparent;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    color: #374151;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-action:hover {
    background-color: #f9fafb;
}

.table-footer {
    padding: 1rem;
    background-color: #ffffff;
    border-top: 1px solid #e5e7eb;
    color: #9ca3af;
    font-size: 0.75rem;
}

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}
</style>