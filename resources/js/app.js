import './bootstrap'
import { createApp } from 'vue'
import App from '../views/App.vue' // O tu componente raíz con <router-view>
import router from './router'

const app = createApp({})
app.use(router)
app.mount('#app')
