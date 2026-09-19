<template>
  <AuthenticatedLayout>
    <div class="student-list-container">
      <h1 class="page-title">Estudiantes</h1>

      <div class="toolbar">
        <SearchInput v-model="searchQuery" placeholder="Buscar por código, documento, nombres o apellidos..." />
        <div class="toolbar-actions">
          <button class="btn-import" @click="abrirModalCsv">Importar CSV</button>
          <button class="btn-primary" @click="abrirModalCrear">Añadir Estudiante</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Código Univ.</th>
              <th>Documento Identidad</th>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in students" :key="student.id">
              <!-- Campos canónicos del backend (tabla `estudiante`) -->
              <td>{{ student.codigo_universitario }}</td>
              <td>{{ student.documento_identidad }}</td>
              <td>{{ student.nombres }}</td>
              <td>{{ student.apellidos }}</td>
              <td>
                <button class="btn-action" @click="abrirModalEditar(student)">Editar</button>
                <button class="btn-action btn-danger" @click="confirmarEliminar(student)">Eliminar</button>
              </td>
            </tr>
            <tr v-if="students.length === 0">
              <td colspan="5" class="empty-cell">
                {{ searchQuery
                  ? 'No se encontraron resultados para la búsqueda.'
                  : 'No hay estudiantes registrados.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :currentPage="currentPage" :totalPages="totalPages" @change-page="changePage" />

      <!-- Modal: registrar / editar estudiante -->
      <Modal
        :open="mostrarModal"
        :title="estudianteSeleccionado ? 'Editar Estudiante' : 'Registrar Estudiante'"
        @close="mostrarModal = false"
      >
        <EstudianteForm
          ref="refFormulario"
          :estudiante="estudianteSeleccionado"
          @success="mostrarModal = false"
        />

        <template #footer>
          <button class="btn-cancelar" @click="mostrarModal = false">Cancelar</button>
          <button class="btn-primary" @click="refFormulario?.emitirGuardado()">
            {{ estudianteSeleccionado ? 'Actualizar Registro' : 'Guardar Registro' }}
          </button>
        </template>
      </Modal>

      <!-- Modal: importación CSV -->
      <Modal :open="csvModal" :title="tituloModalCsv" :wide="true" @close="solicitarCerrarCsv">
        <!-- Paso 1: seleccionar archivo -->
        <div v-if="csvStep === 'upload'" class="csv-upload">
          <p class="help-text">
            El archivo debe llevar la cabecera
            <code>codigo_universitario,documento_identidad,nombres,apellidos</code>.
            Todos los campos son obligatorios (código y documento máx. 20 caracteres; nombres y apellidos máx. 100).
          </p>
          <div class="csv-file-row">
            <input
              type="file"
              accept=".csv"
              @change="handleFileChange"
              class="file-input"
            />
            <button
              class="btn-upload"
              :disabled="csvSubiendo || !csvFile"
              @click="submitCsv"
            >
              {{ csvSubiendo ? 'Importando...' : 'Importar archivo' }}
            </button>
          </div>
          <p v-if="csvError" class="error-msg" role="alert">{{ csvError }}</p>
        </div>

        <!-- Paso 2: reporte y corrección -->
        <div v-else class="csv-results">
          <div class="summary-stats">
            <div class="stats-fila stats-totales">
              <div class="stat-card stat-total">
                <div class="stat-info">
                  <strong class="stat-number">{{ filasTotales }}</strong>
                  <span class="stat-label">Filas procesadas</span>
                </div>
              </div>
              <div class="stat-card stat-ok">
                <div class="stat-info">
                  <strong class="stat-number">{{ totalCreados }}</strong>
                  <span class="stat-label">Registrados</span>
                </div>
              </div>
              <div class="stat-card stat-sin-registrar">
                <div class="stat-info">
                  <strong class="stat-number">{{ sinRegistrar }}</strong>
                  <span class="stat-label">Sin registrar</span>
                </div>
              </div>
            </div>
            <div class="stats-fila stats-detalle" :class="{ 'stats-detalle--cuatro': conteos.inesperado > 0 }">
              <div class="stat-card stat-formato">
                <div class="stat-info">
                  <strong class="stat-number">{{ conteos.formato }}</strong>
                  <span class="stat-label">Errores de formato</span>
                </div>
              </div>
              <div class="stat-card stat-duplicado">
                <div class="stat-info">
                  <strong class="stat-number">{{ conteos.duplicado_archivo }}</strong>
                  <span class="stat-label">Duplicados en archivo</span>
                </div>
              </div>
              <div class="stat-card stat-existente">
                <div class="stat-info">
                  <strong class="stat-number">{{ conteos.ya_registrado }}</strong>
                  <span class="stat-label">Ya registrados</span>
                </div>
              </div>
              <div v-if="conteos.inesperado > 0" class="stat-card stat-inesperado">
                <div class="stat-info">
                  <strong class="stat-number">{{ conteos.inesperado }}</strong>
                  <span class="stat-label">Errores inesperados</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="filasRechazadas.length === 0" class="success-box">
            <p><strong>¡Importación completada con éxito!</strong></p>
            <p class="help-text">Se registraron {{ totalCreados }} estudiantes correctamente.</p>
          </div>

          <div v-else>
            <div class="rejected-list">
              <div v-for="(fila, index) in filasRechazadas" :key="index" class="rejected-row">
                <div class="rejected-head">
                  <span class="rejected-fila">Fila original: {{ fila.fila }}</span>
                  <button class="btn-remove" :disabled="csvSubiendo" @click="descartarFila(index)" title="Descartar fila" aria-label="Descartar fila">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <path d="M1 1l12 12M13 1L1 13" />
                    </svg>
                  </button>
                </div>
                <ul class="motivos">
                  <li
                    v-for="(motivo, i) in fila.motivos"
                    :key="i"
                    :class="`motivo--${motivo.tipo}`"
                  >
                    {{ motivo.texto }}
                  </li>
                </ul>
                <div class="rejected-grid">
                  <div class="form-group">
                    <label class="form-label">Código Univ. <span class="required">*</span></label>
                    <input v-model="fila.codigo_universitario" class="form-input" maxlength="20" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Documento <span class="required">*</span></label>
                    <input v-model="fila.documento_identidad" class="form-input" maxlength="20" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Nombres <span class="required">*</span></label>
                    <input v-model="fila.nombres" class="form-input" maxlength="100" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Apellidos <span class="required">*</span></label>
                    <input v-model="fila.apellidos" class="form-input" maxlength="100" />
                  </div>
                </div>
              </div>
            </div>

            <p v-if="csvError" class="error-msg" role="alert">{{ csvError }}</p>

            <div class="results-actions">
              <button class="btn-primary" :disabled="csvSubiendo" @click="corregirYReintentar">
                {{ csvSubiendo ? 'Reintentando...' : 'Reintentar corregidos' }}
              </button>
              <button class="btn-cancelar" @click="volverSubir">Subir otro archivo</button>
            </div>
          </div>

          <div v-if="filasRechazadas.length === 0" class="results-actions">
            <button class="btn-primary" @click="solicitarCerrarCsv">Cerrar</button>
          </div>
        </div>
      </Modal>

      <!-- Modal: confirmar cierre con rechazados pendientes -->
      <Modal
        :open="confirmarCierre"
        title="¿Cancelar la importación?"
        @close="confirmarCierre = false"
      >
        <p class="confirm-text">
          Aún hay <strong>{{ filasRechazadas.length }}</strong> fila(s) rechazada(s) sin corregir.
          Si cierras ahora, las correcciones se perderán. ¿Deseas cancelar?
        </p>

        <template #footer>
          <button class="btn-cancelar" @click="confirmarCierre = false">Seguir corrigiendo</button>
          <button class="btn-primary btn-peligro" @click="cerrarCsvDefinitivamente">Sí, cancelar</button>
        </template>
      </Modal>

      <!-- Modal: confirmar eliminación de estudiante -->
      <Modal
        :open="modalEliminar"
        title="Eliminar Estudiante"
        @close="modalEliminar = false"
      >
        <div class="confirm-box">
          <div class="confirm-icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </div>
          <div class="confirm-content">
            <p class="confirm-text">
              ¿Está seguro de que desea eliminar al estudiante
              <strong v-if="estudianteAEliminar">
                {{ estudianteAEliminar.nombres }} {{ estudianteAEliminar.apellidos }}
              </strong>?
            </p>
            <p class="confirm-note">Esta acción no se puede deshacer.</p>
          </div>
        </div>

        <template #footer>
          <button class="btn-cancelar" :disabled="eliminando" @click="modalEliminar = false">Cancelar</button>
          <button class="btn-primary btn-peligro" :disabled="eliminando" @click="eliminarEstudiante">
            {{ eliminando ? 'Eliminando...' : 'Sí, eliminar' }}
          </button>
        </template>
      </Modal>
    </div>
  </AuthenticatedLayout>
  <Modal :show="showModal" @close="showModal = false">
    <div class="modal-content">
      <h3>Reporte de Carga Masiva</h3>
      <div class="summary-stats">
        <p>Registros creados: <strong>{{ uploadResults.creados }}</strong></p>
        <p>Registros rechazados: <strong>{{ uploadResults.rechazados }}</strong></p>
      </div>

      <div v-if="uploadResults.detalles_rechazos && uploadResults.detalles_rechazos.length > 0" class="error-container">
        <h4>Motivos de rechazo:</h4>
        <ul class="error-list">
          <li v-for="(error, index) in uploadResults.detalles_rechazos" :key="index">
            <strong>Fila {{ error.fila }}:</strong> {{ error.motivo }}
          </li>
        </ul>
      </div>

      <button @click="showModal = false" class="btn-close">Entendido</button>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchInput from '@/components/SearchInput.vue';
import Pagination from '@/components/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import EstudianteForm from '@/components/forms/EstudianteForm.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
  estudiantes: {
    type: Object,
    default: () => ({ data: [] }),
  },
  filtros: {
    type: Object,
    default: () => ({}),
  },
})

