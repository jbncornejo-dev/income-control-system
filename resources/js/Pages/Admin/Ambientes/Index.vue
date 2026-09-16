<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    ambientes: Object, // Objeto paginado
    totalCapacidad: Number
});
</script>

<template>
    <Head title="Ambientes" />

    <AuthenticatedLayout>
        <div class="panel-container">
            
            <!-- Encabezado de la página -->
            <div class="header-section">
                <div>
                    <h1 class="panel-title">AMBIENTES</h1>
                    <p class="subtitle">Capacidad total habilitada: <span class="highlight-number">{{ totalCapacidad }}</span> personas</p>
                </div>
                <button class="btn-primary">+ Nuevo ambiente</button>
            </div>

            <!-- Barra de Búsqueda -->
            <div class="search-section">
                <input 
                    type="text" 
                    placeholder="Buscar por código, nombre o edificio..." 
                    class="search-input"
                >
            </div>

            <!-- Cuadrícula de Tarjetas (Cards Grid) -->
            <div class="cards-grid">
                <div class="card" v-for="ambiente in ambientes.data" :key="ambiente.id">
                    
                    <!-- Parte Superior de la Tarjeta -->
                    <div class="card-header">
                        <span class="room-code">{{ ambiente.codigo }}</span>
                        <!-- Asumo que tu base de datos tiene un campo que determina si está habilitado -->
                        <span :class="['badge', ambiente.estado === 'Habilitado' ? 'badge-hab' : 'badge-inhab']">
                            {{ ambiente.estado === 'Habilitado' ? 'Hab.' : 'Inhab.' }}
                        </span>
                    </div>

                    <!-- Información Central -->
                    <div class="card-body">
                        <h3 class="room-name">{{ ambiente.nombre }}</h3>
                        <p class="room-block">{{ ambiente.edificio || 'Sin bloque asignado' }}</p>
                        <p class="room-capacity">Capacidad: <strong>{{ ambiente.capacidad }}</strong></p>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="card-actions">
                        <button class="btn-action btn-toggle">
                            {{ ambiente.estado === 'Habilitado' ? 'Deshabilitar' : 'Habilitar' }}
                        </button>
                        <button class="btn-action btn-edit">Editar</button>
                        <button class="btn-action btn-delete">×</button>
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

/* Encabezado */
.header-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
    letter-spacing: 0.05em;
}

.subtitle {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0;
}

.highlight-number {
    font-weight: 700;
    color: #1f2937;
}

.btn-primary {
    background-color: #1e1b4b; /* Azul muy oscuro del mockup */
    color: white;
    padding: 0.6rem 1.5rem;
    border: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
}

/* Búsqueda */
.search-section {
    margin-bottom: 2rem;
}

.search-input {
    width: 100%;
    max-width: 400px;
    padding: 0.6rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

/* Cuadrícula (Grid) */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* Fuerza 3 columnas exactas */
    gap: 1.5rem;
}

/* Estructura de la Tarjeta */
.card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-top: 4px solid #1e1b4b; /* Borde superior característico del mockup */
    border-radius: 0.5rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.room-code {
    font-weight: 800;
    font-size: 0.875rem;
    color: #1e1b4b;
    text-transform: uppercase;
}

/* Badges (Etiquetas) */
.badge {
    padding: 0.15rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-hab {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.badge-inhab {
    background-color: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

/* Cuerpo de la Tarjeta */
.card-body {
    flex-grow: 1; /* Empuja los botones hacia abajo */
    margin-bottom: 1.5rem;
}

.room-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin: 0 0 0.5rem 0;
}

.room-block {
    font-size: 0.8rem;
    color: #9ca3af;
    margin: 0 0 0.25rem 0;
}

.room-capacity {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0;
}

/* Acciones (Botones inferiores) */
.card-actions {
    display: grid;
    grid-template-columns: 1fr auto auto; /* El primer botón ocupa el espacio restante */
    gap: 0.5rem;
}

.btn-action {
    background: transparent;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    padding: 0.4rem 0;
    font-size: 0.75rem;
    color: #374151;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-action:hover {
    background-color: #f9fafb;
}

.btn-edit {
    padding: 0.4rem 1rem;
}

.btn-delete {
    padding: 0.4rem 0.75rem;
    color: #ef4444;
    border-color: #fca5a5;
    font-weight: 600;
}

.btn-delete:hover {
    background-color: #fef2f2;
}
</style>