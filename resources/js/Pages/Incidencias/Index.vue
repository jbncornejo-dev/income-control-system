<script setup>
import { ref, computed, watch } from 'vue';
import { router, Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import Pagination from '@/components/ui/Pagination.vue';
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
    if (!q) return props.estudiantes; // Muestra todos si no hay búsqueda
    
    return props.estudiantes.filter((e) =>
        `${e.nombres} ${e.apellidos}`.toLowerCase().includes(q) ||
        String(e.codigo_universitario).toLowerCase().includes(q) ||
        String(e.documento_identidad).toLowerCase().includes(q)
    );
});

// Forzar el reseteo del select si el operador escribe una nueva búsqueda
watch(busquedaEstudiante, () => {
    form.id_estudiante = '';
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
        <div class="panel-container">
            
            <!-- Encabezado Principal -->
            <header class="header-section">
                <div>
                    <h1 class="panel-title">GESTIÓN DE INCIDENCIAS</h1>
                    <p class="subtitle">Historial inmutable de situaciones ocurridas durante los exámenes</p>
                </div>
                <Button variant="danger" @click="openModal" class="btn-reportar">
                    + REPORTAR INCIDENCIA
                </Button>
            </header>

            <!-- Barra de Filtros -->
            <div class="filters-container mb-6">
                <SelectInput 
                    v-model="filterExamen" 
                    :options="examenesOptions" 
                    placeholder="Todos los exámenes"
                    @change="applyFilters"
                    class="filter-input flex-grow"
                />
                
                <SelectInput 
                    v-model="filterTipo" 
                    :options="tiposOptions" 
                    placeholder="Todos los tipos"
                    @change="applyFilters"
                    class="filter-input w-48"
                />
                
                <div class="flex gap-2 items-end">
                    <TextInput 
                        type="date"
                        v-model="filterDesde"
                        @change="applyFilters"
                        class="filter-date w-36"
                        title="Desde"
                    />
                    <span class="text-gray-400 mb-2">-</span>
                    <TextInput 
                        type="date"
                        v-model="filterHasta"
                        @change="applyFilters"
                        class="filter-date w-36"
                        title="Hasta"
                    />
                </div>
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
                    <label class="form-label font-bold text-xs text-gray-500 mb-2 block">ESTUDIANTE (OPCIONAL)</label>
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
                    <label class="form-label font-bold text-xs text-gray-500 mb-2 block">DESCRIPCIÓN *</label>
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
                    <Button type="button" variant="action" @click="closeModal" class="btn-cancelar">
                        CANCELAR
                    </Button>
                    <Button type="submit" variant="danger" :disabled="form.processing" class="btn-guardar">
                        <span v-if="!form.processing">GUARDAR INCIDENCIA</span>
                        <LoadingSpinner v-else size="small" />
                    </Button>
                </div>

            </form>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-xl);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 4px;
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

.subtitle {
    font-size: 0.95rem;
    color: var(--text-muted);
    margin: 0;
}

.filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    background: var(--color-white);
    padding: var(--spacing-md) var(--spacing-lg);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-card);
    align-items: flex-end;
}

.btn-reportar {
    white-space: nowrap;
}

/* Tarjetas de Lista */
.incidencias-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.incidencia-card {
    background-color: var(--color-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    border-left-width: 4px;
    border-left-style: solid;
    box-shadow: var(--shadow-card);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.incidencia-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.border-red { border-left-color: var(--color-active); }
.border-yellow { border-left-color: var(--color-warning); }
.border-blue { border-left-color: var(--color-primary); }
.border-gray { border-left-color: var(--text-muted); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-sm);
}

.card-examen {
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-tipo {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    border: 1px solid transparent;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.color-red { color: var(--color-active); border-color: #fca5a5; background: #fef2f2; }
.color-yellow { color: #b45309; border-color: #fcd34d; background: #fffbeb; }
.color-blue { color: var(--color-primary); border-color: #bfdbfe; background: #eff6ff; }
.color-gray { color: var(--text-dark); border-color: var(--border-light); background: var(--bg-main); }

.card-estudiante {
    font-family: var(--font-display);
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--spacing-sm);
}

.card-estudiante-null {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: var(--spacing-sm);
    font-style: italic;
}

.card-descripcion {
    font-size: 0.95rem;
    color: var(--text-dark);
    margin-bottom: var(--spacing-lg);
    line-height: 1.6;
}

.card-footer {
    display: flex;
    gap: var(--spacing-lg);
    font-size: 0.8rem;
    color: var(--text-muted);
    border-top: 1px solid var(--border-light);
    padding-top: var(--spacing-sm);
}

/* Modal Formulario */
.form-group {
    margin-bottom: var(--spacing-lg);
}

.bg-light-gray {
    background-color: var(--bg-main);
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
}

.textarea-custom {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    color: var(--text-dark);
    outline: none;
    resize: vertical;
    font-family: var(--font-family);
    transition: border-color var(--transition-fast);
}
.textarea-custom:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(29, 54, 83, 0.15);
}

.select-html {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    color: var(--text-dark);
    outline: none;
    background-color: var(--color-white);
    transition: border-color var(--transition-fast);
}
.select-html:focus { border-color: var(--color-primary); }

.error-msg {
    color: var(--color-active);
    font-size: 0.8rem;
    margin-top: 4px;
    font-weight: 600;
}

.modal-footer-custom {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-md);
    margin-top: var(--spacing-xl);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--border-light);
}

.empty-state {
    text-align: center;
    padding: var(--spacing-xl);
    color: var(--text-muted);
    background: var(--color-white);
    border-radius: var(--radius-md);
    border: 1px dashed var(--border-light);
}

.mb-6 { margin-bottom: 1.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.flex { display: flex; }
.flex-grow { flex-grow: 1; }
.gap-2 { gap: 0.5rem; }
.items-end { align-items: flex-end; }
.w-48 { width: 12rem; }
.w-36 { width: 9rem; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.font-bold { font-weight: 700; }
.text-xs { font-size: 0.75rem; }
.block { display: block; }

@media (max-width: 768px) {
    .header-section {
        flex-direction: column;
        align-items: stretch;
    }
    .filters-container {
        flex-direction: column;
        align-items: stretch;
    }
    .w-48, .w-36 {
        width: 100%;
    }
    .btn-reportar {
        width: 100%;
        justify-content: center;
    }
}
</style>