const pageProps = usePage();

const searchQuery = ref(props.filtros?.search ?? '');
const mostrarModal = ref(false);
const estudianteSeleccionado = ref(null);
const refFormulario = ref(null);

// --- Eliminación ---
const toast = useToastStore();
const modalEliminar = ref(false);
const estudianteAEliminar = ref(null);
const eliminando = ref(false);

const confirmarEliminar = (estudiante) => {
  estudianteAEliminar.value = estudiante;
  modalEliminar.value = true;
};

const eliminarEstudiante = () => {
  if (!estudianteAEliminar.value) return;

  eliminando.value = true;

  router.delete(`/estudiantes/${estudianteAEliminar.value.id}`, {
    preserveScroll: true,
    onSuccess: (page) => {
      // El backend responde con flash de éxito o de error (bloqueo por registros asociados).
      const flash = page?.props?.flash ?? {};
      if (flash.error) toast.error(flash.error);
      else if (flash.success) toast.success(flash.success);
      modalEliminar.value = false;
    },
    onError: () => {
      toast.error('No se pudo eliminar el estudiante.');
      modalEliminar.value = false;
    },
    onFinish: () => {
      eliminando.value = false;
      estudianteAEliminar.value = null;
    },
  });
};

// --- Tabla ---
const students = computed(() => {
  const raw = props.estudiantes?.data ?? props.estudiantes;
  return Array.isArray(raw) ? raw : [];
});

