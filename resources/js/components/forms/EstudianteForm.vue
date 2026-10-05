<template>
  <div>
    <div class="form-group">
      <TextInput
        id="nombres"
        type="text"
        v-model="form.nombres"
        label="Nombres *"
        placeholder="Nombres del estudiante"
        :class="{ 'input-error': form.errors.nombres }"
      />
      <p v-if="form.errors.nombres" class="error-msg">{{ form.errors.nombres }}</p>
    </div>
    <div class="form-group">
      <TextInput
        id="apellidos"
        type="text"
        v-model="form.apellidos"
        label="Apellidos *"
        placeholder="Apellidos del estudiante"
        :class="{ 'input-error': form.errors.apellidos }"
      />
      <p v-if="form.errors.apellidos" class="error-msg">{{ form.errors.apellidos }}</p>
    </div>
    <div class="form-group">
      <TextInput
        id="codigo_universitario"
        type="text"
        v-model="form.codigo_universitario"
        label="Código Universitario *"
        placeholder="9 dígitos iniciando con el año de ingreso (ej: 201809372)"
        maxlength="9"
        inputmode="numeric"
        :disabled="isEdit"
        :class="{ 'input-error': form.errors.codigo_universitario }"
      />
      <p v-if="form.errors.codigo_universitario" class="error-msg">{{ form.errors.codigo_universitario }}</p>
    </div>
    <div class="form-group">
      <TextInput
        id="documento_identidad"
        type="text"
        v-model="form.documento_identidad"
        label="Documento de Identidad *"
        placeholder="6 a 8 dígitos (ej: 12590804)"
        maxlength="8"
        inputmode="numeric"
        :disabled="isEdit"
        :class="{ 'input-error': form.errors.documento_identidad }"
      />
      <p v-if="form.errors.documento_identidad" class="error-msg">{{ form.errors.documento_identidad }}</p>
    </div>
    <div class="form-group">
      <TextInput
        id="email"
        type="email"
        v-model="form.email"
        label="Correo institucional"
        placeholder="Ej: 201809372@est.umss.edu"
        maxlength="255"
        :class="{ 'input-error': form.errors.email }"
      />
      <p v-if="form.errors.email" class="error-msg">{{ form.errors.email }}</p>
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
import TextInput from '@/components/ui/TextInput.vue'
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
  email: props.estudiante?.email || '',
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
        toastStore.success('Estudiante registrado exitosamente.')
        emit('success')
      },
      onError: () => {
        toastStore.error('Por favor, revisa los errores en el formulario.')
      },
    })
  }
}

defineExpose({ emitirGuardado })
</script>

<style scoped>
/* Los estilos son manejados por forms.css y TextInput globalmente */
</style>