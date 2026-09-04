import './bootstrap';
import { createApp } from 'vue';
import Modal from './components/ui/Modal.vue';
import EstudianteForm from './components/forms/EstudianteForm.vue';
import ExamenForm from './components/forms/ExamenForm.vue';

const App = {
  components: { Modal, EstudianteForm, ExamenForm },
  template: `
    <div style="padding: 20px;">
      <h1>Prueba de Componente Modal</h1>

      <button @click="modalEstudiante = true" style="margin-right: 10px; padding: 8px 16px;">
        Abrir Modal Estudiante
      </button>
      <button @click="modalExamen = true" style="padding: 8px 16px;">
        Abrir Modal Examen
      </button>

      <Modal :open="modalEstudiante" title="Registrar Estudiante" @close="modalEstudiante = false">
        <EstudianteForm />
        <template #footer>
          <button @click="modalEstudiante = false">Cancelar</button>
          <button @click="modalEstudiante = false">Guardar</button>
        </template>
      </Modal>

      <Modal :open="modalExamen" title="Registrar Examen" @close="modalExamen = false">
        <ExamenForm />
        <template #footer>
          <button @click="modalExamen = false">Cancelar</button>
          <button @click="modalExamen = false">Guardar</button>
        </template>
      </Modal>
    </div>
  `,
  data() {
    return {
      modalEstudiante: false,
      modalExamen: false
    }
  }
};

const app = createApp(App);
app.mount('#app');