<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Pagination from '@/components/ui/Pagination.vue';

const props = defineProps({
    logs: Object,
    tablas: Array,
    usuarios: Array,
    filters: Object,
});

const tablaAfectada = ref(props.filters?.tabla_afectada || '');
const idUsuario = ref(props.filters?.id_usuario ? String(props.filters.id_usuario) : '');
const desde = ref(props.filters?.desde || '');
const hasta = ref(props.filters?.hasta || '');
const logDetalle = ref(null);

const opcionesTablas = computed(() => (props.tablas || []).map((tabla) => ({
    value: tabla,
    label: tabla.toUpperCase(),
})));

const opcionesUsuarios = computed(() => (props.usuarios || []).map((usuario) => ({
    value: String(usuario.id),
    label: `${usuario.name} (${usuario.username || usuario.id})`,
})));

function aplicarFiltros() {
    router.get('/auditoria', {
        tabla_afectada: tablaAfectada.value || undefined,
        id_usuario: idUsuario.value || undefined,
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function limpiarFiltros() {
    tablaAfectada.value = '';
    idUsuario.value = '';
    desde.value = '';
    hasta.value = '';
    aplicarFiltros();
}

function cambiarPagina(pagina) {
    router.get('/auditoria', {
        tabla_afectada: tablaAfectada.value || undefined,
        id_usuario: idUsuario.value || undefined,
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
        page: pagina,
    }, { preserveState: true, preserveScroll: true });
}

function alternarDetalle(id) {
    logDetalle.value = props.logs.data.find((log) => log.id_log === id) || null;
}

function cerrarDetalle() {
    logDetalle.value = null;
}

function camposDetalle(log) {
    const anteriores = log.datos_anteriores || {};
    const nuevos = log.datos_nuevos || {};
    const campos = new Set([...Object.keys(anteriores), ...Object.keys(nuevos)]);

    return [...campos].map((campo) => ({
        campo,
        anterior: anteriores[campo],
        nuevo: nuevos[campo],
        cambiado: JSON.stringify(anteriores[campo]) !== JSON.stringify(nuevos[campo]),
    }));
}

function mostrarValor(valor) {
    if (valor === undefined || valor === null || valor === '') return '—';
    if (typeof valor === 'object') return JSON.stringify(valor);
    return String(valor);
}
</script>

<template>
    <Head title="Auditoría del Sistema" />
    <AuthenticatedLayout>
        <div class="panel-container">
            <header class="header-section">
                <div>
                    <h1 class="panel-title">AUDITORÍA DE OPERACIONES</h1>
                    <p class="subtitle">Historial de trazabilidad de creaciones, ediciones y eliminaciones del sistema</p>
                </div>
            </header>

            <div class="filters-bar">
                <SelectInput v-model="tablaAfectada" label="Tabla Afectada" placeholder="Todas las tablas"
                    :options="opcionesTablas" @change="aplicarFiltros" />
                <SelectInput v-model="idUsuario" label="Usuario" placeholder="Todos los usuarios"
                    :options="opcionesUsuarios" @change="aplicarFiltros" />
                <TextInput v-model="desde" type="date" label="Desde" @change="aplicarFiltros" />
                <TextInput v-model="hasta" type="date" label="Hasta" @change="aplicarFiltros" />
                <button type="button" class="btn-limpiar" @click="limpiarFiltros">Limpiar filtros</button>
            </div>

            <div class="table-section">
                <table class="data-table">
                    <thead>
                        <tr><th>ID Log</th><th>Usuario</th><th>Tabla Afectada</th><th>ID Registro</th><th>Acción</th><th>Fecha y Hora</th><th>Detalle</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id_log">
                            <td>#{{ log.id_log }}</td>
                            <td class="font-bold">
                                {{ log.user?.name || `Usuario #${log.id_usuario}` }}
                                <small v-if="log.user?.username" class="text-muted block">({{ log.user.username }})</small>
                            </td>
                            <td><span class="badge-tabla">{{ log.tabla_afectada }}</span></td>
                            <td>{{ log.id_registro_afectado ?? '—' }}</td>
                            <td><span class="badge-accion" :class="'accion-' + (log.accion || '').toLowerCase()">{{ log.accion }}</span></td>
                            <td>{{ log.fecha_hora }}</td>
                            <td>
                                <button
                                    v-if="log.datos_anteriores || log.datos_nuevos"
                                    type="button"
                                    class="btn-detalle"
                                    @click="alternarDetalle(log.id_log)"
                                >
                                    Ver campos
                                </button>
                                <span v-else>—</span>
                            </td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="7" class="empty-state">No se encontraron registros de auditoría para los filtros seleccionados.</td>
                        </tr>
                    </tbody>
                </table>
                <Pagination v-if="logs.last_page > 1" :current-page="logs.current_page"
                    :total-pages="logs.last_page" :links="logs.links" @change-page="cambiarPagina" />
            </div>
        </div>

        <div v-if="logDetalle" class="modal-backdrop" @click.self="cerrarDetalle">
            <section class="detail-modal" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title">
                <header class="detail-modal-header">
                    <div>
                        <h2 id="audit-detail-title">Detalle de auditoría #{{ logDetalle.id_log }}</h2>
                        <p>{{ logDetalle.tabla_afectada }} · Registro {{ logDetalle.id_registro_afectado ?? '—' }}</p>
                    </div>
                    <button type="button" class="modal-close" aria-label="Cerrar detalle" @click="cerrarDetalle">×</button>
                </header>
                <div class="detail-modal-body">
                    <div class="detail-summary">
                        <span>Usuario: <strong>{{ logDetalle.user?.name || `Usuario #${logDetalle.id_usuario}` }}</strong></span>
                        <span>Acción: <strong>{{ logDetalle.accion }}</strong></span>
                        <span>Fecha: <strong>{{ logDetalle.fecha_hora }}</strong></span>
                    </div>
                    <div class="detail-grid">
                        <div v-for="campo in camposDetalle(logDetalle)" :key="campo.campo" class="detail-item">
                            <span class="detail-field">{{ campo.campo }}</span>
                            <span class="detail-old">Antes: {{ mostrarValor(campo.anterior) }}</span>
                            <span class="detail-new">Después: {{ mostrarValor(campo.nuevo) }}</span>
                        </div>
                    </div>
                </div>
                <footer class="detail-modal-footer">
                    <button type="button" class="btn-limpiar" @click="cerrarDetalle">Cerrar</button>
                </footer>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.panel-container { padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }
.panel-title { font-size: 1.5rem; font-weight: 700; color: var(--color-primary); font-family: var(--font-display); margin: 0 0 4px; }
.subtitle { font-size: .95rem; color: var(--text-muted); margin: 0; }
.filters-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; align-items: end; background: #fff; padding: 1rem 1.25rem; border: 1px solid var(--border-light, #e5e7eb); border-radius: .5rem; }
.btn-limpiar { padding: .55rem 1rem; border: 1px solid var(--color-primary); background: #fff; color: var(--color-primary); border-radius: .375rem; font-weight: 600; cursor: pointer; height: 40px; }
.badge-tabla, .badge-accion { padding: .2rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
.badge-tabla { background: #eef2f7; color: var(--color-primary); }
.accion-create { background: #ecfdf5; color: #047857; }
.accion-update { background: #eff6ff; color: #1d4ed8; }
.accion-delete { background: #fef2f2; color: #b91c1c; }
.font-bold { font-weight: 600; }
.text-muted { color: var(--text-muted); }
.block { display: block; }
.btn-detalle { border: 0; background: transparent; color: var(--color-primary); cursor: pointer; font-weight: 700; font-size: .8rem; }
.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: .75rem; margin-top: .75rem; }
.detail-item { display: flex; flex-direction: column; gap: .2rem; padding: .65rem; background: #fff; border-radius: .375rem; border: 1px solid #e5e7eb; }
.detail-field { font-weight: 700; color: var(--color-primary); }
.detail-old, .detail-new { font-family: var(--font-mono, monospace); font-size: .75rem; overflow-wrap: anywhere; }
.detail-old { color: #b91c1c; }
.detail-new { color: #047857; }
.modal-backdrop { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, .55); }
.detail-modal { width: min(760px, 100%); max-height: min(680px, 90vh); overflow: auto; background: #fff; border-radius: .75rem; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); }
.detail-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
.detail-modal-header h2 { margin: 0; color: var(--color-primary); font-size: 1.1rem; }
.detail-modal-header p { margin: .35rem 0 0; color: var(--text-muted); font-size: .85rem; text-transform: uppercase; }
.modal-close { border: 0; background: transparent; color: #64748b; font-size: 1.7rem; line-height: 1; cursor: pointer; }
.detail-modal-body { padding: 1.25rem 1.5rem; }
.detail-summary { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; color: #475569; font-size: .85rem; }
.detail-modal-footer { display: flex; justify-content: flex-end; padding: 0 1.5rem 1.25rem; }
</style>
