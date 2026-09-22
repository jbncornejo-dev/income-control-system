<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    asignaturas: { type: Array, default: () => [] },
    ambientes: { type: Array, default: () => [] },
    periodos: { type: Array, default: () => [] },
    // Cuando llega "examen", la página funciona como edición (PATCH) del mismo.
    examen: { type: Object, default: null },
});

const toast = useToastStore();

const esEdicion = computed(() => !!props.examen);

// En un examen en curso (incluye suspendido) solo se editan las normas generales;
// los datos estructurales (fecha, hora, duración, ambientes, asignatura) quedan congelados.
// Se usa `estado_horario` (ciclo según horario): un examen suspendido sigue teniendo
// su ventana activa, por lo que también queda congelado aunque su estado de gestión sea "Suspendido".
const soloNormas = computed(() =>
    esEdicion.value && props.examen?.estado_horario === 'en_curso'
);

// Fecha mínima seleccionable: hoy (la validación exige que el examen sea futuro).
const hoy = ref(new Date().toISOString().slice(0, 10));

const form = useForm({
    id_asignatura: props.examen?.id_asignatura ?? '',
    id_periodo: props.examen?.id_periodo ?? '',
    fecha: props.examen?.fecha ?? '',
    hora_inicio: props.examen ? String(props.examen.hora_inicio ?? '').slice(0, 5) : '',
    duracion_minutos: props.examen?.duracion_minutos ?? 90,
    normas_generales: props.examen?.normas_generales ?? '',
    id_ambientes: (props.examen?.examenes_ambientes ?? []).map((ea) => ea.id_ambiente),
    id_grupos: (props.examen?.grupos ?? []).map((g) => g.id_grupo),
});

// Grupos disponibles para la asignatura elegida. Cada asignatura llega del
// backend con sus grupos (para el docente, solo los suyos).
const gruposAsignatura = computed(() => {
    const asignatura = props.asignaturas.find((a) => a.id_asignatura === form.id_asignatura);
    return asignatura?.grupos ?? [];
});

// Al elegir otra asignatura se preseleccionan todos sus grupos (el docente
// desmarca los que rendirán un examen aparte). En edición la elección inicial
// conserva los grupos ya asociados al examen.
watch(
    () => form.id_asignatura,
    (valor, anterior) => {
        if (!valor || valor === anterior) return;

        const asignatura = props.asignaturas.find((a) => a.id_asignatura === valor);
        form.id_grupos = (asignatura?.grupos ?? []).map((g) => g.id_grupo);
        form.clearErrors('id_grupos');
    }
);

function toggleGrupo(idGrupo) {
    if (form.id_grupos.includes(idGrupo)) {
        form.id_grupos = form.id_grupos.filter((id) => id !== idGrupo);
    } else {
        form.id_grupos = [...form.id_grupos, idGrupo];
    }
    form.clearErrors('id_grupos');
}

// Sugiere el periodo según la fecha del examen: primero el periodo cuyo rango
// contiene la fecha y, si no, el de la misma gestión (año). Si el usuario ya
// eligió un periodo no se sobreescribe.
function periodoSugerido(fecha) {
    if (!fecha) return null;

    const porRango = props.periodos.find(
        (p) => p.fecha_inicio && p.fecha_fin && fecha >= p.fecha_inicio && fecha <= p.fecha_fin
    );
    if (porRango) return porRango.id_periodo;

    const porGestion = props.periodos.find((p) => String(p.gestion) === fecha.slice(0, 4));
    return porGestion?.id_periodo ?? null;
}

watch(
    () => form.fecha,
    (fecha) => {
        if (!form.id_periodo) form.id_periodo = periodoSugerido(fecha) ?? '';
    }
);

const ambientesSeleccionados = computed(() =>
    props.ambientes.filter((a) => form.id_ambientes.includes(a.id_ambiente))
);

