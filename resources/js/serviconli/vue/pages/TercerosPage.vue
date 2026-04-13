<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';
import UiButton from '../components/UiButton.vue';
import UiToast from '../components/UiToast.vue';

const toast = reactive({ show: false, text: '', variant: 'info' });
let toastTimer = null;
function notify(text, variant = 'info') {
    toast.text = text;
    if (variant === 'error') toast.variant = 'error';
    else if (variant === 'success') toast.variant = 'success';
    else if (variant === 'warning') toast.variant = 'warning';
    else toast.variant = 'info';
    toast.show = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.show = false;
    }, 5000);
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

// --- Consignación ---
const savingDeposit = ref(false);
const dupWarning = ref(false);
const depositForm = reactive({
    bank_name: '',
    reference: '',
    amount_pesos: 0,
    deposit_type: 'LOCAL',
    expected_amount_pesos: null,
    notes: '',
});

async function submitDeposit() {
    savingDeposit.value = true;
    dupWarning.value = false;
    try {
        const body = {
            bank_name: depositForm.bank_name,
            reference: depositForm.reference,
            amount_pesos: Number(depositForm.amount_pesos),
            deposit_type: depositForm.deposit_type,
            notes: depositForm.notes || null,
        };
        if (depositForm.expected_amount_pesos != null && depositForm.expected_amount_pesos !== '') {
            body.expected_amount_pesos = Number(depositForm.expected_amount_pesos);
        }
        const res = await apiFetch('/third-parties/bank-deposits', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await jsonOrMessage(res);
        dupWarning.value = !!data.duplicateReferenceWarning;
        notify('Consignación registrada.', dupWarning.value ? 'warning' : 'success');
        depositForm.bank_name = '';
        depositForm.reference = '';
        depositForm.amount_pesos = 0;
        depositForm.expected_amount_pesos = null;
        depositForm.notes = '';
        await loadReceivables();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        savingDeposit.value = false;
    }
}

// --- CxC asesores ---
const loadingRec = ref(false);
const receivables = ref([]);
const filterStatus = ref('');

async function loadReceivables() {
    loadingRec.value = true;
    try {
        const q = new URLSearchParams({ per_page: '50' });
        if (filterStatus.value) q.set('status', filterStatus.value);
        const res = await apiFetch(`/third-parties/advisor-receivables?${q}`);
        const data = await jsonOrMessage(res);
        receivables.value = data.data || [];
    } catch (e) {
        notify(e.message || 'Error cargando CxC', 'error');
    } finally {
        loadingRec.value = false;
    }
}

async function setReceivableStatus(row, status) {
    const label = status === 'PAGADA' ? 'marcar como pagada' : 'anular';
    if (!confirm(`¿Confirmar ${label} esta cuenta por cobrar?`)) return;
    try {
        const res = await apiFetch(`/third-parties/advisor-receivables/${row.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status }),
        });
        await jsonOrMessage(res);
        notify(`Estado actualizado: ${status}`, 'success');
        await loadReceivables();
    } catch (e) {
        notify(e.message, 'error');
    }
}

function statusClass(s) {
    if (s === 'PAGADA') return 'bg-emerald-100 text-emerald-900';
    if (s === 'PENDIENTE') return 'bg-amber-100 text-amber-900';
    if (s === 'ANULADA') return 'bg-stone-200 text-stone-700';
    return 'bg-stone-100 text-stone-800';
}

onMounted(() => {
    if (!requireAuth()) return;
    loadReceivables();
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <div>
            <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
            <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Terceros y consignaciones</h1>
            <p class="mt-1 text-sm text-stone-600">RF-101 / RF-102 — Consignaciones bancarias y CxC a asesores.</p>
        </div>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-800">Registrar consignación</h2>
            <p class="mt-1 text-sm text-stone-600">Referencia duplicada genera advertencia, no bloquea el registro.</p>
            <div v-if="dupWarning" class="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                Esta referencia ya existía en el sistema (revisar duplicados operativos).
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Banco *</label>
                    <input v-model="depositForm.bank_name" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Referencia *</label>
                    <input v-model="depositForm.reference" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Valor consignado ($) *</label>
                    <input v-model.number="depositForm.amount_pesos" type="number" min="1" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Tipo</label>
                    <select v-model="depositForm.deposit_type" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                        <option value="LOCAL">Local</option>
                        <option value="NACIONAL">Nacional</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Valor esperado ($) — opcional (excedente)</label>
                    <input v-model.number="depositForm.expected_amount_pesos" type="number" min="0" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Notas</label>
                    <textarea v-model="depositForm.notes" rows="2" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <div class="mt-4">
                <UiButton type="button" variant="primary" :loading="savingDeposit" @click="submitDeposit">Registrar consignación</UiButton>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-stone-800">Cuentas por cobrar (asesores)</h2>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-stone-600">Estado</label>
                    <select
                        v-model="filterStatus"
                        class="rounded-lg border border-stone-300 px-2 py-1.5 text-sm"
                        @change="loadReceivables"
                    >
                        <option value="">Todos</option>
                        <option value="PENDIENTE">PENDIENTE</option>
                        <option value="PAGADA">PAGADA</option>
                        <option value="ANULADA">ANULADA</option>
                    </select>
                </div>
            </div>
            <div v-if="loadingRec" class="py-10 text-center text-sm text-stone-500">Cargando…</div>
            <div v-else class="mt-4 overflow-x-auto rounded-xl border border-stone-200">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-600">
                        <tr>
                            <th class="px-3 py-2">Id</th>
                            <th class="px-3 py-2">Asesor</th>
                            <th class="px-3 py-2 text-end">Monto</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <tr v-for="r in receivables" :key="r.id" class="hover:bg-stone-50/80">
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ r.id }}</td>
                            <td class="px-3 py-2">
                                <span class="font-mono text-xs text-stone-600">{{ r.advisorCode }}</span>
                                <span v-if="r.advisorName" class="block text-stone-800">{{ r.advisorName }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmtPesos(r.amountPesos) }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">
                                    {{ r.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-end">
                                <template v-if="r.status === 'PENDIENTE'">
                                    <button
                                        type="button"
                                        class="mr-2 text-xs font-medium text-teal-800 hover:underline"
                                        @click="setReceivableStatus(r, 'PAGADA')"
                                    >
                                        Pagar
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-red-700 hover:underline"
                                        @click="setReceivableStatus(r, 'ANULADA')"
                                    >
                                        Anular
                                    </button>
                                </template>
                                <span v-else class="text-xs text-stone-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!receivables.length" class="px-4 py-8 text-center text-sm text-stone-500">Sin registros.</p>
            </div>
        </section>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
