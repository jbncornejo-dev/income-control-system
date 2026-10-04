<script setup>
import { ref } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    grupo: Object,
    inscritos: Object,
    disponibles: Array,
    busqueda: String,
});

const toast = useToastStore();

const termino = ref(props.busqueda ?? '');
const inscribiendo = ref(false);
const dandoBaja = ref(false);
const errorInscripcion = ref('');
const modalBaja = ref(false);
const inscripcionABaja = ref(null);

function buscar() {
    router.get(`/grupos/${props.grupo.id_grupo}`, { busqueda: termino.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function limpiarBusqueda() {
    termino.value = '';
    router.get(`/grupos/${props.grupo.id_grupo}`, {}, { preserveState: true, preserveScroll: true });
}

function inscribir(estudiante) {
    errorInscripcion.value = '';
    inscribiendo.value = true;

    router.post(`/grupos/${props.grupo.id_grupo}/inscripciones`, { id_estudiante: estudiante.id_estudiante }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Estudiante inscrito correctamente.'),
        onError: (e) => {
            errorInscripcion.value = e.id_estudiante ?? 'No se pudo inscribir al estudiante.';
            toast.error(errorInscripcion.value);
        },
        onFinish: () => { inscribiendo.value = false; },
    });
}

function confirmarBaja(inscripcion) {
    inscripcionABaja.value = inscripcion;
    modalBaja.value = true;
}

function darDeBaja() {
    dandoBaja.value = true;

    router.delete(`/grupos/${props.grupo.id_grupo}/inscripciones/${inscripcionABaja.value.id_inscripcion}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Inscripcion dada de baja correctamente.');
            modalBaja.value = false;
        },
        onError: () => {
            toast.error('No se pudo dar de baja la inscripcion.');
            modalBaja.value = false;
        },
        onFinish: () => { dandoBaja.value = false; },
    });
}

function irAPagina(url) {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Detalle del grupo" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <Link href="/grupos" class="volver">Volver a grupos</Link>

            <div class="grupo-card">
                <span class="eyebrow">GRUPO</span>
                <h1 class="grupo-titulo">{{ grupo.asignatura?.nombre_asignatura }} · {{ grupo.nombre_grupo }}</h1>
                <div class="grupo-meta">
                    <span><strong>Docente:</strong> {{ grupo.usuario?.name ?? 'Sin docente' }}</span>
                    <span><strong>Gestion:</strong> {{ grupo.gestion }}</span>
                    <span><strong>Inscritos:</strong> {{ inscritos.total }}</span>
                </div>
            </div>

            <div class="buscador-card">
                <h2 class="seccion-titulo">Inscribir estudiante</h2>
                <div class="buscador">
                    <input
                        v-model="termino"
                        type="text"
                        class="control"
                        placeholder="Buscar por codigo universitario, nombre o apellido"
                        @keyup.enter="buscar"
                    />
                    <button class="btn-primary" @click="buscar">Buscar</button>
                    <button v-if="busqueda" class="btn-secundario" @click="limpiarBusqueda">Limpiar</button>
                </div>

                <div v-if="busqueda" class="resultados">
                    <div v-for="estudiante in disponibles" :key="estudiante.id_estudiante" class="resultado-item">
                        <div class="resultado-datos">
                            <span class="resultado-codigo">{{ estudiante.codigo_universitario }}</span>
                            <span class="resultado-nombre">{{ estudiante.nombres }} {{ estudiante.apellidos }}</span>
                        </div>
                        <button class="btn-inscribir" :disabled="inscribiendo" @click="inscribir(estudiante)">Inscribir</button>
                    </div>
                    <p v-if="disponibles.length === 0" class="sin-resultados">No se encontraron estudiantes disponibles con ese dato.</p>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inscripcion in inscritos.data" :key="inscripcion.id_inscripcion">
                            <td data-label="Codigo" class="col-mono">{{ inscripcion.estudiante?.codigo_universitario }}</td>
                            <td data-label="Nombres">{{ inscripcion.estudiante?.nombres }}</td>
                            <td data-label="Apellidos">{{ inscripcion.estudiante?.apellidos }}</td>
                            <td data-label="Acciones">
                                <button class="btn-baja" @click="confirmarBaja(inscripcion)">Dar de baja</button>
                            </td>
                        </tr>
                        <tr v-if="!inscritos.data || inscritos.data.length === 0">
                            <td colspan="4" class="empty-state">Aun no hay estudiantes inscritos en este grupo.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="paginacion" v-if="inscritos.last_page > 1">
                <button class="btn-page" :disabled="!inscritos.prev_page_url" @click="irAPagina(inscritos.prev_page_url)">Anterior</button>
                <span class="page-info">Pagina {{ inscritos.current_page }} de {{ inscritos.last_page }}</span>
                <button class="btn-page" :disabled="!inscritos.next_page_url" @click="irAPagina(inscritos.next_page_url)">Siguiente</button>
            </div>
        </div>

        <Modal :open="modalBaja" title="Dar de baja inscripcion" @close="modalBaja = false">
            <p class="confirm-text">
                Se dara de baja a
                <strong>{{ inscripcionABaja?.estudiante?.nombres }} {{ inscripcionABaja?.estudiante?.apellidos }}</strong>
                del grupo {{ grupo.nombre_grupo }}. El estudiante no se elimina del sistema.
            </p>
            <template #footer>
                <button class="btn-cancelar" @click="modalBaja = false">Cancelar</button>
                <button class="btn-danger" :disabled="dandoBaja" @click="darDeBaja">
                    <LoadingSpinner v-if="dandoBaja" size="small" />
                    <span v-else>Dar de baja</span>
                </button>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem 3rem; background-color: var(--bg-main); min-height: 100vh; font-family: var(--font-family); }
.volver { color: var(--color-primary); text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-block; margin-bottom: 1rem; }
.grupo-card { background: #fff; border: 1px solid #e5e7eb; border-top: 4px solid var(--color-primary); border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1.5rem; }
.eyebrow { font-size: 0.75rem; color: var(--text-muted); letter-spacing: 0.08em; }
.grupo-titulo { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin: 0.25rem 0 0.75rem; font-family: var(--font-display); }
.grupo-meta { display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.95rem; color: var(--text-muted); }
.grupo-meta strong { color: var(--text-dark); }
.buscador-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1.5rem; }
.seccion-titulo { font-size: 1rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.75rem; }
.buscador { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.control { flex: 1; min-width: 240px; padding: 0.5rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.25rem; font-size: 0.95rem; font-family: inherit; color: var(--text-dark); }
.control:focus { outline: none; border-color: var(--color-primary); }
.resultados { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.resultado-item { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid #f0f0f0; border-left: 3px solid var(--color-primary); border-radius: 0 0.25rem 0.25rem 0; background: #f9fafb; }
.resultado-datos { display: flex; flex-direction: column; }
.resultado-codigo { font-family: monospace; font-size: 0.85rem; color: var(--text-muted); }
.resultado-nombre { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
.sin-resultados { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
.btn-primary { background: var(--color-primary); color: #fff; border: none; padding: 0.5rem 1.25rem; border-radius: 0.25rem; font-size: 0.95rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-secundario { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 0.5rem 1rem; border-radius: 0.25rem; font-size: 0.95rem; cursor: pointer; font-family: inherit; }
.btn-inscribir { background: var(--color-primary); color: #fff; border: none; padding: 0.4rem 1rem; border-radius: 0.25rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-inscribir:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-baja { background: #fdecea; color: var(--color-active); border: 1px solid var(--color-active); padding: 0.35rem 0.85rem; border-radius: 0.25rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-cancelar { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.95rem; padding: 0.6rem 1rem; font-family: inherit; }
.btn-danger { background: var(--color-active); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 0.25rem; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
.table-container { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
.data-table th { background-color: var(--color-primary); color: #fff; text-align: left; padding: 0.75rem 1rem; font-weight: 600; }
.data-table td { padding: 0.9rem 1rem; border-bottom: 1px solid #c7d0da; color: var(--text-dark); }
.data-table tr:last-child td { border-bottom: none; }
.col-mono { font-family: monospace; color: var(--text-muted); }
.empty-state { text-align: center; color: var(--text-muted); padding: 2rem !important; }
.paginacion { display: flex; justify-content: center; align-items: center; gap: 1rem; padding: 1rem; }
.btn-page { background: var(--color-primary); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; color: var(--text-muted); }
.confirm-text { font-size: 0.95rem; color: var(--text-dark); line-height: 1.6; }
@media (max-width: 820px) {
    .panel-container { padding: 1.25rem; }
    .buscador { flex-direction: column; }
    .data-table thead { display: none; }
    .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
    .data-table tr { border-bottom: 1px solid #c7d0da; padding: 0.5rem 0; }
    .data-table td { border: none; padding: 0.35rem 1rem; }
    .data-table td::before { content: attr(data-label); display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 2px; }
}
</style>
