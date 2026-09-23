<template>
  <AuthenticatedLayout>
    <div class="panel-container">
      <h1 class="panel-title">ESTUDIANTES</h1>

      <div class="action-bar">
        <SearchInput v-model="searchQuery" placeholder="Buscar por código, documento, correo, nombres o apellidos..." />
        <div class="action-controls">
          <Button variant="action" @click="abrirModalCsv">Importar CSV</Button>
          <Button variant="primary" @click="abrirModalCrear">Añadir Estudiante</Button>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ESTUDIANTE</th>
              <th>CÓDIGO UNIV.</th>
              <th>DOCUMENTO IDENTIDAD</th>
              <th>CORREO</th>
              <th class="actions-col">ACCIONES</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in students" :key="student.id">
              <td class="name-cell">
                <div class="avatar">{{ getInitial(student.nombres) }}</div>
                <span>{{ student.nombres }} {{ student.apellidos }}</span>
              </td>
              <td>{{ student.codigo_universitario }}</td>
              <td>{{ student.documento_identidad }}</td>
              <td>{{ student.email || '—' }}</td>
              <td class="actions-cell">
                <Button variant="action" @click="abrirQr(student)">QR</Button>
                <Button variant="action" @click="abrirModalEditar(student)">Editar</Button>
                <Button variant="delete" @click="confirmarEliminar(student)">Eliminar</Button>
              </td>
            </tr>
            <tr v-if="students.length === 0">
              <td colspan="5" class="empty-state">
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
          <Button variant="action" class="btn-modal" @click="mostrarModal = false">Cancelar</Button>
          <Button variant="primary" @click="refFormulario?.emitirGuardado()">
            {{ estudianteSeleccionado ? 'Actualizar Registro' : 'Guardar Registro' }}
          </Button>
        </template>
      </Modal>

      <!-- Modal: importación CSV -->
      <Modal :open="csvModal" :title="tituloModalCsv" :wide="true" @close="solicitarCerrarCsv">
        <!-- Paso 1: seleccionar archivo -->
        <div v-if="csvStep === 'upload'" class="csv-upload">
          <p class="help-text">
            El archivo debe llevar la cabecera
            <code>codigo_universitario,documento_identidad,nombres,apellidos</code>,
            con columnas opcionales
            <code>codigo_qr</code> y <code>email</code>.
            Los cuatro primeros campos son obligatorios y deben cumplir el formato:
            código de 9 dígitos iniciando con el año de ingreso (ej: 201809372),
            documento de 6 a 8 dígitos (ej: 12590804), nombres/apellidos solo con
            letras, espacios, apóstrofes y guiones; el correo institucional, si se
            envía, debe ser <code>codigo@est.umss.edu</code> (ej: 201809372@est.umss.edu).
            Si no se envía código QR, se genera automáticamente por estudiante.
          </p>
          <div class="csv-file-row">
            <input
              type="file"
              accept=".csv"
              @change="handleFileChange"
              class="file-input"
            />
            <Button
              variant="primary"
              :disabled="csvSubiendo || !csvFile"
              @click="submitCsv"
            >
              {{ csvSubiendo ? 'Importando...' : 'Importar archivo' }}
            </Button>
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
              <Button variant="primary" :disabled="csvSubiendo" @click="corregirYReintentar">
                {{ csvSubiendo ? 'Reintentando...' : 'Reintentar corregidos' }}
              </Button>
              <Button variant="action" @click="volverSubir">Subir otro archivo</Button>
            </div>
          </div>

          <div v-if="filasRechazadas.length === 0" class="results-actions">
            <Button variant="primary" @click="solicitarCerrarCsv">Cerrar</Button>
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
          <Button variant="action" @click="confirmarCierre = false">Seguir corrigiendo</Button>
          <Button variant="primary" class="btn-peligro" @click="cerrarCsvDefinitivamente">Sí, cancelar</Button>
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
          <Button variant="action" :disabled="eliminando" @click="modalEliminar = false">Cancelar</Button>
          <Button variant="primary" class="btn-peligro" :disabled="eliminando" @click="eliminarEstudiante">
            {{ eliminando ? 'Eliminando...' : 'Sí, eliminar' }}
          </Button>
        </template>
      </Modal>

      <!-- Modal: código QR del estudiante -->
      <Modal
        :open="qrModal"
        :title="estudianteQr ? `QR de ${estudianteQr.nombres} ${estudianteQr.apellidos}` : 'Código QR'"
        @close="qrModal = false"
      >
        <div v-if="estudianteQr" class="qr-content">
          <img :src="`/estudiantes/${estudianteQr.id}/qr`" alt="Código QR del estudiante" class="qr-image" />
          <p class="qr-help">
            El personal de control puede escanear este código al momento del ingreso al examen
            para identificar al estudiante.
          </p>
        </div>

        <template #footer>
          <Button variant="action" class="btn-modal" @click="qrModal = false">Cerrar</Button>
          <a
            v-if="estudianteQr"
            :href="`/estudiantes/${estudianteQr.id}/qr`"
            download
            class="btn-descargar"
          >
            Descargar QR
          </a>
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

      <Button variant="primary" @click="showModal = false">Entendido</Button>
    </div>
  </Modal>
