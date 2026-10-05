<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

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
            <SelectInput
                id="edit_id_rol"
                v-model="form.id_rol"
                :options="roles.map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                label="Rol del Usuario"
                required
            />
            <span v-if="form.errors.id_rol" class="error-msg">{{ form.errors.id_rol }}</span>
        </div>

        <div class="form-actions">
            <Button type="button" variant="action" @click="$emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :disabled="form.processing">Guardar Cambios</Button>
        </div>
    </form>
</template>

<style scoped>
.custom-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg); }
.form-actions { display: flex; justify-content: flex-end; gap: var(--spacing-sm); margin-top: var(--spacing-sm); padding-top: var(--spacing-md); border-top: 1px solid var(--border-light); }

@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions > button { width: 100%; }
}
</style>