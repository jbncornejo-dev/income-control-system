import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import LayoutBase from '../layouts/LayoutBase.vue'
import DashboardView from '../views/DashboardView.vue'
import AccessDeniedView from '../views/AccessDeniedView.vue'

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView
  },
  {
    path: '/',
    component: LayoutBase,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        component: DashboardView
      }
    ]
  },
  {
    path: '/access-denied',
    name: 'access-denied',
    component: AccessDeniedView
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