// --- Disponibilidad de ambientes para el horario elegido ---
// Estado de cada ambiente: {id: true|false}. undefined = aún sin consultar.
const disponibilidad = ref({});
const verificandoDisponibilidad = ref(false);
const mostrarSoloLibres = ref(false);
const avisoSeleccion = ref('');
let temporizadorDisponibilidad = null;

function estaOcupado(idAmbiente) {
    return disponibilidad.value[idAmbiente] === false;
}

function estaLibre(idAmbiente) {
    return disponibilidad.value[idAmbiente] === true;
}

// Con "solo disponibles" activo se ocultan los ambientes ocupados del grid.
const ambientesVisibles = computed(() => {
    if (!mostrarSoloLibres.value) return props.ambientes;

    return props.ambientes.filter((ambiente) => disponibilidad.value[ambiente.id_ambiente] !== false);
});

function solicitarDisponibilidad() {
    if (!form.fecha || !form.hora_inicio || !form.duracion_minutos) {
        disponibilidad.value = {};
        verificandoDisponibilidad.value = false;
        return;
    }

    verificandoDisponibilidad.value = true;

    const params = new URLSearchParams({
        fecha: form.fecha,
        hora_inicio: form.hora_inicio,
        duracion_minutos: String(form.duracion_minutos),
    });

    // En edición se excluye el propio examen para que su ambiente actual no
    // aparezca ocupado por sí mismo.
    if (esEdicion.value) {
        params.set('excluir_examen', String(props.examen.id_examen));
    }

    fetch(`/examenes/disponibilidad?${params.toString()}`)
        .then((respuesta) => {
            if (!respuesta.ok) throw new Error('No se pudo consultar la disponibilidad');
            return respuesta.json();
        })
        .then((datos) => {
            const mapa = {};
            for (const ambiente of datos.ambientes) {
                mapa[ambiente.id_ambiente] = ambiente.disponible;
            }
            disponibilidad.value = mapa;

            // Si algún ambiente seleccionado quedó ocupado en este horario, se quita solo.
            const habiaOcupadosSeleccionados = form.id_ambientes.some((id) => mapa[id] === false);
            if (habiaOcupadosSeleccionados) {
                form.id_ambientes = form.id_ambientes.filter((id) => mapa[id] !== false);
                form.clearErrors('id_ambientes');
                avisoSeleccion.value = 'Se quitaron de la selección ambientes que están ocupados en ese horario.';
            }
        })
        .catch(() => {
            // Si la consulta falla, se conserva el último estado conocido y
            // el backend seguirá validando al guardar.
        })
        .finally(() => {
            verificandoDisponibilidad.value = false;
        });
}

function programarDisponibilidad() {
    avisoSeleccion.value = '';
    clearTimeout(temporizadorDisponibilidad);
    temporizadorDisponibilidad = setTimeout(solicitarDisponibilidad, 350);
}

watch(
    () => [form.fecha, form.hora_inicio, form.duracion_minutos],
    programarDisponibilidad
);

onMounted(programarDisponibilidad);

onUnmounted(() => clearTimeout(temporizadorDisponibilidad));

function toggleAmbiente(idAmbiente) {
    if (estaOcupado(idAmbiente)) return;

    if (form.id_ambientes.includes(idAmbiente)) {
        form.id_ambientes = form.id_ambientes.filter((id) => id !== idAmbiente);
    } else {
        form.id_ambientes = [...form.id_ambientes, idAmbiente];
    }
    form.clearErrors('id_ambientes');
}

// Hora de fin estimada: hora de inicio + duración (solo lectura, se recalcula en vivo).
const horaFin = computed(() => {
    if (!form.hora_inicio || !form.duracion_minutos) return '—';

    const [h, m] = form.hora_inicio.split(':').map(Number);
    const total = h * 60 + m + Number(form.duracion_minutos);
    const horas = String(Math.floor(total / 60) % 24).padStart(2, '0');
    const minutos = String(total % 60).padStart(2, '0');

    return `${horas}:${minutos}`;
});

