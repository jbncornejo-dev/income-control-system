<script setup>
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/ui/FormField.vue';
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
        <FormField :error="form.errors.name">
            <TextInput 
                id="edit_name" 
                v-model="form.name" 
                type="text" 
                label="Nombre Completo"
                required 
                autofocus 
            />
        </FormField>

        <div class="form-row">
            <FormField :error="form.errors.username">
                <TextInput 
                    id="edit_username" 
                    v-model="form.username" 
                    type="text" 
                    label="Nombre de Usuario"
                    disabled 
                />
            </FormField>
            <FormField :error="form.errors.email">
                <TextInput 
                    id="edit_email" 
                    v-model="form.email" 
                    type="email" 
                    label="Correo Electrónico"
                    disabled 
                />
            </FormField>
        </div>

        <FormField :error="form.errors.id_rol">
            <SelectInput
                id="edit_id_rol"
                v-model="form.id_rol"
                :options="roles.map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                label="Rol del Usuario"
                required
            />
        </FormField>

        <div class="form-actions">
            <Button type="button" variant="action" @click="$emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :disabled="form.processing">Guardar Cambios</Button>
        </div>
    </form>
</template>

<style scoped src="./form-layout.css"></style>

<style scoped>
@media (max-width: 640px) {
    .form-actions > button { width: 100%; }
}
</style>
