<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiFetch, logoutAndRedirect, requireAuth } from '../api';

const q = ref('');
const loading = ref(false);
const rows = ref([]);
const meta = ref({ total: 0, currentPage: 1, lastPage: 1, perPage: 15 });

const metaText = computed(
    () => `Total: ${meta.value.total ?? 0} · Página ${meta.value.currentPage ?? 1} de ${meta.value.lastPage ?? 1}`,
);

async function load(page = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: String(meta.value.perPage || 15) });
        if (q.value.trim()) {
            params.set('q', q.value.trim());
        }
        const res = await apiFetch(`/affiliates?${params.toString()}`);
        const data = await res.json();
        rows.value = data.data || [];
        meta.value = {
            total: data.meta?.total ?? 0,
            currentPage: data.meta?.currentPage ?? 1,
            lastPage: data.meta?.lastPage ?? 1,
            perPage: data.meta?.perPage ?? 15,
        };
    } catch {
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

async function downloadExport(format) {
    const res = await apiFetch(`/affiliates/export?format=${encodeURIComponent(format)}`, {
        headers: { Accept: '*/*' },
    });
    if (!res.ok) {
        return;
    }
    const blob = await res.blob();
    const cd = res.headers.get('Content-Disposition');
    let filename = `export-afiliados.${format === 'xlsx' ? 'xlsx' : 'csv'}`;
    if (cd && cd.includes('filename=')) {
        const m = cd.match(/filename="?([^";]+)"?/);
        if (m) {
            filename = m[1];
        }
    }
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

function prevPage() {
    if (meta.value.currentPage > 1) {
        load(meta.value.currentPage - 1);
    }
}

function nextPage() {
    if (meta.value.currentPage < meta.value.lastPage) {
        load(meta.value.currentPage + 1);
    }
}

onMounted(() => {
    if (!requireAuth()) return;
    load(1);
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Mis afiliados</h1>
                <p class="mt-1 text-sm text-stone-600">Vue 3 + Tailwind · RF-020</p>
            </div>
            <button
                type="button"
                class="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                @click="logoutAndRedirect"
            >
                Cerrar sesión
            </button>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200/90 bg-white/90 p-4 shadow-sm">
            <div class="min-w-[14rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-stone-600">Documento o nombre</label>
                <input
                    v-model="q"
                    type="search"
                    class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none ring-teal-700/20 focus:border-teal-700 focus:ring-2"
                    @keyup.enter="load(1)"
                />
            </div>
            <button
                type="button"
                class="rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-900"
                @click="load(1)"
            >
                Buscar
            </button>
            <button
                type="button"
                class="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50"
                @click="downloadExport('csv')"
            >
                Exportar CSV
            </button>
            <button
                type="button"
                class="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50"
                @click="downloadExport('xlsx')"
            >
                Exportar Excel
            </button>
        </div>

        <p class="text-sm text-stone-500">{{ metaText }}</p>

        <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white/90 shadow-sm">
            <div v-if="loading" class="h-1 w-full animate-pulse bg-teal-200" />
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50/80">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-stone-700">Documento</th>
                            <th class="px-4 py-3 text-left font-semibold text-stone-700">Nombre</th>
                            <th class="px-4 py-3 text-left font-semibold text-stone-700">Tipo cliente</th>
                            <th class="px-4 py-3 text-left font-semibold text-stone-700">Mora</th>
                            <th class="px-4 py-3 text-left font-semibold text-stone-700">EPS</th>
                            <th class="px-4 py-3 text-right font-semibold text-stone-700">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="item in rows" :key="item.id" class="hover:bg-teal-50/30">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-stone-900">{{ item.documentNumber || '—' }}</td>
                            <td class="px-4 py-3 text-stone-900">
                                {{ item.fullName || [item.firstName, item.lastName].filter(Boolean).join(' ') || '—' }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">{{ item.clientType || '—' }}</td>
                            <td class="px-4 py-3 text-stone-700">{{ item.moraStatus || '—' }}</td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-stone-700">{{ item.epsName || '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a
                                    class="font-medium text-teal-800 underline decoration-teal-800/30 hover:decoration-teal-800"
                                    :href="`/afiliados/${item.id}/ficha`"
                                >
                                    Ficha 360°
                                </a>
                            </td>
                        </tr>
                        <tr v-if="!loading && rows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-stone-500">No hay resultados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="meta.lastPage > 1" class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm disabled:opacity-40"
                :disabled="meta.currentPage <= 1 || loading"
                @click="prevPage"
            >
                Anterior
            </button>
            <span class="text-sm text-stone-600">Página {{ meta.currentPage }} / {{ meta.lastPage }}</span>
            <button
                type="button"
                class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm disabled:opacity-40"
                :disabled="meta.currentPage >= meta.lastPage || loading"
                @click="nextPage"
            >
                Siguiente
            </button>
        </div>
    </div>
</template>
