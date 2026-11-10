<script setup>
defineProps({
    name: String,
    initials: String,
    referenceUrl: String,
    currentUrl: String,
    referenceError: Boolean,
    currentError: Boolean,
});

defineEmits(['reference-error', 'current-error']);
</script>

<template>
    <div class="photo-comparison-block">
        <div class="photo-comparison-grid">
            <!-- Foto de Referencia -->
            <div class="photo-column">
                <span class="photo-label-badge reference-badge">Foto de Referencia</span>
                <div class="photo-container">
                    <img
                        v-if="referenceUrl && !referenceError"
                        :src="referenceUrl"
                        :alt="`Foto de Referencia de ${name}`"
                        class="photo-img"
                        @error="$emit('reference-error')"
                    />
                    <div v-else class="photo-placeholder">
                        <span class="placeholder-initials">{{ initials }}</span>
                        <span class="placeholder-text">Sin foto de referencia</span>
                    </div>
                </div>
            </div>

            <!-- Foto Actual -->
            <div class="photo-column">
                <span class="photo-label-badge current-badge">Foto Actual</span>
                <div class="photo-container">
                    <img
                        v-if="currentUrl && !currentError"
                        :src="currentUrl"
                        :alt="`Foto Actual de ${name}`"
                        class="photo-img"
                        @error="$emit('current-error')"
                    />
                    <div v-else class="photo-placeholder">
                        <span class="placeholder-initials">{{ initials }}</span>
                        <span class="placeholder-text">Sin foto actual</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.photo-comparison-block {
    padding: 12px 20px 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.photo-comparison-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    max-width: 360px;
    margin: 0 auto;
}
@media (min-width: 640px) {
    .photo-comparison-grid {
        gap: 16px;
        max-width: 400px;
    }
}
.photo-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}
.photo-label-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 9999px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    text-align: center;
}
.reference-badge {
    background-color: rgba(29, 54, 83, 0.1);
    color: var(--color-primary);
    border: 1px solid rgba(29, 54, 83, 0.25);
}
.current-badge {
    background-color: rgba(161, 43, 51, 0.1);
    color: var(--color-active);
    border: 1px solid rgba(161, 43, 51, 0.25);
}
.photo-container {
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 12px;
    overflow: hidden;
    background: #e2e8f0;
    border: 2px solid #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.photo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
    text-align: center;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}
.placeholder-initials {
    font-size: 24px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
    font-family: var(--font-display);
}
.placeholder-text {
    font-size: 10px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    line-height: 1.1;
}
</style>
