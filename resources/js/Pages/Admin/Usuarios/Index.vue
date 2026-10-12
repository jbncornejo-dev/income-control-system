<script setup>
import Modal from '@/components/ui/Modal.vue';
import UsuarioForm from '@/components/forms/UsuarioForm.vue';
import EditUsuarioForm from '@/components/forms/EditUsuarioForm.vue';
import EditPasswordForm from '@/components/forms/EditPasswordForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    usuarios: Object, // Paginador de Laravel
    roles: Array,
    filters: { type: Object, default: () => ({}) }
});

const showCreateModal = ref(false);
const filtroRol = ref(props.filters?.id_rol ?? props.filters?.role ?? '');
const searchQuery = ref(props.filters?.search ?? '');
const showEditModal = ref(false);
const showPasswordModal = ref(false);
const selectedUser = ref(null);

const openEditModal = (user) => {
    selectedUser.value = user;
    showEditModal.value = true;
};

const openPasswordModal = (user) => {
    selectedUser.value = user;
    showPasswordModal.value = true;
};

const eliminarUsuario = (id) => {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.')) {
        router.delete(`/usuarios/${id}`, {
            preserveScroll: true,
        });
    }
};

// Función auxiliar para obtener la inicial del nombre para el avatar
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?';
};

// Determinar el tema de color según el rol
const getRoleTheme = (roleName) => {
    if (!roleName) return 'theme-default';
    const norm = roleName.toLowerCase();
    if (norm.includes('admin')) return 'theme-admin';
    if (norm.includes('docente') || norm.includes('profesor')) return 'theme-docente';
    if (norm.includes('estudiante') || norm.includes('alumno')) return 'theme-estudiante';
    if (norm.includes('control') || norm.includes('personal')) return 'theme-control';
    return 'theme-default';
};

// Búsqueda server-side con debounce para no saturar el servidor con cada tecla.
let searchDebounce = null;

const buscarUsuarios = () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        router.get(
            '/usuarios',
            {
                search: searchQuery.value.trim() || undefined,
                id_rol: filtroRol.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }, 300);
};

const cambiarPagina = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

onBeforeUnmount(() => clearTimeout(searchDebounce));

watch(searchQuery, buscarUsuarios);

