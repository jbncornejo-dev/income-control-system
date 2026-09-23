<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    estudiante: Object,
    examenes: Array,
    stats: Object,
});

// La pestaña "Hoy" se muestra al entrar: el estudiante ve primero lo que le
// toca hoy y puede cambiar de pestaña para ver todo lo demás.
const filtro = ref('hoy');

const etiquetasEstado = {
    programado: 'Programado',
    en_curso: 'En curso',
    finalizado: 'Finalizado',
    suspendido: 'Suspendido',
    cancelado: 'Cancelado',
    anulado: 'Anulado',
};

const estadosDelFiltro = {
    en_curso: ['en_curso'],
    proximos: ['programado'],
    finalizados: ['finalizado'],
    cerrados: ['cancelado', 'anulado', 'suspendido'],
};

const filtros = [
    { clave: 'hoy', etiqueta: 'Hoy' },
    { clave: 'todos', etiqueta: 'Todos' },
    { clave: 'en_curso', etiqueta: 'En curso' },
    { clave: 'proximos', etiqueta: 'Próximos' },
    { clave: 'finalizados', etiqueta: 'Finalizados' },
    { clave: 'cerrados', etiqueta: 'Cerrados' },
];

const examenesHoy = computed(() => (props.examenes || []).filter((e) => esHoy(e.fecha)));

// El backend entrega los exámenes ordenados por relevancia (en curso →
// próximos → finalizados/cerrados); aquí solo se filtra por la pestaña
// seleccionada. La pestaña "Hoy" filtra por fecha (día actual).
const visibles = computed(() => {
    const examenes = props.examenes || [];

    if (filtro.value === 'hoy') {
        return examenes.filter((e) => esHoy(e.fecha));
    }

    const estados = estadosDelFiltro[filtro.value] || null;

    return estados ? examenes.filter((e) => estados.includes(e.estado)) : examenes;
});

function esHoy(fecha) {
    const hoy = new Date();
    const iso = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    return fecha === iso;
}

function mensajeVacio() {
    const mensajes = {
        hoy: 'Hoy no tienes exámenes asignados.',
        todos: 'Aún no tienes exámenes asignados.',
        en_curso: 'No hay exámenes en curso ahora mismo.',
        proximos: 'No tienes exámenes próximos.',
        finalizados: 'Todavía no tienes exámenes finalizados.',
        cerrados: 'No hay exámenes cerrados o cancelados.',
    };
    return mensajes[filtro.value] || mensajes.todos;
}

// El backend expone los contadores bajo keys por estado (total, en_curso,
// ...); acá se mapean a las pestañas. "Hoy" y "Todos" no tienen key propia.
function cantidadDe(f) {
    if (f.clave === 'hoy') return examenesHoy.value.length;
    if (f.clave === 'todos') return props.stats?.total ?? 0;
    return props.stats?.[f.clave] ?? 0;
}

// La habilitación es relevante solo mientras el examen no terminó: en
// finalizados/cerrados no tiene sentido el aviso de "estás habilitado/a".
const estadosConHabilitacion = ['programado', 'en_curso'];

function mostrarHabilitacion(examen) {
    return Boolean(examen.habilitacion) && estadosConHabilitacion.includes(examen.estado);
}
</script>

<template>
    <Head title="Mis Exámenes" />

    <AuthenticatedLayout>
        <div class="mis-examenes">
            <header class="head">
                <h1 class="title">Mis Exámenes</h1>
                <p v-if="estudiante" class="greeting">
                    Hola, {{ estudiante.nombres }} {{ estudiante.apellidos }} —
                    <span class="codigo">{{ estudiante.codigo }}</span>
                </p>
                <p v-else class="greeting">
                    Tu cuenta aún no está vinculada a una matrícula de estudiante.
                </p>
            </header>

            <nav class="filtros" aria-label="Filtrar exámenes">
                <button
                    v-for="f in filtros"
                    :key="f.clave"
                    class="filtro"
                    :class="{ activo: filtro === f.clave }"
                    type="button"
                    @click="filtro = f.clave"
                >
                    {{ f.etiqueta }}
                    <span class="cantidad">{{ cantidadDe(f) }}</span>
                </button>
            </nav>

            <div v-if="visibles.length" class="tarjetas">
                <article
                    v-for="examen in visibles"
                    :key="examen.id"
                    class="tarjeta"
                    :class="{ hoy: esHoy(examen.fecha), en_curso: examen.estado === 'en_curso' }"
                >
                    <div class="tarjeta-cabecera">
                        <div class="tarjeta-titulo">
                            <h2>{{ examen.asignatura || 'Examen sin asignatura' }}</h2>
                            <p class="subtitulo">
                                {{ examen.tipo }}
                                <template v-if="examen.periodo"> · {{ examen.periodo }}</template>
                            </p>
                        </div>
                        <span class="badge" :class="'estado-' + examen.estado">
                            {{ etiquetasEstado[examen.estado] || examen.estado }}
                        </span>
                    </div>

                    <div class="bloque-fecha">
                        <span class="fecha">{{ examen.fecha_formateada }}</span>
                        <span class="hora">{{ examen.hora_inicio }} – {{ examen.hora_fin }} · {{ examen.duracion }} min</span>
                    </div>

                    <dl class="detalles">
                        <div v-if="examen.ambientes.length" class="detalle">
                            <dt>Ambientes</dt>
                            <dd>{{ examen.ambientes.join(', ') }}</dd>
                        </div>
                        <div v-if="examen.grupos.length" class="detalle">
                            <dt>Grupos</dt>
                            <dd>{{ examen.grupos.join(', ') }}</dd>
                        </div>
                    </dl>

                    <div v-if="examen.normas_generales || examen.habilitacion?.normas_particulares" class="normas">
                        <p v-if="examen.normas_generales">
                            <strong>Normas generales:</strong> {{ examen.normas_generales }}
                        </p>
                        <p v-if="examen.habilitacion?.normas_particulares">
                            <strong>Normas para ti:</strong> {{ examen.habilitacion.normas_particulares }}
                        </p>
                    </div>

                    <div
                        v-if="mostrarHabilitacion(examen)"
                        class="habilitacion"
                        :class="examen.habilitacion.estado ? 'habilitado' : 'inhabilitado'"
                    >
                        <strong v-if="examen.habilitacion.estado">Estás habilitado/a para este examen</strong>
                        <template v-else>
                            <strong>No estás habilitado/a</strong>
                            <span v-if="examen.habilitacion.motivo" class="motivo">
                                {{ examen.habilitacion.motivo }}
                            </span>
                        </template>
                    </div>
                </article>
            </div>

            <div v-else class="vacio">{{ mensajeVacio() }}</div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.mis-examenes {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}

