<script setup>
import { ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/components/ui/Modal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToastStore } from '@/stores/useToastStore';

const props = defineProps({
    grupos: Object,
    asignaturas: Array,
    docentes: Array,
    gestiones: Array,
    filters: Object,
});

const toast = useToastStore();

const filtroAsignatura = ref(props.filters?.id_asignatura ?? '');
const filtroGestion = ref(props.filters?.gestion ?? '');

const modalCrear = ref(false);
const modalEditar = ref(false);
const modalEliminar = ref(false);
const guardando = ref(false);
const eliminando = ref(false);

const grupoEditando = ref(null);
const grupoAEliminar = ref(null);

const form = ref({ id_asignatura: '', id_usuario: '', gestion: '', nombre_grupo: '' });
const errores = ref({ id_asignatura: '', id_usuario: '', gestion: '', nombre_grupo: '' });
const docenteNuevo = ref('');
const errorDocente = ref('');

const nombreDocente = (usuario) => (usuario ? `${usuario.name}` : 'Sin docente');

function aplicarFiltros() {
    router.get('/grupos', {
        id_asignatura: filtroAsignatura.value || undefined,
        gestion: filtroGestion.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function limpiarFiltros() {
    filtroAsignatura.value = '';
    filtroGestion.value = '';
    router.get('/grupos', {}, { preserveState: true, preserveScroll: true });
}

function abrirCrear() {
    form.value = { id_asignatura: '', id_usuario: '', gestion: '', nombre_grupo: '' };
    errores.value = { id_asignatura: '', id_usuario: '', gestion: '', nombre_grupo: '' };
    modalCrear.value = true;
}

function validar() {
    let valido = true;
    errores.value = { id_asignatura: '', id_usuario: '', gestion: '', nombre_grupo: '' };

    if (!form.value.id_asignatura) {
        errores.value.id_asignatura = 'Debe seleccionar una asignatura';
        valido = false;
    }
    if (!form.value.id_usuario) {
        errores.value.id_usuario = 'Debe seleccionar un docente';
        valido = false;
    }
    if (!form.value.gestion.trim()) {
        errores.value.gestion = 'Debe indicar la gestion';
        valido = false;
    } else if (!/^\d{4}$/.test(form.value.gestion.trim())) {
        errores.value.gestion = 'La gestion debe ser un ano de cuatro digitos, por ejemplo 2026';
        valido = false;
    }
    if (!form.value.nombre_grupo.trim()) {
        errores.value.nombre_grupo = 'Debe indicar el nombre del grupo';
        valido = false;
    }
    return valido;
}

function guardar() {
    if (!validar()) return;
    guardando.value = true;

    router.post('/grupos', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Grupo registrado correctamente.');
            modalCrear.value = false;
        },
        onError: (e) => {
            errores.value.id_asignatura = e.id_asignatura ?? '';
            errores.value.id_usuario = e.id_usuario ?? '';
            errores.value.gestion = e.gestion ?? '';
            errores.value.nombre_grupo = e.nombre_grupo ?? '';
        },
        onFinish: () => { guardando.value = false; },
    });
}

function abrirEditar(grupo) {
    grupoEditando.value = grupo;
    docenteNuevo.value = grupo.usuario?.id ?? '';
    errorDocente.value = '';
    modalEditar.value = true;
}

function reasignarDocente() {
    if (!docenteNuevo.value) {
        errorDocente.value = 'Debe seleccionar un docente';
        return;
    }
    guardando.value = true;

    router.patch(`/grupos/${grupoEditando.value.id_grupo}`, { id_usuario: docenteNuevo.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Docente reasignado correctamente.');
            modalEditar.value = false;
        },
        onError: (e) => { errorDocente.value = e.id_usuario ?? 'No se pudo reasignar el docente.'; },
        onFinish: () => { guardando.value = false; },
    });
}

function confirmarEliminar(grupo) {
    grupoAEliminar.value = grupo;
    modalEliminar.value = true;
}

function eliminar() {
    eliminando.value = true;

    router.delete(`/grupos/${grupoAEliminar.value.id_grupo}`, {
        preserveScroll: true,
        onSuccess: (pagina) => {
            const error = pagina.props?.flash?.error;
            if (error) toast.error(error);
            else toast.success('Grupo eliminado correctamente.');
            modalEliminar.value = false;
        },
        onError: () => {
            toast.error('No se puede eliminar el grupo porque tiene estudiantes inscritos');
            modalEliminar.value = false;
        },
        onFinish: () => { eliminando.value = false; },
    });
}

