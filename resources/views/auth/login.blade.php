@extends('layouts.serviconli')

@section('page', 'login')

@section('title', 'Iniciar sesión — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="font-serif-svc text-2xl font-bold text-stone-900 mb-2">Iniciar sesión</h1>
        <p class="text-sm text-stone-600 mb-6">Accede con el mismo usuario que usa la API (Sanctum).</p>

        <form id="login-form" class="rounded-xl border border-stone-200/90 bg-white/90 p-6 shadow-sm space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-stone-700 mb-1">Correo</label>
                <input type="email" name="email" id="email" required autocomplete="username"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 shadow-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600" />
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-stone-700 mb-1">Contraseña</label>
                <input type="password" name="password" id="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 shadow-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600" />
            </div>
            <p id="login-error" class="hidden text-sm text-red-700" role="alert"></p>
            <button type="submit"
                class="w-full rounded-lg bg-teal-800 py-2.5 text-sm font-semibold text-white shadow hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">
                Entrar
            </button>
        </form>
        <p class="mt-6 text-center text-xs text-stone-500">
            <a href="{{ url('/') }}" class="text-teal-800 hover:underline">Volver al inicio</a>
        </p>
    </div>
@endsection