.head {
    margin-bottom: 1.25rem;
}

.title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-primary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0 0 0.35rem 0;
}

.greeting {
    font-size: 0.95rem;
    color: var(--text-dark, #333);
    margin: 0;
}

.codigo {
    font-weight: 600;
    color: var(--color-primary);
}

/* Filtros */
.filtros {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.filtro {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.9rem;
    border: 1px solid var(--border-light, #d1d5db);
    border-radius: 999px;
    background: #ffffff;
    color: #4b5563;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.filtro:hover {
    border-color: var(--color-primary);
}

.filtro.activo {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #ffffff;
}

.filtro .cantidad {
    font-size: 0.75rem;
    font-weight: 700;
    min-width: 1.25rem;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    background: rgba(29, 54, 83, 0.1);
    color: var(--color-primary);
}

.filtro.activo .cantidad {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

/* Tarjetas */
.tarjetas {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}

.tarjeta {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-top: 4px solid var(--color-primary);
    border-radius: 0.6rem;
    padding: 1.15rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.tarjeta.en_curso {
    border-top-color: #16a34a;
}

.tarjeta.hoy {
    box-shadow: 0 0 0 2px rgba(161, 43, 51, 0.18);
}

.tarjeta-cabecera {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
}

.tarjeta-titulo h2 {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.15rem 0;
    line-height: 1.25;
}

.subtitulo {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0;
}

.badge {
    flex-shrink: 0;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.badge.estado-programado { background: #eef2f7; color: var(--color-primary); border: 1px solid var(--color-primary); }
.badge.estado-en_curso { background: #ecfdf5; color: #047857; border: 1px solid #10b981; }
.badge.estado-finalizado { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }
.badge.estado-suspendido { background: #fffbeb; color: #b45309; border: 1px solid #f59e0b; }
.badge.estado-cancelado { background: #fef2f2; color: #b91c1c; border: 1px solid #ef4444; }
.badge.estado-anulado { background: #f8fafc; color: #334155; border: 1px solid #64748b; }

.bloque-fecha {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border-radius: 0.4rem;
}

.fecha {
    font-size: 0.9rem;
    font-weight: 600;
    color: #111827;
    text-transform: capitalize;
}

.hora {
    font-size: 0.8rem;
    color: #6b7280;
}

.detalles {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin: 0;
}

.detalle {
    display: flex;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.detalle dt {
    color: #6b7280;
    min-width: 5.5rem;
}

.detalle dd {
    color: #1f2937;
    margin: 0;
}

.normas {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.82rem;
    color: #4b5563;
    padding-top: 0.5rem;
    border-top: 1px dashed #e5e7eb;
}

.normas p {
    margin: 0;
}

.habilitacion {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.82rem;
    padding: 0.65rem 0.75rem;
    border-radius: 0.4rem;
    margin-top: auto;
}

.habilitacion.habilitado {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.habilitacion.inhabilitado {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.habilitacion .motivo {
    color: #b91c1c;
}

.vacio {
    background: #ffffff;
    border: 1px dashed #d1d5db;
    border-radius: 0.6rem;
    padding: 2.5rem 1rem;
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}

@media (max-width: 640px) {
    .mis-examenes {
        padding: 1rem;
    }

    .title {
        font-size: 1.25rem;
    }

    .tarjetas {
        grid-template-columns: 1fr;
    }

    .filtros {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.25rem;
    }
}
</style>