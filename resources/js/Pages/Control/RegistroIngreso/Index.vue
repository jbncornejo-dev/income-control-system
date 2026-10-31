<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import { Camera, Keyboard, UserCheck, UserX, RotateCcw, AlertTriangle, CheckCircle, XCircle, ScanLine, Shield, Users } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted } from 'vue';

onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    // Cargar cola de pendientes existente
    const pendientes = localStorage.getItem('ingresos_pendientes');
    if (pendientes) colaPendientes.value = JSON.parse(pendientes);
});

onBeforeUnmount(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});

function handleOnline() {
    isOffline.value = false;
    if (colaPendientes.value.length > 0) sincronizar();
}
function handleOffline() {
    isOffline.value = true;
}

const isOffline = ref(!navigator.onLine);
const colaPendientes = ref([]);
const sublistaOffline = ref([]);

const props = defineProps({
    examenes: { type: Array, required: true },
});

/* ──────────────── Estado general ──────────────── */
const currentStep           = ref(1);          // 1 = seleccionar examen, 2 = escaneo
const selectedExamenId      = ref('');
const selectedAmbienteId    = ref('');

const inputMode             = ref('camera');    // 'camera' | 'manual'
const datoEstudiante        = ref('');
const estudianteValidado    = ref(null);       // datos del estudiante validado OK
const estudianteError       = ref(null);       // datos del estudiante con error (inhabilitado, ya ingresó)
const errorMessage          = ref('');
const successMessage        = ref('');
const isLoading             = ref(false);
const ingresosRegistrados   = ref(0);
const inputRef              = ref(null);
const misAsignadosList      = ref([]);
const mostrarMisAsignados   = ref(false);
const filtroAsignados       = ref('');
const filtroEstadoAsignados = ref('todos'); // 'todos' | 'pendientes' | 'ingresados'

const cameraReady           = ref(false);
const cameraError           = ref(null);
const lastScannedCode       = ref('');
const scanCooldown          = ref(false);

/* ──────────────── Computed helpers ──────────────── */
const selectedExamenData = computed(() => {
    const e = props.examenes.find(ex => ex.id_examen == selectedExamenId.value);
    if (!e) return null;
    return e;
});

const selectedExamenLabel = computed(() => {
    const e = selectedExamenData.value;
    return e ? `${e.asignatura} — ${e.hora_inicio}` : '';
});

const selectedAmbienteLabel = computed(() => {
    const a = ambientesOptions.value.find(o => o.value == selectedAmbienteId.value);
    return a ? a.label : '';
});

const ambientesDisponibles = computed(() => {
    if (!selectedExamenId.value) return [];
    const e = props.examenes.find(ex => ex.id_examen == selectedExamenId.value);
    return e ? e.ambientes : [];
});

const examenesOptions = computed(() => props.examenes.map(e => ({
    value: e.id_examen,
    label: `${e.asignatura} - ${e.fecha} ${e.hora_inicio}`,
})));

const ambientesOptions = computed(() => ambientesDisponibles.value.map(a => ({
    value: a.id_examen_ambiente,
    label: a.nombre_ambiente,
})));

const fotoUrl = computed(() => {
    const src = estudianteValidado.value || estudianteError.value;
    if (!src?.foto_url) return null;
    const raw = src.foto_url;
    if (raw.startsWith('http') || raw.startsWith('data:') || raw.startsWith('/storage/')) return raw;
    if (raw.startsWith('storage/')) return `/${raw}`;
    const clean = raw.startsWith('/') ? raw.slice(1) : raw;
    return `/storage/${clean}`;
});

const iniciales = computed(() => {
    const src = estudianteValidado.value || estudianteError.value;
    if (!src) return '';
    const n = src.nombres?.trim()?.[0] ?? '';
    const a = src.apellidos?.trim()?.[0] ?? '';
    return `${n}${a}`.toUpperCase();
});

const misPendientesCount = computed(() => {
    return misAsignadosList.value.filter(s => !s.ya_ingreso).length;
});

const misAsignadosFiltrados = computed(() => {
    let lista = misAsignadosList.value;
    if (filtroEstadoAsignados.value === 'pendientes') {
        lista = lista.filter(s => !s.ya_ingreso);
    } else if (filtroEstadoAsignados.value === 'ingresados') {
        lista = lista.filter(s => s.ya_ingreso);
    }
    if (filtroAsignados.value.trim()) {
        const query = filtroAsignados.value.toLowerCase().trim();
        lista = lista.filter(s =>
            s.nombres.toLowerCase().includes(query) ||
            s.apellidos.toLowerCase().includes(query) ||
            s.codigo_universitario.includes(query) ||
            (s.documento_identidad && s.documento_identidad.toLowerCase().includes(query))
        );
    }
    return lista;
});