// --- Búsqueda (server-side) ---
// El backend filtra sobre TODAS las páginas antes de paginar
// (`StudentController::index`); aquí solo disparamos la consulta con debounce
// para no saturar el servidor con cada tecla. El filtrado client-side se eliminó
// porque solo veía la página actual (15 registros).
let debounceTimer = null;

const buscarEstudiantes = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const termino = searchQuery.value.trim();

    router.get('/estudiantes', { search: termino || null }, {
      // preserveState mantiene el texto del buscador mientras llega la respuesta.
      preserveState: true,
      // replace evita llenar el historial del navegador con cada búsqueda.
      replace: true,
      // Solo se recargan las props que cambian con la búsqueda.
      only: ['estudiantes', 'filtros'],
    });
  }, 300);
};

watch(searchQuery, buscarEstudiantes);
onBeforeUnmount(() => clearTimeout(debounceTimer));

const currentPage = computed(() => props.estudiantes?.current_page ?? 1);
const totalPages = computed(() => props.estudiantes?.last_page ?? 1);

const changePage = (newPage) => {
  if (newPage < 1 || newPage > totalPages.value || newPage === currentPage.value) return;
  const url = newPage > currentPage.value
    ? props.estudiantes.next_page_url
    : props.estudiantes.prev_page_url;
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

// --- Crear / Editar ---
const abrirModalCrear = () => {
  estudianteSeleccionado.value = null; // Limpiamos datos previos
  mostrarModal.value = true;
};

const abrirModalEditar = (estudiante) => {
  estudianteSeleccionado.value = estudiante; // Cargamos los datos del estudiante
  mostrarModal.value = true;
};

// --- Importación CSV ---
const csvModal = ref(false);
const csvStep = ref('upload'); // 'upload' | 'results'
const csvFile = ref(null);
const csvSubiendo = ref(false);
const csvError = ref('');
const totalCreados = ref(0);
const filasTotales = ref(0);
const filasRechazadas = ref([]);

// Clasifica un motivo de rechazo del backend en una categoría.
function categorizarMotivo(motivo) {
  const texto = String(motivo ?? '').toLowerCase();
  if (texto.includes('duplicado dentro del archivo')) return 'duplicado_archivo';
  if (texto.includes('ya está registrado')) return 'ya_registrado';
  if (texto.includes('error inesperado')) return 'inesperado';
  return 'formato'; // campos obligatorios / exceden la longitud máxima
}

// Conteo de filas por categoría (una fila puede contar en varias).
const conteos = computed(() => {
  const conteo = { formato: 0, duplicado_archivo: 0, ya_registrado: 0, inesperado: 0 };
  filasRechazadas.value.forEach((fila) => {
    const presentes = new Set(fila.motivos.map((motivo) => motivo.tipo));
    presentes.forEach((tipo) => {
      if (tipo in conteo) conteo[tipo]++;
    });
  });
  return conteo;
});

// Total de filas del archivo que aún no se registraron (incluye pendientes y descartadas).
const sinRegistrar = computed(() => Math.max(0, filasTotales.value - totalCreados.value));

// Título del modal según el paso: consistente al mostrar resultados.
const tituloModalCsv = computed(() =>
  csvStep.value === 'results' ? 'Resultado de la importación' : 'Importar Estudiantes (CSV)'
);

// Confirmación antes de cerrar si quedan filas rechazadas pendientes.
const confirmarCierre = ref(false);

const solicitarCerrarCsv = () => {
  if (csvStep.value === 'results' && filasRechazadas.value.length > 0) {
    confirmarCierre.value = true;
  } else {
    csvModal.value = false;
  }
};

const cerrarCsvDefinitivamente = () => {
  confirmarCierre.value = false;
  csvModal.value = false;
};

const abrirModalCsv = () => {
  csvModal.value = true;
  csvStep.value = 'upload';
  csvFile.value = null;
  csvError.value = '';
  csvSubiendo.value = false;
  totalCreados.value = 0;
  filasTotales.value = 0;
  filasRechazadas.value = [];
};

const volverSubir = () => {
  csvStep.value = 'upload';
  csvError.value = '';
};

const handleFileChange = (event) => {
  csvFile.value = event.target.files[0] ?? null;
  csvError.value = '';
};

const submitCsv = () => {
  if (csvFile.value) {
    ejecutarImportacion(csvFile.value);
  }
};

const ejecutarImportacion = async (archivo, esReintento = false) => {
  if (!archivo) return;

  csvSubiendo.value = true;
  csvError.value = '';

  const formData = new FormData();
  // El backend valida el campo `file` (mimes:csv,txt, max:10240).
  formData.append('file', archivo);

  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const response = await fetch('/estudiantes/importar', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      csvError.value = data?.errors?.file?.[0] ?? data?.mensaje ?? 'Error al importar el archivo.';
      return;
    }

    if (esReintento) {
      totalCreados.value += data?.exitosos ?? 0;
    } else {
      // Archivo nuevo: las estadísticas se calculan sobre las filas de este archivo.
      filasTotales.value = data?.total_filas ?? 0;
      totalCreados.value = data?.exitosos ?? 0;
    }
    filasRechazadas.value = (data?.rechazados ?? []).map((rechazo) => ({
      fila: rechazo.fila,
      codigo_universitario: rechazo.datos?.codigo_universitario ?? '',
      documento_identidad: rechazo.datos?.documento_identidad ?? '',
      nombres: rechazo.datos?.nombres ?? '',
      apellidos: rechazo.datos?.apellidos ?? '',
      motivos: (rechazo.motivos ?? []).map((motivo) => ({
        texto: motivo,
        tipo: categorizarMotivo(motivo),
      })),
    }));

    csvStep.value = 'results';
    // Refresca el listado para que los estudiantes importados aparezcan.
    router.reload({ only: ['estudiantes', 'filtros'] });
  } catch (error) {
    csvError.value = 'Error de conexión al importar el archivo.';
  } finally {
    csvSubiendo.value = false;
  }
};

