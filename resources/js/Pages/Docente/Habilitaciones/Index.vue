<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';

const props = defineProps({
    examen: Object,
    habilitaciones: Object,
    stats: Object
});

// Función para formatear el nombre completo
const getNombreCompleto = (estudiante) => {
    if (!estudiante) return 'N/D';
    return `${estudiante.nombres || ''} ${estudiante.apellidos || ''}`.trim();
};
const showModal = ref(false);
const selectedStudent = ref(null);

// Abre el modal. Si recibe un estudiante, entra en "modo edición". Si no, "modo creación".
const openModal = (estudiante = null) => {
    selectedStudent.value = estudiante;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedStudent.value = null;
};
</script>

<template>
    <Head title="Detalle de Examen" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <!-- Enlace para volver atrás -->
            <div class="back-link-container">
                <Link href="/examenes" class="back-link">&larr; Volver a Exámenes</Link>
            </div>

            <!-- Tarjeta Principal del Examen -->
            <div class="exam-header-card">
                <div class="header-top">
                    <div>
                        <span class="eyebrow">EXAMEN PROGRAMADO</span>
                        <h1 class="exam-title">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</h1>
                    </div>
                    <!-- Nota: El estado dependerá de tu BD, uso un valor estático temporal -->
                    <span class="badge badge-confirmado">Confirmado</span>
                </div>
                
                <div class="exam-meta-row">
                    <span><strong>Docente:</strong> {{ examen.docente?.name || 'N/D' }}</span>
                    <span><strong>Fecha:</strong> <span class="mono">{{ examen.fecha }}</span></span>
                    <span><strong>Hora:</strong> <span class="mono">{{ examen.hora_inicio }}</span></span>
                    <span>
                        <strong>Ambientes:</strong> 
                        <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                            {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                        </span>
                        <span v-else>N/D</span>
                    </span>
                </div>

                <!-- Tarjetas de Estadísticas Internas -->
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-number text-blue">{{ stats.habilitados }}</span>
                        <span class="stat-label">Habilitados</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number text-red">{{ stats.inhabilitados }}</span>
                        <span class="stat-label">Inhabilitados</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number text-gray">{{ stats.ingresaron }}</span>
                        <span class="stat-label">Ingresaron</span>
                    </div>
                </div>
            </div>

            <!-- Barra de Búsqueda y Filtros -->
            <div class="action-bar">
                <div style="display: flex; gap: 1rem; flex: 1;">
                    <input type="text" placeholder="Buscar estudiante..." class="search-input">
                    <div class="filter-group">
                        <button class="filter-btn active">Todos</button>
                        <button class="filter-btn">Habilitados</button>
                        <button class="filter-btn">Inhabilitados</button>
                    </div>
                </div>
                <!-- NUEVO BOTÓN PARA ABRIR MODAL -->
                <button @click="openModal()" class="btn-primary">+ Añadir Estudiante</button>
            </div>

            <!-- Tabla de Estudiantes -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>CÓDIGO</th>
                            <th>NOMBRE</th>
                            <th>ESTADO</th>
                            <th>INGRESO</th>
                            <th>HABILITACIÓN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="hab in habilitaciones.data" :key="hab.id_habilitacion">
                            <!-- Código del estudiante -->
                            <td class="col-mono text-purple">{{ hab.estudiante?.codigo_universitario || 'N/D' }}</td>
                            
                            <!-- Nombre y motivo (si está inhabilitado) -->
                            <td>
                                <div class="student-name">{{ getNombreCompleto(hab.estudiante) }}</div>
                                <div v-if="!hab.estado_habilitado && hab.motivo_inhabilitacion" class="student-reason text-red">
                                    {{ hab.motivo_inhabilitacion }}
                                </div>
                            </td>
                            
                            <!-- Estado (Badge) -->
                            <td>
                                <span :class="['badge', hab.estado_habilitado ? 'badge-hab' : 'badge-inhab']">
                                    {{ hab.estado_habilitado ? 'Habilitado' : 'Inhabilitado' }}
                                </span>
                            </td>
                            
                            <!-- Ingreso -->
                            <td class="col-mono text-purple">
                                <!-- Pendiente de lógica de registro_ingreso -->
                                &mdash;
                            </td>
                            
                            <!-- Botón de Acción -->
                            <td>
                                <!-- Aquí usaríamos un Link con method="patch" para actualizar el estado -->
                                <button :class="['btn-toggle', hab.estado_habilitado ? 'btn-toggle-red' : 'btn-toggle-gray']">
                                    {{ hab.estado_habilitado ? 'Inhabilitar' : 'Habilitar' }}
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!habilitaciones.data || habilitaciones.data.length === 0">
                            <td colspan="5" class="empty-state">No hay estudiantes registrados en este examen.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- MODAL DE ESTUDIANTE -->
            <Modal :show="showModal" @close="closeModal">
                <div class="modal-container">
                    <div class="modal-header">
                        <h2>{{ selectedStudent ? 'Editar Estudiante' : 'Añadir Estudiante al Examen' }}</h2>
                        <button @click="closeModal" class="btn-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- El componente de Iomara recibe el estudiante (si existe) para rellenar los campos -->
                        <EstudianteForm 
                            :estudiante="selectedStudent" 
                            @submitted="closeModal" 
                            @canceled="closeModal"
                        />
                    </div>
                </div>
            </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}

