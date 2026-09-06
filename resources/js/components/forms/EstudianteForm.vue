<template>
  <div>
    <div class="form-group">
      <label>Nombres *</label>
      <input type="text" v-model="form.nombres" placeholder="Nombres del estudiante" />
      <p v-if="form.errors.nombres" class="form-error">{{ form.errors.nombres }}</p>
    </div>
    <div class="form-group">
      <label>Apellidos *</label>
      <input type="text" v-model="form.apellidos" placeholder="Apellidos del estudiante" />
      <p v-if="form.errors.apellidos" class="form-error">{{ form.errors.apellidos }}</p>
    </div>
    <div class="form-group">
      <label>Código Universitario *</label>
      <input type="text" v-model="form.codigo_universitario" placeholder="Ej: 2020-12345" maxlength="20" />
      <p v-if="form.errors.codigo_universitario" class="form-error">{{ form.errors.codigo_universitario }}</p>
    </div>
    <div class="form-group">
      <label>Documento de Identidad *</label>
      <input type="text" v-model="form.documento_identidad" placeholder="CI / Documento" maxlength="20" />
      <p v-if="form.errors.documento_identidad" class="form-error">{{ form.errors.documento_identidad }}</p>
    </div>
    <div class="form-group">
      <label>Código QR (opcional)</label>
      <input type="text" v-model="form.codigo_qr" placeholder="QR si aplica" maxlength="255" />
      <p v-if="form.errors.codigo_qr" class="form-error">{{ form.errors.codigo_qr }}</p>
    </div>
    <div v-if="form.processing" style="display: flex; justify-content: center; margin-top: 12px;">
      <LoadingSpinner size="medium" />
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { useToastStore } from '@/stores/useToastStore'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const emit = defineEmits(['success'])
const toastStore = useToastStore()

const form = useForm({
  nombres: '',
  apellidos: '',
  codigo_universitario: '',
  documento_identidad: '',
  codigo_qr: '',
})

const emitirGuardado = () => {
  if (form.codigo_qr === '') form.codigo_qr = null
  form.post('/estudiantes', {
    onSuccess: () => {
      form.reset()
      toastStore.success('Estudiante registrado correctamente.')
      emit('success')
    },
    onError: () => {
      toastStore.error('Error al registrar el estudiante.')
    },
  })
}

defineExpose({ emitirGuardado })
</script>

<style scoped>
.form-group { margin-bottom: 12px; display: flex; flex-direction: column; gap: 4px; }
input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.form-error { color: #b3261e; font-size: 12px; margin: 4px 0 0; }
</style>