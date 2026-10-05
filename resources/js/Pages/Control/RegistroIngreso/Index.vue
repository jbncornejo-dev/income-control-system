<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import axios from 'axios';

const props = defineProps({
    examenes: {
        type: Array,
        required: true
    }
});

const currentStep = ref(1);
const selectedExamenId = ref('');
const selectedAmbienteId = ref('');
const datoEstudiante = ref('');
const estudianteValidado = ref(null);
const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);
const inputRef = ref(null);

const ingresosRegistrados = ref(0);

// Propiedades computadas para mostrar en la barra oscura
const selectedExamenData = computed(() => {
    const examen = props.examenes.find(e => e.id_examen == selectedExamenId.value);
    return examen ? `${examen.asignatura} — ${examen.hora_inicio}` : '';
});

const selectedAmbienteData = computed(() => {
    const ambiente = ambientesOptions.value.find(a => a.value == selectedAmbienteId.value);
    return ambiente ? ambiente.label : '';
});

const ambientesDisponibles = computed(() => {
    if (!selectedExamenId.value) return [];
    const examen = props.examenes.find(e => e.id_examen == selectedExamenId.value);
    return examen ? examen.ambientes : [];
});

const examenesOptions = computed(() => props.examenes.map(e => ({
    value: e.id_examen,
    label: `${e.asignatura} - ${e.hora_inicio}`
})));

const ambientesOptions = computed(() => ambientesDisponibles.value.map(a => ({
    value: a.id_examen_ambiente,
    label: a.nombre_ambiente
})));

const iniciarRegistro = async () => {
    if (selectedExamenId.value && selectedAmbienteId.value) {
        currentStep.value = 2;
        await nextTick();
        if (inputRef.value?.$el?.querySelector('input')) {
            inputRef.value.$el.querySelector('input').focus();
        }
    }
};

const validarEstudiante = async () => {
    if (!datoEstudiante.value) return;

    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    estudianteValidado.value = null;

    try {
        const response = await axios.post('/registro-ingreso/validar', {
            id_examen: selectedExamenId.value,
            dato_estudiante: datoEstudiante.value
        });
        estudianteValidado.value = response.data;
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Error al validar al estudiante.';
    } finally {
        isLoading.value = false;
        datoEstudiante.value = '';
    }
};

const registrarIngreso = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await axios.post('/registro-ingreso', {
            id_estudiante: estudianteValidado.value.id_estudiante,
            id_examen_ambiente: selectedAmbienteId.value
        });
        
        successMessage.value = `Ingreso registrado para ${estudianteValidado.value.nombres}.`;
        ingresosRegistrados.value++;
        estudianteValidado.value = null;
        
        await nextTick();
        if (inputRef.value?.$el?.querySelector('input')) {
            inputRef.value.$el.querySelector('input').focus();
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Error al registrar el ingreso.';
    } finally {
        isLoading.value = false;
    }
};

const cancelar = async () => {
    estudianteValidado.value = null;
    errorMessage.value = '';
    successMessage.value = '';
    await nextTick();
    if (inputRef.value?.$el?.querySelector('input')) {
        inputRef.value.$el.querySelector('input').focus();
    }
};
</script>