.back-link-container {
    margin-bottom: 1rem;
}

.back-link {
    color: #4f46e5;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
}

/* Tarjeta Cabecera */
.exam-header-card {
    background: #ffffff;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    border-top: 4px solid #4f46e5;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.eyebrow {
    font-size: 0.75rem;
    color: #9ca3af;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.exam-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0.25rem 0 0 0;
    font-family: Georgia, serif;
}

.exam-meta-row {
    display: flex;
    gap: 1.5rem;
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.exam-meta-row strong {
    color: #374151;
}

.mono {
    font-family: monospace;
}

/* Cajas de estadísticas */
.stats-row {
    display: flex;
    gap: 1rem;
}

.stat-box {
    flex: 1;
    background-color: #f9fafb;
    border-radius: 0.375rem;
    padding: 1rem;
    text-align: center;
    border: 1px solid #f3f4f6;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    font-family: Georgia, serif;
}

.stat-label {
    font-size: 0.75rem;
    color: #9ca3af;
}

.text-blue { color: #1e3a8a; }
.text-red { color: #b91c1c; }
.text-gray { color: #4b5563; }
.text-purple { color: #4f46e5; }

/* Buscador y Filtros */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
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

.filter-group {
    display: flex;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    overflow: hidden;
}

.filter-btn {
    background: white;
    border: none;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    color: #374151;
    cursor: pointer;
    border-right: 1px solid #d1d5db;
}

.filter-btn:last-child {
    border-right: none;
}

.filter-btn.active {
    background: #1e1b4b;
    color: white;
}

/* Tabla */
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
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.col-mono {
    font-family: monospace;
    font-size: 0.8rem;
}

.student-name {
    font-weight: 500;
    color: #1f2937;
}

.student-reason {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

/* Badges y Botones */
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.badge-hab { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-inhab { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-confirmado { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }

.btn-toggle {
    background: transparent;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    cursor: pointer;
    font-weight: 500;
}

.btn-toggle-red { color: #ef4444; border-color: #fca5a5; }
.btn-toggle-gray { color: #6b7280; }

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}

/* Estilos para el nuevo botón */
.btn-primary {
    background-color: #1e1b4b; /* Azul oscuro corporativo */
    color: white;
    padding: 0.5rem 1.5rem;
    border: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary:hover {
    background-color: #312e81;
}

/* Estilos internos del Modal */
.modal-container {
    padding: 1.5rem;
    background-color: #ffffff;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #9ca3af;
    cursor: pointer;
    line-height: 1;
}

.btn-close:hover {
    color: #4b5563;
}
</style>