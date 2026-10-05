<script setup>
defineProps({
    label: String,
    value: [String, Number],
    desc: String,
    color: {
        type: String,
        default: 'var(--color-primary)'
    }
});
</script>

<template>
    <div class="stat-card" :style="{ borderTopColor: color }">
        <span class="stat-label">{{ label }}</span>
        <span class="stat-value">{{ value }}</span>
        <span v-if="desc" class="stat-desc">{{ desc }}</span>
    </div>
</template>

<style scoped>
.stat-card {
    background: var(--color-white);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-card);
    border-top: 4px solid var(--color-primary);
    display: flex;
    flex-direction: column;
    transition: box-shadow var(--transition-fast), transform var(--transition-fast);
}

.stat-card:hover {
    box-shadow: var(--shadow-elevated);
    transform: translateY(-2px);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    font-family: var(--font-main);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-main);
    margin: var(--spacing-xs) 0;
    font-family: var(--font-display);
}

.stat-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
}
</style>
