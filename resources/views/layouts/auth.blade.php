<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/serviconli-vue-app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:400,600,700|dm-sans:400,500,600,700" rel="stylesheet" />
    <style>
        body.svc-auth { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }
        .font-serif-svc { font-family: 'Fraunces', Georgia, serif; }
    </style>
</head>
<body class="svc-auth min-h-screen bg-[#f0ece3] text-stone-900 antialiased" data-page="@yield('page')">
    @yield('content')
</body>
</html>
