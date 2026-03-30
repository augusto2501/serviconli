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
            <nav class="flex flex-wrap items-center gap-4 text-sm font-medium" aria-label="Aplicación">
                <a href="{{ url('/mis-afiliados') }}" class="text-teal-800 hover:underline">Mis afiliados</a>
                <a href="{{ route('login') }}" class="text-stone-600 hover:text-teal-800">Iniciar sesión</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
