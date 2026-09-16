<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Panel de Estudiante" />
    <AuthenticatedLayout>
        <div class="p-6 text-gray-900">
            <h1>Bienvenido a tu panel de estudiante</h1>
        </div>
    </AuthenticatedLayout>
</template>