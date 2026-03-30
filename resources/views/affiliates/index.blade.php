@extends('layouts.serviconli')

@section('page', 'mis-afiliados')

@section('title', 'Mis afiliados — '.config('app.name'))

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Mis afiliados</h1>
                <p class="text-sm text-stone-600 mt-1">Listado alineado con la API (<code class="text-xs bg-stone-200/80 px-1 rounded">GET /api/affiliates</code>). RF-020.</p>
            </div>
            <button type="button" id="btn-logout"
                class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50">
                Cerrar sesión
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-stone-200/90 bg-white/90 p-4 shadow-sm">
            <label class="sr-only" for="filter-q">Búsqueda</label>
            <input type="search" id="filter-q" placeholder="Documento o nombre…"
                class="min-w-[12rem] flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-600" />
            <button type="button" id="btn-search"
                class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">
                Buscar
            </button>
            <div class="flex flex-wrap gap-2 border-l border-stone-200 pl-3 ml-1">
                <button type="button" id="btn-export-csv"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 hover:bg-stone-50">
                    Exportar CSV
                </button>
                <button type="button" id="btn-export-xlsx"
                    class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 hover:bg-stone-50">
                    Exportar Excel
                </button>
            </div>
        </div>

        <p id="affiliates-meta" class="text-sm text-stone-500"></p>

        <div class="overflow-x-auto rounded-xl border border-stone-200/90 bg-white/90 shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-stone-200 bg-stone-100/80 text-stone-600">
                        <th class="px-4 py-3 font-semibold">Documento</th>
                        <th class="px-4 py-3 font-semibold">Nombre</th>
                        <th class="px-4 py-3 font-semibold">Tipo cliente</th>
                        <th class="px-4 py-3 font-semibold">Mora</th>
                        <th class="px-4 py-3 font-semibold">EPS</th>
                        <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="affiliates-tbody">
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-stone-500">Cargando…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
