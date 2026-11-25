<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

const props = defineProps({
    roles: { type: Array, required: true }
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    nombres: '',
    apellidos: '',
    codigo_universitario: '',
    documento_identidad: '',
    email: '',
    id_rol: '',
    username: '',
    password: '',
    password_confirmation: '',
});

const rol = computed(() => props.roles.find(r => Number(r.id_rol) === Number(form.id_rol)));
const esAdmin = computed(() => rol.value?.nombre_rol === 'administrador');
const usaClaveSegura = computed(() => esAdmin.value || Array.isArray(rol.value?.permisos));

const submit = () => {
    form.transform(data => ({
        ...data,
        username: esAdmin.value ? data.username : null,
        codigo_universitario: esAdmin.value ? null : data.codigo_universitario,
        password: usaClaveSegura.value ? data.password : null,
        password_confirmation: usaClaveSegura.value ? data.password_confirmation : null,
    })).post('/usuarios', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('success');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="custom-form">
        <div class="form-row">
            <FormField :error="form.errors.nombres">
                <TextInput id="nombres" v-model="form.nombres" type="text" label="Nombres" placeholder="Nombres del usuario" required autofocus />
            </FormField>
            <FormField :error="form.errors.apellidos">
                <TextInput id="apellidos" v-model="form.apellidos" type="text" label="Apellidos" placeholder="Apellidos del usuario" required />
            </FormField>
        </div>

        <div class="form-row">
            <FormField v-if="esAdmin" :error="form.errors.username">
                <TextInput id="username" v-model="form.username" label="Nombre de usuario" placeholder="Nombre individual" autocomplete="off" required />
            </FormField>
            <FormField v-else :error="form.errors.codigo_universitario">
                <TextInput id="codigo_universitario" v-model="form.codigo_universitario" type="text" label="Código universitario" placeholder="Ej. 201809372" maxlength="9" inputmode="numeric" required />
            </FormField>
            <FormField :error="form.errors.documento_identidad">
                <TextInput id="documento_identidad" v-model="form.documento_identidad" type="text" label="CI / Documento de identidad" placeholder="Ej. 12590804" maxlength="8" inputmode="numeric" required />
            </FormField>
        </div>

        <div class="form-row">
            <FormField :error="form.errors.email">
                <TextInput id="email" v-model="form.email" type="email" label="Correo electrónico / institucional" placeholder="correo@ejemplo.com" />
            </FormField>
            <FormField :error="form.errors.id_rol">
                <SelectInput
                    id="id_rol"
                    v-model="form.id_rol"
                    :options="roles.map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                    label="Rol del usuario"
                    placeholder="Seleccione un rol..."
                    :capitalize="true"
                    required
                />
            </FormField>
        </div>

        <div v-if="usaClaveSegura" class="form-row">
            <FormField :error="form.errors.password">
                <TextInput id="password" v-model="form.password" type="password" label="Contraseña inicial" autocomplete="new-password" required />
            </FormField>
            <FormField>
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" required />
            </FormField>
        </div>
        <p v-if="usaClaveSegura" class="password-help">
            Usa al menos 14 caracteres, con mayúsculas, minúsculas, números y símbolos. El usuario deberá cambiarla en su primer ingreso.
        </p>
        <p v-else class="password-help">
            La contraseña inicial se generará automáticamente con el CI y deberá cambiarse en el primer ingreso.
        </p>

        <div class="form-actions">
            <Button type="button" variant="action" class="btn-uniform" @click="emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" class="btn-uniform" :disabled="form.processing">Crear usuario</Button>
        </div>
    </form>
</template>

<style scoped src="./form-layout.css"></style>

<style scoped>
.custom-form {
    width: 100%;
    padding: var(--spacing-xs) 0;
}

.form-row {
    width: 100%;
}

.password-help {
    margin: 0;
    color: var(--text-muted, #6b7280);
    font-size: 0.875rem;
}

.form-actions {
    align-items: center;
    margin-top: var(--spacing-xs);
}

.btn-uniform {
    min-width: 140px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 1.25rem;
    font-size: 0.875rem;
    box-sizing: border-box;
}

@media (max-width: 640px) {
    .form-actions { align-items: stretch; }
    .btn-uniform { width: 100%; }
}
</style>
