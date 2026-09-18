<template>
  <div>
    <div class="form-group">
      <label class="form-label">Nombres <span class="required">*</span></label>
      <input
        type="text"
        v-model="form.nombres"
        placeholder="Nombres del estudiante"
        class="form-input"
        :class="{ 'input-error': form.errors.nombres }"
      />
      <p v-if="form.errors.nombres" class="error-msg">{{ form.errors.nombres }}</p>
    </div>
    <div class="form-group">
      <label class="form-label">Apellidos <span class="required">*</span></label>
      <input
        type="text"
        v-model="form.apellidos"
        placeholder="Apellidos del estudiante"
        class="form-input"
        :class="{ 'input-error': form.errors.apellidos }"
      />
      <p v-if="form.errors.apellidos" class="error-msg">{{ form.errors.apellidos }}</p>
    </div>
    <div class="form-group">
      <label class="form-label">Código Universitario <span class="required">*</span></label>
      <input
        type="text"
        v-model="form.codigo_universitario"
        placeholder="Ej: 2020-12345"
        maxlength="20"
        :disabled="isEdit"
        class="form-input"
        :class="{ 'input-error': form.errors.codigo_universitario, 'input-readonly': isEdit }"
      />
      <p v-if="form.errors.codigo_universitario" class="error-msg">{{ form.errors.codigo_universitario }}</p>
    </div>
    <div class="form-group">
      <label class="form-label">Documento de Identidad <span class="required">*</span></label>
      <input
        type="text"
        v-model="form.documento_identidad"
        placeholder="CI / Documento"
        maxlength="20"
        :disabled="isEdit"
        class="form-input"
        :class="{ 'input-error': form.errors.documento_identidad, 'input-readonly': isEdit }"
      />
      <p v-if="form.errors.documento_identidad" class="error-msg">{{ form.errors.documento_identidad }}</p>
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
import { computed } from 'vue'

const props = defineProps({
  estudiante: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['success'])
const toastStore = useToastStore()

const isEdit = computed(() => !!props.estudiante?.id)

const form = useForm({
  nombres: props.estudiante?.nombres || '',
  apellidos: props.estudiante?.apellidos || '',
  codigo_universitario: props.estudiante?.codigo_universitario || '',
  documento_identidad: props.estudiante?.documento_identidad || '',
})

const emitirGuardado = () => {
  if (isEdit.value) {
    form.put(`/estudiantes/${props.estudiante.id}`, {
      onSuccess: () => {
        toastStore.success('Estudiante actualizado correctamente.')
        emit('success')
      },
      onError: () => {
        toastStore.error('Error al actualizar el estudiante.')
      },
    })
  } else {
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
}

defineExpose({ emitirGuardado })
</script>

<style scoped>
.form-group { margin-bottom: 16px; }
.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-main);
  margin-bottom: 6px;
}
.required { color: #d32f2f; }
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--color-white-soft);
  border-radius: 6px;
  font-size: 14px;
  background: var(--color-bg-input);
  color: var(--color-text-main);
  box-sizing: border-box;
  font-family: var(--font-main);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.form-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-input-focus);
}
.input-error { border-color: #d32f2f !important; }
.input-readonly {
  opacity: 0.6;
  cursor: not-allowed;
}
.input-readonly:focus {
  border-color: var(--color-white-soft);
  box-shadow: none;
}
.error-msg { color: #d32f2f; font-size: 12px; margin-top: 4px; }
</style>