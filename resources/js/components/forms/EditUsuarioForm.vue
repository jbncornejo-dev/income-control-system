<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
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
    username: '',
    password: '',
    password_confirmation: '',
    id_rol: props.user.id_rol,
});

const propiosDatos = usePage().props.auth.user.id === props.user.id;
const rolBloqueado = propiosDatos || props.user.id_estudiante !== null;
const rolElegido = computed(() => props.roles.find(r => Number(r.id_rol) === Number(form.id_rol)));
const esAdmin = computed(() => rolElegido.value?.nombre_rol === 'administrador');
const promocion = computed(() => esAdmin.value && props.user.rol?.nombre_rol !== 'administrador');
const accesoReportes = computed(() => Array.isArray(rolElegido.value?.permisos) && !Array.isArray(props.user.rol?.permisos));
const necesitaClave = computed(() => promocion.value || accesoReportes.value);

const submit = () => {
    form.transform(data => ({
        ...data,
        username: esAdmin.value ? data.username : null,
        password: necesitaClave.value ? data.password : null,
        password_confirmation: necesitaClave.value ? data.password_confirmation : null,
    })).put(`/usuarios/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="custom-form">
        <FormField>
            <TextInput id="edit_name" :model-value="user.name" label="Nombre completo" disabled />
        </FormField>
        <div class="form-row">
            <FormField>
                <TextInput id="edit_username" :model-value="user.username" label="Nombre de usuario actual" disabled />
            </FormField>
            <FormField>
                <TextInput id="edit_email" :model-value="user.email ?? ''" type="email" label="Correo electrónico" disabled />
            </FormField>
        </div>

        <FormField :error="form.errors.id_rol">
            <SelectInput
                id="edit_id_rol"
                v-model="form.id_rol"
                :options="roles.filter(r => r.nombre_rol !== 'estudiante' || r.id_rol === user.id_rol).map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                :disabled="rolBloqueado"
                :capitalize="true"
                label="Rol del usuario"
                required
            />
        </FormField>

        <FormField v-if="esAdmin" :error="form.errors.username">
            <TextInput id="admin_username" v-model="form.username" label="Nuevo nombre de usuario" :required="promocion" />
        </FormField>
        <div v-if="necesitaClave" class="form-row">
            <FormField :error="form.errors.password">
                <TextInput id="admin_password" v-model="form.password" type="password" label="Contraseña inicial" autocomplete="new-password" required />
            </FormField>
            <FormField>
                <TextInput id="admin_password_confirmation" v-model="form.password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" required />
            </FormField>
        </div>
        <p v-if="necesitaClave">Usa al menos 14 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>

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
