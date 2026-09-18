<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/components/ui/TextInput.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    roles: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    name: '',
    username: '',
    email: '',
    id_rol: '',
    password: '',
    password_confirmation: ''
});

const submit = () => {
    // Reemplazamos route('usuarios.store') directamente por la ruta '/usuarios'
    form.post('/usuarios', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('success');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <!-- Campo: Nombre -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nombre Completo</label>
            <TextInput 
                id="name" 
                v-model="form.name" 
                type="text" 
                class="mt-1 block w-full" 
                required 
                autofocus
            />
            <span v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</span>
        </div>

        <!-- Campo: Nombre de Usuario -->
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Nombre de Usuario</label>
            <TextInput 
                id="username" 
                v-model="form.username" 
                type="text" 
                class="mt-1 block w-full" 
                required 
            />
            <span v-if="form.errors.username" class="text-red-500 text-xs">{{ form.errors.username }}</span>
        </div>

        <!-- Campo: Correo Electrónico -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
            <TextInput 
                id="email" 
                v-model="form.email" 
                type="email" 
                class="mt-1 block w-full" 
                required 
            />
            <span v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</span>
        </div>

        <!-- Campo: Rol -->
        <div>
            <label for="id_rol" class="block text-sm font-medium text-gray-700">Rol del Usuario</label>
            <select 
                id="id_rol" 
                v-model="form.id_rol" 
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                required
            >
                <option value="" disabled>Seleccione un rol</option>
                <option v-for="rol in roles" :key="rol.id_rol" :value="rol.id_rol">
                    {{ rol.nombre_rol }} 
                </option>
            </select>
            <span v-if="form.errors.id_rol" class="text-red-500 text-xs">{{ form.errors.id_rol }}</span>
        </div>

        <!-- Campo: Contraseña -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
            <TextInput 
                id="password" 
                v-model="form.password" 
                type="password" 
                class="mt-1 block w-full" 
                required 
            />
            <span v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</span>
        </div>

        <!-- Campo: Confirmar Contraseña -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
            <TextInput 
                id="password_confirmation" 
                v-model="form.password_confirmation" 
                type="password" 
                class="mt-1 block w-full" 
                required 
            />
        </div>

        <!-- Acciones del Formulario -->
        <div class="flex items-center justify-end mt-4 space-x-3">
            <pre class="text-red-500 text-xs">{{ form.errors }}</pre>
            <Button type="button" variant="action" @click="$emit('cancel')">
                Cancelar
            </Button>
            <pre class="text-red-500 text-xs">{{ form.errors }}</pre>
            <Button type="submit" variant="primary" :disabled="form.processing">
                Crear Usuario
            </Button>
            <pre class="text-red-500 text-xs">{{ form.errors }}</pre>
        </div>
    </form>
</template>