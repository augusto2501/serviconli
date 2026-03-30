@extends('layouts.serviconli')

@section('page', 'ficha')

@section('title', 'Ficha 360° — '.config('app.name'))

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ url('/mis-afiliados') }}" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
        </div>
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Ficha 360°</h1>
            <p class="text-sm text-stone-600 mt-1">Afiliado #{{ $affiliateId }} · RF-015 / acciones RF-016</p>
        </div>

        {{-- RF-016: accesos rápidos (destinos a conectar con PILA / certificados / novedades) --}}
        <div id="quick-actions" class="flex flex-wrap gap-3">
            <button type="button" data-quick-action="aporte"
                class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50">
                Registrar aporte
            </button>
            <button type="button" data-quick-action="cert"
                class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50">
                Generar certificado
            </button>
            <button type="button" data-quick-action="historial"
                class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50">
                Ver historial
            </button>
            <button type="button" data-quick-action="novedad"
                class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50">
                Registrar novedad
            </button>
        </div>

        <div id="ficha-root" data-affiliate-id="{{ $affiliateId }}" class="min-h-[12rem]"></div>
    </div>
@endsection
