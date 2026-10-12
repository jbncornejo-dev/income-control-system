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
        <div class="stat-content">
            <span class="stat-label">{{ label }}</span>
            <span class="stat-value">{{ value }}</span>
            <span v-if="desc" class="stat-desc">{{ desc }}</span>
        </div>
        <div class="stat-icon-wrapper" :style="{ backgroundColor: color + '1a', color: color }">
            <slot name="icon"></slot>
        </div>
    </div>
</template>

<style scoped>
.stat-card {
    background: var(--color-white);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-card);
    border-top: 4px solid var(--color-primary);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: box-shadow var(--transition-fast), transform var(--transition-fast);
}

.stat-card:hover {
    box-shadow: var(--shadow-elevated);
    transform: translateY(-2px);
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: var(--spacing-xs);
}

.stat-value {
    font-family: var(--font-display);
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--color-text-main);
    line-height: 1;
}

.stat-desc {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: var(--spacing-xs);
}

.stat-icon-wrapper {
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>
