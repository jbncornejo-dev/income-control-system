<script setup>
import { useForm } from '@inertiajs/vue3';
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
            <div class="form-group">
                <TextInput 
                    id="new_password" 
                    v-model="form.password" 
                    type="password" 
                    label="Nueva Contraseña"
                    required 
                    autofocus 
                />
                <span v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</span>
            </div>
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

<style scoped>
.custom-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg); }
.password-box { background-color: var(--color-bg-input); padding: var(--spacing-lg); border-radius: var(--radius-md); border: 1px solid var(--border-light); }
.form-actions { display: flex; justify-content: flex-end; gap: var(--spacing-sm); margin-top: var(--spacing-sm); padding-top: var(--spacing-md); border-top: 1px solid var(--border-light); }

@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions > button { width: 100%; }
}
</style>