function irAPagina(url) {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Grupos" />

    <AuthenticatedLayout>
        <div class="panel-container">
            <div class="header-section">
                <div>
                    <h1 class="panel-title">GRUPOS</h1>
                    <p class="subtitle">Paralelos por asignatura y gestion con su docente responsable</p>
                </div>
                <button class="btn-primary" @click="abrirCrear">Nuevo grupo</button>
            </div>

            <div class="filtros">
                <div class="filtro-campo">
                    <label class="filtro-label">Asignatura</label>
                    <select v-model="filtroAsignatura" class="control" @change="aplicarFiltros">
                        <option value="">Todas</option>
                        <option v-for="a in asignaturas" :key="a.id_asignatura" :value="a.id_asignatura">{{ a.nombre_asignatura }}</option>
                    </select>
                </div>
                <div class="filtro-campo">
                    <label class="filtro-label">Gestion</label>
                    <select v-model="filtroGestion" class="control" @change="aplicarFiltros">
                        <option value="">Todas</option>
                        <option v-for="g in gestiones" :key="g" :value="g">{{ g }}</option>
                    </select>
                </div>
                <button class="btn-secundario" @click="limpiarFiltros">Limpiar</button>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asignatura</th>
                            <th>Grupo</th>
                            <th>Gestion</th>
                            <th>Docente</th>
                            <th>Inscritos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="grupo in grupos.data" :key="grupo.id_grupo">
                            <td data-label="Asignatura">{{ grupo.asignatura?.nombre_asignatura }}</td>
                            <td data-label="Grupo"><span class="badge">{{ grupo.nombre_grupo }}</span></td>
                            <td data-label="Gestion" class="col-mono">{{ grupo.gestion }}</td>
                            <td data-label="Docente">{{ nombreDocente(grupo.usuario) }}</td>
                            <td data-label="Inscritos">{{ grupo.inscripciones_count }}</td>
                            <td data-label="Acciones" class="col-acciones">
                                <button class="btn-accion btn-editar" @click="abrirEditar(grupo)">Reasignar docente</button>
                                <button class="btn-accion btn-eliminar" @click="confirmarEliminar(grupo)">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="!grupos.data || grupos.data.length === 0">
                            <td colspan="6" class="empty-state">No hay grupos registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="paginacion" v-if="grupos.last_page > 1">
                <button class="btn-page" :disabled="!grupos.prev_page_url" @click="irAPagina(grupos.prev_page_url)">Anterior</button>
                <span class="page-info">Pagina {{ grupos.current_page }} de {{ grupos.last_page }}</span>
                <button class="btn-page" :disabled="!grupos.next_page_url" @click="irAPagina(grupos.next_page_url)">Siguiente</button>
            </div>
        </div>

        <Modal :open="modalCrear" title="Nuevo grupo" @close="modalCrear = false">
            <div class="form-group">
                <label class="form-label">Asignatura <span class="required">*</span></label>
                <select v-model="form.id_asignatura" class="control" :class="{ 'control-error': errores.id_asignatura }">
                    <option value="">Seleccione una asignatura</option>
                    <option v-for="a in asignaturas" :key="a.id_asignatura" :value="a.id_asignatura">{{ a.nombre_asignatura }}</option>
                </select>
                <p v-if="errores.id_asignatura" class="error-msg">{{ errores.id_asignatura }}</p>
            </div>

            <div class="form-group">
                <label class="form-label">Docente <span class="required">*</span></label>
                <select v-model="form.id_usuario" class="control" :class="{ 'control-error': errores.id_usuario }">
                    <option value="">Seleccione un docente</option>
                    <option v-for="d in docentes" :key="d.id" :value="d.id">{{ d.name }} ({{ d.username }})</option>
                </select>
                <p v-if="errores.id_usuario" class="error-msg">{{ errores.id_usuario }}</p>
            </div>

            <div class="form-group">
                <label class="form-label">Gestion <span class="required">*</span></label>
                <input v-model="form.gestion" type="text" class="control" :class="{ 'control-error': errores.gestion }" placeholder="2026" />
                <p v-if="errores.gestion" class="error-msg">{{ errores.gestion }}</p>
                <p class="help-text">Ano de cuatro digitos, por ejemplo 2026.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Nombre del grupo <span class="required">*</span></label>
                <input v-model="form.nombre_grupo" type="text" class="control" :class="{ 'control-error': errores.nombre_grupo }" placeholder="A" />
                <p v-if="errores.nombre_grupo" class="error-msg">{{ errores.nombre_grupo }}</p>
            </div>

            <template #footer>
                <button class="btn-cancelar" @click="modalCrear = false">Cancelar</button>
                <button class="btn-primary" :disabled="guardando" @click="guardar">
                    <LoadingSpinner v-if="guardando" size="small" />
                    <span v-else>Registrar grupo</span>
                </button>
            </template>
        </Modal>

        <Modal :open="modalEditar" title="Reasignar docente" @close="modalEditar = false">
            <p class="confirm-text">
                Grupo <strong>{{ grupoEditando?.nombre_grupo }}</strong> de {{ grupoEditando?.asignatura?.nombre_asignatura }} ({{ grupoEditando?.gestion }}).
            </p>
            <div class="form-group">
                <label class="form-label">Docente <span class="required">*</span></label>
                <select v-model="docenteNuevo" class="control" :class="{ 'control-error': errorDocente }">
                    <option value="">Seleccione un docente</option>
                    <option v-for="d in docentes" :key="d.id" :value="d.id">{{ d.name }} ({{ d.username }})</option>
                </select>
                <p v-if="errorDocente" class="error-msg">{{ errorDocente }}</p>
            </div>
            <template #footer>
                <button class="btn-cancelar" @click="modalEditar = false">Cancelar</button>
                <button class="btn-primary" :disabled="guardando" @click="reasignarDocente">
                    <LoadingSpinner v-if="guardando" size="small" />
                    <span v-else>Guardar cambios</span>
                </button>
            </template>
        </Modal>

        <Modal :open="modalEliminar" title="Eliminar grupo" @close="modalEliminar = false">
            <p class="confirm-text">
                Se eliminara el grupo <strong>{{ grupoAEliminar?.nombre_grupo }}</strong> de
                {{ grupoAEliminar?.asignatura?.nombre_asignatura }} ({{ grupoAEliminar?.gestion }}).
                Esta accion no se puede deshacer.
            </p>
            <template #footer>
                <button class="btn-cancelar" @click="modalEliminar = false">Cancelar</button>
                <button class="btn-danger" :disabled="eliminando" @click="eliminar">
                    <LoadingSpinner v-if="eliminando" size="small" />
                    <span v-else>Eliminar</span>
                </button>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem 3rem; background-color: var(--bg-main); min-height: 100vh; font-family: var(--font-family); }
