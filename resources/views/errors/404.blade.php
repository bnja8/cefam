<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada | Farmacia Asistencial</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('logo.jpg') }}?v={{ filemtime(public_path('logo.jpg')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
</head>
<body class="login-page">
    <main class="login-card">
        <div class="login-brand">
            <span class="sidebar-logo-icon"><img src="{{ asset('logo.jpg') }}" alt=""></span>
            <div><strong>Farmacia Asistencial</strong><small>Atención Primaria de Salud · APS</small></div>
        </div>
        <h1>NO SE ENCONTRÓ LA PÁGINA</h1>
        <p class="muted">La dirección que ingresaste no existe o ya no está disponible.</p>
        <a class="btn btn-primary btn-block" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
            Volver al inicio
        </a>
    </main>
</body>
</html>
