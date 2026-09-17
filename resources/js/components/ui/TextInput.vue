<template>
    <div class="input-wrapper">
        <label v-if="label" class="input-label">{{ label }}</label>
        <input 
            :type="type"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :class="['input-control', { 'input-expanded': expanded, 'input-search': isSearch }]"
        />
    </div>
</template>

<script setup>
defineProps({
    modelValue: [String, Number],
    label: String,
    type: { type: String, default: 'text' },
    placeholder: String,
    expanded: { type: Boolean, default: false },
    isSearch: { type: Boolean, default: false }
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
    color: #4b5563;
    margin-bottom: 8px;
    font-family: var(--font-family);
}

.input-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 4px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    font-family: var(--font-family);
}

.input-control:focus {
    border-color: var(--color-primary);
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
</style>