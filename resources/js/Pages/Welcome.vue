<script setup>
import { Head, Link } from '@inertiajs/vue3';
import '../../css/landing.css'; 
</script>

<template>
    <Head title="Inicio - Sistema de Control" />

    <div class="landing-body">
        <header class="landing-header">
            <div class="logo-container">
                <div class="logo-box">CIE</div>
                <div class="logo-text">
                    <span class="logo-title">Sistema de Control de Ingreso</span>
                    <span class="logo-subtitle">UMSS</span>
                </div>
            </div>
            <Link href="/login" class="btn-login">Iniciar sesión</Link>
        </header>

        <main class="landing-main">
            <span class="overline">Control de acceso universitario</span>
            <h1 class="landing-title">Control de Ingreso a Exámenes Masivos</h1>
            <p class="landing-description">
                Plataforma institucional para la verificación de habilitaciones y el registro de ingreso a exámenes universitarios de gran escala.
            </p>
            
            <div class="actions-container">
                <Link href="/login" class="btn-primary">Acceso para personal</Link>
                <a href="#" class="btn-secondary">Soy estudiante &rarr;</a>
            </div>
        </main>
    </div>
</template>