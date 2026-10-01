<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

// Recibimos los exámenes vigentes desde el backend
const props = defineProps({
    examenes: {
        type: Array,
        required: true
    }
});

// Variables reactivas para el estado de la interfaz
const selectedExamenId = ref('');
const selectedAmbienteId = ref('');
const datoEstudiante = ref('');
const estudianteValidado = ref(null);
const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);
const inputRef = ref(null); // Referencia para auto-enfocar el input

// Computed property para filtrar los ambientes según el examen seleccionado
const ambientesDisponibles = computed(() => {
    if (!selectedExamenId.value) return [];
    const examen = props.examenes.find(e => e.id_examen === selectedExamenId.value);
    return examen ? examen.ambientes : [];
});

// Método 1: Validar al estudiante antes del registro
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
        
        // Si es exitoso, mostramos la tarjeta de confirmación visual
        estudianteValidado.value = response.data;
    } catch (error) {
        // Capturamos los errores 404, 403 o 409 enviados desde el backend
        if (error.response && error.response.data && error.response.data.error) {
            errorMessage.value = error.response.data.error;
        } else {
            errorMessage.value = 'Ocurrió un error de conexión al validar.';
        }
    } finally {
        isLoading.value = false;
        datoEstudiante.value = ''; // Limpiamos el input
    }
};

// Método 2: Confirmar y guardar el registro de ingreso
const registrarIngreso = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        await axios.post('/registro-ingreso', {
            id_estudiante: estudianteValidado.value.id_estudiante,
            id_examen_ambiente: selectedAmbienteId.value
        });
        
        successMessage.value = `Ingreso registrado correctamente para ${estudianteValidado.value.nombres}.`;
        estudianteValidado.value = null; // Limpiamos la pantalla para el siguiente
        
        // Auto-enfocar el input para el siguiente escaneo (prioriza velocidad)
        await nextTick();
        if (inputRef.value) inputRef.value.focus();
        
    } catch (error) {
        if (error.response && error.response.data && error.response.data.error) {
            errorMessage.value = error.response.data.error;
        } else {
            errorMessage.value = 'Error al registrar el ingreso.';
        }
    } finally {
        isLoading.value = false;
    }
};

// Método para cancelar la confirmación y seguir escaneando
const cancelar = async () => {
    estudianteValidado.value = null;
    errorMessage.value = '';
    successMessage.value = '';
    await nextTick();
    if (inputRef.value) inputRef.value.focus();
};
</script>

<template>
    <Head title="Control de Ingreso" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registro de Ingreso a Examen</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Sección 1: Selección de Examen y Ambiente -->
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="examen" class="block text-sm font-medium text-gray-700">1. Seleccionar Examen</label>
                            <select id="examen" v-model="selectedExamenId" @change="selectedAmbienteId = ''" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="" disabled>Seleccione un examen vigentes...</option>
                                <option v-for="examen in examenes" :key="examen.id_examen" :value="examen.id_examen">
                                    {{ examen.asignatura }} - {{ examen.hora_inicio }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="ambiente" class="block text-sm font-medium text-gray-700">2. Seleccionar Ambiente</label>
                            <select id="ambiente" v-model="selectedAmbienteId" :disabled="!selectedExamenId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                                <option value="" disabled>Seleccione el ambiente...</option>
                                <option v-for="ambiente in ambientesDisponibles" :key="ambiente.id_examen_ambiente" :value="ambiente.id_examen_ambiente">
                                    {{ ambiente.nombre_ambiente }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Escaneo/Búsqueda (Solo visible si hay examen y ambiente seleccionados) -->
                <div v-if="selectedExamenId && selectedAmbienteId" class="bg-white p-6 shadow sm:rounded-lg text-center">
                    <label for="datoEstudiante" class="block text-lg font-medium text-gray-700 mb-4">Escanear QR o ingresar código/CI</label>
                    <form @submit.prevent="validarEstudiante">
                        <input 
                            ref="inputRef"
                            id="datoEstudiante" 
                            type="text" 
                            v-model="datoEstudiante" 
                            :disabled="isLoading || estudianteValidado"
                            placeholder="Ingrese código aquí..." 
                            class="mt-1 block w-full md:w-2/3 mx-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-xl p-3"
                            autofocus
                        >
                        <button type="submit" :disabled="!datoEstudiante || isLoading" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                            {{ isLoading && !estudianteValidado ? 'Buscando...' : 'Buscar' }}
                        </button>
                    </form>
                </div>

                <!-- Mensajes de Error y Éxito -->
                <div v-if="errorMessage" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 shadow sm:rounded-lg" role="alert">
                    <p class="font-bold">Error de ingreso</p>
                    <p>{{ errorMessage }}</p>
                </div>
                <div v-if="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 shadow sm:rounded-lg" role="alert">
                    <p>{{ successMessage }}</p>
                </div>

                <!-- Sección 3: Confirmación Visual del Estudiante -->
                <div v-if="estudianteValidado" class="bg-white p-6 shadow sm:rounded-lg text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Confirmar Identidad</h3>
                    
                    <div class="flex flex-col items-center justify-center space-y-4">
                        <div class="w-32 h-32 rounded-full overflow-hidden bg-gray-200 border-2 border-gray-300 flex items-center justify-center">
                            <img v-if="estudianteValidado.foto_url" :src="estudianteValidado.foto_url" alt="Foto del estudiante" class="w-full h-full object-cover">
                            <svg v-else class="h-20 w-20 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        
                        <div>
                            <p class="text-2xl font-bold text-gray-800">{{ estudianteValidado.nombres }} {{ estudianteValidado.apellidos }}</p>
                            <p class="text-gray-500">ID: {{ estudianteValidado.id_estudiante }}</p>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-center space-x-4">
                        <button @click="cancelar" :disabled="isLoading" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            Cancelar
                        </button>
                        <button @click="registrarIngreso" :disabled="isLoading" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                            {{ isLoading ? 'Registrando...' : 'Confirmar Ingreso' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>