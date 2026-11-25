<script setup>
import { FileText, FileSpreadsheet } from 'lucide-vue-next';

defineProps({
    format: { type: String, required: true },
    iconSize: { type: Number, default: 16 },
});
</script>

<template>
    <a :class="['btn-export', `btn-${format}`]">
        <component :is="format === 'pdf' ? FileText : FileSpreadsheet" :size="iconSize" />
        <span>Exportar {{ format === 'pdf' ? 'PDF' : 'Excel' }}</span>
    </a>
</template>

<style scoped>
.btn-export {
    display: inline-flex;
    align-items: center;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    font-family: inherit;
    border: 1px solid transparent;
}

.btn-pdf { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
.btn-pdf:hover { background-color: #fee2e2; color: #991b1b; }
.btn-excel { background-color: #f0fdf4; color: #15803d; border-color: #86efac; }
.btn-excel:hover { background-color: #dcfce7; color: #166534; }
</style>
