<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Camera, RefreshCw, Info } from 'lucide-vue-next';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    estudiante: {
        type: Object,
        required: true,
    }
});

const toast = useToastStore();
const fileInput = ref(null);
const previewFoto = ref(null);
const fileError = ref('');

const photoForm = useForm({
    foto: null,
});

const isRegeneratingQr = ref(false);

// Fallback de URL de QR si no viniese el SVG desde el backend
const qrUrl = computed(() => {
    if (!props.estudiante?.qr_token) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=${encodeURIComponent(props.estudiante.qr_token)}`;
});

const fotoActualUrl = computed(() => {
    if (previewFoto.value) return previewFoto.value;
    if (props.estudiante?.foto_url) return props.estudiante.foto_url;
    if (props.estudiante?.foto_path) return `/storage/${props.estudiante.foto_path}`;
    return null;
});

const iniciales = computed(() => {
    const n = props.estudiante?.nombres?.trim()?.[0] ?? '';
    const a = props.estudiante?.apellidos?.trim()?.[0] ?? '';
    return `${n}${a}`.toUpperCase();
});

const formattedToken = computed(() => {
    if (!props.estudiante?.codigo_universitario) return 'CIE-000000000-0';
    if (props.estudiante.qr_token && props.estudiante.qr_token.includes('-')) {
        const parts = props.estudiante.qr_token.split('-');
        const lastPart = parts[parts.length - 1];
        if (lastPart && /^\d+$/.test(lastPart)) {
            return `CIE-${props.estudiante.codigo_universitario}-${lastPart.slice(-4)}`;
        }
    }
    return `CIE-${props.estudiante.codigo_universitario}-0`;
});

const triggerFileInput = () => {
    fileError.value = '';
    fileInput.value?.click();
};

const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    fileError.value = '';

    if (!file) return;

    const validFormats = ['image/jpeg', 'image/png', 'image/jpg'];
    const maxSize = 2 * 1024 * 1024; // 2MB

    if (!validFormats.includes(file.type)) {
        fileError.value = 'Formato no soportado. Debe seleccionar una imagen JPG o PNG.';
        toast.error(fileError.value);
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    if (file.size > maxSize) {
        fileError.value = 'La imagen excede el límite máximo de 2 MB.';
        toast.error(fileError.value);
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    previewFoto.value = URL.createObjectURL(file);
    photoForm.foto = file;

    photoForm.post('/estudiante/foto', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success('Tu fotografía de perfil se ha actualizado correctamente.');
            photoForm.reset('foto');
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => {
            previewFoto.value = null;
            fileError.value = errors.foto || 'Ocurrió un error al subir la fotografía.';
            toast.error(fileError.value);
        }
    });
};

const handleRegenerateQr = () => {
    if (isRegeneratingQr.value) return;

    isRegeneratingQr.value = true;
    router.post('/estudiante/qr/regenerar', {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Se ha generado un nuevo código QR. El anterior ha quedado invalidado.');
        },
        onError: () => {
            toast.error('No fue posible regenerar el código QR. Intenta nuevamente.');
        },
        onFinish: () => {
            isRegeneratingQr.value = false;
        }
    });
};
</script>

<template>
    <Head title="Mi Credencial - Estudiante" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <div class="credential-wrapper">
                
                <h1 class="panel-title">Identidad Digital</h1>

                <!-- Tarjeta ID -->
                <div class="id-card">
                    
                    <!-- Panel Izquierdo: Datos Personales -->
                    <div class="personal-info-panel">
                        
                        <!-- Marca de agua sutil UMSS -->
                        <div class="watermark-bg">
                            <span>UMSS</span>
                        </div>

                        <span class="status-top-label">Estudiante Registrado</span>
                        
                        <!-- Avatar con botón de edición -->
                        <div class="avatar-container">
                            <div class="avatar-photo-circle">
                                <img
                                    v-if="fotoActualUrl"
                                    :src="fotoActualUrl"
                                    :alt="`Foto de ${estudiante.nombres}`"
                                    class="avatar-photo-img"
                                />
                                <span v-else class="avatar-initials-text">{{ iniciales }}</span>
                            </div>

                            <button
                                type="button"
                                @click="triggerFileInput"
                                :disabled="photoForm.processing"
                                class="btn-camera-upload"
                                title="Cambiar fotografía"
                                aria-label="Cambiar fotografía"
                            >
                                <Camera :size="16" />
                            </button>

                            <input
                                type="file"
                                ref="fileInput"
                                @change="handleFileChange"
                                accept="image/png, image/jpeg, image/jpg"
                                class="hidden-file-input"
                            />
                        </div>

                        <!-- Nombre Completo -->
                        <h2 class="student-full-name">
                            {{ estudiante.nombres }}<br>{{ estudiante.apellidos }}
                        </h2>
                        
                        <!-- Carrera -->
                        <p class="student-career-text">
                            {{ estudiante.carrera || 'Ingeniería de Sistemas' }}
                        </p>
                        
                        <!-- Código SIS -->
                        <div class="sis-code-box">
                            <span class="sis-code-label">Código SIS</span>
                            <div class="sis-code-badge">
                                {{ estudiante.codigo_universitario }}
                            </div>
                        </div>

                    </div>

                    <!-- Panel Derecho: Código QR -->
                    <div class="qr-access-panel">
                        
                        <span class="qr-access-label">Código de Acceso</span>
                        
                        <!-- Contenedor QR -->
                        <div class="qr-frame-box">
                            <!-- Si viene SVG generado desde el backend -->
                            <div 
                                v-if="estudiante.qr_svg" 
                                class="qr-svg-content" 
                                v-html="estudiante.qr_svg"
                            ></div>
                            
                            <!-- Fallback si no viniese el SVG -->
                            <img 
                                v-else-if="qrUrl"
                                :src="qrUrl" 
                                alt="Código QR de Identificación" 
                                class="qr-img-fallback"
                            />

                            <div v-else class="qr-empty-placeholder">
                                <span>No disponible</span>
                            </div>
                        </div>

                        <span class="qr-token-string">
                            {{ formattedToken }}
                        </span>

                        <!-- Botón Regenerar Código QR -->
                        <button 
                            type="button" 
                            class="btn-regenerate-qr" 
                            :disabled="isRegeneratingQr"
                            @click="handleRegenerateQr"
                            title="Generar un nuevo código invalida el actual"
                        >
                            <RefreshCw :size="15" :class="{ 'spin-animation': isRegeneratingQr }" />
                            <span>{{ isRegeneratingQr ? 'REGENERANDO...' : 'REGENERAR CÓDIGO' }}</span>
                        </button>

                    </div>
                </div>

                <!-- Notificación de error de archivo si ocurre -->
                <p v-if="fileError" class="file-error-alert">{{ fileError }}</p>

                <!-- Caja de Aviso Inferior -->
                <div class="notice-info-banner">
                    <div class="notice-icon-wrapper">
                        <Info :size="20" />
                    </div>
                    <div class="notice-text-content">
                        <p>
                            Presenta este código QR al personal de control al momento de ingresar a tu examen. Asegúrate de mantener la pantalla de tu dispositivo con el brillo al máximo para agilizar la lectura.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal con padding responsivo */
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
    padding: 1rem; /* p-4 móvil */
    flex: 1 1 0%;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

@media (min-width: 640px) {
    .panel-container {
        padding: 1.5rem; /* sm:p-6 */
    }
}

@media (min-width: 1024px) {
    .panel-container {
        padding: 2rem; /* lg:p-8 */
    }
}

.credential-wrapper {
    width: 100%;
    max-width: 56rem; /* max-w-4xl (896px) */
    margin: auto;
}

.panel-title {
    font-size: 1.5rem; /* text-2xl */
    line-height: 2rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 1.5rem;
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 0.05em;
    text-align: center;
}

@media (min-width: 768px) {
    .panel-title {
        text-align: left;
    }
}

/* Tarjeta Principal ID */
.id-card {
    background-color: #ffffff;
    border-radius: 1rem; /* rounded-2xl (16px) */
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .id-card {
        flex-direction: row;
    }
}

/* Panel Izquierdo: Datos Personales */
.personal-info-panel {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    border-bottom: 1px solid #f3f4f6;
    background-color: rgba(249, 250, 251, 0.6);
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .personal-info-panel {
        width: 55%;
        align-items: flex-start;
        text-align: left;
        border-bottom: none;
        border-right: 1px solid #f3f4f6;
    }
}

.watermark-bg {
    position: absolute;
    top: 1rem;
    right: 1rem;
    opacity: 0.04;
    pointer-events: none;
}

.watermark-bg span {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: 4rem;
    letter-spacing: -0.05em;
    color: var(--color-primary);
}

.status-top-label {
    font-size: 0.625rem; /* text-[10px] */
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 1.5rem;
}

/* Avatar */
.avatar-container {
    position: relative;
    margin-bottom: 1.5rem;
}

.avatar-photo-circle {
    width: 6rem; /* w-24 (96px) */
    height: 6rem; /* h-24 */
    border-radius: 9999px;
    background-color: var(--color-primary);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 4px solid #ffffff;
    overflow: hidden;
}

.avatar-photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-initials-text {
    font-size: 1.875rem; /* text-3xl */
    font-family: var(--font-display);
    font-weight: 700;
}

.btn-camera-upload {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 2rem; /* w-8 */
    height: 2rem; /* h-8 */
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none;
}

.btn-camera-upload:hover {
    color: var(--color-primary);
    border-color: var(--color-primary);
    transform: scale(1.05);
}

.hidden-file-input {
    display: none;
}

.student-full-name {
    font-size: 1.5rem; /* text-2xl */
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
    margin: 0 0 0.25rem 0;
}

.student-career-text {
    font-size: 0.875rem; /* text-sm */
    font-weight: 500;
    color: #6b7280;
    margin: 0 0 1.5rem 0;
}

.sis-code-box {
    margin-top: auto;
}

.sis-code-label {
    font-size: 0.625rem; /* text-[10px] */
    color: #9ca3af;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 0.25rem;
}

.sis-code-badge {
    display: inline-block;
    background-color: rgba(29, 54, 83, 0.08);
    border: 1px solid rgba(29, 54, 83, 0.2);
    color: var(--color-primary);
    padding: 0.375rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    font-family: var(--font-mono, monospace);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
}

/* Panel Derecho: Código QR */
.qr-access-panel {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: #ffffff;
    position: relative;
}

@media (min-width: 768px) {
    .qr-access-panel {
        width: 45%;
    }
}

.qr-access-label {
    font-size: 0.625rem; /* text-[10px] */
    font-weight: 700;
    color: var(--color-primary);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 1.5rem;
}

.qr-frame-box {
    padding: 0.75rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    box-shadow: 0 4px 20px -4px rgba(29, 54, 83, 0.15);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-svg-content :deep(svg),
.qr-img-fallback {
    width: 180px;
    height: 180px;
    display: block;
}

.qr-empty-placeholder {
    width: 180px;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 0.875rem;
}

.qr-token-string {
    font-family: var(--font-mono, monospace);
    font-size: 0.875rem;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: 0.2em;
    margin-bottom: 1.5rem;
}

.btn-regenerate-qr {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #A12B33;
    background: transparent;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
    outline: none;
}

.btn-regenerate-qr:hover:not(:disabled) {
    background-color: rgba(161, 43, 51, 0.05);
    color: #8B0000;
}

.btn-regenerate-qr:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spin-animation {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.file-error-alert {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 0.75rem;
    text-align: center;
    font-weight: 500;
}

/* Caja de Aviso Inferior */
.notice-info-banner {
    margin-top: 1.5rem;
    background-color: rgba(29, 54, 83, 0.05);
    border: 1px solid rgba(29, 54, 83, 0.12);
    border-radius: 0.75rem;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
}

.notice-icon-wrapper {
    color: var(--color-primary);
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.notice-text-content p {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-primary);
    line-height: 1.6;
    margin: 0;
}
</style>