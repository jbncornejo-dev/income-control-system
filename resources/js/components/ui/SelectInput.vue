<template>
    <div class="select-wrapper">
        <label v-if="label" class="select-label">{{ label }}</label>

        <div class="select-control-wrapper">
            <select
                :value="modelValue"
                @change="$emit('update:modelValue', $event.target.value)"
                class="filter-select"
            >
                <option value="" disabled>{{ placeholder }}</option>
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ formatLabel(option.label) }}
                </option>
            </select>

            <svg
                class="select-chevron"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: [String, Number],
    label: String,
    options: { type: Array, required: true }, // Array de { value, label }
    placeholder: { type: String, default: 'Seleccione una opción' },
    capitalize: { type: Boolean, default: false } // ← nueva prop opcional
});

defineEmits(['update:modelValue']);

// Capitaliza la primera letra de cada palabra
const formatLabel = (label) => {
    if (!props.capitalize || !label) return label;
    return String(label)
        .toLowerCase()
        .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<style scoped>
.select-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    width: 100%;
}

.select-label {
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    font-family: var(--font-family);
}

/* Contenedor relativo para posicionar el chevron */
.select-control-wrapper {
    position: relative;
    width: 100%;
}

.filter-select {
    width: 100%;
    padding: 10px 2.5rem 10px 15px; /* espacio a la derecha para el chevron */
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-dark);
    background-color: var(--text-white);
    font-family: var(--font-family);
    cursor: pointer;
    outline: none;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);

    /* Elimina el chevron nativo del navegador */
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;

    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.filter-select:hover {
    border-color: #9ca3af;
    background-color: #fafafa;
}

.filter-select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Chevron SVG personalizado */
.select-chevron {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    color: #6b7280;
    pointer-events: none; /* no bloquea el click del select */
    transition: color 0.2s ease;
}

.filter-select:focus + .select-chevron {
    color: var(--color-primary);
}
</style>