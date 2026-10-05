<template>
  <Head title="Acceso al Sistema | CIE UMSS" />

  <div class="login-page antialiased">
    <!-- Fondos -->
    <div class="bg-campus">
      <img src="/paseoUniversitario.jpg" alt="Campus Universitario" class="campus-img" />
    </div>
    <div class="bg-overlay"></div>

    <nav class="public-nav">
      <Link href="/" class="back-link group">
        <svg class="back-icon group-hover-translate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        <div class="back-text font-display">
          Volver al <span class="text-brand-gold">Portal</span>
        </div>
      </Link>
    </nav>

    <main class="main-content">
      <div class="auth-card fade-in-up">
        
        <!-- PANEL IZQUIERDO: FORMULARIO -->
        <div class="left-panel">
          
          <!-- Encabezado del Formulario -->
          <div class="panel-header">
            <div class="brand-header">
              <div class="gold-line"></div>
              <h1 class="brand-title font-display">CIE</h1>
            </div>
            <h2 class="section-title">Acceso Restringido</h2>
            <p class="section-desc">Ingrese sus credenciales administrativas para continuar al panel de control de evaluaciones.</p>
          </div>

          <!-- Formulario -->
          <form @submit.prevent="handleLogin" class="login-form">
            
            <!-- Input Identificador (Correo, código o documento) -->
            <div class="form-group">
              <label for="identificador" class="form-label">Correo Electrónico Institucional</label>
              <div class="input-wrapper">
                <div class="input-icon">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <input 
                  type="text" 
                  id="identificador" 
                  v-model.trim="loginForm.identificador"
                  required 
                  autocomplete="username"
                  placeholder="nombre@umss.edu.bo" 
                  class="form-input"
                >
              </div>
              <p v-if="loginForm.errors.identificador" class="form-error" role="alert">{{ loginForm.errors.identificador }}</p>
            </div>

            <!-- Input Password -->
            <div class="form-group">
              <div class="password-header">
                <label for="password" class="form-label">Contraseña</label>
                <!-- <a href="#" @click.prevent class="forgot-link">¿Olvidó su clave?</a> -->
              </div>
              <div class="input-wrapper">
                <div class="input-icon">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  id="password" 
                  v-model="loginForm.password"
                  required 
                  autocomplete="current-password"
                  placeholder="••••••••" 
                  class="form-input password-input"
                >
                
                <!-- Toggle Visibilidad Contraseña -->
                <button type="button" @click="showPassword = !showPassword" class="toggle-password" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                  <svg v-if="!showPassword" class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  <svg v-else class="eye-icon text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                  </svg>
                </button>
              </div>
              <p v-if="loginForm.errors.password" class="form-error" role="alert">{{ loginForm.errors.password }}</p>
            </div>

            <p v-if="loginError" class="form-error global-error" role="alert">{{ loginError }}</p>

            <!-- Botón Submit -->
            <div class="submit-wrapper">
              <button type="submit" class="btn-submit font-display" :disabled="loginForm.processing">
                <template v-if="loginForm.processing">
                  AUTENTICANDO...
                </template>
                <template v-else>
                  Autenticar
                  <svg class="submit-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </template>
              </button>
            </div>
          </form>

        </div>

        <!-- PANEL DERECHO: IDENTIDAD INSTITUCIONAL -->
        <div class="right-panel">
          <!-- Decoración de fondo abstracta -->
          <div class="bg-deco-top"></div>
          <div class="bg-deco-bottom"></div>

          <div class="right-content">
            
            <!-- Emblema Principal UMSS -->
            <div class="umss-emblem-container">
              <div class="umss-circle">
                <span class="umss-text font-display">U<span class="text-brand-gold">M</span>SS</span>
              </div>
              <h3 class="umss-subtitle font-display">Universidad Mayor</h3>
              <p class="umss-subtext font-display text-brand-gold">De San Simón</p>
              <div class="umss-divider"></div>
              <p class="umss-desc">
                Sistema Central de Control de Ingresos para Procesos de Evaluación.
              </p>
            </div>

            <!-- Footer / Agencia -->
            <div class="agency-footer">
              <span class="agency-label">Desarrollado por</span>
              <div class="agency-logo-container">
                <img src="/images/TexCorp.png" alt="TEXCORP" class="texcorp-img" />
                <span class="texcorp-text font-display">TEXCORP</span>
              </div>
            </div>

          </div>
        </div>

      </div>
    </main>

    <footer class="public-footer">
      &copy; {{ new Date().getFullYear() }} TexCorp. Todos los derechos reservados.
    </footer>

    <ToastContainer />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { useToastStore } from '@/stores/useToastStore'
