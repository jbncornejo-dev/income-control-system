<template>
    <div class="select-wrapper">
        <label v-if="label" class="select-label">{{ label }}</label>
        <select 
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
            class="filter-select"
        >
            <option value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </div>
</template>

<script setup>
defineProps({
    modelValue: [String, Number],
    label: String,
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'Seleccione una opción' }
});

defineEmits(['update:modelValue']);
</script>

<style scoped>
.select-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
    font-family: var(--font-family);
}

.filter-select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid var(--border-light);
    border-radius: 4px;
    font-size: 14px;
    color: var(--text-dark);
    background-color: var(--text-white);
    outline: none;
    cursor: pointer;
    font-family: var(--font-family);
    transition: border-color 0.2s;
}

.filter-select:focus {
    border-color: var(--color-primary);
}
</style>