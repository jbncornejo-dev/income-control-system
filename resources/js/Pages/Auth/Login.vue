<template>
  <div class="login-wrapper">
    <div class="container" :class="{ active: isActive }">

      <!-- ==================== REGISTRO ==================== -->
      <div class="form-container sign-up">
        <form @submit.prevent="handleRegister">
          <h1>Crear Cuenta</h1>
          <span>Usa tu correo institucional</span>

          <input
            type="text"
            placeholder="Nombre Completo"
            v-model.trim="registerForm.name"
            required
            autocomplete="name"
          />
          <p v-if="registerForm.errors.name" class="form-error" role="alert">{{ registerForm.errors.name }}</p>
          <input
            type="email"
            placeholder="Correo Electrónico"
            v-model.trim="registerForm.email"
            required
            autocomplete="email"
          />
          <p v-if="registerForm.errors.email" class="form-error" role="alert">{{ registerForm.errors.email }}</p>
          <input
            type="password"
            placeholder="Contraseña"
            v-model="registerForm.password"
            required
            autocomplete="new-password"
            minlength="8"
          />
          <p v-if="registerForm.errors.password" class="form-error" role="alert">{{ registerForm.errors.password }}</p>

          <p v-if="registerError" class="form-error" role="alert">{{ registerError }}</p>

          <button type="submit" :disabled="registerForm.processing">
            {{ registerForm.processing ? 'Registrando...' : 'Registrarse' }}
          </button>
        </form>
      </div>

      <!-- ==================== LOGIN ==================== -->
      <div class="form-container sign-in">
        <form @submit.prevent="handleLogin">
          <h1>Iniciar Sesión</h1>
          <span>Ingresa tus credenciales</span>

          <input
            type="email"
            placeholder="Correo Electrónico"
            v-model.trim="loginForm.email"
            required
            autocomplete="email"
          />
          <p v-if="loginForm.errors.email" class="form-error" role="alert">{{ loginForm.errors.email }}</p>
          <input
            type="password"
            placeholder="Contraseña"
            v-model="loginForm.password"
            required
            autocomplete="current-password"
          />
          <p v-if="loginForm.errors.password" class="form-error" role="alert">{{ loginForm.errors.password }}</p>
          <a href="#" @click.prevent>¿Olvidaste tu contraseña?</a>

          <p v-if="loginError" class="form-error" role="alert">{{ loginError }}</p>

          <button type="submit" :disabled="loginForm.processing">
            {{ loginForm.processing ? 'Ingresando...' : 'Ingresar' }}
          </button>
        </form>
      </div>

      <!-- ==================== PANEL DE TRANSICIÓN ==================== -->
      <div class="toggle-container">
        <div class="toggle">

          <!-- Panel izquierdo -->
          <div class="toggle-panel toggle-left">
            <h1>¡Bienvenido!</h1>
            <p>Si ya tienes una cuenta, ingresa aquí para gestionar el control de ingresos.</p>
            <button type="button" class="hidden" @click="switchToLogin">Iniciar Sesión</button>
          </div>

          <!-- Panel derecho -->
          <div class="toggle-panel toggle-right">
            <h1>Sistema de Ingresos</h1>
            <p>Registra tus datos para solicitar acceso a la plataforma.</p>
            <button type="button" class="hidden" @click="switchToRegister">Registrarse</button>
          </div>

        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const isActive = ref(false)

const loginForm = useForm({
  email: '',
  password: '',
})

const registerForm = useForm({
  name: '',
  email: '',
  password: '',
})

const loginError = ref('')
const registerError = ref('')

function switchToLogin() {
  isActive.value = false
  registerError.value = ''
}

function switchToRegister() {
  isActive.value = true
  loginError.value = ''
}

function handleLogin() {
  loginError.value = ''
  loginForm.post('/login', {
    onError: () => {
      // Inertia ya mapea errors a loginForm.errors; mensaje genérico opcional
      if (!loginForm.errors.email && !loginForm.errors.password) {
        loginError.value = 'Credenciales inválidas. Intenta de nuevo.'
      }
    },
  })
}

function handleRegister() {
  registerError.value = ''
  registerForm.post('/register', {
    onSuccess: () => {
      registerForm.reset('password')
      switchToLogin()
    },
    onError: () => {
      if (!registerForm.errors.email && !registerForm.errors.name && !registerForm.errors.password) {
        registerError.value = 'No se pudo completar el registro.'
      }
    },
  })
}
</script>

<style scoped>
.form-error {
  color: #b3261e;
  font-size: 12px;
  margin: 6px 0 0;
}
</style>
