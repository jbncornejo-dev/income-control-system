<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
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

// Formulario reactivo para la subida de foto
const photoForm = useForm({
    foto: null,
});

// Estado de carga para regeneración de QR
const isRegeneratingQr = ref(false);

// Construcción del QR nítido para escaneo
const qrUrl = computed(() => {
    if (!props.estudiante?.qr_token) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=${encodeURIComponent(props.estudiante.qr_token)}`;
});

// Imagen de perfil actual o null para mostrar iniciales
const fotoActualUrl = computed(() => {
    if (previewFoto.value) return previewFoto.value;
    if (props.estudiante?.foto_url) return props.estudiante.foto_url;
    if (props.estudiante?.foto_path) return `/storage/${props.estudiante.foto_path}`;
    return null;
});

// Iniciales para el estado sin foto
const iniciales = computed(() => {
    const n = props.estudiante?.nombres?.trim()?.[0] ?? '';
    const a = props.estudiante?.apellidos?.trim()?.[0] ?? '';
    return `${n}${a}`.toUpperCase();
});

const triggerFileInput = () => {
    fileError.value = '';
    fileInput.value?.click();
};

// Subida de fotografía
const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    fileError.value = '';

    if (!file) return;

    const validFormats = ['image/jpeg', 'image/png', 'image/jpg'];
    const maxSize = 2 * 1024 * 1024; // 2MB

    if (!validFormats.includes(file.type)) {
        fileError.value = 'Formato no soportado. Debe seleccionar una imagen JPG o PNG.';
        toast.showToast({
            title: 'Error de archivo',
            message: fileError.value,
            type: 'error',
        });
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    if (file.size > maxSize) {
        fileError.value = 'La imagen excede el límite máximo de 2 MB.';
        toast.showToast({
            title: 'Tamaño excedido',
            message: fileError.value,
            type: 'error',
        });
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    previewFoto.value = URL.createObjectURL(file);
    photoForm.foto = file;

    // Ruta directa sin ziggy
    photoForm.post('/estudiante/foto', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.showToast({
                title: 'Foto actualizada',
                message: 'Tu fotografía de perfil se ha guardado correctamente.',
                type: 'success',
            });
            photoForm.reset('foto');
            if (fileInput.value) fileInput.value.value = '';
        },
        onError: (errors) => {
            previewFoto.value = null;
            fileError.value = errors.foto || 'Ocurrió un error al subir la fotografía.';
            toast.showToast({
                title: 'Error',
                message: fileError.value,
                type: 'error',
            });
        }
    });
};

// Regeneración de QR
const handleRegenerateQr = () => {
    if (isRegeneratingQr.value) return;

    isRegeneratingQr.value = true;
    // Ruta directa sin ziggy
    router.post('/estudiante/qr/regenerate', {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.showToast({
                title: 'QR actualizado',
                message: 'Se ha generado un nuevo código QR. El anterior ha quedado invalidado.',
                type: 'success',
            });
        },
        onError: () => {
            toast.showToast({
                title: 'Error',
                message: 'No fue posible regenerar el código QR. Intenta nuevamente.',
                type: 'error',
            });
        },
        onFinish: () => {
            isRegeneratingQr.value = false;
        }
    });
};

// Navegación y Cierre de sesión directo
const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <AuthenticatedLayout title="Panel del Estudiante">
        <div class="panel-container">

            <!-- Cabecera Superior -->
            <div class="panel-header">
                <h1 class="panel-title">Mi Panel de Estudiante</h1>
            </div>

            <!-- Tarjeta Central de la Credencial -->
            <div class="credential-card">
                <!-- Línea decorativa superior: color primario a degradado rojo -->
                <div class="card-gradient-bar"></div>

                <!-- Cabecera de Identidad -->
                <div class="student-identity-row">
                    <!-- Avatar con botón de edición -->
                    <div class="avatar-wrapper">
                        <div class="avatar-circle">
                            <img
                                v-if="fotoActualUrl"
                                :src="fotoActualUrl"
                                :alt="`Foto de ${estudiante.nombres}`"
                                class="avatar-img"
                            />
                            <span v-else class="avatar-initials">{{ iniciales }}</span>
                        </div>

                        <!-- Botón circular rojo con lápiz -->
                        <button
                            type="button"
                            @click="triggerFileInput"
                            :disabled="photoForm.processing"
                            class="btn-edit-photo"
                            title="Cambiar fotografía"
                            aria-label="Cambiar fotografía"
                        >
                            <LoadingSpinner v-if="photoForm.processing" size="small" />
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                        <input
                            type="file"
                            ref="fileInput"
                            @change="handleFileChange"
                            accept="image/png, image/jpeg, image/jpg"
                            class="hidden"
                        />
                    </div>

                    <!-- Datos Académicos Principales -->
                    <div class="student-info">
                        <span class="student-status-label">ESTUDIANTE REGISTRADO</span>
                        <h2 class="student-name">
                            {{ estudiante.nombres }} {{ estudiante.apellidos }}
                        </h2>
                        <span class="student-career">
                            {{ estudiante.carrera || 'Ingeniería de Sistemas' }}
                        </span>
                        <div class="student-code-badge">
                            {{ estudiante.codigo_universitario }}
                        </div>
                    </div>
                </div>

                <!-- Notificación de error de archivo -->
                <p v-if="fileError" class="file-error-msg">
                    {{ fileError }}
                </p>

                <!-- Sección de Código QR -->
                <div class="qr-section">
                    <span class="qr-subtitle">CÓDIGO QR DE IDENTIFICACIÓN</span>

                    <!-- Marco azul marino sólido del QR -->
                    <div class="qr-box">
                        <img
                            v-if="qrUrl"
                            :src="qrUrl"
                            alt="Código QR de Verificación"
                            class="qr-image"
                        />
                        <div v-else class="qr-loading">
                            <LoadingSpinner size="medium" />
                        </div>
                    </div>

                    <!-- Código alfanumérico inferior -->
                    <span class="qr-code-label">
                        CIE-{{ estudiante.codigo_universitario }}-0
                    </span>

                    <!-- Botón Estandarizado: Regenerar QR -->
                    <Button
                        variant="delete"
                        class="btn-regenerar"
                        :disabled="isRegeneratingQr"
                        @click="handleRegenerateQr"
                    >
                        <LoadingSpinner v-if="isRegeneratingQr" size="small" />
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isRegeneratingQr ? 'REGENERANDO CÓDIGO...' : 'REGENERAR CÓDIGO QR' }}</span>
                    </Button>

                    <p class="qr-note">
                        Al regenerar, el código anterior queda inválido.
                    </p>
                </div>
            </div>

            <!-- Caja Informativa -->
            <div class="notice-card">
                <p>
                    Presenta este código QR al personal de control al momento de ingresar a tu examen. Mantén la pantalla visible y con brillo al máximo.
                </p>
            </div>

            <!-- Botón Estandarizado: Cerrar Sesión -->
            <Button
                variant="action"
                class="btn-logout"
                @click="handleLogout"
            >
                CERRAR SESIÓN
            </Button>

            <!-- Pie de página -->
            <div class="panel-footer">
                © 2026 TexCorp. Todos los derechos reservados.
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container {
    max-width: 620px;
    margin: 0 auto;
    padding: 2.5rem 1rem 3rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-family: var(--font-family);
}

/* Header */
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.panel-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--color-primary);
    letter-spacing: -0.01em;
}

/* Tarjeta Principal */
.credential-card {
    background-color: var(--text-white, #ffffff);
    border: 1px solid var(--border-light, #e2e8f0);
    border-radius: var(--radius-md, 6px);
    position: relative;
    overflow: hidden;
    padding: 2.25rem 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.card-gradient-bar {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary) 65%, var(--color-danger, #ef4444) 100%);
}

/* Fila de Identidad */
.student-identity-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.avatar-wrapper {
    position: relative;
    flex-shrink: 0;
}

.avatar-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background-color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-cover: cover;
}

.avatar-initials {
    color: var(--text-white, #ffffff);
    font-size: 2rem;
    font-weight: 700;
}

.btn-edit-photo {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: var(--color-danger, #ef4444);
    border: 2px solid var(--text-white, #ffffff);
    color: var(--text-white, #ffffff);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: background-color 0.2s ease;
}

.btn-edit-photo:hover {
    background-color: var(--color-danger-hover, #dc2626);
}

.student-info {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.student-status-label {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.12em;
    margin-bottom: 0.25rem;
}

.student-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--color-primary);
    line-height: 1.25;
    margin-bottom: 0.25rem;
}

.student-career {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 0.65rem;
}

.student-code-badge {
    background-color: #edf2f7;
    border: 1px solid #cbd5e1;
    color: var(--color-primary);
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 3px;
    width: fit-content;
}

.file-error-msg {
    font-size: 12px;
    color: var(--color-danger, #ef4444);
    background-color: #fef2f2;
    padding: 6px 12px;
    border-radius: 4px;
    text-align: center;
    margin-bottom: 1.5rem;
}

/* Sección QR */
.qr-section {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.qr-subtitle {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.14em;
    margin-bottom: 1.25rem;
}

.qr-box {
    border: 3.5px solid var(--color-primary);
    padding: 4px;
    background-color: var(--text-white, #ffffff);
    line-height: 0;
}

.qr-image {
    width: 210px;
    height: 210px;
    object-fit: contain;
}

.qr-loading {
    width: 210px;
    height: 210px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-code-label {
    margin-top: 1rem;
    margin-bottom: 1.25rem;
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: #5c7094;
}

/* Modificadores para los botones reutilizables */
.btn-regenerar {
    border: 1px solid var(--color-danger, #ef4444) !important;
    color: var(--color-danger, #ef4444) !important;
    background-color: var(--text-white, #ffffff) !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0.06em !important;
    padding: 8px 24px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.btn-regenerar:hover {
    background-color: #fef2f2 !important;
}

.qr-note {
    font-size: 11px;
    color: #7d90b8;
    margin-top: 0.75rem;
    text-align: center;
}

/* Cuadro de Aviso Inferior */
.notice-card {
    background-color: #edf2f9;
    border: 1px solid #d8e2ef;
    border-radius: var(--radius-md, 6px);
    padding: 1.1rem 1.5rem;
    text-align: center;
}

.notice-card p {
    font-size: 13px;
    line-height: 1.5;
    color: #4a5f87;
    font-weight: 500;
    margin: 0;
}

/* Botón Cerrar Sesión */
.btn-logout {
    width: 100% !important;
    padding: 12px 20px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    letter-spacing: 0.1em !important;
    color: #4a5f87 !important;
    border: 1px solid #d8e2ef !important;
    background-color: var(--text-white, #ffffff) !important;
    border-radius: var(--radius-md, 6px) !important;
}

.btn-logout:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
}

.panel-footer {
    text-align: right;
    font-size: 11px;
    color: #94a3b8;
    margin-top: -0.25rem;
}
</style>