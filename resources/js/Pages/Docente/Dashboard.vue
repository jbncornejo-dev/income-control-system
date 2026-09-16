<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    proximosExamenes: Array
});
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">PANEL PRINCIPAL</h1>

            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top-color: #1e1b4b;">
                    <span class="stat-label">Mis exámenes</span>
                    <span class="stat-value">{{ stats.examenes }}</span>
                    <span class="stat-desc">próximos</span>
                </div>
                <div class="stat-card" style="border-top-color: #4f46e5;">
                    <span class="stat-label">Habilitados</span>
                    <span class="stat-value">{{ stats.habilitados }}</span>
                    <span class="stat-desc">estudiantes</span>
                </div>
                <div class="stat-card" style="border-top-color: #b91c1c;">
                    <span class="stat-label">Inhabilitados</span>
                    <span class="stat-value">{{ stats.inhabilitados }}</span>
                    <span class="stat-desc">requieren revisión</span>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="content-grid">
                
                <!-- Columna Izquierda: Mis Exámenes Próximos -->
                <div class="content-box">
                    <div class="box-header">
                        <h2>Mis Exámenes Próximos</h2>
                        <Link href="/examenes" class="link-action">Ver todos &rarr;</Link>
                    </div>
                    
                    <div class="exam-list">
                        <div v-for="examen in proximosExamenes" :key="examen.id_examen" class="exam-item">
                            <div class="exam-info">
                                <h3 class="exam-title">{{ examen.asignatura?.nombre_asignatura || 'N/D' }}</h3>
                                <p class="exam-meta">
                                    {{ examen.fecha }} &middot; {{ examen.hora_inicio }} &middot; 
                                    <span v-if="examen.examenes_ambientes && examen.examenes_ambientes.length > 0">
                                        {{ examen.examenes_ambientes.map(ea => ea.ambiente?.nombre_ambiente).join(', ') }}
                                    </span>
                                </p>
                                <p class="exam-counts">
                                    <span class="count-hab"><strong>{{ examen.hab_count || 0 }}</strong> hab.</span>
                                    <span class="count-inhab"><strong>{{ examen.inhab_count || 0 }}</strong> inhab.</span>
                                </p>
                            </div>
                            <!-- Ajusta la ruta del botón según tu enrutamiento -->
                            <Link :href="`/examenes/${examen.id_examen}`" class="btn-outline">Ver detalle</Link>
                        </div>

                        <!-- Estado vacío -->
                        <div v-if="!proximosExamenes || proximosExamenes.length === 0" class="empty-state">
                            No tienes exámenes próximos programados.
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Accesos Rápidos -->
                <div class="content-box h-fit">
                    <div class="box-header">
                        <h2>Accesos Rápidos</h2>
                    </div>
                    <div class="quick-access-list">
                        <Link href="/examenes" class="qa-item">
                            <div class="qa-content">
                                <span class="qa-title">Mis exámenes</span>
                                <span class="qa-desc">Lista y gestión</span>
                            </div>
                        </Link>
                        <Link href="/habilitaciones" class="qa-item">
                            <div class="qa-content">
                                <span class="qa-title">Habilitaciones</span>
                                <span class="qa-desc">Estado de estudiantes</span>
                            </div>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal */
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
}

/* Tarjetas de Estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #ffffff;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    border-top: 4px solid #ccc;
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.stat-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0.25rem 0;
    font-family: Georgia, serif; /* Estilo clásico similar al mockup */
}

.stat-desc {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Cuadrícula Inferior */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.content-box {
    background: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    padding: 1.5rem;
}

.h-fit {
    height: fit-content;
}

.box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.box-header h2 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.link-action {
    color: #4f46e5;
    font-size: 0.875rem;
    text-decoration: none;
}

/* Lista de Exámenes */
.exam-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.exam-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid #1e1b4b; /* Detalle de color lateral del mockup */
    padding-left: 1rem;
}

.exam-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
}

.exam-meta {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
}

.exam-counts {
    font-size: 0.875rem;
    margin: 0;
}

.count-hab { color: #1e1b4b; margin-right: 1rem; }
.count-inhab { color: #b91c1c; }

.btn-outline {
    border: 1px solid #d1d5db;
    background: transparent;
    color: #374151;
    padding: 0.4rem 1rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
}

.btn-outline:hover {
    background: #f9fafb;
}

/* Accesos Rápidos */
.quick-access-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.qa-item {
    display: flex;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    text-decoration: none;
    color: #1f2937;
    transition: border-color 0.2s;
}

.qa-item:hover {
    border-color: #d1d5db;
}

.qa-content {
    display: flex;
    flex-direction: column;
}

.qa-title {
    font-weight: 600;
    font-size: 0.875rem;
}

.qa-desc {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.empty-state {
    color: #6b7280;
    font-size: 0.875rem;
    text-align: center;
    padding: 2rem;
}
</style>