import ToastContainer from '@/components/ui/ToastContainer.vue'

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
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;600;700;900&display=swap');

/* Reset base variables mapped to mockup */
.login-page {
  font-family: 'Inter', sans-serif;
  color: #1f2937;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
}

.font-display {
  font-family: 'Orbitron', sans-serif;
}

.text-brand-gold { color: #C5A059; }
.text-brand-primary { color: #1D3653; }

/* Fondos Inmersivos */
.bg-campus {
  position: absolute;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}

.campus-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  filter: blur(8px);
  transform: scale(1.05); /* Evita bordes por el blur */
}

.bg-overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: linear-gradient(135deg, rgba(10,25,47,0.75) 0%, rgba(29,54,83,0.5) 100%);
}

/* Navbar de retroceso */
.public-nav {
  width: 100%;
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #ffffff;
  z-index: 10;
  position: relative;
}
@media (min-width: 1024px) {
  .public-nav { padding: 1.5rem 3rem; }
}

.back-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  transition: all 0.3s ease;
  text-decoration: none;
  color: #ffffff;
}

.back-icon {
  width: 1.5rem;
  height: 1.5rem;
  color: #C5A059;
  transition: transform 0.3s ease;
}

.group-hover-translate {
  transform: translateX(0);
}
.back-link:hover .group-hover-translate {
  transform: translateX(-4px);
}

.back-text {
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

/* Contenido Principal */
.main-content {
  flex-grow: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 10;
  position: relative;
}
@media (min-width: 640px) {
  .main-content { padding: 1.5rem; }
}

/* Tarjeta Principal */
.auth-card {
  width: 100%;
  max-width: 64rem; /* max-w-5xl */
  display: flex;
  flex-direction: column;
  background-color: #ffffff;
  border-radius: 1rem;
  box-shadow: 0 20px 40px -10px rgba(10, 25, 47, 0.5);
  overflow: hidden;
}
@media (min-width: 768px) {
  .auth-card { flex-direction: row; }
}

/* Panel Izquierdo */
.left-panel {
  width: 100%;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
}
@media (min-width: 768px) {
  .left-panel { width: 60%; padding: 3rem; }
}
@media (min-width: 1024px) {
  .left-panel { padding: 4rem; }
}

/* Encabezado Panel Izquierdo */
.panel-header {
  margin-bottom: 2.5rem;
}

.brand-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.gold-line {
  height: 2px;
  width: 2rem;
  background-color: #C5A059;
}

.brand-title {
  font-weight: 900;
  font-size: 1.875rem;
  letter-spacing: 0.025em;
  color: #1D3653;
  margin: 0;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0.5rem;
  font-family: 'Inter', sans-serif;
}

.section-desc {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0;
}

/* Formulario */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
  letter-spacing: 0.025em;
  text-transform: uppercase;
}

.password-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}
.password-header .form-label { margin-bottom: 0; }

.forgot-link {
  font-size: 0.75rem;
  font-weight: 500;
  color: #3A5A7E;
  text-decoration: none;
  transition: color 0.2s ease;
}
.forgot-link:hover {
  color: #1D3653;
  text-decoration: underline;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  padding-left: 1rem;
  display: flex;
  align-items: center;
  pointer-events: none;
  color: #9ca3af;
}
.input-icon svg { width: 1.25rem; height: 1.25rem; }

.form-input {
  display: block;
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.75rem;
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  color: #111827;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  outline: none;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #1D3653;
  background-color: #ffffff;
  box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15);
}

.password-input {
  padding-right: 3rem;
}

