<template>
    <button 
        :type="type" 
        :class="['btn-base', variantClass]"
        :disabled="disabled"
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' }, // primary, action, delete, outline
    disabled: { type: Boolean, default: false }
});

defineEmits(['click']);

const variantClass = computed(() => {
    const variants = {
        primary: 'btn-primary',
        action: 'btn-action',
        delete: 'btn-action-delete',
        outline: 'btn-outline-white'
    };
    return variants[props.variant] || 'btn-primary';
});
</script>

<style scoped>
.btn-base {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: opacity 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-family);
}

.btn-base:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Estilos extraídos de styles.css y styles-login.css */
.btn-primary {
    background-color: var(--color-primary);
    color: var(--text-white);
}

.btn-primary:hover {
    opacity: 0.9;
}

.btn-action {
    padding: 5px 12px;
    background-color: var(--text-white);
    border: 1px solid #9ca3af;
    color: #4b5563;
    font-size: 12px;
}

.btn-action:hover {
    background-color: #f3f4f6;
}

.btn-action-delete {
    padding: 5px 12px;
    background-color: transparent;
    border: 1px solid #fca5a5;
    color: #ef4444;
    font-size: 12px;
}

.btn-action-delete:hover {
    background-color: #fef2f2;
    border-color: #ef4444;
}

.btn-outline-white {
    background-color: transparent;
    color: var(--text-white);
    border: 1px solid var(--text-white);
}

.btn-outline-white:hover {
    background-color: var(--text-white);
    color: var(--color-primary);
}
</style>