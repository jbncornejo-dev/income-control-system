<script setup>
import SelectInput from '@/components/ui/SelectInput.vue';
import Modal from '@/components/ui/Modal.vue';
import UsuarioForm from '@/components/forms/UsuarioForm.vue';
import EditUsuarioForm from '@/components/forms/EditUsuarioForm.vue';
import EditPasswordForm from '@/components/forms/EditPasswordForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const showCreateModal = ref(false);
const filtroRol = ref('');
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

const props = defineProps({
    user: { type: Object, required: true },
    usuarios: Object, // Paginador de Laravel
    roles: Array
});

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
</script>

<template>
    <Head title="Usuarios del Sistema" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">USUARIOS DEL SISTEMA</h1>

            <!-- Barra de Acciones Superior -->
            <div class="action-bar">
                <input 
                    type="text" 
                    placeholder="Buscar usuario..." 
                    class="search-input"
                >
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
                                <!-- Botones enlazados a las funciones -->
                                <button class="btn-action" @click="openEditModal(user)">Editar</button>
                                <button class="btn-action" @click="openPasswordModal(user)">Clave</button>
                                <!-- Pasar el ID correcto del usuario iterado -->
                                <Button 
                                    type="button" 
                                    variant="delete" 
                                    @click="eliminarUsuario(user.id)"
                                >
                                    Eliminar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pie de tabla (Paginación base) -->
                <div class="table-footer">
                    <span>{{ usuarios.to || 0 }} de {{ usuarios.total || 0 }} usuarios</span>
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

/* Barra de Acciones */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.search-input {
    flex: 1;
    max-width: 600px;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.action-controls {
    display: flex;
    gap: 1rem;
}

.role-filter {
    padding: 0.5rem 2rem 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    background-color: white;
}

/* Tabla de Datos */
.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
}

/* Celdas específicas */
.name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.avatar {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    background-color: #1e1b4b;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
}

/* Badges (Etiquetas) */
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.badge-role {
    border: 1px solid #d8b4fe;
    color: #6b21a8;
    background-color: #f3e8ff;
}

.badge-active {
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    background-color: #eff6ff;
}

.badge-inactive {
    border: 1px solid #d1d5db;
    color: #6b7280;
    background-color: #f3f4f6;
}

/* Botones de Acción de Fila */
.actions-cell {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.btn-action {
    background: transparent;
    border: 1px solid #d1d5db;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    color: #374151;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-action:hover {
    background-color: #f9fafb;
}

.btn-delete {
    color: #ef4444;
    border-color: #fca5a5;
}

.btn-delete:hover {
    background-color: #fef2f2;
}

/* Pie de Tabla */
.table-footer {
    padding: 1rem;
    background-color: #ffffff;
    border-top: 1px solid #e5e7eb;
    color: #9ca3af;
    font-size: 0.75rem;
}
</style>