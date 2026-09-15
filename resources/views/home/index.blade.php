<!-- resources/views/home/index.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Control de Ingreso - UMSS</title>
    
    <!-- Aquí aseguramos que Vite inyecte tu app.css sin romper la arquitectura -->
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="landing-body"> <!-- Clase de control general para el app.css -->

    <header class="landing-header">
        <div class="logo-container">
            <div class="logo-box">CIE</div>
            <div class="logo-text">
                <span class="logo-title">Sistema de Control de Ingreso</span>
                <span class="logo-subtitle">Universidad Nacional</span>
            </div>
        </div>
        <!-- Enlace dinámico al login de Laravel -->
        <a href="{{ route('login') }}" class="btn-login">Iniciar sesión</a>
    </header>

    <main class="landing-main">
        <span class="overline">Control de acceso universitario</span>
        <h1 class="landing-title">Control de Ingreso a Exámenes Masivos</h1>
        <p class="landing-description">
            Plataforma institucional para la verificación de habilitaciones y el registro de ingreso a exámenes universitarios de gran escala.
        </p>
        
        <div class="actions-container">
            <a href="{{ route('login') }}" class="btn-primary">Acceso para personal</a>
            <!-- Este botón puede apuntar a una futura ruta para estudiantes -->
            <a href="#" class="btn-secondary">Soy estudiante &rarr;</a>
        </div>
    </main>

</body>
</html>