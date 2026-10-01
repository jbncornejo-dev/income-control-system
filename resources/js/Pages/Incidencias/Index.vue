<script setup>
import { ref, computed, watch } from 'vue';
import { router, Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import Pagination from '@/components/Pagination.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    incidencias: Object,
    examenes: Array,
    estudiantes: Array,
    tipos: Array,
    filters: Object,
});

const toast = useToastStore();
const page = usePage();
const userRole = computed(() => page.props.auth.user?.rol || 'CONTROL');

// ==========================================
// ESTADO DE FILTROS
// ==========================================
const filterExamen = ref(props.filters?.id_examen || '');
const filterTipo = ref(props.filters?.tipo_incidencia || '');
const filterDesde = ref(props.filters?.desde || '');
const filterHasta = ref(props.filters?.hasta || '');

const applyFilters = () => {
    router.get('/incidencias', {
        id_examen: filterExamen.value || undefined,
        tipo_incidencia: filterTipo.value || undefined,
        desde: filterDesde.value || undefined,
        hasta: filterHasta.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

// ==========================================
// MAPEOS PARA SELECTS
// ==========================================
const examenesOptions = computed(() => props.examenes.map(e => ({
    value: e.id_examen,
    label: `${e.asignatura?.nombre_asignatura || 'Sin asignatura'} – ${e.fecha}`
})));

const tiposOptions = computed(() => props.tipos.map(t => ({
    value: t, label: t
})));

// ==========================================
// ESTADO Y LÓGICA DEL FORMULARIO (MODAL)
// ==========================================
const isModalOpen = ref(false);
const busquedaEstudiante = ref('');

const form = useForm({
    id_examen: '',
    tipo_incidencia: '',
    id_estudiante: '',
    descripcion_motivo: ''
});

// Buscador/autocompletado sencillo en el cliente para estudiantes
const estudiantesFiltrados = computed(() => {
    const q = busquedaEstudiante.value.trim().toLowerCase();
    if (!q) return props.estudiantes.slice(0, 30);
    return props.estudiantes.filter((e) =>
        `${e.nombres} ${e.apellidos}`.toLowerCase().includes(q) ||
        String(e.codigo_universitario).toLowerCase().includes(q) ||
        String(e.documento_identidad).toLowerCase().includes(q)
    ).slice(0, 30);
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    busquedaEstudiante.value = '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const guardarIncidencia = () => {
    form.post('/incidencias', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Incidencia registrada correctamente.');
            closeModal();
        },
        onError: () => {
            toast.error('Error al registrar la incidencia.');
        }
    });
};

// ==========================================
// UTILIDADES
// ==========================================
const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('es-BO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const getColorClass = (tipo) => {
    switch(tipo) {
        case 'Expulsión': return 'border-red color-red';
        case 'Problema de identificación': return 'border-yellow color-yellow';
        case 'Cambio de ambiente': return 'border-blue color-blue';
        default: return 'border-gray color-gray';
    }
};
</script>

<template>
    <Head title="Gestión de Incidencias" />

    <AuthenticatedLayout>
        <div class="page-wrapper">
            
            <!-- Encabezado Principal -->
            <header class="page-header">
                <h1 class="titulo-principal">Gestión de Incidencias</h1>
            </header>

            <!-- Barra de Filtros y Botón Reportar -->
            <div class="top-bar">
                <div class="filtros-box">
                    <SelectInput 
                        v-model="filterExamen" 
                        :options="examenesOptions" 
                        placeholder="Todos los exámenes"
                        @change="applyFilters"
                        class="w-full md:w-64"
                    />
                    <SelectInput 
                        v-model="filterTipo" 
                        :options="tiposOptions" 
                        placeholder="Todos los tipos"
                        @change="applyFilters"
                        class="w-full md:w-48"
                    />
                    <TextInput 
                        type="date"
                        v-model="filterDesde"
                        @change="applyFilters"
                        class="w-full md:w-32"
                        title="Desde"
                    />
                    <TextInput 
                        type="date"
                        v-model="filterHasta"
                        @change="applyFilters"
                        class="w-full md:w-32"
                        title="Hasta"
                    />
                </div>
                
                <Button variant="danger" @click="openModal" class="btn-reportar">
                    REPORTAR INCIDENCIA
                </Button>
            </div>

            <!-- Alerta Inmutabilidad -->
            <div class="alerta-info">
                Las incidencias registradas son inmutables. Una vez guardadas no pueden editarse ni eliminarse.
            </div>

            <!-- Lista de Incidencias (Tarjetas) -->
            <div class="incidencias-list">
                <div v-if="incidencias.data.length === 0" class="empty-state">
                    No hay incidencias registradas.
                </div>

                <div 
                    v-for="incidencia in incidencias.data" 
                    :key="incidencia.id_incidencia"
                    class="incidencia-card"
                    :class="getColorClass(incidencia.tipo_incidencia).split(' ')[0]"
                >
                    <!-- Cabecera de la tarjeta (Examen y Badge) -->
                    <div class="card-header">
                        <span class="card-examen">
                            {{ incidencia.examen?.asignatura?.nombre_asignatura || 'Sin Asignatura' }} – {{ incidencia.examen?.fecha }}
                        </span>
                        <span class="badge-tipo" :class="getColorClass(incidencia.tipo_incidencia).split(' ')[1]">
                            {{ incidencia.tipo_incidencia }}
                        </span>
                    </div>

                    <!-- Estudiante -->
                    <div v-if="incidencia.estudiante" class="card-estudiante">
                        {{ incidencia.estudiante.codigo_universitario }} – {{ incidencia.estudiante.apellidos }}, {{ incidencia.estudiante.nombres }}
                    </div>
                    <div v-else class="card-estudiante-null">
                        Incidencia general (No asociada a un estudiante)
                    </div>

                    <!-- Descripción -->
                    <p class="card-descripcion">
                        {{ incidencia.descripcion_motivo }}
                    </p>

                    <!-- Footer Metadatos -->
                    <div class="card-footer">
                        <span>Registrado: {{ formatDateTime(incidencia.fecha_hora) }}</span>
                        <span>Por: <strong>{{ incidencia.user?.username || 'Sistema' }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Paginación usando componente base -->
            <div class="mt-8" v-if="incidencias.last_page > 1">
                <Pagination :links="incidencias.links" />
            </div>

        </div>

        <!-- Modal Reportar Incidencia -->
        <Modal :open="isModalOpen" title="Reportar Incidencia" @close="closeModal">
            <form @submit.prevent="guardarIncidencia">
                
                <!-- Examen (Obligatorio) -->
                <div class="form-group">
                    <SelectInput 
                        label="EXAMEN *"
                        v-model="form.id_examen" 
                        :options="examenesOptions"
                        placeholder="Seleccionar examen..."
                    />
                    <p v-if="form.errors.id_examen" class="error-msg">{{ form.errors.id_examen }}</p>
                </div>

                <!-- Tipo de Incidencia (Obligatorio) -->
                <div class="form-group">
                    <SelectInput 
                        label="TIPO DE INCIDENCIA *"
                        v-model="form.tipo_incidencia" 
                        :options="tiposOptions"
                        placeholder="Seleccionar tipo..."
                    />
                    <p v-if="form.errors.tipo_incidencia" class="error-msg">{{ form.errors.tipo_incidencia }}</p>
                </div>

                <!-- Estudiante (Opcional - con mini buscador) -->
                <div class="form-group bg-light-gray p-4 rounded">
                    <label class="form-label-custom">ESTUDIANTE (OPCIONAL)</label>
                    <TextInput 
                        v-model="busquedaEstudiante" 
                        placeholder="Buscar por código, documento o nombre..." 
                        class="mb-2"
                    />
                    <select v-model="form.id_estudiante" class="select-html">
                        <option value="">Ninguno (Incidencia general)</option>
                        <option v-for="estudiante in estudiantesFiltrados" :key="estudiante.id_estudiante" :value="estudiante.id_estudiante">
                            {{ estudiante.codigo_universitario }} – {{ estudiante.nombres }} {{ estudiante.apellidos }}
                        </option>
                    </select>
                    <p v-if="form.errors.id_estudiante" class="error-msg">{{ form.errors.id_estudiante }}</p>
                </div>

                <!-- Descripción (Obligatorio) -->
                <div class="form-group">
                    <label class="form-label-custom">DESCRIPCIÓN *</label>
                    <textarea 
                        v-model="form.descripcion_motivo" 
                        rows="4" 
                        class="textarea-custom" 
                        placeholder="Describe la incidencia en detalle..."
                    ></textarea>
                    <p v-if="form.errors.descripcion_motivo" class="error-msg">{{ form.errors.descripcion_motivo }}</p>
                </div>

                <!-- Footer Modal -->
                <div class="modal-footer-custom">
                    <Button type="button" variant="outline" @click="closeModal" class="text-gray-500 border-gray-300">
                        CANCELAR
                    </Button>
                    <Button type="submit" variant="danger" :disabled="form.processing">
                        <span v-if="!form.processing">GUARDAR INCIDENCIA</span>
                        <LoadingSpinner v-else size="small" />
                    </Button>
                </div>

            </form>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor Principal */
.page-wrapper {
    padding: 30px 40px;
    max-width: 1100px;
    margin: 0 auto;
    font-family: var(--font-family);
}

/* Encabezado */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.titulo-principal {
    font-family: 'Orbitron', var(--font-display);
    font-size: 32px;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0;
}

.badge-rol {
    background-color: var(--color-primary);
    color: var(--text-white);
    font-size: 12px;
    font-weight: 700;
    padding: 8px 16px;
    border-radius: var(--radius-md, 4px);
    letter-spacing: 1.5px;
}

/* Barra Superior */
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
    flex-wrap: wrap;
}

.filtros-box {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    flex-grow: 1;
}

.btn-reportar {
    font-weight: bold;
    letter-spacing: 0.5px;
}

/* Alerta Info */
.alerta-info {
    background-color: #f0f4f8;
    color: #4b6a8f;
    border: 1px solid #d1dce5;
    padding: 15px 20px;
    border-radius: 6px;
    font-size: 14px;
    margin-bottom: 30px;
}

/* Tarjetas de Lista */
.incidencias-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.incidencia-card {
    background-color: var(--color-white);
    border: 1px solid var(--border-light);
    border-radius: 6px;
    padding: 20px 25px;
    border-top-width: 4px;
    border-top-style: solid;
}

.border-red { border-top-color: #dc2626; }
.border-yellow { border-top-color: #eab308; }
.border-blue { border-top-color: #3b82f6; }
.border-gray { border-top-color: #9ca3af; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.card-examen {
    color: #8fa0b3;
    font-size: 13px;
    font-weight: 700;
}

.badge-tipo {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 12px;
    border: 1px solid transparent;
}
.color-red { color: #dc2626; border-color: #fca5a5; background: #fef2f2; }
.color-yellow { color: #ca8a04; border-color: #fde047; background: #fef9c3; }
.color-blue { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
.color-gray { color: #4b5563; border-color: #d1d5db; background: #f3f4f6; }

.card-estudiante {
    font-family: 'Orbitron', var(--font-display);
    font-size: 16px;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 12px;
}

.card-estudiante-null {
    font-size: 14px;
    color: #9ca3af;
    margin-bottom: 12px;
}

.card-descripcion {
    font-size: 15px;
    color: #374151;
    margin-bottom: 20px;
    line-height: 1.5;
}

.card-footer {
    display: flex;
    gap: 20px;
    font-size: 12px;
    color: #8fa0b3;
}

/* Modal Formulario */
.form-group {
    margin-bottom: 20px;
}

.bg-light-gray {
    background-color: #f8fafc;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.form-label-custom {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
    font-family: var(--font-family);
}

.textarea-custom {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 14px;
    color: var(--text-dark);
    outline: none;
    resize: vertical;
    font-family: var(--font-family);
}
.textarea-custom:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(29, 54, 83, 0.15);
}

.select-html {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 14px;
    color: var(--text-dark);
    outline: none;
    background-color: white;
}
.select-html:focus { border-color: var(--color-primary); }

.error-msg {
    color: #dc2626;
    font-size: 13px;
    margin-top: 5px;
    font-weight: 600;
}

.modal-footer-custom {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6b7280;
    background: #f9fafb;
    border-radius: 6px;
    border: 1px dashed #d1d5db;
}

.mt-8 { margin-top: 2rem; }
.mb-2 { margin-bottom: 0.5rem; }
</style>