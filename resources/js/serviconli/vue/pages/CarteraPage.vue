<script setup>
import { ref, reactive, onMounted } from 'vue';
import { apiFetch, requireAuth, logoutAndRedirect } from '../api';
import UiModal from '../components/UiModal.vue';
import UiToast from '../components/UiToast.vue';

const activeTab = ref('cuentas');

const toast = reactive({ show: false, text: '', variant: 'info' });
let toastTimer = null;
function notify(text, color = 'teal-darken-3') {
    toast.text = text;
    toast.variant = color === 'error' ? 'error' : 'info';
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

// --- Cuentas de Cobro ---
const cuentas = ref([]);
const loadingCuentas = ref(false);

const selectedCuenta = ref(null);
const showCuentaDetail = ref(false);

async function loadCuentas() {
    loadingCuentas.value = true;
    try {
        const res = await apiFetch('/cuentas-cobro');
        const data = await jsonOrMessage(res);
        cuentas.value = data.data || [];
    } catch (e) {
        notify(e.message || 'Error cargando cuentas', 'error');
    } finally {
        loadingCuentas.value = false;
    }
}

async function selectCuenta(item) {
    if (!item) return;
    try {
        const res = await apiFetch(`/cuentas-cobro/${item.id}`);
        selectedCuenta.value = await jsonOrMessage(res);
        showCuentaDetail.value = true;
    } catch (e) {
        notify(e.message || 'Error cargando detalle', 'error');
    }
}

const showNewCuenta = ref(false);
const savingCuenta = ref(false);
const payers = ref([]);
const newCuenta = reactive({
    payer_id: null,
    period_year: new Date().getFullYear(),
    period_month: new Date().getMonth() + 1,
    mode: 'PLENO',
});

async function loadPayers() {
    try {
        const res = await apiFetch('/batches/payers');
        const data = await res.json();
        payers.value = data.data || data || [];
    } catch {
        /* ignore */
    }
}

async function createCuenta() {
    savingCuenta.value = true;
    try {
        const res = await apiFetch('/cuentas-cobro', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newCuenta),
        });
        await jsonOrMessage(res);
        notify('Pre-cuenta generada');
        showNewCuenta.value = false;
        await loadCuentas();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        savingCuenta.value = false;
    }
}

const showDefinitiva = ref(false);
const savingDefinitiva = ref(false);
const definitivaCuentaId = ref(null);
const definitivaForm = reactive({ payment_date_1: '', payment_date_2: '', mora_days: 0 });

function openDefinitiva(item) {
    definitivaCuentaId.value = item.id;
    showDefinitiva.value = true;
}

async function submitDefinitiva() {
    savingDefinitiva.value = true;
    try {
        const res = await apiFetch(`/cuentas-cobro/${definitivaCuentaId.value}/definitiva`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(definitivaForm),
        });
        await jsonOrMessage(res);
        notify('Cuenta convertida a definitiva');
        showDefinitiva.value = false;
        await loadCuentas();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        savingDefinitiva.value = false;
    }
}

const showPay = ref(false);
const paying = ref(false);
const payingCuenta = ref(null);
const payForm = reactive({ payment_method: 'EFECTIVO', amount_pesos: 0, bank_name: '', bank_reference: '' });

function openPay(item) {
    payingCuenta.value = {
        ...item,
        isOportuno: !item.payment_date_1 || new Date() <= new Date(item.payment_date_1),
    };
    payForm.amount_pesos = payingCuenta.value.isOportuno ? item.total_1 : item.total_2;
    showPay.value = true;
}

async function submitPay() {
    paying.value = true;
    try {
        const res = await apiFetch(`/cuentas-cobro/${payingCuenta.value.id}/pay`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payForm),
        });
        await jsonOrMessage(res);
        notify('Pago registrado exitosamente');
        showPay.value = false;
        await loadCuentas();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        paying.value = false;
    }
}

const showCancelCuenta = ref(false);
const cancelling = ref(false);
const cancelCuentaId = ref(null);
const cancelForm = reactive({ cancellation_reason: '', cancellation_motive: '' });

