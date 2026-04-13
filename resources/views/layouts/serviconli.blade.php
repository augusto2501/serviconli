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
        /* Ocultar marcador nativo de <summary> en menús desplegables */
        .svc-details-nav > summary { list-style: none; }
        .svc-details-nav > summary::-webkit-details-marker { display: none; }
    </style>
    @php
        $svcNavQuick = [
            ['label' => 'Dashboard', 'url' => url('/dashboard'), 'hint' => 'Indicadores y reportes'],
        ];
        $svcNavGroups = [
            [
                'label' => 'Afiliados',
                'items' => [
                    ['label' => 'Mis afiliados', 'url' => url('/mis-afiliados')],
                    ['label' => 'Incapacidades', 'url' => url('/incapacidades')],
                ],
            ],
            [
                'label' => 'PILA',
                'items' => [
                    ['label' => 'Liquidación por lotes', 'url' => url('/liquidacion-lotes')],
                    ['label' => 'Archivo planilla PILA', 'url' => url('/generar-pila')],
                ],
            ],
            [
                'label' => 'Facturación',
                'items' => [
                    ['label' => 'Cartera y recibos', 'url' => url('/cartera')],
                    ['label' => 'Cuadre de caja', 'url' => url('/cuadre-caja')],
                ],
            ],
            [
                'label' => 'Comercial',
                'items' => [
                    ['label' => 'Asesores', 'url' => url('/asesores')],
                    ['label' => 'Comisiones', 'url' => url('/comisiones')],
                    ['label' => 'Terceros y consignaciones', 'url' => url('/terceros')],
                ],
            ],
        ];
        $svcNavDocs = [
            ['label' => 'Contratos y certificados', 'url' => url('/documentos'), 'hint' => 'PDF / RF-103'],
        ];
    @endphp
</head>
<body class="svc-app min-h-screen bg-[#f4f1ea] text-stone-900 antialiased" data-page="@yield('page')">
    <header class="sticky top-0 z-40 border-b border-stone-200/90 bg-white/95 shadow-sm backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 lg:py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ url('/') }}" class="font-serif-svc shrink-0 text-lg font-bold tracking-tight text-stone-900 no-underline sm:text-xl">
                    {{ config('app.name') }}
                </a>
                <span class="hidden text-xs font-medium uppercase tracking-wider text-stone-400 sm:inline">Operaciones</span>
            </div>

            {{-- Escritorio: menú por grupos con hover --}}
            <nav class="hidden flex-1 items-center justify-center gap-1 lg:flex" aria-label="Principal">
                @foreach ($svcNavQuick as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="shrink-0 rounded-lg bg-teal-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @foreach ($svcNavGroups as $group)
                    <div class="relative group/nav">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100 hover:text-teal-900"
                            aria-expanded="false"
                            aria-haspopup="true"
                        >
                            {{ $group['label'] }}
                            <svg class="h-4 w-4 text-stone-500 transition group-hover/nav:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            class="invisible absolute left-0 top-full z-50 min-w-[15.5rem] origin-top scale-95 pt-2 opacity-0 transition-all duration-150 group-hover/nav:visible group-hover/nav:scale-100 group-hover/nav:opacity-100"
                            role="menu"
                        >
                            {{-- pt-2 actúa como puente para mantener el hover al bajar al submenú --}}
                            <div class="rounded-xl border border-stone-200/90 bg-white py-2 shadow-xl ring-1 ring-stone-900/5">
                                @foreach ($group['items'] as $sub)
                                    <a
                                        href="{{ $sub['url'] }}"
                                        class="block px-4 py-2.5 text-sm text-stone-700 transition hover:bg-teal-50 hover:text-teal-950"
                                        role="menuitem"
                                    >
                                        {{ $sub['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach ($svcNavDocs as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="shrink-0 rounded-lg border border-stone-300 bg-white px-3.5 py-2 text-sm font-medium text-stone-800 transition hover:border-teal-700 hover:bg-teal-50/80 hover:text-teal-900"
                        title="{{ $item['hint'] ?? '' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <a
                    id="svc-nav-login"
                    href="{{ route('login') }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100 hover:text-teal-900"
                >
                    Iniciar sesión
                </a>
                <button
                    id="svc-nav-logout"
                    type="button"
                    class="hidden rounded-lg px-3 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100 hover:text-teal-900"
                >
                    Cerrar sesión
                </button>
                <button
                    id="svc-menu-toggle"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-stone-300 bg-white p-2 text-stone-800 shadow-sm lg:hidden"
                    aria-controls="svc-mobile-menu"
                    aria-expanded="false"
                    aria-label="Abrir menú"
                >
                    <svg id="svc-icon-menu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg id="svc-icon-close" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        {{-- Móvil / tablet: panel acordeón --}}
        <div id="svc-mobile-menu" class="hidden border-t border-stone-200 bg-[#faf8f4] lg:hidden">
            <nav class="mx-auto max-w-7xl space-y-1 px-4 py-4" aria-label="Principal móvil">
                @foreach ($svcNavQuick as $item)
                    <a href="{{ $item['url'] }}" class="block rounded-xl bg-teal-800 px-4 py-3 text-center text-sm font-semibold text-white">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @foreach ($svcNavGroups as $group)
                    <details class="svc-details-nav group rounded-xl border border-stone-200 bg-white open:shadow-sm">
                        <summary class="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-semibold text-stone-800">
                            {{ $group['label'] }}
                            <svg class="h-5 w-5 text-stone-500 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </summary>
                        <div class="border-t border-stone-100 pb-2 pt-1">
                            @foreach ($group['items'] as $sub)
                                <a href="{{ $sub['url'] }}" class="block px-4 py-2.5 text-sm text-stone-700 hover:bg-teal-50 hover:text-teal-950">
                                    {{ $sub['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach

                @foreach ($svcNavDocs as $item)
                    <a href="{{ $item['url'] }}" class="block rounded-xl border border-stone-300 bg-white px-4 py-3 text-center text-sm font-medium text-stone-800">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>
    <script>
        (function () {
            var t = sessionStorage.getItem('serviconli_api_token');
            var login = document.getElementById('svc-nav-login');
            var logout = document.getElementById('svc-nav-logout');
            if (login && logout) {
                if (t) {
                    login.classList.add('hidden');
                    logout.classList.remove('hidden');
                }
                logout.addEventListener('click', function () {
                    sessionStorage.removeItem('serviconli_api_token');
                    fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + t } }).catch(function () {});
                    window.location.href = '/login';
                });
            }

            var toggle = document.getElementById('svc-menu-toggle');
            var panel = document.getElementById('svc-mobile-menu');
            var iconMenu = document.getElementById('svc-icon-menu');
            var iconClose = document.getElementById('svc-icon-close');
            if (toggle && panel) {
                toggle.addEventListener('click', function () {
                    panel.classList.toggle('hidden');
                    var open = !panel.classList.contains('hidden');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
                    if (iconMenu && iconClose) {
                        iconMenu.classList.toggle('hidden', open);
                        iconClose.classList.toggle('hidden', !open);
                    }
                });
            }
        })();
    </script>
    <main class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
