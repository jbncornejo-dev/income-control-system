<template>
    <Head title="Auditoría" />

    <AuthenticatedLayout>
        <div class="auditoria">
            <header class="cabecera">
                <div>
                    <span class="cabecera-eyebrow">Seguridad</span>
                    <h1 class="cabecera-titulo">Auditoría de operaciones</h1>
                </div>
                <span class="cabecera-total">{{ registros.total }} registros</span>
            </header>

            <section class="filtros">
                <SelectInput
                    v-model="filtroUsuario"
                    label="Usuario"
                    placeholder="Todos los usuarios"
                    :options="usuariosOptions"
                />
                <SelectInput
                    v-model="filtroTabla"
                    label="Módulo"
                    placeholder="Todos los módulos"
                    :options="tablasOptions"
                />
                <SelectInput
                    v-model="filtroAccion"
                    label="Acción"
                    placeholder="Todas las acciones"
                    :options="accionesOptions"
                />
                <TextInput v-model="filtroDesde" label="Desde" type="date" />
                <TextInput v-model="filtroHasta" label="Hasta" type="date" />
                <div class="filtros-acciones">
                    <Button @click="aplicarFiltros">Aplicar</Button>
                    <button type="button" class="limpiar" @click="limpiarFiltros">Limpiar</button>
                </div>
            </section>

            <section class="panel">
                <p v-if="!registros.data.length" class="vacio">No hay operaciones registradas con esos criterios.</p>

                <div v-else class="tabla-contenedor">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th class="columna-numero">Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="registro in registros.data" :key="registro.id_log">
                                <td class="celda-fecha">{{ formatearFecha(registro.fecha_hora) }}</td>
                                <td>
                                    <span v-if="registro.user" class="usuario">
                                        {{ registro.user.name }}
                                        <small>{{ registro.user.username }}</small>
                                    </span>
                                    <span v-else class="sin-usuario">Sistema</span>
                                </td>
                                <td>
                                    <span class="etiqueta" :class="claseAccion(registro.accion)">{{ registro.accion }}</span>
                                </td>
                                <td class="celda-tabla">{{ nombreModulo(registro.tabla_afectada) }}</td>
                                <td class="columna-numero">{{ registro.id_registro_afectado ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="registros.last_page > 1"
                    :current-page="registros.current_page"
                    :total-pages="registros.last_page"
                    @change-page="irAPagina"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';
import Pagination from '@/components/ui/Pagination.vue';

const props = defineProps({
    registros: Object,
    usuarios: Array,
    tablas: Array,
    acciones: Array,
    filters: Object,
});

const filtroUsuario = ref(props.filters?.id_usuario || '');
const filtroTabla = ref(props.filters?.tabla_afectada || '');
const filtroAccion = ref(props.filters?.accion || '');
const filtroDesde = ref(props.filters?.desde || '');
const filtroHasta = ref(props.filters?.hasta || '');

const usuariosOptions = computed(() => props.usuarios.map(u => ({ value: u.id, label: `${u.name} (${u.username})` })));
const tablasOptions = computed(() => props.tablas.map(t => ({ value: t, label: nombreModulo(t) })));
const accionesOptions = computed(() => props.acciones.map(a => ({ value: a, label: a })));

const criterios = () => ({
    id_usuario: filtroUsuario.value || undefined,
    tabla_afectada: filtroTabla.value || undefined,
    accion: filtroAccion.value || undefined,
    desde: filtroDesde.value || undefined,
    hasta: filtroHasta.value || undefined,
});

const aplicarFiltros = () => {
    router.get('/auditoria', criterios(), { preserveState: true, preserveScroll: true });
};

const limpiarFiltros = () => {
    filtroUsuario.value = '';
    filtroTabla.value = '';
    filtroAccion.value = '';
    filtroDesde.value = '';
    filtroHasta.value = '';
    router.get('/auditoria', {}, { preserveState: true, preserveScroll: true });
};

const irAPagina = (pagina) => {
    router.get('/auditoria', { ...criterios(), page: pagina }, { preserveState: true, preserveScroll: true });
};

const MODULOS = {
    ambiente: 'Ambientes',
    ambientes: 'Ambientes',
    asignatura: 'Asignaturas',
    asignaturas: 'Asignaturas',
    estudiante: 'Estudiantes',
    estudiantes: 'Estudiantes',
    examen: 'Gestión de exámenes',
    examenes: 'Gestión de exámenes',
    examen_ambiente: 'Ambientes del examen',
    grupo: 'Grupos',
    grupos: 'Grupos',
    habilitacion: 'Habilitaciones',
    habilitaciones: 'Habilitaciones',
    incidencia: 'Incidencias',
    incidencias: 'Incidencias',
    inscripcion: 'Inscripciones',
    inscripciones: 'Inscripciones',
    periodo: 'Periodos',
    registro_ingreso: 'Registro de ingreso',
    tipo_examen: 'Tipos de examen',
    users: 'Usuarios',
};

const nombreModulo = (tabla) => {
    if (!tabla) return '-';
    if (MODULOS[tabla]) return MODULOS[tabla];
    const limpio = tabla.replace(/_/g, ' ');
    return limpio.charAt(0).toUpperCase() + limpio.slice(1);
};

const claseAccion = (accion) => {
    if (accion === 'CREATE') return 'etiqueta-alta';
    if (accion === 'DELETE') return 'etiqueta-baja';
    if (accion === 'UPDATE') return 'etiqueta-cambio';
    return 'etiqueta-otra';
};

const formatearFecha = (valor) => {
    if (!valor) return '-';
    const fecha = new Date(valor);
    return fecha.toLocaleString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<style scoped>
.auditoria {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.cabecera {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    background: linear-gradient(120deg, var(--color-primary) 0%, #27507a 55%, #2f6290 100%);
    border-radius: 0.75rem;
    padding: 1.4rem 1.6rem;
    color: #fff;
}

.cabecera-eyebrow {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
}

.cabecera-titulo {
    margin: 0.2rem 0 0;
    font-family: var(--font-display);
    font-size: 1.9rem;
    letter-spacing: 0.02em;
}

.cabecera-total {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.filtros {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: end;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.1rem 1.25rem;
}

.filtros-acciones {
    display: flex;
    gap: 0.6rem;
    align-items: center;
}

.limpiar {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.4rem 0.2rem;
}

.limpiar:hover {
    color: var(--color-active);
}

.panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.15rem 1.25rem;
}

.vacio {
    margin: 0;
    font-size: 0.88rem;
    color: var(--text-muted);
}

.tabla-contenedor {
    overflow-x: auto;
}

.tabla {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.tabla thead th {
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 0 0.75rem 0.6rem;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.tabla tbody td {
    padding: 0.7rem 0.75rem;
    border-bottom: 1px solid #f1f3f6;
    color: var(--text-dark);
    vertical-align: middle;
}

.tabla tbody tr:last-child td {
    border-bottom: none;
}

.columna-numero {
    text-align: right;
}

.celda-fecha {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.celda-tabla {
    font-weight: 600;
}

.usuario {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}

.usuario small {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.sin-usuario {
    color: var(--text-muted);
    font-style: italic;
}

.etiqueta {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.etiqueta-alta {
    background: #e7f4ec;
    color: #1d6b40;
}

.etiqueta-cambio {
    background: #e8eff7;
    color: var(--color-primary);
}

.etiqueta-baja {
    background: #fbeaec;
    color: var(--color-active);
}

.etiqueta-otra {
    background: #f1f3f6;
    color: var(--text-muted);
}

@media (max-width: 760px) {
    .cabecera {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 1.1rem 1.2rem;
    }

    .cabecera-titulo {
        font-size: 1.45rem;
    }

    .filtros {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .filtros-acciones {
        justify-content: space-between;
    }

    .panel {
        padding: 1rem 0.75rem;
    }

    .tabla {
        font-size: 0.82rem;
    }
}
</style>
