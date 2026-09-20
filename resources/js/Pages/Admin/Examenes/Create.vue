<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    asignaturas: { type: Array, default: () => [] },
    ambientes: { type: Array, default: () => [] },
    // Cuando llega "examen", la página funciona como edición (PATCH) del mismo.
    examen: { type: Object, default: null },
});

const toast = useToastStore();

const esEdicion = computed(() => !!props.examen);

// Fecha mínima seleccionable: hoy (la validación exige que el examen sea futuro).
const hoy = ref(new Date().toISOString().slice(0, 10));

const form = useForm({
    id_asignatura: props.examen?.id_asignatura ?? '',
    fecha: props.examen?.fecha ?? '',
    hora_inicio: props.examen ? String(props.examen.hora_inicio ?? '').slice(0, 5) : '',
    duracion_minutos: props.examen?.duracion_minutos ?? 90,
    normas_generales: props.examen?.normas_generales ?? '',
    id_ambientes: (props.examen?.examenes_ambientes ?? []).map((ea) => ea.id_ambiente),
});

const ambientesSeleccionados = computed(() =>
    props.ambientes.filter((a) => form.id_ambientes.includes(a.id_ambiente))
);

function toggleAmbiente(idAmbiente) {
    if (form.id_ambientes.includes(idAmbiente)) {
        form.id_ambientes = form.id_ambientes.filter((id) => id !== idAmbiente);
    } else {
        form.id_ambientes = [...form.id_ambientes, idAmbiente];
    }
    form.clearErrors('id_ambientes');
}

function guardar() {
    if (form.processing) return;

    const opciones = {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash?.success) toast.success(page.props.flash.success);
        },
    };

    if (esEdicion.value) {
        form.patch(`/examenes/${props.examen.id_examen}`, opciones);
    } else {
        form.post('/examenes', opciones);
    }
}
</script>

