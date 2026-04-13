<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';
import UiToast from '../components/UiToast.vue';

const toast = reactive({ show: false, text: '', variant: 'info' });
let toastTimer;
function notify(text, variant = 'info') {
    toast.text = text;
    toast.variant = variant === 'error' ? 'error' : variant === 'success' ? 'success' : 'info';
    toast.show = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.show = false;
    }, 4000);
}

async function jsonOrMessage(res) {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg =
            data.message ||
            (data.errors && Object.values(data.errors).flat().join(' ')) ||
            `Error ${res.status}`;
        throw new Error(msg);
    }
    return data;
}

function fmtPesos(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
}

const loading = ref(false);
const rows = ref([]);
const advisors = ref([]);
const filterAdvisor = ref('');
const filterStatus = ref('');

async function loadAdvisors() {
    try {
        const res = await apiFetch('/advisors?per_page=100');
        const data = await jsonOrMessage(res);
        advisors.value = data.data || [];
    } catch {
        advisors.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const q = new URLSearchParams({ per_page: '50' });
        if (filterAdvisor.value) q.set('advisor_id', filterAdvisor.value);
        if (filterStatus.value) q.set('status', filterStatus.value);
        const res = await apiFetch(`/advisor-commissions?${q}`);
        const data = await jsonOrMessage(res);
        rows.value = data.data || [];
    } catch (e) {
        notify(e.message || 'Error cargando comisiones', 'error');
    } finally {
        loading.value = false;
    }
}

async function setStatus(row, status) {
    if (!confirm(`¿Marcar comisión ${row.publicNumber} como ${status}?`)) return;
    try {
        const res = await apiFetch(`/advisor-commissions/${row.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status }),
        });
        await jsonOrMessage(res);
        notify('Estado actualizado', 'success');
        await load();
    } catch (e) {
        notify(e.message, 'error');
    }
}

function statusClass(s) {
    if (s === 'PAGADA') return 'bg-emerald-100 text-emerald-900';
    if (s === 'CALCULADA') return 'bg-amber-100 text-amber-900';
    if (s === 'ANULADA') return 'bg-stone-200 text-stone-700';
    return 'bg-stone-100 text-stone-800';
}

onMounted(() => {
    if (!requireAuth()) return;
    loadAdvisors();
    load();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="/asesores" class="text-sm font-medium text-teal-800 hover:underline">← Asesores</a>
                <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Comisiones de asesores</h1>
                <p class="mt-1 text-sm text-stone-600">RF-100 — Comprobantes CE y estados CALCULADA → PAGADA | ANULADA.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white/90 p-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">Asesor</label>
                <select v-model="filterAdvisor" class="rounded-xl border border-stone-300 px-3 py-2 text-sm" @change="load">
                    <option value="">Todos</option>
                    <option v-for="a in advisors" :key="a.id" :value="String(a.id)">{{ a.code }} — {{ a.firstName }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">Estado</label>
                <select v-model="filterStatus" class="rounded-xl border border-stone-300 px-3 py-2 text-sm" @change="load">
                    <option value="">Todos</option>
                    <option value="CALCULADA">CALCULADA</option>
                    <option value="PAGADA">PAGADA</option>
                    <option value="ANULADA">ANULADA</option>
                </select>
            </div>
            <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="load">Actualizar</button>
        </div>

        <div v-if="loading" class="py-12 text-center text-sm text-stone-500">Cargando…</div>
        <div v-else class="overflow-x-auto rounded-2xl border border-stone-200 bg-white/90 shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-600">
                    <tr>
                        <th class="px-3 py-2">CE</th>
                        <th class="px-3 py-2">Asesor</th>
                        <th class="px-3 py-2">Afiliado</th>
                        <th class="px-3 py-2">Tipo</th>
                        <th class="px-3 py-2 text-end">Monto</th>
                        <th class="px-3 py-2">Estado</th>
                        <th class="px-3 py-2 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-stone-50/80">
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ r.publicNumber }}</td>
                        <td class="px-3 py-2 text-xs">{{ r.advisorCode }} {{ r.advisorName }}</td>
                        <td class="px-3 py-2 text-xs">{{ r.affiliateId }} · {{ r.affiliateName || '—' }}</td>
                        <td class="px-3 py-2">{{ r.commissionType }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmtPesos(r.amountPesos) }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{ r.status }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-end">
                            <template v-if="r.status === 'CALCULADA'">
                                <button type="button" class="mr-2 text-xs font-medium text-teal-800 hover:underline" @click="setStatus(r, 'PAGADA')">
                                    Pagar
                                </button>
                                <button type="button" class="text-xs font-medium text-red-700 hover:underline" @click="setStatus(r, 'ANULADA')">
                                    Anular
                                </button>
                            </template>
                            <span v-else class="text-xs text-stone-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!rows.length" class="px-4 py-8 text-center text-sm text-stone-500">Sin comisiones con los filtros actuales.</p>
        </div>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