const descartarFila = (index) => {
  filasRechazadas.value.splice(index, 1);
};

const corregirYReintentar = async () => {
  csvError.value = '';

  const invalidas = filasRechazadas.value.some(
    (f) =>
      !(f.codigo_universitario ?? '').trim() ||
      !(f.documento_identidad ?? '').trim() ||
      !(f.nombres ?? '').trim() ||
      !(f.apellidos ?? '').trim()
  );

  if (invalidas) {
    csvError.value = 'Completa los campos obligatorios de todas las filas antes de reintentar.';
    return;
  }

  const archivoCorregido = new File(
    [construirCsv(filasRechazadas.value)],
    'estudiantes-corregidos.csv',
    { type: 'text/csv' }
  );

  await ejecutarImportacion(archivoCorregido, true);
};

const escaparCsv = (valor) => `"${String(valor ?? '').replace(/"/g, '""')}"`;

const construirCsv = (filas) => {
  const encabezado = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'];
  const lineas = [encabezado.join(',')];

  filas.forEach((f) => {
    lineas.push([
      f.codigo_universitario,
      f.documento_identidad,
      f.nombres,
      f.apellidos,
    ].map(escaparCsv).join(','));
  });

  return lineas.join('\n');
};
</script>

<style scoped>
.page-title {
  color: var(--color-primary);
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  margin: 0 0 1.5rem;
}
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
/* El buscador ocupa el espacio disponible y queda alineado verticalmente con los botones */
.student-list-container :deep(.search-wrapper) {
  margin-bottom: 0;
  max-width: 420px;
  flex: 1;
}
.btn-primary { background-color: var(--color-primary); color: var(--color-white); border: none; padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); font-size: 14px; font-weight: 600; transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease; }
.btn-primary:hover:not(:disabled) { box-shadow: 0 3px 8px rgba(0, 0, 0, 0.18); transform: translateY(-1px); }
.btn-primary:hover:not(:disabled):not(.btn-peligro) { background-color: var(--color-primary-hover); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-peligro { background-color: #dc3545; }
.btn-peligro:hover:not(:disabled) { background-color: #b02a37; }
.confirm-text { color: var(--color-text-main); font-size: 14px; line-height: 1.6; margin: 0; }
.confirm-note { color: #9f3a38; font-size: 13px; margin: 0; }
.confirm-box {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 16px;
}
.confirm-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #fde8e8;
  color: #dc3545;
}
.confirm-content { display: flex; flex-direction: column; gap: 6px; }
.confirm-content strong { color: #b3261e; }
.btn-import { background: transparent; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 10px 20px; border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-main); font-size: 14px; font-weight: 600; transition: background-color 0.15s ease, color 0.15s ease; }
.btn-import:hover { background-color: var(--color-primary); color: var(--color-white); }
.table-responsive { background: var(--color-white); border-radius: var(--radius-md); box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-family: var(--font-main); }
.data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { background-color: #f8f9fa; color: var(--color-text-main); font-weight: bold; }
.empty-cell { text-align: center; color: #6c757d; padding: 24px 15px; }
.btn-action { background: transparent; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 4px 8px; border-radius: 4px; cursor: pointer; }
.flash-success { background: #e6f4ea; color: #1e7a34; border: 1px solid #b6e2c0; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
.flash-error { background: #fdecea; color: #b3261e; border: 1px solid #f5c2b9; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
.btn-cancelar {
  background: transparent;
  color: var(--color-primary);
  border: 1px solid var(--color-primary);
  padding: 10px 20px;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  font-family: var(--font-main);
  transition: background-color 0.15s ease, color 0.15s ease;
}
.btn-cancelar:hover {
  background-color: var(--color-primary);
  color: var(--color-white);
}

/* ---- Modal CSV ---- */
.csv-upload { display: flex; flex-direction: column; gap: 14px; }
.csv-file-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.file-input { border: 1px solid var(--color-white-soft); padding: 0.5rem; border-radius: 6px; background: var(--color-bg-input); width: 100%; max-width: 320px; }
.btn-upload { padding: 10px 20px; background-color: #198754; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; font-family: var(--font-main); }
.btn-upload:disabled { opacity: 0.6; cursor: not-allowed; }
.help-text { color: var(--color-text-secondary); font-size: 13px; line-height: 1.5; }
.help-text code { background: var(--color-bg-input); padding: 2px 5px; border-radius: 4px; font-size: 12px; }
.error-msg { color: #d32f2f; font-size: 13px; }
.summary-stats { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
.stats-fila { display: grid; gap: 10px; }
.stats-totales { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.stats-detalle { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.stats-detalle--cuatro { grid-template-columns: repeat(4, minmax(0, 1fr)); }
@media (max-width: 560px) {
  .stats-totales,
  .stats-detalle,
  .stats-detalle--cuatro { grid-template-columns: 1fr; }
}
.stat-card {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid var(--color-white-soft);
  border-left: 4px solid transparent;
}
.stat-info { display: flex; flex-direction: column; min-width: 0; }
.stat-number { font-size: 1.4rem; line-height: 1; color: var(--color-text-main); }
.stats-totales .stat-number { font-size: 1.6rem; }
.stat-label { font-size: 11px; color: var(--color-text-secondary); margin-top: 3px; }
.stat-ok { border-left-color: #198754; background: #f0faf4; }
.stat-ok .stat-number { color: #198754; }
.stat-total { border-left-color: #334155; background: #f8fafc; }
.stat-total .stat-number { color: #334155; }
.stat-sin-registrar { border-left-color: #dc2626; background: #fef2f2; }
.stat-sin-registrar .stat-number { color: #dc2626; }
.stat-formato { border-left-color: #d97706; background: #fffbeb; }
.stat-formato .stat-number { color: #d97706; }
.stat-duplicado { border-left-color: #ea580c; background: #fff7ed; }
.stat-duplicado .stat-number { color: #ea580c; }
.stat-existente { border-left-color: #dc3545; background: #fef2f2; }
.stat-existente .stat-number { color: #dc3545; }
.stat-inesperado { border-left-color: #6c757d; background: #f8f9fa; }
.stat-inesperado .stat-number { color: #6c757d; }
.success-box { background: #e6f4ea; color: #1e7a34; border: 1px solid #b6e2c0; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; }
.success-box .help-text { color: #1e7a34; }
.rejected-list { display: flex; flex-direction: column; gap: 14px; max-height: 420px; overflow-y: auto; margin-bottom: 16px; padding-right: 4px; }
@media (max-width: 480px) { .rejected-list { max-height: none; overflow: visible; } }
.rejected-row { border: 1px solid var(--color-white-soft); border-left: 4px solid #d32f2f; border-radius: 6px; padding: 12px 14px; background: #fff; }
.rejected-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.rejected-fila { font-size: 12px; font-weight: 700; color: var(--color-text-secondary); }
.btn-remove {
  background: transparent;
  border: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.btn-remove:hover { background: #fdecea; color: #dc3545; }
.btn-remove:disabled { opacity: 0.5; cursor: not-allowed; }
.motivos { margin: 0 0 10px; padding: 0; list-style: none; }
.motivos li { font-size: 12px; margin-bottom: 2px; padding-left: 14px; position: relative; }
.motivos li::before { content: '•'; position: absolute; left: 0; }
.motivo--formato { color: #b45309; }
.motivo--duplicado_archivo { color: #c2410c; }
.motivo--ya_registrado { color: #b3261e; }
.motivo--inesperado { color: #6c757d; }
.rejected-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 480px) { .rejected-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 768px) { .rejected-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.form-group { margin-bottom: 8px; }
.form-label { display: block; font-size: 12px; font-weight: 600; color: var(--color-text-main); margin-bottom: 4px; }
.required { color: #d32f2f; }
.form-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--color-white-soft);
  border-radius: 6px;
  font-size: 14px;
  background: var(--color-bg-input);
  color: var(--color-text-main);
  box-sizing: border-box;
  font-family: var(--font-main);
}
.form-input:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--shadow-input-focus); }
.results-actions { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 8px; }
.btn-danger {
  color: #dc3545;
  border-color: #dc3545;
  margin-left: 8px;
}
.btn-danger:hover {
  background-color: #dc3545;
  color: white;
}
</style>