function openCancelCuenta(item) {
    cancelCuentaId.value = item.id;
    showCancelCuenta.value = true;
}

async function submitCancelCuenta() {
    cancelling.value = true;
    try {
        const res = await apiFetch(`/cuentas-cobro/${cancelCuentaId.value}/cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(cancelForm),
        });
        await jsonOrMessage(res);
        notify('Cuenta anulada');
        showCancelCuenta.value = false;
        await loadCuentas();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        cancelling.value = false;
    }
}

// --- Recibos ---
const invoices = ref([]);
const loadingInvoices = ref(false);
const tiposRecibo = ['AFILIACION', 'APORTE', 'REINGRESO', 'CUENTA', 'CAJA_GENERAL'];
const invoiceFilter = reactive({ tipo: '', estado: '' });

async function loadInvoices() {
    loadingInvoices.value = true;
    try {
        const params = new URLSearchParams();
        if (invoiceFilter.tipo !== '') params.set('tipo', invoiceFilter.tipo);
        if (invoiceFilter.estado !== '') params.set('estado', invoiceFilter.estado);
        const res = await apiFetch(`/invoices?${params}`);
        const data = await jsonOrMessage(res);
        invoices.value = data.data || [];
    } catch (e) {
        notify(e.message || 'Error cargando recibos', 'error');
    } finally {
        loadingInvoices.value = false;
    }
}

const selectedInvoice = ref(null);
const showInvoiceDetail = ref(false);

async function selectInvoice(item) {
    if (!item) return;
    try {
        const res = await apiFetch(`/invoices/${item.id}`);
        selectedInvoice.value = await jsonOrMessage(res);
        showInvoiceDetail.value = true;
    } catch (e) {
        notify(e.message || 'Error cargando recibo', 'error');
    }
}

const showNewRecibo = ref(false);
const savingRecibo = ref(false);
const newRecibo = reactive({
    tipo: 'CAJA_GENERAL',
    payment_method: 'EFECTIVO',
    bank_name: '',
    bank_reference: '',
    items: [{ concept: '', amount_pesos: 0 }],
});

async function createRecibo() {
    savingRecibo.value = true;
    try {
        const res = await apiFetch('/invoices', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newRecibo),
        });
        await jsonOrMessage(res);
        notify('Recibo generado exitosamente');
        showNewRecibo.value = false;
        await loadInvoices();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        savingRecibo.value = false;
    }
}

const showCancelInvoice = ref(false);
const cancellingInvoice = ref(false);
const cancelInvoiceId = ref(null);
const cancelInvoiceForm = reactive({ cancellation_reason: '', cancellation_motive: '' });

function openCancelInvoice(item) {
    cancelInvoiceId.value = item.id;
    showCancelInvoice.value = true;
}

async function submitCancelInvoice() {
    cancellingInvoice.value = true;
    try {
        const res = await apiFetch(`/invoices/${cancelInvoiceId.value}/cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(cancelInvoiceForm),
        });
        await jsonOrMessage(res);
        notify('Recibo anulado');
        showCancelInvoice.value = false;
        await loadInvoices();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        cancellingInvoice.value = false;
    }
}

function formatCurrency(v) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(
        v || 0,
    );
}

function periodCobroLabel(row) {
    if (row.period_cobro) return row.period_cobro;
    if (row.period_year != null && row.period_month != null) {
        return `${row.period_year}-${String(row.period_month).padStart(2, '0')}`;
    }
    return '—';
}

function statusBadgeClassCuenta(s) {
    const map = {
        PRE_CUENTA: 'bg-orange-100 text-orange-900',
        DEFINITIVA: 'bg-teal-100 text-teal-900',
        PAGADA: 'bg-emerald-100 text-emerald-900',
        ANULADA: 'bg-red-100 text-red-900',
    };
    return map[s] || 'bg-stone-100 text-stone-800';
}

function statusBadgeClassRecibo(estado) {
    return estado === 'ACTIVO' ? 'bg-teal-100 text-teal-900' : 'bg-red-100 text-red-900';
}

