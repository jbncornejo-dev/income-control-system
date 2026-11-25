<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import PasswordField from '@/components/ui/PasswordField.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useToastStore } from '@/stores/useToastStore';

const toastStore = useToastStore();
const page = usePage();
const requiereClaveSegura = computed(() => page.props.auth.user.requiere_clave_segura);
const longitudMinima = computed(() => requiereClaveSegura.value ? 14 : 8);

const form = useForm({
  password_actual: '',
  password: '',
  password_confirmation: '',
});

function guardar() {
  form.post('/cambiar-password', {
    onSuccess: () => {
      toastStore.success('Contraseña actualizada correctamente.');
    },
    onError: () => {
      toastStore.error('Revisa los errores del formulario.');
    },
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>

<template>
  <Head title="Cambiar contraseña" />
  <AuthenticatedLayout>
    <div class="panel-container">
      <h1 class="panel-title">Cambiar contraseña</h1>

      <div class="card">
        <p class="help-text">
          Usa una contraseña nueva de al menos {{ longitudMinima }} caracteres<span v-if="requiereClaveSegura">, con mayúsculas, minúsculas, números y símbolos</span>.
        </p>

        <form @submit.prevent="guardar" class="form-cambiar">
          <PasswordField v-model="form.password_actual" label="Contraseña actual" toggle-label="Mostrar contraseña actual" :error="form.errors.password_actual" />
          <PasswordField v-model="form.password" label="Nueva contraseña" toggle-label="Mostrar nueva contraseña" :minlength="longitudMinima" :error="form.errors.password" />
          <PasswordField v-model="form.password_confirmation" label="Confirmar nueva contraseña" toggle-label="Mostrar confirmación" :minlength="longitudMinima" :error="form.errors.password_confirmation" />

          <div v-if="form.processing" style="display: flex; justify-content: center; margin-top: 12px;">
            <LoadingSpinner size="medium" />
          </div>

          <Button type="submit" variant="primary" class="btn-submit" :disabled="form.processing">
            Guardar contraseña
          </Button>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem; background-color: #f3f4f6; min-height: 100vh; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; }
.card {
  max-width: 480px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.help-text { color: #6b7280; font-size: 14px; line-height: 1.5; margin: 0 0 1.25rem; }
.btn-submit { width: 100%; margin-top: 8px; }
</style>
