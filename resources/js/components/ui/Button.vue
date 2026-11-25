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
        outline: 'btn-outline-white',
        danger: 'btn-danger'
    };
    return variants[props.variant] || 'btn-primary';
});
</script>

<style scoped>
.btn-base {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all var(--transition-fast);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-family);
}

.btn-base:focus-visible {
    outline: none;
    box-shadow: var(--shadow-input-focus);
}

.btn-base:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background-color: var(--color-primary);
    color: var(--text-white);
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--color-primary-hover);
}

.btn-action {
    padding: var(--spacing-xs) var(--spacing-sm);
    background-color: var(--text-white);
    border-color: var(--border-light);
    color: var(--text-dark);
    font-size: 12px;
}

.btn-action:hover:not(:disabled) {
    background-color: var(--color-gray-light);
}

.btn-action-delete {
    padding: var(--spacing-xs) var(--spacing-sm);
    background-color: transparent;
    border-color: #fca5a5;
    color: var(--color-danger);
    font-size: 12px;
}

.btn-action-delete:hover:not(:disabled) {
    background-color: #fef2f2;
    border-color: var(--color-danger);
}

.btn-outline-white {
    background-color: transparent;
    color: var(--text-white);
    border-color: var(--text-white);
}

.btn-outline-white:hover:not(:disabled) {
    background-color: var(--text-white);
    color: var(--color-primary);
}

.btn-danger {
    background-color: var(--color-danger);
    color: var(--text-white);
    border-color: var(--color-danger);
}

.btn-danger:hover:not(:disabled) {
    background-color: var(--color-danger-hover);
    border-color: var(--color-danger-hover);
}
</style>