function seleccionarDesdeAsignados(codigo) {
    mostrarMisAsignados.value = false;
    buscarEstudiante(codigo);
}

/* ──────────────── Paso 1 → 2 ──────────────── */
const iniciarRegistro = async () => {
    if (!selectedExamenId.value || !selectedAmbienteId.value) return;
    currentStep.value = 2;
    resetEstado();
    
    const asignados = selectedExamenData.value?.mis_asignados
        ? JSON.parse(JSON.stringify(selectedExamenData.value.mis_asignados))
        : [];
        
    misAsignadosList.value = asignados;
    
    // GUARDAR OFFLINE (Task 3)
    if (!isOffline.value) {
        localStorage.setItem(`sublista_examen_${selectedExamenId.value}`, JSON.stringify(asignados));
        sublistaOffline.value = asignados;
    } else {
        const cached = localStorage.getItem(`sublista_examen_${selectedExamenId.value}`);
        if (cached) sublistaOffline.value = JSON.parse(cached);
    }

    if (inputMode.value === 'manual') {
        await nextTick();
        focusInput();
    }
};

/* ──────────────── QR Camera ──────────────── */
function onCameraReady() {
    cameraReady.value = true;
    cameraError.value = null;
}

function onCameraError(err) {
    cameraReady.value = false;
    if (err.name === 'NotAllowedError') {
        cameraError.value = 'Permiso de cámara denegado. Habilita el acceso en tu navegador.';
    } else if (err.name === 'NotFoundError') {
        cameraError.value = 'No se encontró una cámara disponible.';
    } else {
        cameraError.value = 'Error al acceder a la cámara: ' + err.message;
    }
    // Auto-switch to manual on camera error
    inputMode.value = 'manual';
}

async function onQrDetected(detectedCodes) {
    if (!detectedCodes || detectedCodes.length === 0) return;
    const code = detectedCodes[0].rawValue;
    if (!code || scanCooldown.value) return;
    if (code === lastScannedCode.value && (estudianteValidado.value || estudianteError.value)) return;

    lastScannedCode.value = code;
    scanCooldown.value = true;
    
    await buscarEstudiante(code);
    
    // Cooldown de 2s para evitar lecturas repetidas
    setTimeout(() => { scanCooldown.value = false; }, 2000);
}

/* ──────────────── Manual Input ──────────────── */
function focusInput() {
    const el = inputRef.value?.$el?.querySelector?.('input') || inputRef.value;
    el?.focus?.();
}

async function submitManual() {
    if (!datoEstudiante.value.trim()) return;
    await buscarEstudiante(datoEstudiante.value.trim());
    datoEstudiante.value = '';
}

/* ──────────────── Core: buscar y validar ──────────────── */
async function buscarEstudiante(dato) {
    isLoading.value    = true;
    errorMessage.value = '';
    successMessage.value = '';
    estudianteValidado.value = null;
    estudianteError.value    = null;

    try {
        const response = await axios.post('/registro-ingreso/validar', {
            id_examen: selectedExamenId.value,
            dato_estudiante: dato,
        });
        estudianteValidado.value = response.data;
    } catch (error) {
        const status = error.response?.status;
        const data   = error.response?.data;

        errorMessage.value = data?.error || 'Error al validar al estudiante.';

        // Si vino con datos del estudiante (inhabilitado o ya ingresó)
        if (data?.estudiante) {
            estudianteError.value = data.estudiante;
        }
    } finally {
        isLoading.value = false;
    }
}

