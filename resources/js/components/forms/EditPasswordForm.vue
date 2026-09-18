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
        <p class="text-sm text-gray-500 mb-2">Actualizando la contraseña para: <strong class="text-gray-800">{{ user.name }}</strong></p>
        
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
.custom-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
label { font-size: 0.875rem; font-weight: 600; color: #1f2937; }
:deep(.input-control) { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; color: #374151; box-sizing: border-box; transition: border-color 0.2s; }
:deep(.input-control):focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
.password-box { background-color: #f8fafc; padding: 1.25rem; border-radius: 0.5rem; border: 1px solid #f1f5f9; }
.error-msg { color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; }
.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
</style>