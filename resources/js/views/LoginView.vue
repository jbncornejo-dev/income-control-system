<template>
  <div class="login-container">
    <form @submit.prevent="handleLogin" class="login-card">
      <h2 style="color: var(--color-primary);">Acceso al Sistema</h2>
      <div class="input-group">
        <label>Usuario / Correo</label>
        <input type="text" v-model="credentials.email" required />
      </div>
      <div class="input-group">
        <label>Contraseña</label>
        <input type="password" v-model="credentials.password" required />
      </div>
      <button type="submit" class="btn-primary">Ingresar</button>
    </form>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const credentials = reactive({ email: '', password: '' });

const handleLogin = () => {
  // Lógica temporal de redirección. Luego usaremos Axios.
  console.log('Autenticando:', credentials.email);
  router.push('/dashboard'); 
};
</script>

<style scoped>
.login-container { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: var(--color-bg-base); }
.login-card { background: var(--color-white); padding: 40px; border-radius: var(--radius-md); box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; font-family: var(--font-main); }
.input-group { margin-bottom: 20px; }
.input-group label { display: block; margin-bottom: 5px; color: var(--color-text-main); }
.input-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.btn-primary { width: 100%; background-color: var(--color-primary); color: var(--color-white); border: none; border-radius: var(--radius-md); padding: 12px; cursor: pointer; transition: 0.3s; }
.btn-primary:hover { background-color: var(--color-secondary); }
</style>