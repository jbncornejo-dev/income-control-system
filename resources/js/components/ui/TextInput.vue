<template>
    <div class="input-wrapper">
        <label v-if="label" class="input-label">{{ label }}</label>
        <input 
            :type="type"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="['input-control', { 'input-expanded': expanded, 'input-search': isSearch, 'input-disabled': disabled }]"        />
    </div>
</template>

<script setup>
defineProps({
    modelValue: [String, Number],
    label: String,
    type: { type: String, default: 'text' },
    placeholder: String,
    expanded: { type: Boolean, default: false },
    isSearch: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }
});

defineEmits(['update:modelValue']);
</script>

<style scoped>
.input-wrapper {
    width: 100%;
}

.input-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-family: var(--font-family);
}

.input-control {
    width: 100%;
    height: 42px;
    padding: 0 0.8rem;
    border: 1px solid var(--border-light);
    border-radius: 0.45rem;
    font-size: 14px;
    color: var(--text-dark);
    background-color: var(--color-cream);
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    font-family: var(--font-family);
}

.input-control:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(29, 54, 83, 0.15);
}

/* Modificador para el buscador heredado de styles.css */
.input-search {
    border: 1px solid var(--color-primary);
    padding: 10px 15px;
}

.input-expanded {
    flex-grow: 1;
    width: auto;
}

/* Modificador para campos deshabilitados (solo texto grisáceo) */
.input-disabled {
    background-color: transparent; /* Quitamos el color de fondo */
    border: 1px solid transparent; /* Ocultamos el borde */
    color: #9ca3af; /* Texto grisáceo */
    box-shadow: none; /* Quitamos sombras */
    cursor: not-allowed; /* Indicador de que no se puede interactuar */
    padding: 0; /* Removemos el padding para que se alinee perfectamente a la izquierda con el label */
}

.input-disabled:focus {
    border-color: transparent;
    box-shadow: none;
}
</style>