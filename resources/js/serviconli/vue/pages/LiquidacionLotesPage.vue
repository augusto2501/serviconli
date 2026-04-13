<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';

const loading = ref(false);
const creating = ref(false);
const confirming = ref(false);
const error = ref('');
const success = ref('');

const payers = ref([]);
const selectedPayerId = ref(null);
const periodYear = ref(new Date().getFullYear());
const periodMonth = ref(new Date().getMonth() + 1);

const batch = ref(null);
const batches = ref([]);

const months = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
];

function fmt(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
}

function batchStatusClass(s) {
    if (s === 'BORRADOR') return 'bg-orange-100 text-orange-900';
    if (s === 'LIQUIDADO') return 'bg-emerald-100 text-emerald-900';
    return 'bg-stone-100 text-stone-800';
}

function lineStatusClass(s) {
    return s === 'INCLUIDO' ? 'bg-emerald-100 text-emerald-900' : 'bg-stone-100 text-stone-700';
}

async function loadPayers() {
    try {
        const res = await apiFetch('/batches/payers');
        const data = await res.json();
        payers.value = data.data || [];
    } catch { /* ignore */ }
}

async function loadBatches() {
    try {
        const res = await apiFetch('/batches?per_page=20');
        const data = await res.json();
        batches.value = data.data || [];
    } catch { /* ignore */ }
}

async function createBatch() {
    if (!selectedPayerId.value) {
        error.value = 'Seleccione un pagador.';
        return;
    }
    creating.value = true;
    error.value = '';
    success.value = '';
    batch.value = null;

    try {
        const res = await apiFetch('/batches', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                payer_id: selectedPayerId.value,
                period_year: periodYear.value,
                period_month: periodMonth.value,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error al crear lote.';
            return;
        }
        batch.value = data;
        success.value = `Lote creado con ${data.cant_affiliates} afiliados. Revise y confirme.`;
        loadBatches();
    } catch (e) {
        error.value = e.message;
    } finally {
        creating.value = false;
    }
}