/* ──────────────── Confirmar / Rechazar ingreso ──────────────── */
async function confirmarIngreso() {
    if (!estudianteValidado.value) return;
    isLoading.value    = true;
    errorMessage.value = '';

    const idEstudiante = estudianteValidado.value.id_estudiante;
    const asignado = misAsignadosList.value.find(s => s.id_estudiante === idEstudiante);

    // MODO OFFLINE (Task 3)
    if (isOffline.value) {
        const nuevoRegistro = {
            uuid_offline: crypto.randomUUID(),
            id_estudiante: idEstudiante,
            id_examen_ambiente: selectedAmbienteId.value,
            fecha_hora_intento: new Date().toISOString()
        };
        
        colaPendientes.value.push(nuevoRegistro);
        localStorage.setItem('ingresos_pendientes', JSON.stringify(colaPendientes.value));
        
        // Actualizar UI local
        successMessage.value = `✓ (OFFLINE) Encolado: ${estudianteValidado.value.nombres} ${estudianteValidado.value.apellidos}`;
        ingresosRegistrados.value++;
        if (asignado) asignado.ya_ingreso = true;
        
        // Actualizar cache de sublista para no permitir doble ingreso offline
        const subIndex = sublistaOffline.value.findIndex(s => s.id_estudiante === idEstudiante);
        if (subIndex > -1) {
            sublistaOffline.value[subIndex].ya_ingreso = true;
            localStorage.setItem(`sublista_examen_${selectedExamenId.value}`, JSON.stringify(sublistaOffline.value));
        }

        resetAfterSubmit();
        return;
    }

    // MODO ONLINE (código original)
    try {
        await axios.post('/registro-ingreso', {
            id_estudiante: idEstudiante,
            id_examen_ambiente: selectedAmbienteId.value,
        });
        successMessage.value = `✓ Ingreso registrado: ${estudianteValidado.value.nombres} ${estudianteValidado.value.apellidos}`;
        ingresosRegistrados.value++;
        if (asignado) asignado.ya_ingreso = true;
        
        resetAfterSubmit();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Error al registrar el ingreso.';
        isLoading.value = false;
    }
}

async function resetAfterSubmit() {
    estudianteValidado.value = null;
    lastScannedCode.value    = '';
    if (inputMode.value === 'manual') {
        await nextTick();
        focusInput();
    }
    isLoading.value = false;
}

async function sincronizar() {
    if (isOffline.value || colaPendientes.value.length === 0) return;
    
    try {
        const response = await axios.post('/registro-ingreso/sync', {
            registros: colaPendientes.value
        });
        
        const data = response.data;
        const totalProcesados = data.aceptados.length + data.duplicados.length + data.rechazados.length + data.conflictos.length;
        
        if (totalProcesados > 0) {
            // Limpiar la cola local si el servidor respondió correctamente
            colaPendientes.value = [];
            localStorage.removeItem('ingresos_pendientes');
            successMessage.value = `Sincronización completada: ${data.aceptados.length} aceptados, ${data.conflictos.length} conflictos detectados.`;
        }
    } catch (error) {
        errorMessage.value = "Error al sincronizar con el servidor.";
    }
}

function rechazarIngreso() {
    estudianteValidado.value = null;
    estudianteError.value    = null;
    errorMessage.value       = '';
    successMessage.value     = '';
    lastScannedCode.value    = '';
    if (inputMode.value === 'manual') {
        nextTick(() => focusInput());
    }
}

/* ──────────────── Reset / Utils ──────────────── */
function resetEstado() {
    estudianteValidado.value = null;
    estudianteError.value    = null;
    errorMessage.value       = '';
    successMessage.value     = '';
    lastScannedCode.value    = '';
    datoEstudiante.value     = '';
}

function cambiarExamen() {
    currentStep.value = 1;
    resetEstado();
}

function toggleMode() {
    inputMode.value = inputMode.value === 'camera' ? 'manual' : 'camera';
    resetEstado();
    if (inputMode.value === 'manual') {
        nextTick(() => focusInput());
    }
}

function irAIncidencias() {
    router.visit('/incidencias');
}
</script>

