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

const imageLoadError = ref(false);

const fotoActualUrl = computed(() => {
    if (previewFoto.value) return previewFoto.value;

    const raw = props.estudiante?.foto_url || props.estudiante?.foto_path;
    if (!raw) return null;

    // Si ya es una URL completa o Base64
    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:')) {
        return raw;
    }

    // Si ya empieza con '/storage/'
    if (raw.startsWith('/storage/')) {
        return raw;
    }

    // Si empieza con 'storage/' sin barra inicial
    if (raw.startsWith('storage/')) {
        return `/${raw}`;
    }

    // Si viene solo la ruta interna (ej. 'fotos/abc.jpg' o '/fotos/abc.jpg')
    const clean = raw.startsWith('/') ? raw.slice(1) : raw;
    return `/storage/${clean}`;
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

const comprimirImagen = (file) => {
    return new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            URL.revokeObjectURL(url);
            const max = 800;
            let w = img.width, h = img.height;
            if (w > max || h > max) {
                if (w > h) { h = Math.round((h * max) / w); w = max; }
                else { w = Math.round((w * max) / h); h = max; }
            }
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            canvas.toBlob((blob) => {
                if (!blob) return resolve(file);
                resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.82);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(file);
        };
        img.src = url;
    });
};

const handleFileChange = async (e) => {
    const file = e.target.files?.[0];
    fileError.value = '';
    if (!file) return;

    const validExts = /\.(jpe?g|png|webp|heic|heif)$/i;
    if (!file.type.startsWith('image/') && !validExts.test(file.name)) {
        fileError.value = 'Formato no soportado. Selecciona una imagen válida.';
        toast.error(fileError.value);
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    const processedFile = await comprimirImagen(file);
    if (previewFoto.value) URL.revokeObjectURL(previewFoto.value);
    previewFoto.value = URL.createObjectURL(processedFile);
    photoForm.foto = processedFile;
    if (fileInput.value) fileInput.value.value = '';
};

const confirmarSubidaFoto = () => {
    if (!photoForm.foto || photoForm.processing) return;

    photoForm.post('/estudiante/foto', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success('Tu fotografía de perfil se ha actualizado correctamente.');
            cancelarSubidaFoto();
        },
        onError: (errors) => {
            fileError.value = errors.foto || 'Ocurrió un error al subir la fotografía.';
            toast.error(fileError.value);
        }
    });
};

const cancelarSubidaFoto = () => {
    imageLoadError.value = false;
    if (previewFoto.value) URL.revokeObjectURL(previewFoto.value);
    previewFoto.value = null;
    photoForm.reset('foto');
    fileError.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

const handleRegenerateQr = () => {
    if (isRegeneratingQr.value) return;

    const confirmado = window.confirm(
        'Esto invalidará tu código QR actual (incluyendo impresiones o capturas anteriores). ¿Estás seguro de continuar?'
    );
    if (!confirmado) return;

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
                                    v-if="fotoActualUrl && !imageLoadError"
                                    :src="fotoActualUrl"
                                    :alt="`Foto de ${estudiante.nombres}`"
                                    class="avatar-photo-img"
                                    @error="imageLoadError = true"
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
                                accept="image/png, image/jpeg, image/jpg, image/webp, image/heic, image/heif, .heic, .heif"
                                class="hidden-file-input"
                            />
                        </div>
                        
                        <!-- Botones Aceptar / Cancelar vista previa de foto -->
                        <div v-if="previewFoto" class="photo-preview-actions">
                            <button
                                imageLoadError.value = false;
                                type="button"
                                class="btn-confirm-photo"
                                :disabled="photoForm.processing"
                                @click="confirmarSubidaFoto"
                            >
                                {{ photoForm.processing ? 'Guardando...' : 'Aceptar' }}
                            </button>
                            <button
                                type="button"
                                class="btn-cancel-photo"
                                :disabled="photoForm.processing"
                                @click="cancelarSubidaFoto"
                            >
                                Cancelar
                            </button>
                        </div>

                        <!-- Nombre Completo -->
                        <h2 class="student-full-name">
                            {{ estudiante.nombres }}<br>{{ estudiante.apellidos }}
                        </h2>
                        
                        <!-- Carrera -->
                        <p v-if="estudiante.carrera" class="student-career-text">
                            {{ estudiante.carrera }}
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
                            <!-- SVG generado desde el propio sistema -->
                            <div 
                                v-if="estudiante.tiene_examen_activo && estudiante.qr_svg" 
                                class="qr-svg-content" 
                                v-html="estudiante.qr_svg"
                            ></div>

                            <!-- Mensaje CS1 cuando no hay exámenes -->
                            <div v-else-if="!estudiante.tiene_examen_activo" class="text-center p-4">
                                <p class="text-sm font-semibold text-gray-800">No hay un código QR disponible en este momento.</p>
                                <p class="text-xs text-gray-500 mt-2">El código QR se habilitará cuando tengas un examen pendiente o en curso.</p>
                            </div>

                            <div v-else class="qr-empty-placeholder">
                                <span>No disponible</span>
                            </div>
                        </div>

                        <!-- Ocultar Token y Botón si no hay examen activo -->
                        <span v-if="estudiante.tiene_examen_activo" class="qr-token-string">
                            {{ formattedToken }}
                        </span>

                        <button 
                            v-if="estudiante.tiene_examen_activo"
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