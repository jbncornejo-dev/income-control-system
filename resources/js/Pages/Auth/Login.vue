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
              <label>Correo, código o documento</label>
              <input 
                type="text" 
                v-model.trim="loginForm.identificador" 
                required 
                autocomplete="username" 
                class="input-control"
                placeholder="ej: 201809372"
              />
              <p v-if="loginForm.errors.identificador" class="form-error" role="alert">{{ loginForm.errors.identificador }}</p>
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
              <div class="texcorp-brand" aria-label="TexCorp"><svg class="texcorp-x" viewBox="0 0 160 96" xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 30,0 95,48 65,48" fill="#1f2d4f"/><polygon points="65,48 95,48 160,96 130,96" fill="#c8641f"/><polygon points="112,0 160,0 48,96 0,96" fill="#d6d1ca"/></svg><span class="texcorp-text">TEXCORP</span></div>
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

const loginForm = useForm({ identificador: '', password: '' })
const loginError = ref('')

function handleLogin() {
  loginError.value = ''
  loginForm.post('/login', {
    onError: () => {
      toastStore.error('Credenciales inválidas. Intenta de nuevo.')
      if (!loginForm.errors.identificador && !loginForm.errors.password) {
        loginError.value = 'Credenciales inválidas. Intenta de nuevo.'
      }
    },
    onFinish: () => loginForm.reset('password'),
  })
}
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&display=swap");
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
.logo-large { font-family: "Orbitron", sans-serif; letter-spacing: 0.12em; }
.info-panel { background: radial-gradient(circle at 30% 20%, #2b4f78 0%, #1d3653 45%, #0f2238 100%); }
.umss-logo { transition: scale 0.35s ease, filter 0.35s ease; }
.umss-logo:hover { scale: 1.08; filter: drop-shadow(0 0 18px rgba(255, 255, 255, 0.35)); }
.dev-text { font-family: "Orbitron", sans-serif; font-size: 0.75rem; letter-spacing: 0.3em; color: rgba(255, 255, 255, 0.75); }
.input-control { transition: border-color 0.25s ease, box-shadow 0.25s ease; }
.input-control:focus { border-color: #1d3653 !important; box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15), 0 0 14px rgba(29, 54, 83, 0.2) !important; outline: none; }
.btn-submit { font-family: "Orbitron", sans-serif !important; text-transform: uppercase; letter-spacing: 0.2em; clip-path: polygon(12px 0, 100% 0, calc(100% - 12px) 100%, 0 100%); transition: letter-spacing 0.3s ease, background 0.3s ease !important; }
.btn-submit:hover { letter-spacing: 0.26em; background: #a12b33 !important; }
.texcorp-brand { display: flex; flex-direction: column; align-items: center; gap: 14px; transition: scale 0.35s ease, filter 0.35s ease; cursor: default; }
.texcorp-brand:hover { scale: 1.08; filter: drop-shadow(0 0 12px rgba(255, 255, 255, 0.7)) drop-shadow(0 0 28px rgba(255, 255, 255, 0.35)); }
.texcorp-x { width: 90px; height: auto; filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.45)); }
.texcorp-text { font-family: "Orbitron", sans-serif; font-weight: 800; font-size: 2.2rem; letter-spacing: 0.22em; background: linear-gradient(90deg, #e8e4de 0%, #d6d1ca 35%, #c8641f 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
</style>