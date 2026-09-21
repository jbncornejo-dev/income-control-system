<template>
  <div class="login-body">
    <!-- Navbar público reutilizado -->
    <PublicNavbar />

    <main class="login-main">
      <div class="auth-container">
        
        <!-- Panel Izquierdo: Formulario de Login -->
        <div class="login-panel">
          <div class="panel-header">
            <div class="logo-large">CIE</div>
            <h2>Iniciar Sesión</h2>
            <p>Acceso para personal autorizado</p>
          </div>

          <form @submit.prevent="handleLogin" class="login-form">
            <div class="form-group">
              <label>Correo Electrónico</label>
              <input 
                type="email" 
                v-model.trim="loginForm.email" 
                required 
                autocomplete="email" 
                class="input-control"
              />
              <p v-if="loginForm.errors.email" class="form-error" role="alert">{{ loginForm.errors.email }}</p>
            </div>

            <div class="form-group">
              <label>Contraseña</label>
              <div class="password-input-container">
                <input
                  :type="showPassword ? 'text' : 'password'"
                  v-model="loginForm.password"
                  required
                  autocomplete="current-password"
                  class="input-control"
                />
                
                <button type="button" class="btn-eye" @click="showPassword = !showPassword" aria-label="Mostrar contraseña">
                  <!-- Icono de Ojo Abierto -->
                  <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <!-- Icono de Ojo Cerrado -->
                  <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
              <p v-if="loginForm.errors.password" class="form-error" role="alert">{{ loginForm.errors.password }}</p>
              
              <div class="forgot-link">
                <a href="#" @click.prevent>¿Olvidaste tu contraseña?</a>
              </div>
            </div>

            <p v-if="loginError" class="form-error global-error" role="alert">{{ loginError }}</p>

            <Button type="submit" variant="primary" class="btn-submit" :disabled="loginForm.processing">
              <LoadingSpinner v-if="loginForm.processing" size="small" />
              <span v-else>Ingresar</span>
            </Button>
          </form>
        </div>

        <!-- Panel Derecho: Información (Sin opción de crear cuenta) -->
        <div class="info-panel">
          <div class="logos-container">
            <img src="/images/umss-logo.png" alt="Logotipo UMSS" class="umss-logo" />
            
            <div class="dev-container">
              <span class="dev-text">Desarrollado por</span>
              <img src="/images/texcorp-logo.png" alt="Logotipo Texcorp" class="texcorp-logo" />
            </div>
          </div>
        </div>

      </div>
    </main>

    <footer class="login-footer">
      Copyright &copy; {{ new Date().getFullYear() }} TexCorp. Todos los derechos reservados.
    </footer>
    
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToastStore } from '@/stores/useToastStore'
import PublicNavbar from '@/components/ui/PublicNavbar.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import Button from '@/components/ui/Button.vue'

const toastStore = useToastStore()
const showPassword = ref(false)

const loginForm = useForm({ email: '', password: '' })
const loginError = ref('')

function handleLogin() {
  loginError.value = ''
  loginForm.post('/login', {
    onError: () => {
      toastStore.error('Credenciales inválidas. Intenta de nuevo.')
      if (!loginForm.errors.email && !loginForm.errors.password) {
        loginError.value = 'Credenciales inválidas. Intenta de nuevo.'
      }
    },
    onFinish: () => loginForm.reset('password'),
  })
}
</script>

<style scoped>
/* Hereda el fondo base del sistema */
.login-body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: var(--bg-main, #f4f4f4);
}

.login-main {
    flex-grow: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
}

/* Tarjeta principal dividida */
.auth-container {
    display: flex;
    background-color: var(--text-white, #ffffff);
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    width: 100%;
    max-width: 850px;
    border-top: 4px solid var(--color-active, #a12b33);
}

/* --- Panel del Formulario (Izquierda) --- */
.login-panel {
    flex: 1.2;
    padding: 50px 60px;
    display: flex;
    flex-direction: column;
}

.panel-header {
    text-align: center;
    margin-bottom: 35px;
}

.logo-large {
    font-size: 32px;
    font-weight: 900;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
    color: var(--color-primary, #1d3653);
}

.panel-header h2 {
    font-size: 20px;
    color: var(--text-dark, #333333);
    margin-bottom: 5px;
}

.panel-header p {
    font-size: 13px;
    color: #6b7280;
}

/* Inputs y Formularios */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
}

.input-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-light, #d1d5db);
    border-radius: 4px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    color: var(--text-dark, #333333);
}

.input-control:focus {
    border-color: var(--color-primary, #1d3653);
}

/* Contenedor de contraseña con ojo integrado */
.password-input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-container input {
    padding-right: 45px; /* Espacio para que el texto no pise el icono */
}

.btn-eye {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.btn-eye:hover {
    color: var(--color-primary, #1d3653);
}

.btn-eye svg {
    width: 20px;
    height: 20px;
}

.forgot-link {
    text-align: right;
    margin-top: 8px;
}

.forgot-link a {
    font-size: 12px;
    color: #2563eb;
    text-decoration: none;
}

.forgot-link a:hover {
    text-decoration: underline;
}

.btn-submit {
    width: 100%;
    margin-top: 10px;
    padding: 12px !important;
    font-size: 14px !important;
}

.btn-submit:hover:not(:disabled) {
    opacity: 0.9;
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.form-error { 
    color: #b3261e; 
    font-size: 12px; 
    margin: 6px 0 0; 
    font-weight: 500;
}

.global-error {
    text-align: center;
    margin-bottom: 15px;
}

/* --- Panel de Información (Derecha) --- */
.info-panel {
    flex: 1;
    background-color: var(--color-primary, #1d3653);
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.logos-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4rem;
}

.umss-logo {
    width: 100%;
    max-width: 220px;
    height: auto;
}

.dev-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.dev-text {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.texcorp-logo {
    width: 100%;
    max-width: 160px;
    height: auto;
}

.info-panel h2 {
    font-size: 24px;
    margin-bottom: 20px;
    font-weight: 600;
}

.info-panel p {
    font-size: 14px;
    margin-bottom: 15px;
    line-height: 1.6;
    color: #e5e7eb;
}

.info-subtext {
    font-size: 12px !important;
    opacity: 0.8;
    margin-top: 2x0px;
}

/* Footer */
.login-footer {
    text-align: center;
    padding: 20px;
    font-size: 13px;
    color: #9ca3af;
}
</style>