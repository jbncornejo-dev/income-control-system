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
            v-model.trim="registerData.name"
            required
            autocomplete="name"
          />
          <input
            type="email"
            placeholder="Correo Electrónico"
            v-model.trim="registerData.email"
            required
            autocomplete="email"
          />
          <input
            type="password"
            placeholder="Contraseña"
            v-model="registerData.password"
            required
            autocomplete="new-password"
            minlength="8"
          />

          <p v-if="registerError" class="form-error" role="alert">{{ registerError }}</p>

          <button type="submit" :disabled="isRegisterLoading">
            {{ isRegisterLoading ? 'Registrando...' : 'Registrarse' }}
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
            v-model.trim="loginData.email"
            required
            autocomplete="email"
          />
          <input
            type="password"
            placeholder="Contraseña"
            v-model="loginData.password"
            required
            autocomplete="current-password"
          />
          <a href="#" @click.prevent>¿Olvidaste tu contraseña?</a>

          <p v-if="loginError" class="form-error" role="alert">{{ loginError }}</p>

          <button type="submit" :disabled="isLoginLoading">
            {{ isLoginLoading ? 'Ingresando...' : 'Ingresar' }}
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
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const isActive = ref(false)

const loginData = reactive({ email: '', password: '' })
const registerData = reactive({ name: '', email: '', password: '' })

const isLoginLoading = ref(false)
const isRegisterLoading = ref(false)
const loginError = ref('')
const registerError = ref('')

function resetLoginForm() {
  loginData.email = ''
  loginData.password = ''
  loginError.value = ''
}

function resetRegisterForm() {
  registerData.name = ''
  registerData.email = ''
  registerData.password = ''
  registerError.value = ''
}

function switchToLogin() {
  isActive.value = false
  registerError.value = ''
}

function switchToRegister() {
  isActive.value = true
  loginError.value = ''
}

async function handleLogin() {
  loginError.value = ''
  isLoginLoading.value = true

  try {
    // Ajusta este método al que exponga tu store/API real de autenticación.
    await authStore.login({
      email: loginData.email,
      password: loginData.password,
    })
    resetLoginForm()
    router.push('/dashboard')
  } catch (error) {
    loginError.value = error?.response?.data?.message || 'Credenciales inválidas. Intenta de nuevo.'
  } finally {
    isLoginLoading.value = false
  }
}

async function handleRegister() {
  registerError.value = ''
  isRegisterLoading.value = true

  try {
    // Ajusta este método al que exponga tu store/API real de registro.
    await authStore.register({
      name: registerData.name,
      email: registerData.email,
      password: registerData.password,
    })
    resetRegisterForm()
    switchToLogin()
  } catch (error) {
    registerError.value = error?.response?.data?.message || 'No se pudo completar el registro.'
  } finally {
    isRegisterLoading.value = false
  }
}
</script>

<style scoped>
.form-error {
  color: #b3261e;
  font-size: 12px;
  margin: 6px 0 0;
}
</style>