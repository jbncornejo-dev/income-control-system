<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, required: true }
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    name: props.user.name,
    username: props.user.username,
    email: props.user.email,
    id_rol: props.user.id_rol,
});

const submit = () => {
    form.put(`/usuarios/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="custom-form">
        <div class="form-group">
            <TextInput 
                id="edit_name" 
                v-model="form.name" 
                type="text" 
                label="Nombre Completo"
                required 
                autofocus 
            />
            <span v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <TextInput 
                    id="edit_username" 
                    v-model="form.username" 
                    type="text" 
                    label="Nombre de Usuario"
                    disabled 
                />
                
                <span v-if="form.errors.username" class="error-msg">{{ form.errors.username }}</span>
            </div>
            <div class="form-group">
                <TextInput 
                    id="edit_email" 
                    v-model="form.email" 
                    type="email" 
                    label="Correo Electrónico"
                    disabled 
                />
                <span v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</span>
            </div>
        </div>

        <div class="form-group">
            <label for="edit_id_rol">Rol del Usuario</label>
            <select id="edit_id_rol" v-model="form.id_rol" class="input-control select-control" required>
                <option v-for="rol in roles" :key="rol.id_rol" :value="rol.id_rol">
                    {{ rol.nombre_rol }}
                </option>
            </select>
            <span v-if="form.errors.id_rol" class="error-msg">{{ form.errors.id_rol }}</span>
        </div>

        <div class="form-actions">
            <Button type="button" variant="action" @click="$emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :disabled="form.processing">Guardar Cambios</Button>
        </div>
    </form>
</template>

<style scoped>
.custom-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
label { font-size: 0.875rem; font-weight: 600; color: #1f2937; }
:deep(.input-control), .select-control { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; color: #374151; background-color: #ffffff; box-sizing: border-box; transition: border-color 0.2s; }
:deep(.input-control):focus, .select-control:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
.select-control { appearance: auto; text-transform: capitalize; }
.error-msg { color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; }
.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
</style>