watch(filtroRol, (value) => {
    router.get(
        '/usuarios',
        {
            id_rol: value || undefined,
            search: searchQuery.value.trim() || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
});
</script>

<template>
    <Head title="Usuarios del Sistema" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">USUARIOS DEL SISTEMA</h1>

            <!-- Barra de Acciones y Filtros -->
            <div class="actions-bar">
                <!-- Buscador -->
                <div class="search-wrapper">
                    <div class="search-icon-box">
                        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Buscar por email o nombre..." 
                        class="search-input-field"
                    />
                </div>

                <!-- Filtros y Botón -->
                <div class="filter-actions-group">
                    <div class="role-select-wrapper">
                        <select v-model="filtroRol" class="custom-role-select">
                            <option value="">Todos los roles</option>
                            <option v-for="r in roles" :key="r.id_rol" :value="r.id_rol">
                                {{ r.nombre_rol }}
                            </option>
                        </select>
                        <div class="select-chevron-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <button type="button" class="btn-new-user" @click="showCreateModal = true">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nuevo usuario</span>
                    </button>
                </div>
            </div>

            <!-- Contenedor de la Tabla -->
            <div class="table-card">
                <div class="table-scroll-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th class="th-actions">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in usuarios.data" :key="user.id">
                                <td>
                                    <div class="name-cell-wrapper">
                                        <div class="user-avatar-circle" :class="getRoleTheme(user.rol?.nombre_rol)">
                                            {{ getInitial(user.name) }}
                                        </div>
                                        <span class="user-name-text">{{ user.name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="user-email-text">{{ user.email }}</span>
                                </td>
                                <td>
                                    <span class="role-badge" :class="getRoleTheme(user.rol?.nombre_rol)">
                                        {{ user.rol ? user.rol.nombre_rol : 'Sin rol' }}
                                    </span>
                                </td>
                                <td class="td-actions">
                                    <div class="actions-btn-group">
                                        <button type="button" class="btn-action-edit" @click="openEditModal(user)">Editar</button>
                                        <button type="button" class="btn-action-pwd" @click="openPasswordModal(user)">Clave</button>
                                        <button type="button" class="btn-action-del" @click="eliminarUsuario(user.id)">Eliminar</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!usuarios.data || usuarios.data.length === 0">
                                <td colspan="4" class="empty-table-state">
                                    {{ searchQuery || filtroRol ? 'No se encontraron usuarios que coincidan con la búsqueda o filtro.' : 'No hay usuarios registrados.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="pagination-container">
                    <span class="pagination-text">
                        Mostrando {{ usuarios.from || 0 }} al {{ usuarios.to || 0 }} de {{ usuarios.total || 0 }} usuarios
                    </span>
                    <div class="pagination-buttons">
                        <button 
                            type="button"
                            class="btn-page-nav"
                            :disabled="!usuarios.prev_page_url" 
                            @click="cambiarPagina(usuarios.prev_page_url)"
                        >
                            Anterior
                        </button>
                        <button 
                            type="button"
                            class="btn-page-nav"
                            :disabled="!usuarios.next_page_url" 
                            @click="cambiarPagina(usuarios.next_page_url)"
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modales -->
            <Modal :open="showCreateModal" @close="showCreateModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Crear Nuevo Usuario</h2>
                    <UsuarioForm 
                        :roles="roles" 
                        @success="showCreateModal = false" 
                        @cancel="showCreateModal = false" 
                    />
                </div>
            </Modal>

            <Modal :open="showEditModal" @close="showEditModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Editar Usuario</h2>
                    <EditUsuarioForm 
                        v-if="selectedUser"
                        :user="selectedUser" 
                        :roles="roles" 
                        @success="showEditModal = false" 
                        @cancel="showEditModal = false" 
                    />
                </div>
            </Modal>

            <Modal :open="showPasswordModal" @close="showPasswordModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Cambiar Contraseña</h2>
                    <EditPasswordForm 
                        v-if="selectedUser"
                        :user="selectedUser" 
                        @success="showPasswordModal = false" 
                        @cancel="showPasswordModal = false" 
                    />
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Contenedor principal con padding responsivo fiel al mockup */
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
    padding: 1rem; /* p-4 en móvil */
    flex: 1 1 0%;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

@media (min-width: 640px) {
    .panel-container {
        padding: 1.5rem; /* sm:p-6 */
    }
}

@media (min-width: 1024px) {
    .panel-container {
        padding: 2rem; /* lg:p-8 */
    }
}

/* Título */
.panel-title {
    font-size: 1.5rem; /* text-2xl */
    line-height: 2rem;
    font-weight: 700;
    color: var(--color-primary); /* brand.primary #1D3653 */
    margin-bottom: 1.5rem; /* mb-6 */
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 0.05em; /* tracking-wide */
}

/* Barra de Acciones y Filtros */
.actions-bar {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
    gap: 1rem; /* gap-4 */
    margin-bottom: 1.5rem; /* mb-6 */
}

@media (min-width: 1024px) {
    .actions-bar {
        flex-direction: row;
        align-items: center;
    }
}

/* Buscador */
.search-wrapper {
    position: relative;
    width: 100%;
}

@media (min-width: 1024px) {
    .search-wrapper {
        width: 24rem; /* lg:w-96 (384px) */
    }
}

.search-icon-box {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    padding-left: 0.75rem; /* pl-3 */
    display: flex;
    align-items: center;
    pointer-events: none;
    color: #9ca3af; /* text-gray-400 */
}

.search-icon {
    width: 1.25rem; /* h-5 w-5 */
    height: 1.25rem;
}

.search-input-field {
    display: block;
    width: 100%;
    padding-left: 2.5rem; /* pl-10 */
    padding-right: 0.75rem; /* pr-3 */
    padding-top: 0.625rem; /* py-2.5 */
    padding-bottom: 0.625rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb; /* border-gray-200 */
    border-radius: 0.5rem; /* rounded-lg */
    font-size: 0.875rem; /* text-sm */
    color: #1f2937;
    font-family: var(--font-family);
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    transition: all 0.2s ease;
    outline: none;
}

.search-input-field::placeholder {
    color: #9ca3af; /* placeholder-gray-400 */
}

.search-input-field:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15); /* focus:ring-brand-primary/20 */
}

/* Filtros y Botón Nuevo Usuario */
.filter-actions-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem; /* gap-3 */
    width: 100%;
}

@media (min-width: 640px) {
    .filter-actions-group {
        flex-direction: row;
        width: auto;
    }
}

.role-select-wrapper {
    position: relative;
    width: 100%;
}

@media (min-width: 640px) {
    .role-select-wrapper {
        width: 14rem; /* sm:w-56 (224px) */
    }
}

.custom-role-select {
    display: block;
    width: 100%;
    padding-left: 1rem; /* pl-4 */
    padding-right: 2.5rem; /* pr-10 */
    padding-top: 0.625rem; /* py-2.5 */
    padding-bottom: 0.625rem;
    background-color: #ffffff;
    border: 1px solid #e5e7eb; /* border-gray-200 */
    border-radius: 0.5rem; /* rounded-lg */
    font-size: 0.875rem; /* text-sm */
    color: #374151; /* text-gray-700 */
    font-family: var(--font-family);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    transition: all 0.2s ease;
    outline: none;
}

.custom-role-select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15);
}

.select-chevron-icon {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    transform: translateY(-50%);
    width: 1rem;
    height: 1rem;
    color: #6b7280;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.select-chevron-icon svg {
    width: 100%;
    height: 100%;
}

.btn-new-user {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem; /* gap-2 */
    background-color: var(--color-primary); /* bg-brand-primary */
    color: #ffffff;
    padding: 0.625rem 1.25rem; /* py-2.5 px-5 */
    border-radius: 0.5rem; /* rounded-lg */
    font-size: 0.875rem; /* text-sm */
    font-weight: 600;
    letter-spacing: 0.025em; /* tracking-wide */
    border: none;
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    transition: background-color 0.2s ease, transform 0.1s ease;
    width: 100%;
    white-space: nowrap;
    font-family: var(--font-family);
}

@media (min-width: 640px) {
    .btn-new-user {
        width: auto;
    }
}

.btn-new-user:hover {
    background-color: var(--color-primary-hover, #3A5A7E); /* hover:bg-brand-light */
}

.btn-icon {
    width: 1rem; /* w-4 h-4 */
    height: 1rem;
    stroke-width: 2;
}

/* Tarjeta y Tabla de Usuarios */
.table-card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb; /* border-gray-200 */
    border-radius: 0.75rem; /* rounded-xl (12px) */
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05); /* shadow-sm */
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.table-scroll-container {
    overflow-x: auto;
    width: 100%;
    scrollbar-width: thin;
}

.users-table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
    font-size: 0.875rem; /* text-sm */
}

