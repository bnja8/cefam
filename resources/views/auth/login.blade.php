<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Farmacia Asistencial</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('logo.jpg') }}?v={{ filemtime(public_path('logo.jpg')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
</head>
<body class="login-page">
    @include('partials.flash')
    <main class="login-card">
        <div class="login-brand"><span class="sidebar-logo-icon"><img src="{{ asset('logo.jpg') }}" alt=""></span><div><strong>Farmacia Asistencial</strong><small>Atención Primaria de Salud · APS</small></div></div>
        <h1>Bienvenido</h1>
        <p class="muted">Ingresa con tu cuenta institucional para continuar.</p>
        <form method="post" action="{{ route('login.store') }}" class="stack-form">
            @csrf
            <div class="form-group"><label for="rut">RUT</label><input class="form-control" id="rut" name="rut" value="{{ old('rut') }}" placeholder="12.345.678-9" required autofocus autocomplete="username" data-rut-input aria-describedby="rut-error"><small id="rut-error" class="field-error" data-rut-error aria-live="polite"></small></div>
            <div class="form-group"><label for="password">Contraseña</label><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password"></div>
            <button class="btn btn-primary btn-block" type="submit">Iniciar sesión <i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <p class="login-footnote">Acceso exclusivo para personal autorizado.</p>
    </main>
</body>
</html>
