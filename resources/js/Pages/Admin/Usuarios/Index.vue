<script setup>
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Modal from '@/components/ui/Modal.vue';
import UsuarioForm from '@/components/forms/UsuarioForm.vue';
import EditUsuarioForm from '@/components/forms/EditUsuarioForm.vue';
import EditPasswordForm from '@/components/forms/EditPasswordForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
    // La confirmación nativa previene eliminaciones accidentales
    if (confirm('¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.')) {
        router.delete(`/usuarios/${id}`, {
            preserveScroll: true,
            // Opcional: manejar éxito o error aquí
        });
    }
};

// Función auxiliar para obtener la inicial del nombre para el avatar
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?';
};

// Búsqueda server-side con debounce para no saturar el servidor con cada tecla.
// El backend filtra por email usando el parámetro 'search' (UserController::index).
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

            <!-- Barra de Acciones Superior -->
            <div class="action-bar">
                <TextInput 
                    id="buscar-usuario"
                    v-model="searchQuery"
                    placeholder="Buscar por email..." 
                    class="search-input"
                />
                <div class="action-controls">
                    <SelectInput
                        v-model="filtroRol"
                        :options="roles.map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                        placeholder="Todos los roles"
                        :capitalize="true"
                        class="role-filter-custom"
                        style="width: 180px; flex: 0 0 auto;"
                    />
                    <Button @click="showCreateModal = true" variant="primary">+ Nuevo usuario</Button>
                </div>
            </div>

            <!-- Contenedor de la Tabla -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>EMAIL</th>
                            <th>ROL</th>
                            <th>ACCIONES</th>

                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in usuarios.data" :key="user.id">
                            <td class="name-cell">
                                <div class="avatar">{{ getInitial(user.name) }}</div>
                                <span>{{ user.name }}</span>
                            </td>
                            <td>{{ user.email }}</td>
                            <td>
                                <!-- Renderizado correcto del rol -->
                                <span class="badge badge-role">
                                    {{ user.rol ? user.rol.nombre_rol : 'Sin rol' }}
                                </span>
                            </td>
                            <td class="actions-cell">
                                <Button type="button" variant="action" @click="openEditModal(user)">Editar</Button>
                                <Button type="button" variant="secondary" @click="openPasswordModal(user)">Clave</Button>
                                <Button 
                                    type="button" 
                                    variant="danger" 
                                    @click="eliminarUsuario(user.id)"
                                >
                                    Eliminar
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!usuarios.data || usuarios.data.length === 0">
                            <td colspan="4" class="empty-state">
                                {{ searchQuery || filtroRol ? 'No se encontraron usuarios que coincidan con la búsqueda o filtro.' : 'No hay usuarios registrados.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pie de tabla (Paginación base) -->
                <div class="table-footer">
                    <div class="pagination-info">
                        <span>{{ usuarios.from || 0 }} al {{ usuarios.to || 0 }} de {{ usuarios.total || 0 }} usuarios</span>
                    </div>
                    <div class="pagination-controls" v-if="usuarios.last_page > 1">
                        <Button 
                            variant="primary" 
                            :disabled="!usuarios.prev_page_url" 
                            @click="cambiarPagina(usuarios.prev_page_url)"
                        >
                            Anterior
                        </Button>
                        <span class="page-info">Página {{ usuarios.current_page }} de {{ usuarios.last_page }}</span>
                        <Button 
                            variant="primary" 
                            :disabled="!usuarios.next_page_url" 
                            @click="cambiarPagina(usuarios.next_page_url)"
                        >
                            Siguiente
                        </Button>
                    </div>
                </div>
            </div>
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
            <!-- Modal Editar Datos -->
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

            <!-- Modal Editar Contraseña -->
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
/* Contenedor principal */
.panel-container {
    font-family: var(--font-family);
    background-color: transparent;
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: var(--spacing-lg);
    text-transform: uppercase;
    font-family: var(--font-display);
    letter-spacing: 1px;
}

/* Barra de Acciones */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 250px;
    max-width: 400px;
}

.action-controls {
    display: flex;
    gap: var(--spacing-md);
    flex-wrap: wrap;
    align-items: center;
}

/* Tabla de Datos */
.table-container {
    background: var(--color-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    overflow-x: auto;
    box-shadow: var(--shadow-card);
}

/* Celdas específicas */
.name-cell {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    font-weight: 600;
    color: var(--text-dark);
}

.avatar {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 50%;
    background-color: var(--color-primary);
    color: var(--color-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
}

/* Badges (Etiquetas) */
.badge {
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-role {
    border: 1px solid var(--color-primary);
    color: var(--color-primary);
    background-color: var(--color-white-soft);
}

/* Botones de Acción de Fila */
.actions-cell {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

/* Empty state */
.empty-state {
    text-align: center;
    color: var(--text-muted);
    padding: var(--spacing-xl) !important;
    font-style: italic;
}

/* Pie de Tabla */
.table-footer {
    padding: var(--spacing-md) var(--spacing-lg);
    background-color: var(--color-white);
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.pagination-info {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.page-info {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Responsive */
@media (max-width: 768px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .search-input {
        max-width: 100%;
    }
    .action-controls {
        flex-direction: column;
        align-items: stretch;
    }
    .action-controls > * {
        width: 100% !important;
        margin: 0;
    }
}
</style>