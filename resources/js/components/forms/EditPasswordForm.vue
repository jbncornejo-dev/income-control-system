<script setup>
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    user: { type: Object, required: true }
});

const emit = defineEmits(['success', 'cancel']);

const form = useForm({
    password: '',
    password_confirmation: ''
});

const submit = () => {
    form.patch(`/usuarios/${props.user.id}/password`, {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="custom-form">
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: var(--spacing-md);">Actualizando la contraseña para: <strong style="color: var(--text-dark);">{{ user.name }}</strong></p>
        
        <div class="form-row password-box">
            <FormField :error="form.errors.password">
                <TextInput 
                    id="new_password" 
                    v-model="form.password" 
                    type="password" 
                    label="Nueva Contraseña"
                    required 
                    autofocus 
                />
            </FormField>
            <div class="form-group">
                <TextInput 
                    id="new_password_confirmation" 
                    v-model="form.password_confirmation" 
                    type="password" 
                    label="Confirmar Contraseña"
                    required 
                />
            </div>
        </div>

        <div class="form-actions">
            <Button type="button" variant="action" @click="$emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :disabled="form.processing">Actualizar Clave</Button>
        </div>
    </form>
</template>

<style scoped src="./form-layout.css"></style>

<style scoped>
.password-box { background-color: var(--color-bg-input); padding: var(--spacing-lg); border-radius: var(--radius-md); border: 1px solid var(--border-light); }
@media (max-width: 640px) {
    .form-actions > button { width: 100%; }
}
</style>
