<template>
    <span :class="['badge', badgeClass]">
        <slot>{{ label }}</slot>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: '' }
});

const badgeClass = computed(() => {
    const classes = {
        active: 'badge-active', // o badge-hab / badge-confirmed
        inactive: 'badge-inactive', // o badge-inhab / badge-pending
        draft: 'badge-draft',
        admin: 'badge-role-admin',
        docente: 'badge-role-docente',
        control: 'badge-role-control'
    };
    return classes[props.status.toLowerCase()] || 'badge-inactive';
});
</script>

<style scoped>
.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid;
    display: inline-block;
    font-family: var(--font-family);
}

/* --- Estados --- */
.badge-active {
    background-color: #eef2ff;
    color: #4f46e5;
    border-color: #c7d2fe;
}

.badge-inactive {
    background-color: #f3f4f6;
    color: #6b7280;
    border-color: #d1d5db;
}

.badge-draft {
    background-color: #fef9c3;
    color: #854d0e;
    border-color: #fde047;
}

/* --- Roles --- */
.badge-role-admin {
    background-color: #fef2f2;
    color: #ef4444;
    border-color: #fca5a5;
}

.badge-role-docente {
    background-color: #f5f3ff;
    color: #6d28d9;
    border-color: #c4b5fd;
}

.badge-role-control {
    background-color: #f3f4f6;
    color: #4b5563;
    border-color: #d1d5db;
}
</style>