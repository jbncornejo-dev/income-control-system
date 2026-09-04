import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // Solo guardamos los metadatos del usuario para la UI
  const user = ref(JSON.parse(localStorage.getItem('user')) || null)

  // Si hay datos de usuario, asumimos que la sesión web está activa
  const isAuthenticated = computed(() => !!user.value)
  const userRole = computed(() => user.value?.role || null)

  const setUser = (userData) => {
    user.value = userData
    localStorage.setItem('user', JSON.stringify(userData))
  }

  const logout = () => {
    user.value = null
    localStorage.removeItem('user')
  }

  return { user, isAuthenticated, userRole, setUser, logout }
})