.header-section { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); margin: 0 0 4px; letter-spacing: 0.05em; font-family: var(--font-display); }
.subtitle { font-size: 0.95rem; color: var(--text-muted); margin: 0; }
.filtros { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem; }
.filtro-campo { display: flex; flex-direction: column; gap: 4px; }
.filtro-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.control { padding: 0.5rem 0.75rem; border: 1px solid var(--border-light); border-radius: 0.25rem; font-size: 0.95rem; background: #fff; color: var(--text-dark); font-family: inherit; width: 100%; box-sizing: border-box; }
.control:focus { outline: none; border-color: var(--color-primary); }
.control-error { border-color: var(--color-active); }
.btn-primary { background: var(--color-primary); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 0.25rem; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
.btn-secundario { background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); padding: 0.5rem 1rem; border-radius: 0.25rem; font-size: 0.95rem; cursor: pointer; font-family: inherit; }
.btn-cancelar { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.95rem; padding: 0.6rem 1rem; font-family: inherit; }
.btn-danger { background: var(--color-active); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 0.25rem; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
.table-container { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
.data-table th { background-color: var(--color-primary); color: #fff; text-align: left; padding: 0.75rem 1rem; font-weight: 600; }
.data-table td { padding: 0.9rem 1rem; border-bottom: 1px solid #c7d0da; color: var(--text-dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.col-mono { font-family: monospace; color: var(--text-muted); }
.col-acciones { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.badge { background: #eff6ff; color: var(--color-primary); padding: 3px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; }
.btn-accion { padding: 0.35rem 0.75rem; border-radius: 0.25rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.btn-editar { background: #eff6ff; color: var(--color-primary); border: 1px solid var(--color-primary); }
.btn-eliminar { background: #fdecea; color: var(--color-active); border: 1px solid var(--color-active); }
.empty-state { text-align: center; color: var(--text-muted); padding: 2rem !important; }
.paginacion { display: flex; justify-content: center; align-items: center; gap: 1rem; padding: 1rem; }
.btn-page { background: var(--color-primary); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.9rem; font-family: inherit; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; color: var(--text-muted); }
.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; }
.required { color: var(--color-active); }
.error-msg { color: var(--color-active); font-size: 0.85rem; margin: 4px 0 0; }
.help-text { color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0; }
.confirm-text { font-size: 0.95rem; color: var(--text-dark); line-height: 1.6; }
@media (max-width: 820px) {
    .panel-container { padding: 1.25rem; }
    .header-section { flex-direction: column; align-items: stretch; }
    .filtros { flex-direction: column; align-items: stretch; }
    .data-table thead { display: none; }
    .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
    .data-table tr { border-bottom: 1px solid #c7d0da; padding: 0.5rem 0; }
    .data-table td { border: none; padding: 0.35rem 1rem; }
    .data-table td::before { content: attr(data-label); display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 2px; }
}
</style>
