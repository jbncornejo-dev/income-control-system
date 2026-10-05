<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import SelectInput from '@/components/ui/SelectInput.vue';

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
    <form @submit.prevent="submit" class="custom-form">
        <!-- Campo: Nombre Completo -->
        <div class="form-group">
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                label="Nombre Completo"
                placeholder="Ej. Juan Pérez"
                required
                autofocus
            />
            <span v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</span>
        </div>

        <!-- Fila: Username y Email -->
        <div class="form-row">
            <div class="form-group">
                <TextInput
                    id="username"
                    v-model="form.username"
                    type="text"
                    label="Nombre de Usuario"
                    placeholder="Ej. jperez"
                    required
                />
                <span v-if="form.errors.username" class="error-msg">{{ form.errors.username }}</span>
            </div>

            <div class="form-group">
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    label="Correo Electrónico"
                    placeholder="correo@ejemplo.com"
                    required
                />
                <span v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</span>
            </div>
        </div>

        <!-- Campo: Rol -->
        <div class="form-group">
            <SelectInput
                id="id_rol"
                v-model="form.id_rol"
                :options="roles.map(r => ({ value: r.id_rol, label: r.nombre_rol }))"
                label="Rol del Usuario"
                placeholder="Seleccione un rol..."
                :capitalize="true"
            />
            <span v-if="form.errors.id_rol" class="error-msg">{{ form.errors.id_rol }}</span>
        </div>

        <!-- Fila: Contraseñas -->
        <div class="form-row">
            <div class="form-group">
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    label="Contraseña"
                    required
                />
                <span v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</span>
            </div>

            <div class="form-group">
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    label="Confirmar Contraseña"
                    required
                />
                <span v-if="form.errors.password_confirmation" class="error-msg">{{ form.errors.password_confirmation }}</span>
            </div>
        </div>

        <!-- Acciones -->
        <div class="form-actions">
            <Button type="button" variant="action" class="btn-uniform" @click="$emit('cancel')">
                Cancelar
            </Button>
            <Button type="submit" variant="primary" class="btn-uniform" :disabled="form.processing">
                Crear Usuario
            </Button>
        </div>
    </form>
</template>

<style scoped>
.custom-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
    width: 100%;
    padding: var(--spacing-xs) 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-lg);
    width: 100%;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-xs);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--border-light);
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
    .form-row {
        grid-template-columns: 1fr;
        gap: var(--spacing-lg);
    }
    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }
    .btn-uniform {
        width: 100%;
    }
}
</style>