async function loadBatch(id) {
    loading.value = true;
    error.value = '';
    try {
        const res = await apiFetch(`/batches/${id}`);
        batch.value = await res.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function toggleLine(line) {
    try {
        const res = await apiFetch(`/batches/${batch.value.id}/lines/${line.id}/toggle`, { method: 'POST' });
        if (!res.ok) return;
        const updated = await res.json();
        const idx = batch.value.lines.findIndex(l => l.id === line.id);
        if (idx >= 0) batch.value.lines[idx] = updated;
        await loadBatch(batch.value.id);
    } catch { /* ignore */ }
}

async function confirmBatch() {
    if (!batch.value) return;
    confirming.value = true;
    error.value = '';
    success.value = '';
    try {
        const res = await apiFetch(`/batches/${batch.value.id}/confirm`, { method: 'POST' });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error al confirmar.';
            return;
        }
        batch.value = data;
        success.value = `Lote confirmado. ${data.planilla_number ? 'Cuenta de cobro: ' + data.planilla_number : ''}`;
        loadBatches();
    } catch (e) {
        error.value = e.message;
    } finally {
        confirming.value = false;
    }
}

async function cancelBatch() {
    if (!batch.value) return;
    try {
        const res = await apiFetch(`/batches/${batch.value.id}/cancel`, { method: 'POST' });
        if (!res.ok) return;
        batch.value = null;
        success.value = 'Lote cancelado.';
        loadBatches();
    } catch { /* ignore */ }
}

onMounted(() => {
    if (!requireAuth()) return;
    loadPayers();
    loadBatches();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Liquidación por Lotes</h1>
            <p class="mt-1 text-sm text-stone-600">Flujo 4 — Liquidación masiva por empresa/pagador.</p>
        </div>

        <div v-if="error" class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <span>{{ error }}</span>
            <button type="button" class="shrink-0 text-red-700 hover:underline" @click="error = ''">Cerrar</button>
        </div>
        <div v-if="success" class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <span>{{ success }}</span>
            <button type="button" class="shrink-0 text-emerald-800 hover:underline" @click="success = ''">Cerrar</button>
        </div>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-800">Crear pre-liquidación</h2>
            <div class="mt-4 grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Pagador / empresa</label>
                    <select
                        v-model="selectedPayerId"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                    >
                        <option :value="null" disabled>Seleccione…</option>
                        <option v-for="p in payers" :key="p.id" :value="p.id">
                            {{ p.nit }}-{{ p.dv }} {{ p.razon_social }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Año</label>
                    <input
                        v-model.number="periodYear"
                        type="number"
                        min="2020"
                        max="2100"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Mes</label>
                    <input
                        v-model.number="periodMonth"
                        type="number"
                        min="1"
                        max="12"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                    />
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-900 disabled:opacity-50"
                    :disabled="creating"
                    @click="createBatch"
                >
                    <span v-if="creating" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                    {{ creating ? 'Generando…' : 'Generar pre-liquidación' }}
                </button>
            </div>
        </section>

        <section v-if="batches.length && !batch" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-800">Lotes recientes</h2>
            <ul class="mt-3 divide-y divide-stone-200 rounded-xl border border-stone-200">
                <li
                    v-for="b in batches"
                    :key="b.id"
                    class="cursor-pointer px-4 py-3 hover:bg-stone-50"
                    @click="loadBatch(b.id)"
                >
                    <div class="font-medium text-stone-900">{{ b.payer?.razon_social || 'Sin pagador' }} — {{ b.period }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-stone-600">
                        <span>{{ b.cant_affiliates }} afiliados</span>
                        <span>·</span>
                        <span>{{ fmt(b.grand_total) }}</span>
                        <span>·</span>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="batchStatusClass(b.status)">
                            {{ b.status }}
                        </span>
                    </div>
                </li>
            </ul>
        </section>

        <template v-if="batch">
            <section class="rounded-2xl border border-teal-200 bg-teal-50/30 p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-teal-900">
                        Lote #{{ batch.id }} — {{ batch.payer?.razon_social }}
                        ({{ months[(batch.period_month || 1) - 1] }} {{ batch.period_year }})
                    </h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="batchStatusClass(batch.status)">
                        {{ batch.status }}
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div class="rounded-lg border border-stone-200 bg-white p-3">
                        <div class="text-stone-500">Afiliados</div>
                        <div class="text-lg font-semibold">{{ batch.cant_affiliates }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-white p-3">
                        <div class="text-stone-500">Salud</div>
                        <div class="text-lg font-semibold">{{ fmt(batch.totals?.health) }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-white p-3">
                        <div class="text-stone-500">Pensión</div>
                        <div class="text-lg font-semibold">{{ fmt(batch.totals?.pension) }}</div>
                    </div>
                    <div class="rounded-lg border border-teal-300 bg-teal-100 p-3">
                        <div class="font-semibold text-teal-800">Gran total</div>
                        <div class="text-xl font-bold text-teal-950">{{ fmt(batch.totals?.grand_total) }}</div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white/90 shadow-sm">
                <div class="border-b border-stone-200 px-5 py-3">
                    <h2 class="text-lg font-semibold text-stone-800">Detalle por afiliado</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-600">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2">Documento</th>
                                <th class="min-w-[8rem] px-3 py-2">Nombre</th>
                                <th class="whitespace-nowrap px-3 py-2">Tipo</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Salario</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">IBC</th>
                                <th class="whitespace-nowrap px-3 py-2 text-center">Días</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Salud</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Pensión</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">ARL</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">CCF</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Total</th>
                                <th class="whitespace-nowrap px-3 py-2">Estado</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="line in batch.lines || []" :key="line.id" class="hover:bg-stone-50/80">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ line.document_number || '—' }}</td>
                                <td class="px-3 py-2">{{ line.affiliate_name }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ line.contributor_type }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.salary) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.ibc) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-center">{{ line.days_eps }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.health_total) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.pension_total) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.arl_total) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmt(line.ccf_total) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end font-semibold">{{ fmt(line.total_payable) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="lineStatusClass(line.line_status)">
                                        {{ line.line_status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">
                                    <button
                                        v-if="batch.status === 'BORRADOR'"
                                        type="button"
                                        class="text-xs font-medium underline"
                                        :class="line.line_status === 'INCLUIDO' ? 'text-red-700' : 'text-emerald-800'"
                                        @click="toggleLine(line)"
                                    >
                                        {{ line.line_status === 'INCLUIDO' ? 'Excluir' : 'Incluir' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="batch.entity_summaries?.length" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-stone-800">Distribución por entidad</h2>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                    <div
                        v-for="es in batch.entity_summaries"
                        :key="es.entity_code + es.subsystem"
                        class="rounded-lg border border-stone-200 bg-stone-50 p-2"
                    >
                        <div class="text-xs text-stone-500">{{ es.entity_code }} · {{ es.subsystem }}</div>
                        <div class="font-semibold">{{ fmt(es.amount) }}</div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-medium text-stone-800 hover:bg-stone-50"
                    @click="batch = null"
                >
                    Cerrar
                </button>
                <button
                    v-if="batch.status === 'BORRADOR'"
                    type="button"
                    class="rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-800 hover:bg-red-50"
                    @click="cancelBatch"
                >
                    Cancelar lote
                </button>
                <button
                    v-if="batch.status === 'BORRADOR'"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-900 disabled:opacity-50"
                    :disabled="confirming"
                    @click="confirmBatch"
                >
                    <span v-if="confirming" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                    {{ confirming ? 'Confirmando…' : 'Confirmar liquidación' }}
                </button>
            </div>
        </template>
    </div>
</template>