<template>
    <Head title="Control de Ingreso - Escaneo QR" />

    <AuthenticatedLayout>
        <div class="registro-wrapper">

            <!-- ═══════════════ PASO 1: Selección ═══════════════ -->
            <div v-if="currentStep === 1" class="paso1-container">
                <div class="paso1-header">
                    <div class="header-icon-box">
                        <Shield :size="28" />
                    </div>
                    <div>
                        <h1 class="titulo-principal">Control de Ingreso</h1>
                        <p class="subtitulo">Selecciona el examen y ambiente para iniciar el registro</p>
                    </div>
                </div>

                <div class="paso1-card">
                    <span class="paso-indicador">PASO 1 DE 2</span>
                    <h2 class="card-titulo">Configurar Sesión de Control</h2>

                    <div class="form-group">
                        <SelectInput
                            label="EXAMEN"
                            v-model="selectedExamenId"
                            :options="examenesOptions"
                            placeholder="Seleccione un examen..."
                            @change="selectedAmbienteId = ''"
                        />
                    </div>

                    <!-- Detalle del examen seleccionado -->
                    <div v-if="selectedExamenData" class="examen-preview">
                        <div class="preview-row">
                            <span class="preview-label">Asignatura</span>
                            <span class="preview-value">{{ selectedExamenData.asignatura }}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Fecha</span>
                            <span class="preview-value">{{ selectedExamenData.fecha }}</span>
                        </div>
                        <div class="preview-row">
                            <span class="preview-label">Hora</span>
                            <span class="preview-value">{{ selectedExamenData.hora_inicio }}</span>
                        </div>
                        <div v-if="selectedExamenData.estado" class="preview-row">
                            <span class="preview-label">Estado</span>
                            <span :class="['estado-badge', `estado-${selectedExamenData.estado}`]">
                                {{ selectedExamenData.estado }}
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <SelectInput
                            label="AMBIENTE"
                            v-model="selectedAmbienteId"
                            :options="ambientesOptions"
                            :disabled="!selectedExamenId"
                            placeholder="Seleccione un ambiente..."
                        />
                    </div>

                    <Button
                        @click="iniciarRegistro"
                        :disabled="!selectedExamenId || !selectedAmbienteId"
                        variant="primary"
                        class="btn-full"
                    >
                        <ScanLine :size="18" class="btn-icon" />
                        INICIAR CONTROL DE INGRESO
                    </Button>
                </div>
            </div>

            <!-- ═══════════════ PASO 2: Escaneo ═══════════════ -->
            <div v-if="currentStep === 2" class="paso2-container">

                <!-- Barra de contexto -->
                <div class="context-bar">
                    <div class="context-info">
                        <div class="context-item">
                            <span class="context-label">EXAMEN</span>
                            <span class="context-value">{{ selectedExamenLabel }}</span>
                        </div>
                        <div class="context-divider"></div>
                        <div class="context-item">
                            <span class="context-label">AMBIENTE</span>
                            <span class="context-value context-highlight">{{ selectedAmbienteLabel }}</span>
                        </div>
                        <div class="context-divider"></div>
                        <div class="context-item">
                            <span class="context-label">INGRESOS</span>
                            <span class="context-value context-count">{{ ingresosRegistrados }}</span>
                        </div>
                        <div class="context-divider"></div>
                        <div class="context-item">
                            <span class="context-label">MIS PENDIENTES</span>
                            <span class="context-value context-pending">{{ misPendientesCount }}</span>
                        </div>
                    </div>
                    <div class="context-actions">
                        <button
                            type="button"
                            @click="mostrarMisAsignados = !mostrarMisAsignados"
                            :class="['btn-asignados', { active: mostrarMisAsignados }]"
                        >
                            <Users :size="14" />
                            <span>MIS ASIGNADOS</span>
                            <span class="badge-pendientes">{{ misPendientesCount }}</span>
                        </button>
                        <button @click="cambiarExamen" class="btn-cambiar">
                            <RotateCcw :size="14" />
                            CAMBIAR
                        </button>
                    </div>
                </div>

                <!-- Banner Offline -->
                <div v-if="isOffline || colaPendientes.length > 0" 
                    class="alert" 
                    :class="isOffline ? 'alert-error' : 'alert-success'"
                    style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <AlertTriangle v-if="isOffline" :size="18" />
                        <CheckCircle v-else :size="18" />
                        <span v-if="isOffline">Sin conexión — Trabajando localmente ({{ colaPendientes.length }} pendientes)</span>
                        <span v-else>Conexión recuperada — {{ colaPendientes.length }} registros pendientes</span>
                    </div>
                    
                    <button v-if="!isOffline && colaPendientes.length > 0" 
                            @click="sincronizar" 
                            class="btn-validar-item" 
                            style="margin: 0; background: #059669;">
                        Sincronizar ahora
                    </button>
                </div>
                
                <!-- ═══ Sublista filtrable: Mis Asignados ═══ -->
                <div v-if="mostrarMisAsignados" class="asignados-card">
                    <div class="asignados-header">
                        <div class="asignados-titulo-box">
                            <Users :size="18" />
                            <h3 class="asignados-titulo">Sublista de Estudiantes Asignados</h3>
                        </div>
                        <span class="asignados-resumen">{{ misPendientesCount }} pendientes de {{ misAsignadosList.length }}</span>
                    </div>

                    <div class="asignados-filtros">
                        <input
                            v-model="filtroAsignados"
                            type="text"
                            placeholder="Buscar por nombre, código SIS o CI..."
                            class="input-filtro"
                        />
                        <div class="estado-filtros">
                            <button
                                type="button"
                                :class="['btn-filtro-tag', { active: filtroEstadoAsignados === 'todos' }]"
                                @click="filtroEstadoAsignados = 'todos'"
                            >
                                Todos ({{ misAsignadosList.length }})
                            </button>
                            <button
                                type="button"
                                :class="['btn-filtro-tag', { active: filtroEstadoAsignados === 'pendientes' }]"
                                @click="filtroEstadoAsignados = 'pendientes'"
                            >
                                Pendientes ({{ misPendientesCount }})
                            </button>
                            <button
                                type="button"
                                :class="['btn-filtro-tag', { active: filtroEstadoAsignados === 'ingresados' }]"
                                @click="filtroEstadoAsignados = 'ingresados'"
                            >
                                Ingresados ({{ misAsignadosList.length - misPendientesCount }})
                            </button>
                        </div>
                    </div>

                    <div class="asignados-lista">
                        <div v-if="misAsignadosFiltrados.length === 0" class="asignados-vacio">
                            No se encontraron estudiantes en este filtro.
                        </div>
                        <div
                            v-for="item in misAsignadosFiltrados"
                            :key="item.id_habilitacion"
                            class="asignado-item"
                        >
                            <div class="asignado-info">
                                <span class="asignado-nombre">{{ item.nombres }} {{ item.apellidos }}</span>
                                <span class="asignado-sub">SIS: {{ item.codigo_universitario }} <span v-if="item.documento_identidad">· CI: {{ item.documento_identidad }}</span></span>
                            </div>
                            <div class="asignado-accion">
                                <span v-if="item.ya_ingreso" class="badge-ingresado">Ingresó</span>
                                <button
                                    v-else
                                    type="button"
                                    class="btn-validar-item"
                                    @click="seleccionarDesdeAsignados(item.codigo_universitario)"
                                >
                                    Validar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toggle de modo -->
                <div class="mode-toggle">
                    <button
                        :class="['mode-btn', { active: inputMode === 'camera' }]"
                        @click="inputMode !== 'camera' && toggleMode()"
                    >
                        <Camera :size="18" />
                        <span>Cámara QR</span>
                    </button>
                    <button
                        :class="['mode-btn', { active: inputMode === 'manual' }]"
                        @click="inputMode !== 'manual' && toggleMode()"
                    >
                        <Keyboard :size="18" />
                        <span>Código Manual</span>
                    </button>
                </div>

                <!-- ═══ Área de entrada (cámara o manual) ═══ -->
                <div v-if="!estudianteValidado && !estudianteError" class="input-area">

                    <!-- Modo cámara -->
                    <div v-if="inputMode === 'camera'" class="camera-container">
                        <div class="camera-viewport">
                            <QrcodeStream
                                @detect="onQrDetected"
                                @camera-on="onCameraReady"
                                @error="onCameraError"
                            >
                                <!-- Overlay de escaneo -->
                                <div class="scan-overlay">
                                    <div class="scan-frame">
                                        <div class="corner tl"></div>
                                        <div class="corner tr"></div>
                                        <div class="corner bl"></div>
                                        <div class="corner br"></div>
                                    </div>
                                    <p v-if="cameraReady && !isLoading" class="scan-hint">
                                        Apunta al código QR del estudiante
                                    </p>
                                    <div v-if="isLoading" class="scan-loading">
                                        <LoadingSpinner size="small" />
                                        <span>Verificando...</span>
                                    </div>
                                </div>
                            </QrcodeStream>
                        </div>
                        <div v-if="cameraError" class="camera-error-box">
                            <AlertTriangle :size="20" />
                            <span>{{ cameraError }}</span>
                        </div>
                    </div>

                    <!-- Modo manual -->
                    <div v-if="inputMode === 'manual'" class="manual-container">
                        <div class="manual-card">
                            <p class="manual-hint">Escribe el código universitario o escanea con lector físico (pistola).</p>
                            <form @submit.prevent="submitManual" class="manual-form">
                                <TextInput
                                    ref="inputRef"
                                    v-model="datoEstudiante"
                                    label="CÓDIGO UNIVERSITARIO / CI"
                                    placeholder="Ej: 202109372"
                                    :disabled="isLoading"
                                    class="flex-1"
                                />
                                <Button
                                    type="submit"
                                    :disabled="!datoEstudiante.trim() || isLoading"
                                    variant="primary"
                                    class="btn-buscar"
                                >
                                    <span v-if="!isLoading">BUSCAR</span>
                                    <LoadingSpinner v-else size="small" />
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ═══ Resultado: Estudiante habilitado (confirmar/rechazar) ═══ -->
                <div v-if="estudianteValidado" class="result-card result-ok">
                    <div class="result-status-bar ok">
                        <CheckCircle :size="20" />
                        <span>ESTUDIANTE HABILITADO</span>
                    </div>

                    <div class="student-info-row">
                        <div class="student-avatar">
                            <img v-if="fotoUrl" :src="fotoUrl" :alt="estudianteValidado.nombres" class="avatar-img" @error="(e) => e.target.style.display='none'" />
                            <span v-else class="avatar-initials">{{ iniciales }}</span>
                        </div>
                        <div class="student-details">
                            <h3 class="student-name">{{ estudianteValidado.nombres }} {{ estudianteValidado.apellidos }}</h3>
                            <div class="student-meta">
                                <span class="meta-badge">
                                    <span class="meta-label">Código SIS</span>
                                    <span class="meta-value">{{ estudianteValidado.codigo_universitario }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button @click="rechazarIngreso" :disabled="isLoading" class="btn-reject">
                            <XCircle :size="20" />
                            RECHAZAR
                        </button>
                        <button @click="confirmarIngreso" :disabled="isLoading" class="btn-accept">
                            <UserCheck :size="20" />
                            <span v-if="!isLoading">ACEPTAR INGRESO</span>
                            <LoadingSpinner v-else size="small" />
                        </button>
                    </div>
                </div>

                <!-- ═══ Resultado: Error (inhabilitado o ya ingresó) ═══ -->
                <div v-if="estudianteError" class="result-card result-error">
                    <div class="result-status-bar error">
                        <UserX :size="20" />
                        <span v-if="estudianteError.inhabilitado">ESTUDIANTE INHABILITADO</span>
                        <span v-else-if="estudianteError.ya_ingreso">INGRESO YA REGISTRADO</span>
                        <span v-else>ACCESO DENEGADO</span>
                    </div>

                    <div class="student-info-row">
                        <div class="student-avatar error-avatar">
                            <img v-if="fotoUrl" :src="fotoUrl" :alt="estudianteError.nombres" class="avatar-img" @error="(e) => e.target.style.display='none'" />
                            <span v-else class="avatar-initials">{{ iniciales }}</span>
                        </div>
                        <div class="student-details">
                            <h3 class="student-name">{{ estudianteError.nombres }} {{ estudianteError.apellidos }}</h3>
                            <div class="student-meta">
                                <span class="meta-badge">
                                    <span class="meta-label">Código SIS</span>
                                    <span class="meta-value">{{ estudianteError.codigo_universitario }}</span>
                                </span>
                            </div>
                            <p class="error-detail">{{ errorMessage }}</p>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button @click="rechazarIngreso" class="btn-dismiss">
                            <RotateCcw :size="18" />
                            ESCANEAR SIGUIENTE
                        </button>
                        <button @click="irAIncidencias" class="btn-incidencia">
                            <AlertTriangle :size="18" />
                            REPORTAR INCIDENCIA
                        </button>
                    </div>
                </div>

                <!-- ═══ Alertas globales (sin datos de estudiante) ═══ -->
                <div v-if="errorMessage && !estudianteError && !estudianteValidado" class="alert alert-error">
                    <AlertTriangle :size="18" />
                    <span>{{ errorMessage }}</span>
                    <button @click="errorMessage = ''" class="alert-close">×</button>
                </div>

                <div v-if="successMessage" class="alert alert-success">
                    <CheckCircle :size="18" />
                    <span>{{ successMessage }}</span>
                    <button @click="successMessage = ''" class="alert-close">×</button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* ═══════════════════════════════════════════════
   LAYOUT & CONTENEDOR
   ═══════════════════════════════════════════════ */
.registro-wrapper {
    padding: 16px;
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    font-family: var(--font-family);
    box-sizing: border-box;
}
@media (min-width: 640px) {
    .registro-wrapper { padding: 24px; }
}
@media (min-width: 1024px) {
    .registro-wrapper { padding: 32px; }
}

/* ═══════════════════════════════════════════════
   PASO 1: SELECCIÓN DE EXAMEN
   ═══════════════════════════════════════════════ */
.paso1-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 32px;
}
.header-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--color-primary), #2a4e73);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(29, 54, 83, 0.3);
}
.titulo-principal {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0;
    letter-spacing: 0.5px;
}
.subtitulo {
    font-size: 14px;
    color: var(--text-muted);
    margin: 4px 0 0;
}