.users-table thead tr {
    background-color: #f9fafb; /* bg-gray-50 */
    border-bottom: 1px solid #f3f4f6; /* border-gray-100 */
    color: #6b7280; /* text-gray-500 */
    font-size: 0.75rem; /* text-xs */
    text-transform: uppercase;
    letter-spacing: 0.05em; /* tracking-wider */
    font-weight: 600;
}

.users-table th {
    padding: 1rem 1.5rem; /* px-6 py-4 */
    white-space: nowrap;
}

.users-table th.th-actions {
    text-align: right;
}

.users-table tbody tr {
    border-bottom: 1px solid #f3f4f6; /* divide-y divide-gray-100 */
    transition: background-color 0.15s ease;
}

.users-table tbody tr:hover {
    background-color: rgba(249, 250, 251, 0.7); /* hover:bg-gray-50/50 */
}

.users-table tbody tr:last-child {
    border-bottom: none;
}

.users-table td {
    padding: 1rem 1.5rem; /* px-6 py-4 */
    vertical-align: middle;
}

.users-table td.td-actions {
    text-align: right;
    white-space: nowrap;
}

/* Celda de Nombre y Avatar */
.name-cell-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem; /* gap-3 */
    white-space: nowrap;
}

.user-avatar-circle {
    width: 2rem; /* h-8 w-8 (32px) */
    height: 2rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem; /* text-xs */
    font-family: var(--font-display);
    flex-shrink: 0;
}

.user-name-text {
    font-weight: 500;
    color: #111827; /* text-gray-900 */
}

.user-email-text {
    color: #6b7280; /* text-gray-500 */
    white-space: nowrap;
}

/* Badges de Roles */
.role-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.625rem; /* px-2.5 py-0.5 */
    border-radius: 9999px;
    font-size: 0.75rem; /* text-xs */
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em; /* tracking-wide */
    white-space: nowrap;
}

/* Temas por Rol según Mockup */
/* 1. Docente (emerald) */
.user-avatar-circle.theme-docente {
    background-color: #d1fae5; /* bg-emerald-100 */
    color: #047857; /* text-emerald-700 */
    border: 1px solid #a7f3d0; /* border-emerald-200 */
}
.role-badge.theme-docente {
    background-color: #ecfdf5; /* bg-emerald-50 */
    color: #047857; /* text-emerald-700 */
    border: 1px solid #a7f3d0; /* border-emerald-200 */
}

