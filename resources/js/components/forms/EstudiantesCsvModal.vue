<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';
import TextInput from '@/components/ui/TextInput.vue';

const props = defineProps({
  open: Boolean,
});
const emit = defineEmits(['close']);

const csvStep = ref('upload');
const csvFile = ref(null);
const csvSubiendo = ref(false);
const csvError = ref('');
const totalCreados = ref(0);
const filasTotales = ref(0);
const filasRechazadas = ref([]);
const confirmarCierre = ref(false);

watch(() => props.open, (newVal) => {
  if (newVal) {
    csvStep.value = 'upload';
    csvFile.value = null;
    csvError.value = '';
    csvSubiendo.value = false;
    totalCreados.value = 0;
    filasTotales.value = 0;
    filasRechazadas.value = [];
    confirmarCierre.value = false;
  }
});

function categorizarMotivo(motivo) {
  const texto = String(motivo ?? '').toLowerCase();
  if (texto.includes('duplicado dentro del archivo')) return 'duplicado_archivo';
  if (texto.includes('ya está registrado')) return 'ya_registrado';
  if (texto.includes('error inesperado')) return 'inesperado';
  return 'formato';
}

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

const sinRegistrar = computed(() => Math.max(0, filasTotales.value - totalCreados.value));

const tituloModalCsv = computed(() =>
  csvStep.value === 'results' ? 'Resultado de la importación' : 'Importar Estudiantes (CSV)'
);

const solicitarCerrarCsv = () => {
  if (csvStep.value === 'results' && filasRechazadas.value.length > 0) {
    confirmarCierre.value = true;
  } else {
    emit('close');
  }
};

const cerrarCsvDefinitivamente = () => {
  confirmarCierre.value = false;
  emit('close');
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

<template>
  <Modal :open="open" :title="tituloModalCsv" :wide="true" @close="solicitarCerrarCsv">
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
                <TextInput v-model="fila.codigo_universitario" label="Código Univ. *" maxlength="20" />
              </div>
              <div class="form-group">
                <TextInput v-model="fila.documento_identidad" label="Documento *" maxlength="20" />
              </div>
              <div class="form-group">
                <TextInput v-model="fila.nombres" label="Nombres *" maxlength="100" />
              </div>
              <div class="form-group">
                <TextInput v-model="fila.apellidos" label="Apellidos *" maxlength="100" />
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
</template>

<style scoped>
.btn-peligro { background-color: var(--color-danger); }
.btn-peligro:hover:not(:disabled) { filter: brightness(0.9); }
.confirm-text { color: var(--text-dark); font-size: 14px; line-height: 1.6; margin: 0; }

/* ---- Modal CSV ---- */
.csv-upload { display: flex; flex-direction: column; gap: var(--spacing-md); }
.csv-file-row { display: flex; align-items: center; gap: var(--spacing-md); flex-wrap: wrap; }
.file-input { border: 1px solid var(--border-light); padding: var(--spacing-sm); border-radius: var(--radius-md); background: var(--color-bg-input); width: 100%; max-width: 320px; font-family: var(--font-family); font-size: 14px; }
.help-text { color: var(--text-muted); font-size: 13px; line-height: 1.5; }
.help-text code { background: var(--color-bg-input); padding: 2px 5px; border-radius: 4px; font-size: 12px; }
.summary-stats { display: flex; flex-direction: column; gap: var(--spacing-sm); margin-bottom: var(--spacing-md); }
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
.rejected-grid { display: grid; grid-template-columns: 1fr; gap: var(--spacing-md); }
@media (min-width: 480px) { .rejected-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 768px) { .rejected-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.results-actions { display: flex; align-items: center; gap: var(--spacing-sm); flex-wrap: wrap; margin-top: var(--spacing-sm); }
</style>