</template>

<script setup>
import Button from '@/components/ui/Button.vue';
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

// --- QR del estudiante ---
const qrModal = ref(false);
const estudianteQr = ref(null);

const abrirQr = (estudiante) => {
  estudianteQr.value = estudiante;
  qrModal.value = true;
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

// Función auxiliar para obtener la inicial del nombre para el avatar
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?';
};
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
/* El buscador ocupa el espacio disponible y queda alineado verticalmente con los botones */

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
.flash-success { background: #e6f4ea; color: #1e7a34; border: 1px solid #b6e2c0; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
.flash-error { background: #fdecea; color: #b3261e; border: 1px solid #f5c2b9; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }

/* ---- Modal CSV ---- */
.csv-upload { display: flex; flex-direction: column; gap: 14px; }
.csv-file-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.file-input { border: 1px solid var(--color-white-soft); padding: 0.5rem; border-radius: 6px; background: var(--color-bg-input); width: 100%; max-width: 320px; }
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
/* Estandarización de botones secundarios en toolbar y modales */
.btn-toolbar,
.btn-modal {
  padding: 10px 20px !important;
  font-size: 14px !important;
}

/* Espaciado entre botones de acción en la tabla */
.data-table td :deep(.btn-base) + :deep(.btn-base) {
  margin-left: 8px;
}

/* Botón destructivo para modales de confirmación */
.btn-peligro {
  background-color: #dc3545 !important;
  color: #ffffff !important;
}
.btn-peligro:hover:not(:disabled) {
  background-color: #b02a37 !important;
}

/* Contenedor principal */
.panel-container {
    padding: 2rem;
    background-color: #f3f4f6;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}

.panel-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
}

/* Barra de Acciones */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.panel-container :deep(.search-wrapper) {
    flex: 1;
    max-width: 600px;
    margin-bottom: 0;
}

/* Tabla de Datos */
.table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background-color: #f9fafb;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    text-transform: uppercase;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
}

/* Celdas específicas */
.name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.avatar {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    background-color: #1e1b4b;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
}

.actions-cell {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
    white-space: nowrap;
    min-width: 180px;
}

.actions-col {
    text-align: center !important;
}

.empty-state {
    text-align: center;
    color: #6b7280;
    padding: 2rem !important;
}

/* ---- Modal QR ---- */
.qr-content { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.qr-image {
  width: 220px;
  height: 220px;
  object-fit: contain;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  background: #ffffff;
}
.qr-help { color: #6b7280; font-size: 13px; line-height: 1.5; text-align: center; margin: 0; }
.btn-descargar {
  display: inline-flex;
  align-items: center;
  padding: 10px 20px;
  background: #1d3653;
  color: #ffffff;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
}
.btn-descargar:hover { background: #152a45; }
</style>