/* 2. Admin (blue) */
.user-avatar-circle.theme-admin {
    background-color: #dbeafe; /* bg-blue-100 */
    color: #1d4ed8; /* text-blue-700 */
    border: 1px solid #bfdbfe; /* border-blue-200 */
}
.role-badge.theme-admin {
    background-color: #eff6ff; /* bg-blue-50 */
    color: #1d4ed8; /* text-blue-700 */
    border: 1px solid #bfdbfe; /* border-blue-200 */
}

/* 3. Estudiante (gray) */
.user-avatar-circle.theme-estudiante {
    background-color: #f3f4f6; /* bg-gray-100 */
    color: #374151; /* text-gray-700 */
    border: 1px solid #e5e7eb; /* border-gray-200 */
}
.role-badge.theme-estudiante {
    background-color: #f3f4f6; /* bg-gray-100 */
    color: #374151; /* text-gray-700 */
    border: 1px solid #e5e7eb; /* border-gray-200 */
}

/* 4. Control de Ingreso (amber) */
.user-avatar-circle.theme-control {
    background-color: #fef3c7; /* bg-amber-100 */
    color: #b45309; /* text-amber-700 */
    border: 1px solid #fde68a; /* border-amber-200 */
}
.role-badge.theme-control {
    background-color: #fffbeb; /* bg-amber-50 */
    color: #b45309; /* text-amber-700 */
    border: 1px solid #fde68a; /* border-amber-200 */
}

/* 5. Default */
.user-avatar-circle.theme-default {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.role-badge.theme-default {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

/* Botones de Acción en Filas */
.actions-btn-group {
    display: inline-flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.5rem; /* gap-2 */
}

.btn-action-edit,
.btn-action-pwd {
    padding: 0.375rem 0.75rem; /* px-3 py-1.5 */
    border: 1px solid #e5e7eb; /* border-gray-200 */
    color: #4b5563; /* text-gray-600 */
    background-color: #ffffff;
    border-radius: 0.375rem; /* rounded-md */
    font-size: 0.75rem; /* text-xs */
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.btn-action-edit:hover,
.btn-action-pwd:hover {
    background-color: #f9fafb; /* hover:bg-gray-50 */
    color: var(--color-primary); /* hover:text-brand-primary */
    border-color: #d1d5db;
}

.btn-action-del {
    padding: 0.375rem 0.75rem; /* px-3 py-1.5 */
    border: 1px solid #fee2e2; /* border-red-100 */
    color: #A12B33; /* text-brand-red */
    background-color: rgba(254, 242, 242, 0.4); /* bg-red-50/30 */
    border-radius: 0.375rem; /* rounded-md */
    font-size: 0.75rem; /* text-xs */
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.btn-action-del:hover {
    background-color: #fee2e2; /* hover:bg-red-50 */
    border-color: #fca5a5; /* hover:border-red-200 */
    color: #881337;
}

/* Estado de tabla vacía */
.empty-table-state {
    text-align: center;
    padding: 3rem 1.5rem !important;
    color: #6b7280;
    font-size: 0.875rem;
}

/* Paginación */
.pagination-container {
    padding: 1rem; /* p-4 */
    border-top: 1px solid #f3f4f6; /* border-gray-100 */
    background-color: rgba(249, 250, 251, 0.6); /* bg-gray-50/50 */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    gap: 1rem; /* gap-4 */
}

@media (min-width: 640px) {
    .pagination-container {
        flex-direction: row;
    }
}

.pagination-text {
    font-size: 0.875rem; /* text-sm */
    color: #6b7280; /* text-gray-500 */
    font-weight: 500;
}

.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem; /* gap-2 */
}

.btn-page-nav {
    padding: 0.375rem 0.75rem; /* px-3 py-1.5 */
    border: 1px solid #e5e7eb; /* border-gray-200 */
    background-color: #ffffff;
    border-radius: 0.375rem; /* rounded-md */
    font-size: 0.875rem; /* text-sm */
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: var(--font-family);
}

.btn-page-nav:hover:not(:disabled) {
    background-color: #f9fafb;
    border-color: #d1d5db;
    color: var(--color-primary);
}

.btn-page-nav:disabled {
    color: #9ca3af; /* text-gray-400 */
    cursor: not-allowed;
    opacity: 0.6;
    background-color: #ffffff;
    border-color: #e5e7eb;
}
</style>