onMounted(() => {
    if (!requireAuth()) return;
    loadCuentas();
    loadInvoices();
    loadPayers();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
                <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Cartera y facturación</h1>
                <p class="mt-1 text-sm text-stone-600">Flujos 5, 6 y 7 — Cuentas de cobro y recibos de caja.</p>
            </div>
            <button
                type="button"
                class="rounded-xl border border-teal-800 px-4 py-2 text-sm font-medium text-teal-900 hover:bg-teal-50"
                @click="logoutAndRedirect"
            >
                Cerrar sesión
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white/90 shadow-sm">
            <div class="flex border-b border-stone-200">
                <button
                    type="button"
                    class="px-5 py-3 text-sm font-semibold"
                    :class="activeTab === 'cuentas' ? 'border-b-2 border-teal-800 text-teal-900' : 'text-stone-600 hover:text-stone-900'"
                    @click="activeTab = 'cuentas'"
                >
                    Cuentas de cobro
                </button>
                <button
                    type="button"
                    class="px-5 py-3 text-sm font-semibold"
                    :class="activeTab === 'recibos' ? 'border-b-2 border-teal-800 text-teal-900' : 'text-stone-600 hover:text-stone-900'"
                    @click="activeTab = 'recibos'"
                >
                    Recibos de caja
                </button>
            </div>

            <div v-show="activeTab === 'cuentas'" class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-stone-800">Cuentas de cobro</h2>
                    <button
                        type="button"
                        class="rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900"
                        @click="showNewCuenta = true"
                    >
                        Nueva cuenta
                    </button>
                </div>
                <div v-if="loadingCuentas" class="py-8 text-center text-sm text-stone-500">Cargando…</div>
                <div v-else class="overflow-x-auto rounded-xl border border-stone-200">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-600">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2">Número</th>
                                <th class="px-3 py-2">Pagador</th>
                                <th class="whitespace-nowrap px-3 py-2">Período</th>
                                <th class="whitespace-nowrap px-3 py-2">Modo</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Total oportuno</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Total mora</th>
                                <th class="whitespace-nowrap px-3 py-2">Estado</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr
                                v-for="item in cuentas"
                                :key="item.id"
                                class="cursor-pointer hover:bg-stone-50/80"
                                @click="selectCuenta(item)"
                            >
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ item.cuenta_number }}</td>
                                <td class="px-3 py-2">{{ item.payer?.razon_social || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ periodCobroLabel(item) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ item.generation_mode }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ formatCurrency(item.total_1) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ formatCurrency(item.total_2) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClassCuenta(item.status)">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-end" @click.stop>
                                    <button
                                        v-if="item.status === 'PRE_CUENTA'"
                                        type="button"
                                        class="mr-2 text-xs font-medium text-teal-800 hover:underline"
                                        @click="openDefinitiva(item)"
                                    >
                                        Definitiva
                                    </button>
                                    <button
                                        v-if="item.status === 'DEFINITIVA'"
                                        type="button"
                                        class="mr-2 text-xs font-medium text-teal-800 hover:underline"
                                        @click="openPay(item)"
                                    >
                                        Pagar
                                    </button>
                                    <button
                                        v-if="item.status !== 'PAGADA' && item.status !== 'ANULADA'"
                                        type="button"
                                        class="text-xs font-medium text-red-700 hover:underline"
                                        @click="openCancelCuenta(item)"
                                    >
                                        Anular
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="activeTab === 'recibos'" class="p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-stone-800">Recibos de caja</h2>
                    <button
                        type="button"
                        class="rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900"
                        @click="showNewRecibo = true"
                    >
                        Nuevo recibo
                    </button>
                </div>
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Tipo</label>
                        <select
                            v-model="invoiceFilter.tipo"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                            @change="loadInvoices"
                        >
                            <option value="">Todos</option>
                            <option v-for="t in tiposRecibo" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Estado</label>
                        <select
                            v-model="invoiceFilter.estado"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                            @change="loadInvoices"
                        >
                            <option value="">Todos</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="ANULADO">ANULADO</option>
                        </select>
                    </div>
                </div>
                <div v-if="loadingInvoices" class="py-8 text-center text-sm text-stone-500">Cargando…</div>
                <div v-else class="overflow-x-auto rounded-xl border border-stone-200">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-600">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2">Número</th>
                                <th class="whitespace-nowrap px-3 py-2">Fecha</th>
                                <th class="whitespace-nowrap px-3 py-2">Tipo</th>
                                <th class="whitespace-nowrap px-3 py-2">Medio</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Total</th>
                                <th class="whitespace-nowrap px-3 py-2">Estado</th>
                                <th class="whitespace-nowrap px-3 py-2 text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr
                                v-for="item in invoices"
                                :key="item.id"
                                class="cursor-pointer hover:bg-stone-50/80"
                                @click="selectInvoice(item)"
                            >
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ item.public_number }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ item.fecha }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ item.tipo }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ item.payment_method }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">{{ formatCurrency(item.total_pesos) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClassRecibo(item.estado)">
                                        {{ item.estado }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-end" @click.stop>
                                    <button
                                        v-if="item.estado === 'ACTIVO'"
                                        type="button"
                                        class="text-xs font-medium text-red-700 hover:underline"
                                        @click="openCancelInvoice(item)"
                                    >
                                        Anular
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detalle cuenta -->
        <UiModal v-model="showCuentaDetail" title="" max-width="max-w-4xl">
            <template v-if="selectedCuenta" #title>Cuenta {{ selectedCuenta.cuenta_number }}</template>
            <template v-if="selectedCuenta">
                <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                    <p><strong class="text-stone-600">Pagador:</strong> {{ selectedCuenta.payer?.razon_social }}</p>
                    <p>
                        <strong class="text-stone-600">Período:</strong>
                        {{ selectedCuenta.period_year }}-{{ String(selectedCuenta.period_month).padStart(2, '0') }}
                    </p>
                    <p><strong class="text-stone-600">Modo:</strong> {{ selectedCuenta.generation_mode }}</p>
                </div>
                <hr class="my-4 border-stone-200" />
                <div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                        <div class="text-xs text-stone-500">EPS</div>
                        <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_eps) }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                        <div class="text-xs text-stone-500">AFP</div>
                        <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_afp) }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                        <div class="text-xs text-stone-500">ARL</div>
                        <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_arl) }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                        <div class="text-xs text-stone-500">CCF</div>
                        <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_ccf) }}</div>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                        <div class="text-xs text-stone-500">Admin</div>
                        <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_admin) }}</div>
                    </div>
                    <div class="rounded-lg border border-teal-200/80 bg-teal-50/80 p-2">
                        <div class="text-xs font-medium text-teal-800">Total oportuno</div>
                        <div class="font-bold text-teal-950">{{ formatCurrency(selectedCuenta.total_1) }}</div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div class="rounded-lg border border-stone-200 p-2">
                        <strong class="text-stone-600">Intereses mora:</strong>
                        {{ formatCurrency(selectedCuenta.interest_mora) }}
                    </div>
                    <div class="rounded-lg border border-teal-200 bg-teal-50/50 p-2">
                        <strong class="text-teal-900">Total con mora:</strong>
                        {{ formatCurrency(selectedCuenta.total_2) }}
                    </div>
                </div>
                <h3 class="mb-2 mt-4 text-base font-semibold text-stone-800">Detalle por afiliado</h3>
                <div v-if="selectedCuenta.details" class="overflow-x-auto rounded-lg border border-stone-200">
                    <table class="min-w-full divide-y divide-stone-200 text-xs sm:text-sm">
                        <thead class="bg-stone-50 text-left font-semibold text-stone-600">
                            <tr>
                                <th class="px-2 py-1.5">Afiliado</th>
                                <th class="px-2 py-1.5 text-end">Salud</th>
                                <th class="px-2 py-1.5 text-end">Pensión</th>
                                <th class="px-2 py-1.5 text-end">ARL</th>
                                <th class="px-2 py-1.5 text-end">CCF</th>
                                <th class="px-2 py-1.5 text-end">Admin</th>
                                <th class="px-2 py-1.5 text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="(d, idx) in selectedCuenta.details" :key="idx">
                                <td class="px-2 py-1.5">{{ d.affiliate_id }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(d.health_pesos) }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(d.pension_pesos) }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(d.arl_pesos) }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(d.ccf_pesos) }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(d.admin_pesos) }}</td>
                                <td class="px-2 py-1.5 text-end font-medium">{{ formatCurrency(d.total_pesos) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <template v-if="selectedCuenta" #footer>
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50"
                        @click="showCuentaDetail = false"
                    >
                        Cerrar
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Nueva cuenta -->
        <UiModal v-model="showNewCuenta" title="Generar cuenta de cobro" max-width="max-w-lg">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Pagador</label>
                    <select
                        v-model="newCuenta.payer_id"
                        class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                    >
                        <option :value="null" disabled>Seleccione…</option>
                        <option v-for="p in payers" :key="p.id" :value="p.id">{{ p.razon_social }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Año</label>
                        <input v-model.number="newCuenta.period_year" type="number" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Mes</label>
                        <input
                            v-model.number="newCuenta.period_month"
                            type="number"
                            min="1"
                            max="12"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                        />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Modo de generación</label>
                    <select v-model="newCuenta.mode" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                        <option value="PLENO">PLENO</option>
                        <option value="SOLO_APORTES">SOLO_APORTES</option>
                        <option value="SOLO_AFILIACIONES">SOLO_AFILIACIONES</option>
                    </select>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showNewCuenta = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="savingCuenta"
                        @click="createCuenta"
                    >
                        <span v-if="savingCuenta" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Generar pre-cuenta
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Definitiva -->
        <UiModal v-model="showDefinitiva" title="Convertir a definitiva" max-width="max-w-lg">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Fecha pago oportuno</label>
                    <input v-model="definitivaForm.payment_date_1" type="date" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Fecha pago con mora</label>
                    <input v-model="definitivaForm.payment_date_2" type="date" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Días de mora (para intereses)</label>
                    <input v-model.number="definitivaForm.mora_days" type="number" min="0" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showDefinitiva = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="savingDefinitiva"
                        @click="submitDefinitiva"
                    >
                        <span v-if="savingDefinitiva" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Confirmar
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Pagar -->
        <UiModal v-model="showPay" title="Pagar cuenta de cobro" max-width="max-w-lg">
            <div v-if="payingCuenta" class="space-y-3">
                <div class="rounded-xl border border-teal-200 bg-teal-50/80 px-3 py-2 text-sm text-teal-950">
                    <strong>{{ payingCuenta.isOportuno ? 'Pago oportuno' : 'Pago con mora' }}:</strong>
                    {{ formatCurrency(payingCuenta.isOportuno ? payingCuenta.total_1 : payingCuenta.total_2) }}
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Medio de pago</label>
                    <select v-model="payForm.payment_method" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                        <option value="EFECTIVO">EFECTIVO</option>
                        <option value="CONSIGNACION">CONSIGNACION</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Monto</label>
                    <input v-model.number="payForm.amount_pesos" type="number" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div v-if="payForm.payment_method === 'CONSIGNACION'">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Banco</label>
                    <input v-model="payForm.bank_name" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div v-if="payForm.payment_method === 'CONSIGNACION'">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Referencia</label>
                    <input v-model="payForm.bank_reference" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showPay = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="paying"
                        @click="submitPay"
                    >
                        <span v-if="paying" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Registrar pago
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Anular cuenta -->
        <UiModal v-model="showCancelCuenta" title="Anular cuenta de cobro" max-width="max-w-lg">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Causal</label>
                    <input v-model="cancelForm.cancellation_reason" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Motivo</label>
                    <textarea v-model="cancelForm.cancellation_motive" rows="3" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showCancelCuenta = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="cancelling"
                        @click="submitCancelCuenta"
                    >
                        <span v-if="cancelling" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Anular
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Nuevo recibo -->
        <UiModal v-model="showNewRecibo" title="Nuevo recibo de caja" max-width="max-w-2xl">
            <div class="space-y-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Tipo</label>
                        <select v-model="newRecibo.tipo" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                            <option v-for="t in tiposRecibo" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Medio de pago</label>
                        <select v-model="newRecibo.payment_method" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                            <option value="EFECTIVO">EFECTIVO</option>
                            <option value="CONSIGNACION">CONSIGNACION</option>
                            <option value="CREDITO">CREDITO</option>
                        </select>
                    </div>
                </div>
                <div v-if="newRecibo.payment_method === 'CONSIGNACION'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Banco</label>
                        <input v-model="newRecibo.bank_name" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Referencia</label>
                        <input v-model="newRecibo.bank_reference" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <h4 class="mt-2 text-sm font-semibold text-stone-700">Conceptos</h4>
                <div v-for="(line, idx) in newRecibo.items" :key="idx" class="mb-2 flex flex-wrap items-end gap-2">
                    <div class="min-w-[12rem] flex-1">
                        <label class="mb-1 block text-xs text-stone-600">Concepto</label>
                        <input v-model="line.concept" type="text" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div class="w-36">
                        <label class="mb-1 block text-xs text-stone-600">Monto</label>
                        <input v-model.number="line.amount_pesos" type="number" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm" />
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-lg leading-none text-red-600 hover:bg-red-50"
                        aria-label="Quitar"
                        @click="newRecibo.items.splice(idx, 1)"
                    >
                        ×
                    </button>
                </div>
                <button
                    type="button"
                    class="text-sm font-medium text-teal-800 hover:underline"
                    @click="newRecibo.items.push({ concept: '', amount_pesos: 0 })"
                >
                    + Agregar concepto
                </button>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showNewRecibo = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="savingRecibo"
                        @click="createRecibo"
                    >
                        <span v-if="savingRecibo" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Generar recibo
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Detalle recibo -->
        <UiModal v-model="showInvoiceDetail" title="" max-width="max-w-3xl">
            <template v-if="selectedInvoice" #title>Recibo {{ selectedInvoice.invoice?.public_number }}</template>
            <template v-if="selectedInvoice">
                <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                    <p><strong class="text-stone-600">Tipo:</strong> {{ selectedInvoice.invoice?.tipo }}</p>
                    <p><strong class="text-stone-600">Medio:</strong> {{ selectedInvoice.invoice?.payment_method }}</p>
                    <p><strong class="text-stone-600">Fecha:</strong> {{ selectedInvoice.invoice?.fecha }}</p>
                </div>
                <div class="mt-4 rounded-lg border border-teal-200 bg-teal-50/40 p-3 text-sm">
                    <p class="mb-1">
                        <strong class="text-teal-900">Total:</strong>
                        {{ formatCurrency(selectedInvoice.invoice?.total_pesos) }}
                    </p>
                    <p class="text-stone-700">
                        <strong class="text-stone-600">En letras:</strong> {{ selectedInvoice.total_in_words }}
                    </p>
                </div>
                <div v-if="selectedInvoice.invoice?.items" class="mt-4 overflow-x-auto rounded-lg border border-stone-200">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs font-semibold text-stone-600">
                            <tr>
                                <th class="px-2 py-1.5">#</th>
                                <th class="px-2 py-1.5">Concepto</th>
                                <th class="px-2 py-1.5 text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="it in selectedInvoice.invoice.items" :key="it.line_number ?? it.id">
                                <td class="px-2 py-1.5">{{ it.line_number }}</td>
                                <td class="px-2 py-1.5">{{ it.concept }}</td>
                                <td class="px-2 py-1.5 text-end">{{ formatCurrency(it.amount_pesos) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <template v-if="selectedInvoice" #footer>
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50"
                        @click="showInvoiceDetail = false"
                    >
                        Cerrar
                    </button>
                </div>
            </template>
        </UiModal>

        <!-- Anular recibo -->
        <UiModal v-model="showCancelInvoice" title="Anular recibo" max-width="max-w-lg">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Causal</label>
                    <input v-model="cancelInvoiceForm.cancellation_reason" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Motivo</label>
                    <textarea v-model="cancelInvoiceForm.cancellation_motive" rows="3" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm" @click="showCancelInvoice = false">Cancelar</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="cancellingInvoice"
                        @click="submitCancelInvoice"
                    >
                        <span v-if="cancellingInvoice" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Anular
                    </button>
                </div>
            </template>
        </UiModal>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
