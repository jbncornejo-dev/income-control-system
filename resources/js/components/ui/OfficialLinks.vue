<script setup>
defineProps({
    links: Array,
    variant: { type: String, default: 'admin' },
});
</script>

<template>
    <div class="link-list">
        <a
            v-for="link in links"
            :key="link.url"
            :href="link.url"
            target="_blank"
            rel="noopener"
            :class="variant === 'docente' ? 'official-link-card' : 'link-item'"
            :style="{ borderLeftColor: link.color }"
        >
            <span :class="variant === 'docente' ? 'official-link-title' : 'link-title'">{{ link.titulo }}</span>
            <span :class="variant === 'docente' ? 'official-link-desc' : 'link-desc'">{{ link.desc }}</span>
        </a>
    </div>
</template>
