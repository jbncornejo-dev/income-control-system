<template>
    <Head title="Estadísticas de Incidencias" />

    <AuthenticatedLayout>
        <div class="estadisticas">
            <header class="cabecera">
                <div>
                    <span class="cabecera-eyebrow">Incidencias</span>
                    <h1 class="cabecera-titulo">Estadísticas</h1>
                </div>
                <Link href="/incidencias" class="volver">Ver el listado</Link>
            </header>

            <section class="filtros">
                <SelectInput
                    v-model="filtroExamen"
                    label="Examen"
                    placeholder="Todos los exámenes"
                    :options="examenesOptions"
                />
                <SelectInput
                    v-model="filtroTipo"
                    label="Tipo"
                    placeholder="Todos los tipos"
                    :options="tiposOptions"
                />
                <TextInput v-model="filtroDesde" label="Desde" type="date" />
                <TextInput v-model="filtroHasta" label="Hasta" type="date" />
                <div class="filtros-acciones">
                    <Button @click="aplicarFiltros">Aplicar</Button>
                    <button type="button" class="limpiar" @click="limpiarFiltros">Limpiar</button>
                </div>
            </section>

            <section class="indicadores">
                <article class="indicador">
                    <span class="indicador-label">Total de incidencias</span>
                    <strong class="indicador-valor">{{ resumen.total }}</strong>
                </article>
                <article class="indicador">
                    <span class="indicador-label">Promedio por día</span>
                    <strong class="indicador-valor">{{ resumen.promedio_diario }}</strong>
                </article>
                <article class="indicador">
                    <span class="indicador-label">Exámenes afectados</span>
                    <strong class="indicador-valor">{{ resumen.examenes_afectados }}</strong>
                </article>
                <article class="indicador indicador-ancho">
                    <span class="indicador-label">Tipo más frecuente</span>
                    <strong class="indicador-valor indicador-texto">{{ resumen.tipo_mas_frecuente || 'Sin registros' }}</strong>
                </article>
            </section>

            <div class="paneles">
                <section class="panel">
                    <h2 class="panel-titulo">Distribución por tipo</h2>
                    <p v-if="!porTipo.length" class="vacio">No hay incidencias en el rango seleccionado.</p>
                    <ul v-else class="barras">
                        <li v-for="fila in porTipo" :key="fila.tipo" class="barra-fila">
                            <div class="barra-encabezado">
                                <span class="barra-nombre">{{ fila.tipo }}</span>
                                <span class="barra-valor">{{ fila.total }} ({{ fila.porcentaje }}%)</span>
                            </div>
                            <div class="barra-pista">
                                <div class="barra-relleno" :style="{ width: fila.porcentaje + '%' }"></div>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="panel">
                    <h2 class="panel-titulo">Exámenes con más incidencias</h2>
                    <p v-if="!porExamen.length" class="vacio">No hay incidencias en el rango seleccionado.</p>
                    <ul v-else class="barras">
                        <li v-for="fila in porExamen" :key="fila.id_examen" class="barra-fila">
                            <div class="barra-encabezado">
                                <span class="barra-nombre">{{ fila.examen }}<small v-if="fila.fecha"> · {{ fila.fecha }}</small></span>
                                <span class="barra-valor">{{ fila.total }}</span>
                            </div>
                            <div class="barra-pista">
                                <div class="barra-relleno barra-acento" :style="{ width: anchoExamen(fila.total) }"></div>
                            </div>
                        </li>
                    </ul>
                </section>
                <section class="panel">
                <h2 class="panel-titulo">Evolución por día</h2>
                <p v-if="!porDia.length" class="vacio">No hay incidencias en el rango seleccionado.</p>
                <div v-else class="columnas">
                    <div v-for="fila in porDia" :key="fila.dia" class="columna">
                        <span class="columna-valor">{{ fila.total }}</span>
                        <div class="columna-barra" :style="{ height: alturaDia(fila.total) }"></div>
                        <span class="columna-dia">{{ formatearDia(fila.dia) }}</span>
                    </div>
                </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/components/ui/SelectInput.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    resumen: Object,
    porTipo: Array,
    porDia: Array,
    porExamen: Array,
    examenes: Array,
    tipos: Array,
    filters: Object,
});

const filtroExamen = ref(props.filters?.id_examen || '');
const filtroTipo = ref(props.filters?.tipo_incidencia || '');
const filtroDesde = ref(props.filters?.desde || '');
const filtroHasta = ref(props.filters?.hasta || '');