.paso1-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: var(--shadow-card);
    padding: 28px 24px;
    border-top: 4px solid var(--color-active);
}
.paso-indicador {
    display: block;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    margin-bottom: 4px;
}
.card-titulo {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--color-primary);
    margin: 0 0 28px;
}
.form-group {
    margin-bottom: 20px;
}

/* Preview del examen */
.examen-preview {
    background: rgba(29, 54, 83, 0.04);
    border: 1px solid rgba(29, 54, 83, 0.1);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: grid;
    gap: 8px;
}
.preview-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.preview-label {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.preview-value {
    font-size: 14px;
    color: var(--text-dark);
    font-weight: 600;
}

.estado-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.estado-programado {
    background: #dbeafe;
    color: #1d4ed8;
}
.estado-en_curso {
    background: #dcfce7;
    color: #15803d;
}
.estado-suspendido {
    background: #fef3c7;
    color: #b45309;
}

.btn-full {
    width: 100%;
    padding: 14px;
    font-size: 14px;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 8px;
}
.btn-icon { flex-shrink: 0; }

/* ═══════════════════════════════════════════════
   PASO 2: ESCANEO
   ═══════════════════════════════════════════════ */

/* Barra de contexto */
.context-bar {
    background: linear-gradient(135deg, var(--color-primary), #2a4e73);
    color: #fff;
    padding: 16px 20px;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 16px;
    box-shadow: 0 4px 20px rgba(29, 54, 83, 0.25);
}
@media (min-width: 640px) {
    .context-bar {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        padding: 18px 28px;
    }
}
.context-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
@media (min-width: 640px) {
    .context-info {
        flex-direction: row;
        align-items: center;
        gap: 24px;
    }
}
.context-divider {
    display: none;
}
@media (min-width: 640px) {
    .context-divider {
        display: block;
        width: 1px;
        height: 32px;
        background: rgba(255, 255, 255, 0.2);
    }
}
.context-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.context-label {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.55);
    letter-spacing: 1.2px;
}
.context-value {
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
}
.context-highlight {
    color: #fbbf24;
}
.context-count {
    font-size: 22px;
    color: #34d399;
}

.btn-cambiar {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.5px;
    flex-shrink: 0;
    align-self: flex-start;
}
@media (min-width: 640px) {
    .btn-cambiar { align-self: center; }
}
.btn-cambiar:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Toggle de modo */
.mode-toggle {
    display: flex;
    gap: 0;
    background: #f1f5f9;
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 16px;
}
.mode-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    cursor: pointer;
    transition: all 0.25s ease;
    font-family: var(--font-family);
}
.mode-btn.active {
    background: #fff;
    color: var(--color-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.mode-btn:not(.active):hover {
    color: var(--text-dark);
}

/* ═══ Cámara ═══ */
.camera-container {
    border-radius: 16px;
    overflow: hidden;
    background: #000;
    box-shadow: var(--shadow-card);
}
.camera-viewport {
    position: relative;
    width: 100%;
    max-height: 420px;
    overflow: hidden;
}
.camera-viewport :deep(video) {
    width: 100%;
    display: block;
}
.scan-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.scan-frame {
    width: 200px;
    height: 200px;
    position: relative;
}
.corner {
    position: absolute;
    width: 28px;
    height: 28px;
    border-color: #34d399;
    border-style: solid;
    border-width: 0;
}
.corner.tl { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-radius: 6px 0 0 0; }
.corner.tr { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-radius: 0 6px 0 0; }
.corner.bl { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-radius: 0 0 0 6px; }
.corner.br { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-radius: 0 0 6px 0; }

.scan-hint {
    margin-top: 20px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
    font-weight: 600;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    letter-spacing: 0.3px;
}
.scan-loading {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #34d399;
    font-weight: 600;
    font-size: 14px;
}

.camera-error-box {
    background: #fef2f2;
    color: var(--color-danger);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
}

/* ═══ Manual ═══ */
.manual-container {
    margin-bottom: 16px;
}
.manual-card {
    background: #fff;
    border: 1px solid var(--border-light);
    border-radius: 14px;
    padding: 24px;
    box-shadow: var(--shadow-subtle);
}
.manual-hint {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0 0 16px;
}
.manual-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
@media (min-width: 640px) {
    .manual-form {
        flex-direction: row;
        align-items: flex-end;
    }
}
.flex-1 { flex: 1; }
.btn-buscar {
    padding: 0 32px !important;
    height: 42px;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   RESULTADO: TARJETA DE ESTUDIANTE
   ═══════════════════════════════════════════════ */
.result-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
    margin-top: 16px;
    animation: slideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Barra de estado */
.result-status-bar {
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1px;
}
.result-status-bar.ok {
    background: linear-gradient(135deg, #059669, #10b981);
    color: #fff;
}
.result-status-bar.error {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: #fff;
}

/* Info del estudiante */
.student-info-row {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
}
.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-primary), #2a4e73);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.student-avatar.error-avatar {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    border-color: #fecaca;
}
.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-initials {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    font-family: var(--font-display);
}
.student-details {
    flex: 1;
    min-width: 0;
}
.student-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 8px;
    line-height: 1.2;
}
.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(29, 54, 83, 0.06);
    border: 1px solid rgba(29, 54, 83, 0.15);
    border-radius: 8px;
    padding: 6px 12px;
}
.meta-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.meta-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-primary);
    font-family: var(--font-mono);
    letter-spacing: 1px;
}
.error-detail {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--color-danger);
    font-weight: 600;
    line-height: 1.4;
}