function guardar() {
    if (form.processing) return;

    // En un examen en curso el formulario solo envía las normas generales;
    // los demás campos ni siquiera viajan al backend.
    if (soloNormas.value) {
        form.transform((datos) => ({
            normas_generales: datos.normas_generales,
        }));
    }

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
                    {{ esEdicion
                        ? (soloNormas
                            ? 'El examen está en curso: solo puedes actualizar las normas generales.'
                            : 'Modifica asignatura, periodo, horario o ambientes de la evaluación.')
                        : 'Programa una nueva evaluación asignándole asignatura, periodo, horario y ambientes.' }}
                </p>
                <p v-if="soloNormas" class="form-aviso">
                    ⏸ El examen está <strong>en curso</strong>. Fecha, hora, duración, ambientes, grupos, asignatura y periodo quedan congelados; podrás reajustarlos una vez finalice.
                </p>

                <form @submit.prevent="guardar" novalidate>
                    <!-- Asignatura -->
                    <div v-if="!soloNormas" class="form-group">
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

                    <!-- Grupos que rinden el examen -->
                    <div v-if="!soloNormas" class="form-group">
                        <span class="form-label">Grupos que rinden el examen <span class="required">*</span></span>
                        <p class="help-text">
                            Marca los grupos de la asignatura que presentan este examen. Si tus grupos avanzan
                            a distinto ritmo, crea exámenes separados: cada grupo (o conjunto de grupos al mismo
                            ritmo) con su propia fecha.
                        </p>

                        <div v-if="gruposAsignatura.length > 0" class="ambiente-grid">
                            <label
                                v-for="grupo in gruposAsignatura"
                                :key="grupo.id_grupo"
                                class="ambiente-item"
                                :class="{ 'ambiente-item--selected': form.id_grupos.includes(grupo.id_grupo) }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.id_grupos.includes(grupo.id_grupo)"
                                    :value="grupo.id_grupo"
                                    :disabled="form.processing"
                                    @change="toggleGrupo(grupo.id_grupo)"
                                />
                                <span class="ambiente-name">{{ grupo.nombre_grupo }}</span>
                            </label>
                        </div>
                        <p v-else-if="form.id_asignatura" class="help-text">
                            No hay grupos registrados para esta asignatura.
                        </p>
                        <p v-else class="help-text">
                            Selecciona primero una asignatura para marcar sus grupos.
                        </p>
                        <p v-if="form.errors.id_grupos" class="error-msg">{{ form.errors.id_grupos }}</p>
                    </div>

                    <!-- Periodo -->
                    <div v-if="!soloNormas" class="form-group">
                        <label for="id_periodo" class="form-label">Periodo <span class="required">*</span></label>
                        <select
                            id="id_periodo"
                            v-model="form.id_periodo"
                            class="form-input"
                            :class="{ 'input-error': form.errors.id_periodo }"
                            :disabled="form.processing"
                            @change="form.clearErrors('id_periodo')"
                        >
                            <option value="" disabled>Seleccione un periodo...</option>
                            <option v-for="periodo in periodos" :key="periodo.id_periodo" :value="periodo.id_periodo" :title="periodo.nombre">
                                {{ periodo.codigo }}
                            </option>
                        </select>
                        <p v-if="form.errors.id_periodo" class="error-msg">{{ form.errors.id_periodo }}</p>
                        <p v-if="periodos.length === 0" class="help-text">No hay periodos registrados. Crea el periodo desde el módulo de administración.</p>
                    </div>

                    <!-- Fecha / Hora / Duración -->
                    <div v-if="!soloNormas" class="form-grid">
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

                        <div class="form-group">
                            <span class="form-label">Hora de finalización</span>
                            <div class="hora-fin-display" aria-live="polite">
                                <span class="hora-fin-icon">⏱</span>
                                <span>{{ horaFin }}</span>
                            </div>
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
                        <p v-if="soloNormas" class="help-text">Durante el examen se actualizan únicamente las indicaciones que se muestran a los estudiantes.</p>
                    </div>

                    <!-- Ambientes -->
                    <div v-if="!soloNormas" class="form-group">
                        <span class="form-label">Ambientes <span class="required">*</span></span>

                        <div v-if="ambientes.length > 0" class="ambiente-toolbar">
                            <label class="toggle-libres">
                                <input
                                    type="checkbox"
                                    v-model="mostrarSoloLibres"
                                    :disabled="form.processing"
                                />
                                <span>Mostrar solo disponibles</span>
                            </label>
                            <span v-if="verificandoDisponibilidad" class="disponibilidad-aviso" aria-live="polite">
                                Verificando disponibilidad…
                            </span>
                        </div>

                        <p v-if="avisoSeleccion" class="disponibilidad-aviso" aria-live="polite">{{ avisoSeleccion }}</p>

                        <div v-if="ambientesVisibles.length > 0" class="ambiente-grid">
                            <label
                                v-for="ambiente in ambientesVisibles"
                                :key="ambiente.id_ambiente"
                                class="ambiente-item"
                                :class="{
                                    'ambiente-item--selected': form.id_ambientes.includes(ambiente.id_ambiente),
                                    'ambiente-item--libre': estaLibre(ambiente.id_ambiente),
                                    'ambiente-item--ocupado': estaOcupado(ambiente.id_ambiente),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.id_ambientes.includes(ambiente.id_ambiente)"
                                    :value="ambiente.id_ambiente"
                                    :disabled="form.processing || estaOcupado(ambiente.id_ambiente)"
                                    @change="toggleAmbiente(ambiente.id_ambiente)"
                                />
                                <span class="ambiente-name">{{ ambiente.nombre_ambiente }}</span>
                                <span v-if="estaOcupado(ambiente.id_ambiente)" class="ambiente-ocupado-tag">Ocupado</span>
                                <span v-else class="ambiente-capacity">{{ ambiente.capacidad }} pers.</span>
                            </label>
                        </div>
                        <p v-if="ambientes.length > 0 && ambientesVisibles.length === 0" class="help-text">
                            No hay ambientes disponibles en ese horario.
                        </p>
                        <p v-else-if="ambientes.length === 0" class="help-text">No hay ambientes registrados. Crea uno desde el módulo Ambientes.</p>
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

.form-aviso {
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    padding: 0.625rem 0.75rem;
    margin: -0.75rem 0 1.25rem;
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

.form-input--readonly {
    background-color: #f1f3f5;
    color: #6b7280;
    border-style: dashed;
}

/* Hora de finalización: dato calculado, se muestra como etiqueta informativa */
.hora-fin-display {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.4375rem 0.875rem;
    border-radius: 9999px;
    background-color: #eef2f7;
    color: var(--color-primary);
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.hora-fin-icon { font-size: 0.8125rem; line-height: 1; }

.input-error { border-color: #d32f2f !important; }

.error-msg { color: #d32f2f; font-size: 0.75rem; margin-top: 0.25rem; }
.help-text { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }

/* Selección de ambientes */
.ambiente-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.toggle-libres {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    color: #4b5563;
    cursor: pointer;
}

.disponibilidad-aviso {
    font-size: 0.75rem;
    color: var(--color-primary);
    margin-top: 0.125rem;
}

.ambiente-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.5rem;
    margin-top: 0.25rem;
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

.ambiente-item--libre {
    background-color: #f0fdf4;
    border-color: #86efac;
}

.ambiente-item--ocupado {
    background-color: #fef2f2;
    border-color: #fca5a5;
    opacity: 0.8;
    cursor: not-allowed;
}

.ambiente-item input { cursor: pointer; }
.ambiente-item--ocupado input { cursor: not-allowed; }

.ambiente-name { font-size: 0.875rem; color: #374151; flex: 1; }
.ambiente-capacity { font-size: 0.75rem; color: #6b7280; white-space: nowrap; }

.ambiente-ocupado-tag {
    font-size: 0.6875rem;
    font-weight: 700;
    color: #b91c1c;
    background-color: #fee2e2;
    border-radius: 9999px;
    padding: 0.125rem 0.5rem;
    white-space: nowrap;
}

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