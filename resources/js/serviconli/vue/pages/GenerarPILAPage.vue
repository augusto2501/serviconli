<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';

const loading = ref(false);
const generating = ref(false);
const error = ref('');
const success = ref('');

const batches = ref([]);
const generations = ref([]);
const selectedBatchId = ref(null);
const selectedFormat = ref('PLANO_ARUS');

const formats = [
    { value: 'PLANO_ARUS', label: 'Plano ARUS (TXT)' },
    { value: 'XLSX', label: 'Excel (42 columnas)' },
];

function fmt(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
}

async function loadBatches() {
    try {
        const res = await apiFetch('/batches?status=LIQUIDADO&per_page=50');
        const data = await res.json();
        batches.value = (data.data || []).map((b) => ({
            ...b,
            label: `${b.payer?.razon_social || 'Sin pagador'} — ${b.period} (${b.cant_affiliates} afil. · ${fmt(b.grand_total)})`,
        }));
    } catch {
        /* ignore */
    }
}

async function loadGenerations() {
    loading.value = true;
    try {
        const res = await apiFetch('/pila-files');
        const data = await res.json();
        generations.value = data.data || [];
    } catch {
        /* ignore */
    } finally {
        loading.value = false;
    }
}

async function generate() {
    if (!selectedBatchId.value) {
        error.value = 'Seleccione un lote.';
        return;
    }
    generating.value = true;
    error.value = '';
    success.value = '';

    try {
        const res = await apiFetch('/pila-files/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                batch_id: selectedBatchId.value,
                format: selectedFormat.value,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error al generar.';
            return;
        }
        success.value = `Archivo generado: ${data.planilla_number} (${data.affiliates_count} afiliados)`;
        loadGenerations();
    } catch (e) {
        error.value = e.message;
    } finally {
        generating.value = false;
    }
}

async function downloadFile(genId) {
    try {
        const res = await apiFetch(`/pila-files/${genId}/download`, {
            headers: { Accept: '*/*' },
        });
        if (!res.ok) return;
        const blob = await res.blob();
        const cd = res.headers.get('Content-Disposition');
        let filename = 'planilla_pila.txt';
        if (cd && cd.includes('filename=')) {
            const m = cd.match(/filename="?([^";]+)"?/);
            if (m) filename = m[1];
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        /* ignore */
    }
}

onMounted(() => {
    if (!requireAuth()) return;
    loadBatches();
    loadGenerations();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Generación archivo PILA</h1>
            <p class="mt-1 text-sm text-stone-600">Flujo 8 — Plano ARUS o Excel desde lotes confirmados.</p>
        </div>

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
        <div v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ success }}</div>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Generar archivo</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Lote confirmado</label>
                    <select
                        v-model="selectedBatchId"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                    >
                        <option :value="null" disabled>Seleccione…</option>
                        <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Formato</label>
                    <select
                        v-model="selectedFormat"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2.5 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                    >
                        <option v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                </div>
            </div>
            <button
                type="button"
                class="mt-4 rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50"
                :disabled="generating"
                @click="generate"
            >
                {{ generating ? 'Generando…' : 'Generar archivo PILA' }}
            </button>
        </section>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Archivos generados</h2>
            <div v-if="loading" class="mt-3 h-1 w-32 animate-pulse rounded bg-teal-200" />
            <div v-else-if="generations.length" class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Pagador</th>
                            <th class="px-3 py-2 text-left font-medium">Período</th>
                            <th class="px-3 py-2 text-left font-medium">Formato</th>
                            <th class="px-3 py-2 text-center font-medium">Afiliados</th>
                            <th class="px-3 py-2 text-left font-medium">No. planilla</th>
                            <th class="px-3 py-2 text-left font-medium">Fecha</th>
                            <th class="px-3 py-2 text-right font-medium">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="g in generations" :key="g.id">
                            <td class="px-3 py-2">{{ g.payer_name }}</td>
                            <td class="px-3 py-2">{{ g.period }}</td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded px-1.5 py-0.5 text-xs font-medium"
                                    :class="g.file_format === 'PLANO_ARUS' ? 'bg-blue-100 text-blue-900' : 'bg-emerald-100 text-emerald-900'"
                                >
                                    {{ g.file_format }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">{{ g.affiliates_count }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ g.planilla_number }}</td>
                            <td class="px-3 py-2 text-xs text-stone-600">
                                {{ g.created_at ? new Date(g.created_at).toLocaleString('es-CO') : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="font-medium text-teal-800 hover:underline" @click="downloadFile(g.id)">
                                    Descargar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-3 text-sm text-stone-500">No hay archivos generados aún.</p>
        </section>
    </div>
</template>
