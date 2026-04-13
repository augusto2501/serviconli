<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/serviconli-vue-app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:600,700|dm-sans:400,500,600,700" rel="stylesheet" />
    <style>
        body.svc-app { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }
        .font-serif-svc { font-family: 'Fraunces', Georgia, serif; }
    </style>
</head>
<body class="svc-app min-h-screen bg-[#f4f1ea] text-stone-900 antialiased" data-page="@yield('page')">
    <header class="border-b border-stone-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
            <a href="{{ url('/') }}" class="font-serif-svc text-xl font-bold text-stone-900 no-underline">{{ config('app.name') }}</a>
            <nav class="flex flex-wrap items-center gap-3 text-sm font-medium" aria-label="Aplicación">
                <a href="{{ url('/dashboard') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Dashboard</a>
                <a href="{{ url('/mis-afiliados') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Afiliados</a>
                <a href="{{ url('/documentos') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Documentos</a>
                <a href="{{ url('/liquidacion-lotes') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Lotes PILA</a>
                <a href="{{ url('/generar-pila') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Archivo PILA</a>
                <a href="{{ url('/cartera') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Cartera</a>
                <a href="{{ url('/cuadre-caja') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Caja</a>
                <a href="{{ url('/asesores') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Asesores</a>
                <a href="{{ url('/terceros') }}" class="rounded-lg px-2 py-1 text-teal-800 hover:bg-teal-50 hover:underline">Terceros</a>
                <span class="hidden h-4 w-px bg-stone-300 sm:inline" aria-hidden="true"></span>
                <a id="svc-nav-login" href="{{ route('login') }}" class="rounded-lg px-2 py-1 text-stone-600 hover:text-teal-800">Iniciar sesión</a>
                <button id="svc-nav-logout" type="button" class="hidden rounded-lg px-2 py-1 text-stone-600 hover:text-teal-800">Cerrar sesión</button>
            </nav>
        </div>
    </header>
    <script>
        (function () {
            var t = sessionStorage.getItem('serviconli_api_token');
            var login = document.getElementById('svc-nav-login');
            var logout = document.getElementById('svc-nav-logout');
            if (!login || !logout) return;
            if (t) {
                login.classList.add('hidden');
                logout.classList.remove('hidden');
            }
            logout.addEventListener('click', function () {
                sessionStorage.removeItem('serviconli_api_token');
                fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + t } }).catch(function () {});
                window.location.href = '/login';
            });
        })();
    </script>
    <main class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
