import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import router from '../router/index';

const api = axios.create({
    baseURL: '/', // Apuntamos a la raíz en lugar de /api
    withCredentials: true, // Obligatorio para sesiones basadas en cookies
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Interceptor de Respuestas
api.interceptors.response.use(response => {
    return response;
}, error => {
    // 401: No Autenticado | 419: Token CSRF expirado o inválido
    if (error.response && [401, 419].includes(error.response.status)) {
        const authStore = useAuthStore();
        authStore.logout(); // Limpiamos el estado local
        router.push({ name: 'login' });
    }
    return Promise.reject(error);
});

export default api;