<template>
    <Head title="Registro de Ingreso" />

    <AuthenticatedLayout>
        <div class="registro-wrapper">
            
            <!-- Encabezado -->
            <header class="registro-header">
                <h1 class="titulo-principal">Registro de Ingreso</h1>
                <span class="badge-control">CONTROL</span>
            </header>

            <!-- Paso 1: Selección de Examen y Ambiente -->
            <div v-if="currentStep === 1" class="registro-card border-red">
                <span class="paso-indicador">PASO 1 DE 2</span>
                <h2 class="card-titulo">Seleccionar Examen y Ambiente</h2>

                <div class="formulario-seccion">
                    <div class="form-group">
                        <SelectInput 
                            label="EXAMEN ACTIVO"
                            v-model="selectedExamenId" 
                            :options="examenesOptions"
                            @change="selectedAmbienteId = ''"
                        />
                    </div>

                    <div class="form-group">
                        <SelectInput 
                            label="AMBIENTE"
                            v-model="selectedAmbienteId" 
                            :options="ambientesOptions"
                            :disabled="!selectedExamenId"
                        />
                    </div>

                    <Button 
                        @click="iniciarRegistro" 
                        :disabled="!selectedExamenId || !selectedAmbienteId"
                        variant="primary"
                        class="btn-full mt-10"
                    >
                        INICIAR REGISTRO
                    </Button>
                </div>
            </div>

            <!-- Paso 2: Escaneo y Verificación -->
            <div v-if="currentStep === 2" class="step-section">
                
                <!-- Barra oscura de resumen -->
                <div class="resumen-bar">
                    <div class="resumen-info">
                        <div class="resumen-item">
                            <span class="resumen-label">EXAMEN ACTIVO</span>
                            <span class="resumen-valor">{{ selectedExamenData }}</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-label">AMBIENTE</span>
                            <span class="resumen-valor text-red">{{ selectedAmbienteData }}</span>
                        </div>
                    </div>
                    <button @click="currentStep = 1" class="btn-cambiar">
                        CAMBIAR
                    </button>
                </div>

                <!-- Contador de ingresos -->
                <div class="contador-ingresos">
                    Ingresos registrados: <strong>{{ ingresosRegistrados }}</strong>
                </div>

                <!-- Lector (Formulario) -->
                <div v-if="!estudianteValidado" class="formulario-card">
                    <form @submit.prevent="validarEstudiante">
                        <div class="flex-inline-input">
                            <TextInput 
                                ref="inputRef"
                                label="CÓDIGO / CI / QR DEL ESTUDIANTE"
                                v-model="datoEstudiante" 
                                :disabled="isLoading"
                                placeholder="Escanear o escribir..." 
                                class="flex-1"
                            />
                            
                            <Button 
                                type="submit" 
                                :disabled="!datoEstudiante || isLoading" 
                                variant="primary"
                                class="btn-buscar"
                            >
                                <span v-if="!isLoading">BUSCAR</span>
                                <LoadingSpinner v-else size="small" />
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Botones inferiores de acción secundaria -->
                <div v-if="!estudianteValidado" class="acciones-inferiores">
                    <Button variant="action" class="btn-limpiar" @click="datoEstudiante = ''">
                        LIMPIAR
                    </Button>
                    <Button variant="danger" class="btn-incidencia">
                        REPORTAR INCIDENCIA
                    </Button>
                </div>

                <!-- Confirmación Visual -->
                <div v-if="estudianteValidado" class="confirmacion-box">
                    <div class="foto-contenedor">
                        <img v-if="estudianteValidado.foto_url" :src="estudianteValidado.foto_url" class="foto-perfil">
                        <div v-else class="foto-placeholder">Sin Foto</div>
                    </div>
                    <div class="info-estudiante">
                        <h3 class="nombre-estudiante">{{ estudianteValidado.nombres }} {{ estudianteValidado.apellidos }}</h3>
                        <p class="id-estudiante">ID: {{ estudianteValidado.id_estudiante }}</p>

                        <div class="botones-accion">
                            <Button @click="cancelar" :disabled="isLoading" variant="action">
                                Cancelar
                            </Button>
                            <Button @click="registrarIngreso" :disabled="isLoading" variant="primary">
                                <span v-if="!isLoading">Confirmar ingreso</span>
                                <LoadingSpinner v-else size="small" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Alertas -->
                <div v-if="errorMessage" class="alerta alerta-error">
                    {{ errorMessage }}
                </div>
                <div v-if="successMessage" class="alerta alerta-exito">
                    {{ successMessage }}
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor Base */
.registro-wrapper {
    padding: 40px 20px;
    max-width: 800px;
    margin: 0 auto;
    font-family: var(--font-family);
}

/* Encabezado */
.registro-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
}

.titulo-principal {
    font-family: 'Orbitron', var(--font-display);
    font-size: 32px;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0;
    letter-spacing: 1px;
}

