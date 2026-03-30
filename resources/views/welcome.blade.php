<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name') }} — plataforma en línea.">

        <title>{{ config('app.name', 'Serviconli') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:600,700|dm-sans:400,500,600,700" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                --ink: #0c1210;
                --muted: #4a5754;
                --paper: #f4f1ea;
                --accent: #0d6b5c;
                --accent-soft: rgba(13, 107, 92, 0.12);
                --line: rgba(12, 18, 16, 0.08);
                --shadow: 0 24px 48px -12px rgba(12, 18, 16, 0.12);
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body.landing {
                min-height: 100vh;
                font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
                color: var(--ink);
                background: var(--paper);
                -webkit-font-smoothing: antialiased;
            }

            .landing__bg {
                position: fixed;
                inset: 0;
                pointer-events: none;
                background:
                    radial-gradient(ellipse 80% 50% at 20% -10%, rgba(13, 107, 92, 0.18), transparent 55%),
                    radial-gradient(ellipse 60% 40% at 100% 0%, rgba(180, 140, 90, 0.15), transparent 50%),
                    linear-gradient(180deg, #ebe6dc 0%, var(--paper) 38%);
            }

            .landing__wrap {
                position: relative;
                max-width: 68rem;
                margin: 0 auto;
                padding: clamp(1.5rem, 4vw, 2.5rem) clamp(1.25rem, 4vw, 2rem) 3rem;
            }

            .landing__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: clamp(3rem, 12vw, 7rem);
            }

            .landing__brand {
                font-family: 'Fraunces', Georgia, serif;
                font-weight: 700;
                font-size: 1.25rem;
                letter-spacing: -0.02em;
                color: var(--ink);
                text-decoration: none;
            }

            .landing__nav {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .landing__nav a {
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                color: var(--muted);
                padding: 0.5rem 0.85rem;
                border-radius: 0.375rem;
                border: 1px solid transparent;
                transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
            }

            .landing__nav a:hover {
                color: var(--ink);
                border-color: var(--line);
                background: rgba(255, 255, 255, 0.5);
            }

            .landing__nav-cta {
                color: #fff !important;
                background: var(--accent);
                border-color: var(--accent);
            }

            .landing__nav-cta:hover {
                filter: brightness(1.06);
                border-color: var(--accent);
            }

            .landing__hero {
                display: grid;
                gap: clamp(2rem, 6vw, 3.5rem);
                align-items: center;
            }

            @media (min-width: 56rem) {
                .landing__hero {
                    grid-template-columns: 1.1fr 0.9fr;
                }
            }

            .landing__eyebrow {
                display: inline-block;
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--accent);
                margin-bottom: 1rem;
            }

            .landing__title {
                font-family: 'Fraunces', Georgia, serif;
                font-weight: 700;
                font-size: clamp(2.25rem, 5vw, 3.25rem);
                line-height: 1.12;
                letter-spacing: -0.03em;
                margin-bottom: 1.25rem;
            }

            .landing__lead {
                font-size: 1.125rem;
                line-height: 1.65;
                color: var(--muted);
                max-width: 32rem;
                margin-bottom: 2rem;
            }

            .landing__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
            }

            .landing__status {
                margin-top: 1rem;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.85rem;
                color: var(--muted);
                background: rgba(255, 255, 255, 0.65);
                border: 1px solid var(--line);
                border-radius: 0.5rem;
                padding: 0.45rem 0.65rem;
            }

            .landing__status-dot {
                width: 0.55rem;
                height: 0.55rem;
                border-radius: 999px;
                background: #0fad87;
                box-shadow: 0 0 0 4px rgba(15, 173, 135, 0.18);
                flex-shrink: 0;
            }

            .landing__status a {
                color: var(--accent);
                text-decoration: none;
                font-weight: 600;
            }

            .landing__status a:hover {
                text-decoration: underline;
            }

            .landing__btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                font-size: 0.9375rem;
                font-weight: 600;
                padding: 0.75rem 1.35rem;
                border-radius: 0.5rem;
                text-decoration: none;
                transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
            }

            .landing__btn:hover {
                transform: translateY(-1px);
            }

            .landing__btn--solid {
                color: #fff;
                background: var(--accent);
                box-shadow: var(--shadow);
            }

            .landing__btn--solid:hover {
                filter: brightness(1.05);
            }

            .landing__btn--ghost {
                color: var(--ink);
                background: rgba(255, 255, 255, 0.65);
                border: 1px solid var(--line);
            }

            .landing__btn--ghost:hover {
                border-color: rgba(12, 18, 16, 0.15);
            }

            .landing__panel {
                background: rgba(255, 255, 255, 0.72);
                border: 1px solid var(--line);
                border-radius: 1rem;
                padding: 1.75rem;
                box-shadow: var(--shadow);
                backdrop-filter: blur(8px);
            }

            .landing__panel h2 {
                font-family: 'Fraunces', Georgia, serif;
                font-size: 1.125rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .landing__list {
                list-style: none;
                display: flex;
                flex-direction: column;
                gap: 0.85rem;
            }

            .landing__list li {
                display: flex;
                gap: 0.75rem;
                align-items: flex-start;
                font-size: 0.9375rem;
                line-height: 1.5;
                color: var(--muted);
            }

            .landing__list li::before {
                content: '';
                flex-shrink: 0;
                width: 0.5rem;
                height: 0.5rem;
                margin-top: 0.4rem;
                border-radius: 999px;
                background: var(--accent);
                box-shadow: 0 0 0 4px var(--accent-soft);
            }

            .landing__footer {
                margin-top: clamp(4rem, 14vw, 6rem);
                padding-top: 1.5rem;
                border-top: 1px solid var(--line);
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem 1.5rem;
                align-items: center;
                justify-content: space-between;
                font-size: 0.8125rem;
                color: var(--muted);
            }

            .landing__footer a {
                color: var(--accent);
                text-decoration: none;
                font-weight: 500;
            }

            .landing__footer a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body class="landing">
        <div class="landing__bg" aria-hidden="true"></div>

        <div class="landing__wrap">
            <header class="landing__header">
                <a class="landing__brand" href="{{ url('/') }}">{{ config('app.name', 'Serviconli') }}</a>

                <nav class="landing__nav" aria-label="Principal">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('mis-afiliados') }}">Mis afiliados</a>
                        @else
                            <a href="{{ route('login') }}">Iniciar sesión</a>
                            @if (Route::has('register'))
                                <a class="landing__nav-cta" href="{{ route('register') }}">Registrarse</a>
                            @endif
                        @endauth
                    @endif
                </nav>
            </header>

            <main class="landing__hero">
                <div>
                    <span class="landing__eyebrow">En línea</span>
                    <h1 class="landing__title">Bienvenido a {{ config('app.name', 'Serviconli') }}</h1>
                    <p class="landing__lead">
                        Tu aplicación está activa en este entorno. Desde aquí podrás ampliar rutas, paneles y API según lo necesites el proyecto.
                    </p>
                    <div class="landing__actions">
                        @if (Route::has('login') && ! auth()->check())
                            <a class="landing__btn landing__btn--solid" href="{{ route('login') }}">Acceder</a>
                        @endif
                        <a class="landing__btn landing__btn--ghost" href="https://laravel.com/docs" target="_blank" rel="noopener noreferrer">
                            Documentación Laravel
                        </a>
                    </div>
                    <div class="landing__status" role="status" aria-live="polite">
                        <span class="landing__status-dot" aria-hidden="true"></span>
                        <span>Despliegue activo en</span>
                        <a href="https://serviconli-main-kxt1bq.free.laravel.cloud/" target="_blank" rel="noopener noreferrer">
                            Laravel Cloud
                        </a>
                    </div>
                </div>

                <aside class="landing__panel">
                    <h2>Siguientes pasos</h2>
                    <ul class="landing__list">
                        <li>Define <strong>APP_URL</strong> y el resto de variables en el panel de Laravel Cloud.</li>
                        <li>Ejecuta migraciones y optimiza con <code style="font-size:0.85em;background:rgba(13,107,92,0.1);padding:0.1em 0.35em;border-radius:0.25em;">php artisan migrate --force</code> en despliegue.</li>
                        <li>Añade autenticación o Breeze/Jetstream si necesitas registro y panel de usuario.</li>
                    </ul>
                </aside>
            </main>

            <footer class="landing__footer">
                <span>Laravel {{ app()->version() }}</span>
                <span>
                    <a href="https://laravel.com/docs" target="_blank" rel="noopener noreferrer">Docs</a>
                    ·
                    <a href="https://laracasts.com" target="_blank" rel="noopener noreferrer">Laracasts</a>
                </span>
            </footer>
        </div>
    </body>
</html>
