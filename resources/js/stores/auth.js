import { defineStore } from 'pinia'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const useAuthStore = defineStore('auth', () => {
  // Auth ahora viene de Inertia shared props (HandleInertiaRequests), no localStorage
  const page = usePage()
  const user = computed(() => page.props.auth?.user ?? null)
  const isAuthenticated = computed(() => !!user.value)
  const userRole = computed(() => user.value?.role || null)

  // Compatibilidad con vistas previas que llamaban setUser/logout; ahora son no-ops o helpers UI
  const setUser = () => {}
  const logout = () => {}

  return { user, isAuthenticated, userRole, setUser, logout }
})
