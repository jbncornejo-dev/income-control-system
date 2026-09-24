<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useForm } from '@inertiajs/vue3';
import { useToastStore } from '@/stores/useToastStore';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const toastStore = useToastStore();
const showActual = ref(false);
const showNueva = ref(false);
const showConfirmacion = ref(false);

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
  <Head title="Cambiar Contraseña" />
  <AuthenticatedLayout>
    <div class="panel-container">
      <h1 class="panel-title">CAMBIAR CONTRASEÑA</h1>

      <div class="card">
        <p class="help-text">
          Tu cuenta está usando una contraseña temporal. Para continuar, crea una
          contraseña personal nueva (mínimo 8 caracteres).
        </p>

        <form @submit.prevent="guardar" class="form-cambiar">
          <div class="form-group">
            <label class="form-label">Contraseña actual <span class="required">*</span></label>
            <div class="password-input-container">
              <input
                :type="showActual ? 'text' : 'password'"
                v-model="form.password_actual"
                required
                class="form-input"
                :class="{ 'input-error': form.errors.password_actual }"
              />
              <button type="button" class="btn-eye" @click="showActual = !showActual" aria-label="Mostrar contraseña actual">👁</button>
            </div>
            <p v-if="form.errors.password_actual" class="error-msg">{{ form.errors.password_actual }}</p>
          </div>

          <div class="form-group">
            <label class="form-label">Nueva contraseña <span class="required">*</span></label>
            <div class="password-input-container">
              <input
                :type="showNueva ? 'text' : 'password'"
                v-model="form.password"
                required
                minlength="8"
                class="form-input"
                :class="{ 'input-error': form.errors.password }"
              />
              <button type="button" class="btn-eye" @click="showNueva = !showNueva" aria-label="Mostrar nueva contraseña">👁</button>
            </div>
            <p v-if="form.errors.password" class="error-msg">{{ form.errors.password }}</p>
          </div>

          <div class="form-group">
            <label class="form-label">Confirmar nueva contraseña <span class="required">*</span></label>
            <div class="password-input-container">
              <input
                :type="showConfirmacion ? 'text' : 'password'"
                v-model="form.password_confirmation"
                required
                minlength="8"
                class="form-input"
                :class="{ 'input-error': form.errors.password_confirmation }"
              />
              <button type="button" class="btn-eye" @click="showConfirmacion = !showConfirmacion" aria-label="Mostrar confirmación">👁</button>
            </div>
            <p v-if="form.errors.password_confirmation" class="error-msg">{{ form.errors.password_confirmation }}</p>
          </div>

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
.panel-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 1.5rem; text-transform: uppercase; }
.card {
  max-width: 480px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.help-text { color: #6b7280; font-size: 14px; line-height: 1.5; margin: 0 0 1.25rem; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.required { color: #d32f2f; }
.password-input-container { position: relative; }
.password-input-container .form-input { padding-right: 42px; }
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 14px;
  background: #f9fafb;
  color: #1f2937;
  box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: #1d3653; box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15); }
.input-error { border-color: #d32f2f !important; }
.error-msg { color: #d32f2f; font-size: 12px; margin-top: 4px; }
.btn-eye {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  opacity: 0.7;
}
.btn-submit { width: 100%; margin-top: 8px; }
</style>