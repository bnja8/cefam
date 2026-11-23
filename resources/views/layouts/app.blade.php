<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Farmacia Asistencial' }} | CESFAM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
    <link rel="icon" type="image/jpeg" href="{{ asset('logo.jpg') }}?v={{ filemtime(public_path('logo.jpg')) }}">
</head>

<body class="{{ request()->routeIs('delivery-orders.label', 'delivery-orders.labels') ? 'print-delivery-label' : (request()->routeIs('delivery-orders.receipt') ? 'print-delivery-order' : (request()->routeIs('recipes.print') ? 'print-prescription' : '')) }}">
    <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
    <div class="app-container">
        <button class="sidebar-backdrop" type="button" aria-label="Cerrar menú de navegación" hidden></button>
        @include('partials.sidebar')
        <main class="main-content" id="main-content" tabindex="-1">
            @include('partials.topbar')
            <div class="content-body">
                @include('partials.flash')
                @yield('content')
            
            
            </div>
        </main>
    </div>
</body>
</html>