.badge-control {
    background-color: var(--color-primary);
    color: var(--text-white);
    font-size: 12px;
    font-weight: 700;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    letter-spacing: 1.5px;
}

/* Tarjeta Principal */
.registro-card {
    background-color: var(--color-white);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-card);
    padding: 40px 50px;
    max-width: 600px;
    margin: 0 auto;
}

.border-red { border-top: 5px solid var(--color-active); }
.border-blue { border-top: 5px solid var(--color-primary); }

/* Textos de la Tarjeta */
.paso-indicador {
    display: block;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.card-titulo {
    font-family: 'Orbitron', var(--font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--color-primary);
    margin-top: 0;
    margin-bottom: 30px;
}

/* Formularios */
.form-group {
    margin-bottom: 25px;
}

.btn-full {
    width: 100%;
    padding: 14px;
    font-size: 14px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.mt-10 { margin-top: 10px; }
.mt-15 { margin-top: 15px; }

/* Paso 2: Escaneo */
.header-paso2 {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.btn-link {
    background: none;
    border: none;
    color: var(--text-muted);
    text-decoration: underline;
    font-size: 13px;
    cursor: pointer;
}

.btn-link:hover { color: var(--color-active); }

.seccion-escaneo {
    text-align: center;
    padding: 20px 0;
}

.input-central :deep(input) {
    text-align: center;
    font-size: 18px;
    padding: 15px;
    height: 55px;
}

/* Confirmación */
.seccion-confirmacion {
    text-align: center;
    padding: 10px 0;
}

.foto-contenedor {
    width: 120px;
    height: 120px;
    margin: 0 auto 20px auto;
    border-radius: 50%;
    border: 4px solid var(--color-primary);
    overflow: hidden;
    background-color: var(--color-gray-light);
}

.foto-perfil {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.foto-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: bold;
}

.nombre-estudiante {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 5px 0;
}

.id-estudiante {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0 0 30px 0;
}

.botones-accion {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Alertas */
.alerta {
    margin-top: 25px;
    padding: 15px;
    border-radius: var(--radius-md);
    font-size: 14px;
    text-align: center;
    font-weight: 600;
}

.alerta-error {
    background-color: #fef2f2;
    color: var(--color-danger);
    border-left: 4px solid var(--color-danger);
}

.alerta-exito {
    background-color: #f0fdf4;
    color: #15803d;
    border-left: 4px solid #16a34a;
}

/* Barra oscura del Paso 2 */
.resumen-bar {
    background-color: var(--color-primary);
    color: var(--text-white);
    padding: 20px 30px;
    border-radius: var(--radius-md);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.resumen-info {
    display: flex;
    gap: 40px;
}

.resumen-item {
    display: flex;
    flex-direction: column;
}

.resumen-label {
    font-size: 11px;
    color: #8fa0b3;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.resumen-valor {
    font-family: 'Orbitron', var(--font-display);
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.text-red {
    color: var(--color-active);
}

.btn-cambiar {
    background: transparent;
    border: 1px solid #8fa0b3;
    color: var(--text-white);
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-cambiar:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.contador-ingresos {
    text-align: right;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 20px;
}

.contador-ingresos strong {
    color: var(--color-primary);
}

/* Tarjeta del buscador */
.formulario-card {
    background: var(--color-white);
    border: 1px solid var(--border-light);
    padding: 30px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.flex-inline-input {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.flex-1 {
    flex: 1;
}

.btn-buscar {
    padding: 0 40px !important;
    height: 42px; /* Misma altura que el TextInput */
}

/* Botones inferiores de acción */
.acciones-inferiores {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
}

.btn-limpiar {
    background-color: var(--color-bg-base) !important;
    border: 1px solid var(--border-light) !important;
    color: var(--color-primary) !important;
    font-weight: bold;
}

.btn-incidencia {
    background-color: transparent !important;
    color: var(--color-active) !important;
    border: 1px solid var(--color-active) !important;
    font-weight: bold;
}

.btn-incidencia:hover {
    background-color: #fef2f2 !important;
}
</style>