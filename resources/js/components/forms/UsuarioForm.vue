<script setup>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/components/ui/TextInput.vue';
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
            <label for="id_rol">Rol del Usuario</label>
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
    gap: 1.25rem;
    width: 100%;
    padding: 0.25rem 0;
}

/* Filas de dos columnas */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    width: 100%;
}

/* Cada campo */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-dark);
    line-height: 1.3;
}

/* Inputs y select: mismo look en todos los campos, usando tokens */
.select-control {
    width: 100%;
    height: 42px;
    padding: 0 0.8rem;
    border: 1px solid var(--border-light);
    border-radius: 0.45rem;
    font-size: 0.875rem;
    font-family: var(--font-family);
    color: var(--text-dark);
    background-color: var(--color-cream);
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

/* Select */
.select-control {
    appearance: auto;
    cursor: pointer;
    text-transform: capitalize;
}

/* Mensajes de error */
.error-msg {
    color: var(--color-active);
    font-size: 0.75rem;
    line-height: 1.2;
    margin-top: 0.1rem;
}

/* Botones */
.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
}

/* Fuerza el mismo tamaño en ambos botones, sin importar la variante */
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

/* Adaptación para pantallas pequeñas */
@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 1.25rem;
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