<template>
    <Head :title="esEdicion ? 'Editar Examen' : 'Registrar Examen'" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <div class="back-link-container">
                <Link href="/examenes" class="back-link">&larr; Volver a Exámenes</Link>
            </div>

            <div class="form-card">
                <h1 class="form-title">{{ esEdicion ? 'Editar Examen' : 'Registrar Examen' }}</h1>
                <p class="form-subtitle">
                    {{ esEdicion ? 'Modifica asignatura, horario o ambientes de la evaluación.' : 'Programa una nueva evaluación asignándole asignatura, horario y ambientes.' }}
                </p>

                <form @submit.prevent="guardar" novalidate>
                    <!-- Asignatura -->
                    <div class="form-group">
                        <label for="id_asignatura" class="form-label">Asignatura <span class="required">*</span></label>
                        <select
                            id="id_asignatura"
                            v-model="form.id_asignatura"
                            class="form-input"
                            :class="{ 'input-error': form.errors.id_asignatura }"
                            :disabled="form.processing"
                            @change="form.clearErrors('id_asignatura')"
                        >
                            <option value="" disabled>Seleccione una asignatura...</option>
                            <option v-for="asignatura in asignaturas" :key="asignatura.id_asignatura" :value="asignatura.id_asignatura">
                                {{ asignatura.nombre_asignatura }}
                            </option>
                        </select>
                        <p v-if="form.errors.id_asignatura" class="error-msg">{{ form.errors.id_asignatura }}</p>
                        <p v-if="asignaturas.length === 0" class="help-text">No hay asignaturas registradas. Crea una desde el módulo Asignaturas.</p>
                    </div>

                    <!-- Fecha / Hora / Duración -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fecha" class="form-label">Fecha <span class="required">*</span></label>
                            <input
                                id="fecha"
                                v-model="form.fecha"
                                type="date"
                                class="form-input"
                                :min="hoy"
                                :class="{ 'input-error': form.errors.fecha }"
                                :disabled="form.processing"
                                @input="form.clearErrors('fecha')"
                            />
                            <p v-if="form.errors.fecha" class="error-msg">{{ form.errors.fecha }}</p>
                        </div>

                        <div class="form-group">
                            <label for="hora_inicio" class="form-label">Hora de inicio <span class="required">*</span></label>
                            <input
                                id="hora_inicio"
                                v-model="form.hora_inicio"
                                type="time"
                                class="form-input"
                                :class="{ 'input-error': form.errors.hora_inicio }"
                                :disabled="form.processing"
                                @input="form.clearErrors('hora_inicio')"
                            />
                            <p v-if="form.errors.hora_inicio" class="error-msg">{{ form.errors.hora_inicio }}</p>
                        </div>

                        <div class="form-group">
                            <label for="duracion_minutos" class="form-label">Duración (minutos) <span class="required">*</span></label>
                            <input
                                id="duracion_minutos"
                                v-model.number="form.duracion_minutos"
                                type="number"
                                min="1"
                                max="720"
                                class="form-input"
                                :class="{ 'input-error': form.errors.duracion_minutos }"
                                :disabled="form.processing"
                                @input="form.clearErrors('duracion_minutos')"
                            />
                            <p v-if="form.errors.duracion_minutos" class="error-msg">{{ form.errors.duracion_minutos }}</p>
                        </div>
                    </div>

                    <!-- Normas generales -->
                    <div class="form-group">
                        <label for="normas_generales" class="form-label">Normas generales</label>
                        <textarea
                            id="normas_generales"
                            v-model="form.normas_generales"
                            rows="4"
                            maxlength="5000"
                            class="form-input"
                            :class="{ 'input-error': form.errors.normas_generales }"
                            placeholder="Ej: Presentar documento de identidad. Prohibido el uso de celulares."
                            :disabled="form.processing"
                            @input="form.clearErrors('normas_generales')"
                        ></textarea>
                        <p v-if="form.errors.normas_generales" class="error-msg">{{ form.errors.normas_generales }}</p>
                    </div>

                    <!-- Ambientes -->
                    <div class="form-group">
                        <span class="form-label">Ambientes <span class="required">*</span></span>
                        <p class="help-text">Selecciona uno o más ambientes del examen.</p>

                        <div v-if="ambientes.length > 0" class="ambiente-grid">
                            <label
                                v-for="ambiente in ambientes"
                                :key="ambiente.id_ambiente"
                                class="ambiente-item"
                                :class="{ 'ambiente-item--selected': form.id_ambientes.includes(ambiente.id_ambiente) }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.id_ambientes.includes(ambiente.id_ambiente)"
                                    :value="ambiente.id_ambiente"
                                    :disabled="form.processing"
                                    @change="toggleAmbiente(ambiente.id_ambiente)"
                                />
                                <span class="ambiente-name">{{ ambiente.nombre_ambiente }}</span>
                                <span class="ambiente-capacity">{{ ambiente.capacidad }} pers.</span>
                            </label>
                        </div>
                        <p v-else class="help-text">No hay ambientes registrados. Crea uno desde el módulo Ambientes.</p>
                        <p v-if="form.errors.id_ambientes" class="error-msg">{{ form.errors.id_ambientes }}</p>
                    </div>

                    <div class="form-actions">
                        <Link href="/examenes" class="btn-cancel">Cancelar</Link>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            <LoadingSpinner v-if="form.processing" size="small" />
                            <span v-else>{{ esEdicion ? 'Guardar Cambios' : 'Registrar Examen' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

.back-link-container { margin-bottom: 1rem; }
.back-link { color: var(--color-primary); text-decoration: none; font-size: 0.875rem; font-weight: 500; }

.form-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 2rem;
    max-width: 860px;
    margin: 0 auto;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.form-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0 0 4px;
}

.form-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0 0 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.form-group { margin-bottom: 1.25rem; }

.form-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.375rem;
}

.required { color: #d32f2f; }

.form-input {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background-color: #f9fafb;
    color: #374151;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: var(--shadow-input-focus);
}

textarea.form-input { resize: vertical; }

.input-error { border-color: #d32f2f !important; }

.error-msg { color: #d32f2f; font-size: 0.75rem; margin-top: 0.25rem; }
.help-text { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }

/* Selección de ambientes */
.ambiente-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.5rem;
}

.ambiente-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    background-color: #f9fafb;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.ambiente-item--selected {
    background-color: #eef2f7;
    border-color: var(--color-primary);
}

.ambiente-item input { cursor: pointer; }

.ambiente-name { font-size: 0.875rem; color: #374151; flex: 1; }
.ambiente-capacity { font-size: 0.75rem; color: #6b7280; white-space: nowrap; }

/* Acciones */
.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e5e7eb;
}

.btn-primary {
    background: var(--color-primary);
    color: #ffffff;
    border: none;
    padding: 0.625rem 1.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-cancel {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
    padding: 0.625rem 1.25rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    text-decoration: none;
    cursor: pointer;
}

.btn-cancel:hover { background-color: #f9fafb; }

/* Responsive: apilar los campos en pantallas pequeñas */
@media (max-width: 640px) {
    .panel-container { padding: 1rem; }
    .form-card { padding: 1.25rem; }
    .form-grid { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column-reverse; align-items: stretch; }
    .form-actions .btn-primary,
    .form-actions .btn-cancel { width: 100%; justify-content: center; }
}
</style>