const examenesOptions = computed(() => props.examenes.map(e => ({ value: e.id_examen, label: e.nombre })));
const tiposOptions = computed(() => props.tipos.map(t => ({ value: t, label: t })));

const maximoExamen = computed(() => Math.max(...props.porExamen.map(f => f.total), 1));
const maximoDia = computed(() => Math.max(...props.porDia.map(f => f.total), 1));

const anchoExamen = (total) => `${Math.max((total / maximoExamen.value) * 100, 4)}%`;
const alturaDia = (total) => `${Math.max((total / maximoDia.value) * 100, 6)}%`;

const formatearDia = (dia) => {
    const [, mes, numero] = dia.split('-');
    return `${numero}/${mes}`;
};

const aplicarFiltros = () => {
    router.get('/incidencias/estadisticas', {
        id_examen: filtroExamen.value || undefined,
        tipo_incidencia: filtroTipo.value || undefined,
        desde: filtroDesde.value || undefined,
        hasta: filtroHasta.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const limpiarFiltros = () => {
    filtroExamen.value = '';
    filtroTipo.value = '';
    filtroDesde.value = '';
    filtroHasta.value = '';
    router.get('/incidencias/estadisticas', {}, { preserveState: true, preserveScroll: true });
};
</script>

<style scoped>
.estadisticas {
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

.volver {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.4);
    padding-bottom: 2px;
}

.volver:hover {
    color: #fff;
}

.filtros {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
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

.indicadores {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1rem;
}

.indicador {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid var(--color-primary);
    border-radius: 0.5rem;
    padding: 1rem 1.1rem;
}

.indicador-ancho {
    border-left-color: var(--color-active);
}

.indicador-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.indicador-valor {
    display: block;
    margin-top: 0.35rem;
    font-family: var(--font-display);
    font-size: 2rem;
    line-height: 1;
    color: var(--color-primary);
}

.indicador-texto {
    font-size: 1.1rem;
    line-height: 1.3;
}

.paneles {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 1rem;
}

.panel {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.15rem 1.25rem;
    min-width: 0;
}

.panel-titulo {
    margin: 0 0 1rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-dark);
}

.vacio {
    margin: 0;
    font-size: 0.88rem;
    color: var(--text-muted);
}

.barras {
    list-style: none;
    margin: 0;
    padding: 0 0.3rem 0 0;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    max-height: 330px;
    overflow-y: auto;
}

.barra-encabezado {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.35rem;
}

.barra-nombre {
    font-size: 0.86rem;
    color: var(--text-dark);
}

.barra-nombre small {
    color: var(--text-muted);
}

.barra-valor {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted);
    white-space: nowrap;
}

.barra-pista {
    height: 8px;
    background: #eef1f5;
    border-radius: 999px;
    overflow: hidden;
}

.barra-relleno {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--color-primary), #3b7bb5);
}

.barra-acento {
    background: linear-gradient(90deg, var(--color-active), #c9545c);
}

.columnas {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 0.5rem;
    flex: 1;
    min-height: 300px;
    overflow-x: auto;
    padding-bottom: 0.3rem;
}

.columna {
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    min-width: 38px;
    max-width: 54px;
}

.columna-barra {
    min-height: 6px;
}

.columna-valor {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
}

.columna-barra {
    width: 100%;
    border-radius: 0.3rem 0.3rem 0 0;
    background: linear-gradient(180deg, #3b7bb5, var(--color-primary));
}

.columna-dia {
    font-size: 0.72rem;
    color: var(--text-muted);
    white-space: nowrap;
}

@media (max-width: 1100px) {
    .paneles {
        grid-template-columns: 1fr;
    }

    .columnas {
        min-height: 230px;
    }
}

@media (max-width: 760px) {
    .estadisticas {
        gap: 1rem;
    }

    .cabecera {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.6rem;
        padding: 1.1rem 1.2rem;
    }

    .cabecera-titulo {
        font-size: 1.5rem;
    }

    .filtros {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .filtros-acciones {
        justify-content: space-between;
    }

    .indicadores {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.7rem;
    }

    .indicador {
        padding: 0.8rem 0.9rem;
    }

    .indicador-valor {
        font-size: 1.5rem;
    }

    .indicador-texto {
        font-size: 0.95rem;
    }

    .panel {
        padding: 1rem;
    }

    .barra-encabezado {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.15rem;
    }

    .columnas {
        height: 190px;
        gap: 0.45rem;
        justify-content: flex-start;
    }

    .columna {
        min-width: 34px;
        max-width: 44px;
    }
}

@media (max-width: 420px) {
    .indicadores {
        grid-template-columns: 1fr;
    }
}
</style>
