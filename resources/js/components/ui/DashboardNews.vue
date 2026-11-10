<script setup>
import { Globe } from 'lucide-vue-next';

defineProps({
    news: Array,
    variant: { type: String, default: 'admin' },
});
</script>

<template>
    <div v-if="news" class="news-list">
        <a
            v-for="(item, index) in news"
            :key="index"
            :href="variant === 'docente' ? item.enlace || 'https://www.umss.edu.bo/categoria/noticias/' : item.enlace"
            target="_blank"
            rel="noopener"
            :class="variant === 'docente' ? 'news-card-item' : 'news-item'"
        >
            <span :class="variant === 'docente' ? 'news-card-date' : 'news-fecha'">{{ item.fecha }}</span>
            <span :class="variant === 'docente' ? 'news-card-title' : 'news-titulo'">{{ item.titulo }}</span>
            <p :class="variant === 'docente' ? 'news-card-summary' : 'news-texto'">{{ item.resumen }}</p>
        </a>
    </div>
    <div v-else class="news-fallback">
        <Globe :size="32" class="news-fallback-icon" />
        <p class="news-fallback-text">No se pudieron cargar las noticias en este momento.</p>
        <a href="https://www.umss.edu.bo/categoria/noticias/" target="_blank" class="news-fallback-link">Ver noticias en UMSS &rarr;</a>
    </div>
</template>