.toggle-password {
  position: absolute;
  top: 0;
  bottom: 0;
  right: 0;
  padding-right: 1rem;
  display: flex;
  align-items: center;
  color: #9ca3af;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: color 0.2s ease;
  outline: none;
}
.toggle-password:hover { color: #1D3653; }
.eye-icon { width: 1.25rem; height: 1.25rem; }

.form-error {
  margin-top: 0.5rem;
  font-size: 0.75rem;
  color: #A12B33;
}
.global-error {
  font-weight: 600;
  background-color: #fef2f2;
  border-left: 3px solid #A12B33;
  padding: 0.5rem 0.75rem;
}

/* Botón Submit */
.submit-wrapper {
  padding-top: 1rem;
}

.btn-submit {
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  z-index: 1;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
  background-color: #1D3653;
  color: #ffffff;
  padding: 0.875rem 1rem;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  cursor: pointer;
  outline: none;
}

.btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-submit::before {
  content: '';
  position: absolute;
  top: 0; left: 0; width: 0%; height: 100%;
  background-color: #0A192F;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: -1;
}

.btn-submit:not(:disabled):hover::before {
  width: 100%;
}

.btn-submit:not(:disabled):hover {
  box-shadow: 0 8px 20px rgba(10, 25, 47, 0.3);
  transform: translateY(-1px);
}

.submit-icon {
  width: 1rem;
  height: 1rem;
}

/* Panel Derecho */
.right-panel {
  width: 100%;
  background-color: #0A192F;
  padding: 2rem;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}
@media (min-width: 768px) {
  .right-panel { width: 40%; padding: 3rem; }
}

.bg-deco-top {
  position: absolute;
  top: 0; right: 0;
  margin-right: -4rem; margin-top: -4rem;
  width: 16rem; height: 16rem;
  border-radius: 9999px;
  background-color: #1D3653;
  opacity: 0.2;
  filter: blur(40px);
}

.bg-deco-bottom {
  position: absolute;
  bottom: 0; left: 0;
  margin-left: -4rem; margin-bottom: -4rem;
  width: 12rem; height: 12rem;
  border-radius: 9999px;
  background-color: #C5A059;
  opacity: 0.1;
  filter: blur(32px);
}

.right-content {
  position: relative;
  z-index: 10;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 4rem;
}

/* Emblema UMSS */
.umss-emblem-container {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.umss-circle {
  width: 5rem;
  height: 5rem;
  border: 2px solid rgba(197, 160, 89, 0.3);
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.umss-text {
  font-weight: 700;
  font-size: 1.875rem;
  color: #ffffff;
  letter-spacing: -0.05em;
}

.umss-subtitle {
  color: #ffffff;
  font-size: 1.125rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  margin-bottom: 0.25rem;
}

.umss-subtext {
  font-size: 0.875rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin: 0;
}

.umss-divider {
  height: 1px;
  width: 3rem;
  background-color: rgba(255, 255, 255, 0.2);
  margin: 1.5rem auto;
}

.umss-desc {
  color: #9ca3af;
  font-size: 0.75rem;
  line-height: 1.625;
  max-width: 250px;
  font-weight: 300;
  margin: 0;
}

/* Agency Footer */
.agency-footer {
  display: flex;
  flex-direction: column;
  align-items: center;
  opacity: 0.8;
  transition: opacity 0.3s ease;
}
.agency-footer:hover { opacity: 1; }

.agency-label {
  font-size: 0.625rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 0.75rem;
  font-weight: 600;
}

.agency-logo-container {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.texcorp-img {
  height: 24px;
  object-fit: contain;
}

.texcorp-text {
  color: #ffffff;
  font-weight: 700;
  letter-spacing: 0.1em;
  font-size: 0.875rem;
}

/* Global Footer */
.public-footer {
  width: 100%;
  text-align: center;
  padding: 1.5rem;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.5);
  z-index: 10;
  position: relative;
}

/* Animaciones */
.fade-in-up {
  animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  opacity: 0;
  transform: translateY(30px);
}

@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .fade-in-up {
    animation: none;
    opacity: 1;
    transform: translateY(0);
  }
}
</style>