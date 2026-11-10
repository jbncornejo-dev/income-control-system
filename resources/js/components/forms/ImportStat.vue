<script setup>
defineProps({
    value: [String, Number],
    label: String,
});
</script>

<template>
    <div class="stat-card">
        <div class="stat-info">
            <strong class="stat-number">{{ value }}</strong>
            <span class="stat-label">{{ label }}</span>
        </div>
    </div>
</template>

<style scoped>
.stat-card {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid var(--color-white-soft);
  border-left: 4px solid transparent;
}
.stat-info { display: flex; flex-direction: column; min-width: 0; }
.stat-number { font-size: 1.4rem; line-height: 1; color: var(--color-text-main); }
.stat-label { font-size: 11px; color: var(--color-text-secondary); margin-top: 3px; }
.stat-ok { border-left-color: #198754; background: #f0faf4; }
.stat-ok .stat-number { color: #198754; }
.stat-total { border-left-color: #334155; background: #f8fafc; }
.stat-total .stat-number { color: #334155; }
.stat-sin-registrar { border-left-color: #dc2626; background: #fef2f2; }
.stat-sin-registrar .stat-number { color: #dc2626; }
.stat-formato { border-left-color: #d97706; background: #fffbeb; }
.stat-formato .stat-number { color: #d97706; }
.stat-duplicado { border-left-color: #ea580c; background: #fff7ed; }
.stat-duplicado .stat-number { color: #ea580c; }
.stat-existente { border-left-color: #dc3545; background: #fef2f2; }
.stat-existente .stat-number { color: #dc3545; }
.stat-inesperado { border-left-color: #6c757d; background: #f8f9fa; }
.stat-inesperado .stat-number { color: #6c757d; }
</style>