/* Botones de acción */
.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-top: 1px solid #e2e8f0;
}

.btn-accept, .btn-reject, .btn-dismiss, .btn-incidencia {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-family: var(--font-family);
}

.btn-accept {
    background: #059669;
    color: #fff;
    border-radius: 0 0 16px 0;
}
.btn-accept:hover:not(:disabled) {
    background: #047857;
}
.btn-accept:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-reject {
    background: #f8fafc;
    color: #64748b;
    border-radius: 0 0 0 16px;
    border-right: 1px solid #e2e8f0;
}
.btn-reject:hover:not(:disabled) {
    background: #f1f5f9;
    color: var(--color-danger);
}

.btn-dismiss {
    background: #f8fafc;
    color: var(--color-primary);
    border-radius: 0 0 0 16px;
    border-right: 1px solid #e2e8f0;
}
.btn-dismiss:hover {
    background: #f1f5f9;
}

.btn-incidencia {
    background: #fef2f2;
    color: var(--color-danger);
    border-radius: 0 0 16px 0;
}
.btn-incidencia:hover {
    background: #fee2e2;
}

/* ═══════════════════════════════════════════════
   ALERTAS
   ═══════════════════════════════════════════════ */
.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    margin-top: 16px;
    animation: slideUp 0.3s ease;
}
.alert-error {
    background: #fef2f2;
    color: var(--color-danger);
    border: 1px solid #fecaca;
}
.alert-success {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    font-size: 20px;
    cursor: pointer;
    padding: 0 4px;
    opacity: 0.6;
    transition: opacity 0.15s;
}
.alert-close:hover {
    opacity: 1;
}

