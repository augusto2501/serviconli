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
        .svc-details-nav > summary { list-style: none; }
        .svc-details-nav > summary::-webkit-details-marker { display: none; }
        /* Sidebar colapsada: ocultar etiquetas, mostrar solo iconos */
        .svc-sidebar-collapsed .svc-label { display: none !important; }
        .svc-sidebar-collapsed .svc-sidebar-logo-long { display: none !important; }
        .svc-sidebar-collapsed .svc-sidebar-logo-short { display: inline !important; }
        .svc-sidebar { transition: width 250ms ease; }
    </style>
    @php
        $svcCurrentPage = request()->path();
        $svcNavItems = [
            [
                'label'  => 'Dashboard',
                'url'    => url('/dashboard'),
                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6m-6 0v6m-4-6H5m4 6H5"/>',
                'active' => $svcCurrentPage === 'dashboard',
            ],
            [
                'section' => 'Afiliados',
                'items'   => [
                    ['label' => 'Mis afiliados',   'url' => url('/mis-afiliados'),  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6 5.87a4 4 0 10-8 0m12-8a4 4 0 10-8 0"/>'],
                    ['label' => 'Incapacidades',   'url' => url('/incapacidades'),  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                ],
            ],
            [
                'section' => 'PILA',
                'items'   => [
                    ['label' => 'Liquidación lotes', 'url' => url('/liquidacion-lotes'), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>'],
                    ['label' => 'Archivo PILA',      'url' => url('/generar-pila'),       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>'],
                ],
            ],
            [
                'section' => 'Facturación',
                'items'   => [
                    ['label' => 'Cartera y recibos', 'url' => url('/cartera'),      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/>'],
                    ['label' => 'Cuadre de caja',    'url' => url('/cuadre-caja'), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'],
                ],
            ],
            [
                'section' => 'Comercial',
                'items'   => [
                    ['label' => 'Asesores',      'url' => url('/asesores'),    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                    ['label' => 'Comisiones',    'url' => url('/comisiones'),  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    ['label' => 'Terceros',      'url' => url('/terceros'),    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>'],
                ],
            ],
            [
                'section' => 'Documentos',
                'items'   => [
                    ['label' => 'Contratos PDF', 'url' => url('/documentos'), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
                ],
            ],
        ];
    @endphp
</head>
<body class="svc-app bg-[#f0ece3] text-stone-900 antialiased" data-page="@yield('page')">
<div class="flex min-h-screen">

    {{-- ============================================================
         SIDEBAR ESCRITORIO
    ============================================================= --}}
    <aside
        id="svc-sidebar"
        class="svc-sidebar hidden w-60 flex-col border-r border-stone-200/90 bg-white lg:flex"
        style="min-height:100vh"
    >
        {{-- Logo --}}
        <div class="flex h-14 shrink-0 items-center gap-3 border-b border-stone-200/80 px-5">
            <a href="{{ url('/') }}" class="font-serif-svc svc-sidebar-logo-long truncate text-base font-bold leading-tight text-stone-900 no-underline">
                {{ config('app.name') }}
            </a>
            <a href="{{ url('/') }}" class="svc-sidebar-logo-short hidden font-serif-svc text-lg font-bold text-teal-800 no-underline">S</a>
        </div>

        {{-- Navegación (oculta hasta confirmar autenticación vía JS) --}}
        <nav id="svc-sidebar-nav" class="flex-1 overflow-y-auto px-3 py-4 hidden" aria-label="Sidebar">
            @foreach ($svcNavItems as $group)
                @if (isset($group['url']))
                    {{-- Enlace directo (Dashboard) --}}
                    <a
                        href="{{ $group['url'] }}"
                        class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition
                            {{ $group['active'] ?? false
                                ? 'bg-teal-800 text-white shadow-sm'
                                : 'text-stone-700 hover:bg-teal-50 hover:text-teal-950' }}"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">{!! $group['icon'] !!}</svg>
                        <span class="svc-label">{{ $group['label'] }}</span>
                    </a>
                @else
                    {{-- Sección con sub-ítems --}}
                    <p class="svc-label mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-widest text-stone-400 first:mt-0">
                        {{ $group['section'] }}
                    </p>
                    @foreach ($group['items'] as $item)
                        @php
                            $isActive = rtrim(request()->getRequestUri(), '/') === rtrim(parse_url($item['url'], PHP_URL_PATH), '/');
                        @endphp
                        <a
                            href="{{ $item['url'] }}"
                            class="mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition
                                {{ $isActive
                                    ? 'bg-teal-50 font-semibold text-teal-900'
                                    : 'font-medium text-stone-700 hover:bg-stone-100 hover:text-teal-900' }}"
                        >
                            <svg class="h-4.5 h-[1.125rem] w-[1.125rem] shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">{!! $item['icon'] !!}</svg>
                            <span class="svc-label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        {{-- Footer del sidebar: sesión --}}
        <div id="svc-sidebar-footer" class="shrink-0 border-t border-stone-200/80 px-3 py-3 hidden">
            <a id="svc-nav-login" href="{{ route('login') }}" class="mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100 hover:text-teal-900">
                <svg class="h-[1.125rem] w-[1.125rem] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                <span class="svc-label">Iniciar sesión</span>
            </a>
            <button id="svc-nav-logout" type="button" class="hidden w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 transition hover:bg-red-50 hover:text-red-800">
                <svg class="h-[1.125rem] w-[1.125rem] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span class="svc-label">Cerrar sesión</span>
            </button>
        </div>
    </aside>

    {{-- ============================================================
         COLUMNA PRINCIPAL
    ============================================================= --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Topbar (solo en móvil y como barra de utilidades en escritorio) --}}
        <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between border-b border-stone-200/80 bg-white/95 px-4 backdrop-blur-sm">
            {{-- Hamburguesa (solo móvil, oculta hasta confirmar auth) --}}
            <button
                id="svc-menu-toggle"
                type="button"
                class="hidden items-center justify-center rounded-lg p-2 text-stone-700 transition hover:bg-stone-100 lg:hidden"
                aria-controls="svc-drawer"
                aria-expanded="false"
                aria-label="Abrir menú"
            >
                <svg id="svc-icon-menu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="svc-icon-close" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            {{-- Título de la página actual --}}
            <span class="font-serif-svc hidden text-base font-semibold text-stone-800 lg:inline">@yield('title', config('app.name'))</span>
            <span class="font-serif-svc text-sm font-semibold text-stone-800 lg:hidden">{{ config('app.name') }}</span>

            <div class="flex items-center gap-2">
                <a id="svc-nav-login-top" href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100 lg:hidden">
                    Iniciar sesión
                </a>
                <button id="svc-nav-logout-top" type="button" class="hidden rounded-lg px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100 lg:hidden">
                    Salir
                </button>
            </div>
        </header>

        {{-- Drawer móvil (overlay) --}}
        <div id="svc-overlay" class="fixed inset-0 z-40 bg-stone-900/50 backdrop-blur-sm lg:hidden hidden"></div>
        <aside
            id="svc-drawer"
            class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full overflow-y-auto border-r border-stone-200 bg-white transition-transform duration-250 lg:hidden"
        >
            <div class="flex h-14 items-center justify-between border-b border-stone-200/80 px-5">
                <a href="{{ url('/') }}" class="font-serif-svc truncate text-base font-bold text-stone-900 no-underline">{{ config('app.name') }}</a>
                <button id="svc-drawer-close" type="button" class="rounded-lg p-1.5 text-stone-500 hover:bg-stone-100" aria-label="Cerrar menú">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav id="svc-drawer-nav" class="px-3 py-4" aria-label="Drawer">
                @foreach ($svcNavItems as $group)
                    @if (isset($group['url']))
                        <a href="{{ $group['url'] }}" class="mb-1 flex items-center gap-3 rounded-xl bg-teal-800 px-3 py-2.5 text-sm font-semibold text-white">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $group['icon'] !!}</svg>
                            {{ $group['label'] }}
                        </a>
                    @else
                        <p class="mb-1 mt-5 px-3 text-[10px] font-bold uppercase tracking-widest text-stone-400 first:mt-0">{{ $group['section'] }}</p>
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-700 hover:bg-teal-50 hover:text-teal-950">
                                <svg class="h-[1.125rem] w-[1.125rem] shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $item['icon'] !!}</svg>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
                <div class="mt-6 border-t border-stone-200 pt-4">
                    <a id="svc-nav-login-drawer" href="{{ route('login') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-100">
                        <svg class="h-[1.125rem] w-[1.125rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
                        Iniciar sesión
                    </a>
                    <button id="svc-nav-logout-drawer" type="button" class="hidden w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-red-50 hover:text-red-800">
                        <svg class="h-[1.125rem] w-[1.125rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
                        Cerrar sesión
                    </button>
                </div>
            </nav>
        </aside>

        {{-- Contenido principal --}}
        <main class="flex-1 overflow-auto px-4 py-6 sm:px-6 sm:py-8">
            <div class="mx-auto max-w-5xl">
                @yield('content')
            </div>
        </main>
    </div>
</div>

<script>
(function () {
    var t = sessionStorage.getItem('serviconli_api_token');

    /* ── Elementos de navegación ── */
    var sidebarNav    = document.getElementById('svc-sidebar-nav');
    var sidebarFooter = document.getElementById('svc-sidebar-footer');
    var drawerNav     = document.getElementById('svc-drawer-nav');
    var toggle        = document.getElementById('svc-menu-toggle');

    /* ── Mostrar/ocultar toda la interfaz autenticada ── */
    function setAuth(authed) {
        /* Mostrar/ocultar la navegación completa del sidebar y drawer */
        [sidebarNav, sidebarFooter].forEach(function(el) {
            if (el) el.classList.toggle('hidden', !authed);
        });
        /* El botón hamburguesa solo aparece si hay sesión */
        if (toggle) {
            toggle.classList.toggle('hidden', !authed);
            toggle.classList.toggle('inline-flex', authed);
        }

        /* Botones login / logout (sidebar + topbar + drawer) */
        ['svc-nav-login', 'svc-nav-login-top', 'svc-nav-login-drawer'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', authed);
        });
        ['svc-nav-logout', 'svc-nav-logout-top', 'svc-nav-logout-drawer'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !authed);
        });
    }

    function doLogout() {
        fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + t } }).catch(function(){});
        sessionStorage.removeItem('serviconli_api_token');
        window.location.href = '/login';
    }

    /* Estado inicial según token */
    setAuth(!!t);

    /* Listeners de logout */
    ['svc-nav-logout', 'svc-nav-logout-top', 'svc-nav-logout-drawer'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', doLogout);
    });

    /* ── Drawer móvil ── */
    var overlay  = document.getElementById('svc-overlay');
    var drawer   = document.getElementById('svc-drawer');
    var iconMenu  = document.getElementById('svc-icon-menu');
    var iconClose = document.getElementById('svc-icon-close');
    var closeBtn  = document.getElementById('svc-drawer-close');

    function openDrawer() {
        if (!drawer || !overlay) return;
        drawer.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        if (iconMenu)  iconMenu.classList.add('hidden');
        if (iconClose) iconClose.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        if (!drawer || !overlay) return;
        drawer.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (iconMenu)  iconMenu.classList.remove('hidden');
        if (iconClose) iconClose.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (toggle)   toggle.addEventListener('click', openDrawer);
    if (overlay)  overlay.addEventListener('click', closeDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
})();
</script>
</body>
</html>
