<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/components/ui/Button.vue';
import FormField from '@/components/ui/FormField.vue';
import Modal from '@/components/ui/Modal.vue';
import TextInput from '@/components/ui/TextInput.vue';

const props = defineProps({
    roles: { type: Array, required: true },
    rolesBase: { type: Object, required: true },
    permisos: { type: Object, required: true },
    asignaturas: { type: Array, required: true },
});

const mostrarFormulario = ref(false);
const editando = ref(null);
const limitarAsignaturas = ref(false);
const form = useForm({ nombre_rol: '', permisos: [], asignaturas_reportes: [] });
const nombreVisible = nombre => nombre.charAt(0).toLocaleUpperCase('es') + nombre.slice(1);

function abrir(rol = null) {
    editando.value = rol;
    form.clearErrors();
    form.nombre_rol = rol?.nombre_rol ?? '';
    form.permisos = [...(rol?.permisos ?? [])];
    limitarAsignaturas.value = Array.isArray(rol?.asignaturas_reportes);
    form.asignaturas_reportes = props.asignaturas
        .filter(asignatura => rol?.asignaturas_reportes?.includes(asignatura.id_asignatura))
        .map(asignatura => asignatura.id_asignatura);
    mostrarFormulario.value = true;
}

function guardar() {
    const opciones = { preserveScroll: true, onSuccess: () => { mostrarFormulario.value = false; } };
    form.transform(data => ({
        ...data,
        asignaturas_reportes: limitarAsignaturas.value ? data.asignaturas_reportes : null,
    }));
    if (editando.value) {
        form.patch('/roles/' + editando.value.id_rol, opciones);
    } else {
        form.post('/roles', opciones);
    }
}
</script>

<template>
    <Head title="Roles" />
    <AuthenticatedLayout>
        <div class="panel-container">
            <h1 class="panel-title">Roles</h1>
            <Button variant="primary" @click="abrir()">Nuevo rol</Button>

            <ul class="role-list">
                <li v-for="rol in roles" :key="rol.id_rol" class="role-card">
                    <div>
                        <h2>{{ nombreVisible(rol.nombre_rol) }}</h2>
                        <p>{{ (Object.hasOwn(rolesBase, rol.nombre_rol) ? rolesBase[rol.nombre_rol] : (rol.permisos ?? []).map(permiso => permisos[permiso]).join(', ')) || 'Sin permisos' }}</p>
                        <p>{{ rol.users_count === 1 ? '1 usuario' : rol.users_count + ' usuarios' }}</p>
                    </div>
                    <Button v-if="!Object.hasOwn(rolesBase, rol.nombre_rol)" variant="action" @click="abrir(rol)">Editar</Button>
                </li>
            </ul>

            <Modal :open="mostrarFormulario" :title="editando ? 'Editar rol' : 'Nuevo rol'" @close="mostrarFormulario = false">
                <form class="role-form" @submit.prevent="guardar">
                    <FormField :error="form.errors.nombre_rol">
                        <TextInput id="nombre_rol" v-model="form.nombre_rol" label="Nombre del rol" maxlength="50" required />
                    </FormField>
                    <fieldset>
                        <legend>Permisos</legend>
                        <label v-for="(etiqueta, clave) in permisos" :key="clave">
                            <input v-model="form.permisos" type="checkbox" :value="clave" />
                            {{ etiqueta }}
                        </label>
                        <p v-if="form.errors.permisos" role="alert">{{ form.errors.permisos }}</p>
                    </fieldset>
                    <fieldset>
                        <legend>Asignaturas de los reportes</legend>
                        <label>
                            <input v-model="limitarAsignaturas" type="checkbox" />
                            Limitar a las asignaturas seleccionadas
                        </label>
                        <div v-if="limitarAsignaturas" class="asignaturas-list">
                            <label v-for="asignatura in asignaturas" :key="asignatura.id_asignatura">
                                <input v-model="form.asignaturas_reportes" type="checkbox" :value="asignatura.id_asignatura" />
                                {{ asignatura.nombre_asignatura }}
                            </label>
                            <p v-if="!asignaturas.length">Todavía no hay asignaturas registradas.</p>
                        </div>
                        <p v-else>Todas las asignaturas.</p>
                        <p v-if="form.errors.asignaturas_reportes" role="alert">{{ form.errors.asignaturas_reportes }}</p>
                    </fieldset>
                    <div class="role-actions">
                        <Button type="button" variant="action" @click="mostrarFormulario = false">Cancelar</Button>
                        <Button type="submit" variant="primary" :disabled="form.processing">Guardar rol</Button>
                    </div>
                </form>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped src="../../../../css/panels.css"></style>
<style scoped>
.role-list, .role-form { display: grid; gap: 1rem; }
.role-list { list-style: none; padding: 0; margin-top: 1rem; }
.role-card { display: flex; justify-content: space-between; align-items: start; gap: 1rem; padding: 1rem; background: white; border: 1px solid var(--border-light); border-radius: 0.5rem; }
.role-card h2 { margin: 0; color: var(--color-primary); font-size: 1rem; }
.role-card p, .role-form fieldset p { margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem; }
.role-form fieldset { display: grid; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 0.5rem; }
.role-form legend { padding: 0 0.25rem; }
.asignaturas-list { display: grid; gap: 0.35rem; max-height: 11rem; overflow-y: auto; }
.role-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 0.5rem; }
</style>