/* ═══ Responsividad final ═══ */
@media (max-width: 480px) {
    .titulo-principal { font-size: 20px; }
    .student-info-row { flex-direction: column; text-align: center; }
    .student-avatar { width: 72px; height: 72px; }
    .student-name { font-size: 18px; }
    .student-meta { justify-content: center; }
    .context-value { font-size: 13px; }
}
.context-pending {
    font-size: 22px;
    color: #fbbf24;
}

.context-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-asignados {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-asignados:hover, .btn-asignados.active {
    background: #fff;
    color: var(--color-primary);
}
.badge-pendientes {
    background: #fbbf24;
    color: #1e293b;
    border-radius: 999px;
    padding: 2px 7px;
    font-size: 11px;
    font-weight: 800;
}

/* Panel desplegable Mis Asignados */
.asignados-card {
    background: #fff;
    border: 1px solid var(--border-light, #e2e8f0);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.asignados-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.asignados-titulo-box {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-primary);
}
.asignados-titulo {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
}
.asignados-resumen {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
}
.asignados-filtros {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 14px;
}
.input-filtro {
    width: 100%;
    padding: 9px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    box-sizing: border-box;
}
.estado-filtros {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.btn-filtro-tag {
    background: #f1f5f9;
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
}
.btn-filtro-tag.active {
    background: var(--color-primary);
    color: #fff;
}
.asignados-lista {
    max-height: 280px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.asignados-vacio {
    text-align: center;
    font-size: 13px;
    color: var(--text-muted);
    padding: 16px;
}
.asignado-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
}
.asignado-nombre {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
    display: block;
}
.asignado-sub {
    font-size: 12px;
    color: var(--text-muted);
}
.badge-ingresado {
    background: #dcfce7;
    color: #15803d;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.btn-validar-item {
    background: var(--color-primary);
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.btn-validar-item:hover {
    opacity: 0.9;
}
</style>