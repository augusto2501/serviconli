<script setup>
import { ref, reactive, onMounted } from 'vue';
import { apiFetch, requireAuth, logoutAndRedirect } from '../api';

const activeTab = ref('cuentas');
const snackbar = reactive({ show: false, text: '', color: 'teal-darken-3' });
function notify(text, color = 'teal-darken-3') {
    snackbar.text = text;
    snackbar.color = color;
    snackbar.show = true;
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
const cuentaHeaders = [
    { title: 'Número', key: 'cuenta_number' },
    { title: 'Pagador', key: 'payer.razon_social' },
    { title: 'Período', key: 'period_cobro' },
    { title: 'Modo', key: 'generation_mode' },
    { title: 'Total Oportuno', key: 'total_1', align: 'end' },
    { title: 'Total Mora', key: 'total_2', align: 'end' },
    { title: 'Estado', key: 'status' },
    { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
];

const detailHeaders = [
    { title: 'Afiliado', key: 'affiliate_id' },
    { title: 'Salud', key: 'health_pesos', align: 'end' },
    { title: 'Pensión', key: 'pension_pesos', align: 'end' },
    { title: 'ARL', key: 'arl_pesos', align: 'end' },
    { title: 'CCF', key: 'ccf_pesos', align: 'end' },
    { title: 'Admin', key: 'admin_pesos', align: 'end' },
    { title: 'Total', key: 'total_pesos', align: 'end' },
];

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

async function selectCuenta(_e, row) {
    const item = row?.item;
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
const invoiceFilter = reactive({ tipo: null, estado: null });
const invoiceHeaders = [
    { title: 'Número', key: 'public_number' },
    { title: 'Fecha', key: 'fecha' },
    { title: 'Tipo', key: 'tipo' },
    { title: 'Medio', key: 'payment_method' },
    { title: 'Total', key: 'total_pesos', align: 'end' },
    { title: 'Estado', key: 'estado' },
    { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
];

const itemHeaders = [
    { title: '#', key: 'line_number', width: '48px' },
    { title: 'Concepto', key: 'concept' },
    { title: 'Monto', key: 'amount_pesos', align: 'end' },
];

async function loadInvoices() {
    loadingInvoices.value = true;
    try {
        const params = new URLSearchParams();
        if (invoiceFilter.tipo) params.set('tipo', invoiceFilter.tipo);
        if (invoiceFilter.estado) params.set('estado', invoiceFilter.estado);
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

async function selectInvoice(_e, row) {
    const item = row?.item;
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

/** Colores alineados con liquidación por lotes + identidad teal Serviconli */
function statusColorCuenta(s) {
    return (
        {
            PRE_CUENTA: 'orange',
            DEFINITIVA: 'teal-darken-2',
            PAGADA: 'green',
            ANULADA: 'error',
        }[s] || 'grey'
    );
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
                <h1 class="font-serif-svc text-2xl font-bold text-stone-900 mt-2">Cartera y facturación</h1>
                <p class="text-sm text-stone-600 mt-1">Flujos 5, 6 y 7 — Cuentas de cobro y recibos de caja.</p>
            </div>
            <v-btn variant="outlined" color="teal-darken-3" @click="logoutAndRedirect">Cerrar sesión</v-btn>
        </div>

        <v-card class="rounded-xl border border-stone-200/90 bg-white/90 overflow-hidden shadow-none">
            <v-tabs v-model="activeTab" color="teal-darken-3" slider-color="teal-darken-3" align-tabs="start">
                <v-tab value="cuentas" class="text-none">Cuentas de cobro</v-tab>
                <v-tab value="recibos" class="text-none">Recibos de caja</v-tab>
            </v-tabs>

            <v-window v-model="activeTab">
                <v-window-item value="cuentas">
                    <v-card flat class="bg-transparent">
                        <v-card-title class="text-stone-800 text-lg flex flex-wrap items-center gap-3">
                            <span>Cuentas de cobro</span>
                            <v-spacer />
                            <v-btn color="teal-darken-3" @click="showNewCuenta = true">Nueva cuenta</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <v-data-table
                                :headers="cuentaHeaders"
                                :items="cuentas"
                                :loading="loadingCuentas"
                                density="comfortable"
                                class="text-sm"
                                hover
                                @click:row="selectCuenta"
                            >
                                <template #item.total_1="{ item }">
                                    {{ formatCurrency(item.total_1) }}
                                </template>
                                <template #item.total_2="{ item }">
                                    {{ formatCurrency(item.total_2) }}
                                </template>
                                <template #item.status="{ item }">
                                    <v-chip :color="statusColorCuenta(item.status)" size="small" variant="flat">
                                        {{ item.status }}
                                    </v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <v-btn
                                        v-if="item.status === 'PRE_CUENTA'"
                                        size="small"
                                        variant="text"
                                        color="teal-darken-3"
                                        @click.stop="openDefinitiva(item)"
                                    >
                                        Definitiva
                                    </v-btn>
                                    <v-btn
                                        v-if="item.status === 'DEFINITIVA'"
                                        size="small"
                                        variant="text"
                                        color="teal-darken-3"
                                        @click.stop="openPay(item)"
                                    >
                                        Pagar
                                    </v-btn>
                                    <v-btn
                                        v-if="item.status !== 'PAGADA' && item.status !== 'ANULADA'"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        @click.stop="openCancelCuenta(item)"
                                    >
                                        Anular
                                    </v-btn>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>
                </v-window-item>

                <v-window-item value="recibos">
                    <v-card flat class="bg-transparent">
                        <v-card-title class="text-stone-800 text-lg flex flex-wrap items-center gap-3">
                            <span>Recibos de caja</span>
                            <v-spacer />
                            <v-btn color="teal-darken-3" @click="showNewRecibo = true">Nuevo recibo</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                                <v-select
                                    v-model="invoiceFilter.tipo"
                                    :items="tiposRecibo"
                                    label="Tipo"
                                    clearable
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details
                                    @update:model-value="loadInvoices"
                                />
                                <v-select
                                    v-model="invoiceFilter.estado"
                                    :items="['ACTIVO', 'ANULADO']"
                                    label="Estado"
                                    clearable
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details
                                    @update:model-value="loadInvoices"
                                />
                            </div>
                            <v-data-table
                                :headers="invoiceHeaders"
                                :items="invoices"
                                :loading="loadingInvoices"
                                density="comfortable"
                                class="text-sm"
                                hover
                                @click:row="selectInvoice"
                            >
                                <template #item.total_pesos="{ item }">
                                    {{ formatCurrency(item.total_pesos) }}
                                </template>
                                <template #item.estado="{ item }">
                                    <v-chip
                                        :color="item.estado === 'ACTIVO' ? 'teal-darken-2' : 'error'"
                                        size="small"
                                        variant="flat"
                                    >
                                        {{ item.estado }}
                                    </v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <v-btn
                                        v-if="item.estado === 'ACTIVO'"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        @click.stop="openCancelInvoice(item)"
                                    >
                                        Anular
                                    </v-btn>
                                </template>
                            </v-data-table>
                        </v-card-text>
                    </v-card>
                </v-window-item>
            </v-window>
        </v-card>

        <!-- Detalle cuenta -->
        <v-dialog v-model="showCuentaDetail" max-width="880" scrollable>
            <v-card v-if="selectedCuenta" class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800 font-serif-svc text-lg">
                    Cuenta {{ selectedCuenta.cuenta_number }}
                </v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm mb-4">
                        <p><strong class="text-stone-600">Pagador:</strong> {{ selectedCuenta.payer?.razon_social }}</p>
                        <p>
                            <strong class="text-stone-600">Período:</strong>
                            {{ selectedCuenta.period_year }}-{{ String(selectedCuenta.period_month).padStart(2, '0') }}
                        </p>
                        <p><strong class="text-stone-600">Modo:</strong> {{ selectedCuenta.generation_mode }}</p>
                    </div>
                    <v-divider class="border-stone-200 mb-4" />
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 text-sm mb-4">
                        <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                            <div class="text-stone-500 text-xs">EPS</div>
                            <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_eps) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                            <div class="text-stone-500 text-xs">AFP</div>
                            <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_afp) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                            <div class="text-stone-500 text-xs">ARL</div>
                            <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_arl) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                            <div class="text-stone-500 text-xs">CCF</div>
                            <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_ccf) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-stone-50/80 p-2">
                            <div class="text-stone-500 text-xs">Admin</div>
                            <div class="font-semibold">{{ formatCurrency(selectedCuenta.total_admin) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-teal-50/80 border-teal-200/80 p-2">
                            <div class="text-teal-800 text-xs font-medium">Total oportuno</div>
                            <div class="font-bold text-teal-950">{{ formatCurrency(selectedCuenta.total_1) }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm mb-4">
                        <div class="rounded-lg border border-stone-200 p-2">
                            <strong class="text-stone-600">Intereses mora:</strong>
                            {{ formatCurrency(selectedCuenta.interest_mora) }}
                        </div>
                        <div class="rounded-lg border border-teal-200 bg-teal-50/50 p-2">
                            <strong class="text-teal-900">Total con mora:</strong>
                            {{ formatCurrency(selectedCuenta.total_2) }}
                        </div>
                    </div>
                    <h3 class="text-stone-800 text-base font-semibold mb-2">Detalle por afiliado</h3>
                    <v-data-table
                        v-if="selectedCuenta.details"
                        :headers="detailHeaders"
                        :items="selectedCuenta.details"
                        density="compact"
                        items-per-page="-1"
                        hide-default-footer
                    >
                        <template #item.total_pesos="{ item }">
                            {{ formatCurrency(item.total_pesos) }}
                        </template>
                    </v-data-table>
                </v-card-text>
                <v-card-actions class="border-t border-stone-200/90">
                    <v-spacer />
                    <v-btn variant="outlined" color="stone" @click="showCuentaDetail = false">Cerrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Nueva cuenta -->
        <v-dialog v-model="showNewCuenta" max-width="520">
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Generar cuenta de cobro</v-card-title>
                <v-card-text class="space-y-3">
                    <v-select
                        v-model="newCuenta.payer_id"
                        :items="payers"
                        item-title="razon_social"
                        item-value="id"
                        label="Pagador"
                        variant="outlined"
                        density="comfortable"
                    />
                    <div class="grid grid-cols-2 gap-3">
                        <v-text-field
                            v-model.number="newCuenta.period_year"
                            label="Año"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-text-field
                            v-model.number="newCuenta.period_month"
                            label="Mes"
                            type="number"
                            min="1"
                            max="12"
                            variant="outlined"
                            density="comfortable"
                        />
                    </div>
                    <v-select
                        v-model="newCuenta.mode"
                        :items="['PLENO', 'SOLO_APORTES', 'SOLO_AFILIACIONES']"
                        label="Modo de generación"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showNewCuenta = false">Cancelar</v-btn>
                    <v-btn color="teal-darken-3" :loading="savingCuenta" @click="createCuenta">Generar pre-cuenta</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Definitiva -->
        <v-dialog v-model="showDefinitiva" max-width="520">
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Convertir a definitiva</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="definitivaForm.payment_date_1"
                        label="Fecha pago oportuno"
                        type="date"
                        variant="outlined"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-text-field
                        v-model="definitivaForm.payment_date_2"
                        label="Fecha pago con mora"
                        type="date"
                        variant="outlined"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-text-field
                        v-model.number="definitivaForm.mora_days"
                        label="Días de mora (para intereses)"
                        type="number"
                        min="0"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showDefinitiva = false">Cancelar</v-btn>
                    <v-btn color="teal-darken-3" :loading="savingDefinitiva" @click="submitDefinitiva">Confirmar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Pagar -->
        <v-dialog v-model="showPay" max-width="520">
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Pagar cuenta de cobro</v-card-title>
                <v-card-text v-if="payingCuenta">
                    <v-alert type="info" variant="tonal" color="teal-darken-3" class="mb-4">
                        <strong>{{ payingCuenta.isOportuno ? 'Pago oportuno' : 'Pago con mora' }}:</strong>
                        {{ formatCurrency(payingCuenta.isOportuno ? payingCuenta.total_1 : payingCuenta.total_2) }}
                    </v-alert>
                    <v-select
                        v-model="payForm.payment_method"
                        :items="['EFECTIVO', 'CONSIGNACION']"
                        label="Medio de pago"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-text-field
                        v-model.number="payForm.amount_pesos"
                        label="Monto"
                        type="number"
                        prefix="$"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-text-field
                        v-if="payForm.payment_method === 'CONSIGNACION'"
                        v-model="payForm.bank_name"
                        label="Banco"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-text-field
                        v-if="payForm.payment_method === 'CONSIGNACION'"
                        v-model="payForm.bank_reference"
                        label="Referencia"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showPay = false">Cancelar</v-btn>
                    <v-btn color="teal-darken-3" :loading="paying" @click="submitPay">Registrar pago</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Anular cuenta -->
        <v-dialog v-model="showCancelCuenta" max-width="520">
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Anular cuenta de cobro</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="cancelForm.cancellation_reason"
                        label="Causal"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-textarea v-model="cancelForm.cancellation_motive" label="Motivo" rows="3" variant="outlined" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showCancelCuenta = false">Cancelar</v-btn>
                    <v-btn color="error" :loading="cancelling" @click="submitCancelCuenta">Anular</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Nuevo recibo -->
        <v-dialog v-model="showNewRecibo" max-width="640" scrollable>
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Nuevo recibo de caja</v-card-title>
                <v-card-text>
                    <v-select
                        v-model="newRecibo.tipo"
                        :items="tiposRecibo"
                        label="Tipo"
                        variant="outlined"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-select
                        v-model="newRecibo.payment_method"
                        :items="['EFECTIVO', 'CONSIGNACION', 'CREDITO']"
                        label="Medio de pago"
                        variant="outlined"
                        density="comfortable"
                        class="mb-2"
                    />
                    <v-text-field
                        v-if="newRecibo.payment_method === 'CONSIGNACION'"
                        v-model="newRecibo.bank_name"
                        label="Banco"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-text-field
                        v-if="newRecibo.payment_method === 'CONSIGNACION'"
                        v-model="newRecibo.bank_reference"
                        label="Referencia"
                        variant="outlined"
                        density="comfortable"
                    />
                    <h4 class="text-stone-700 text-sm font-semibold mt-4 mb-2">Conceptos</h4>
                    <div v-for="(line, idx) in newRecibo.items" :key="idx" class="flex flex-wrap gap-2 mb-2 items-center">
                        <v-text-field
                            v-model="line.concept"
                            label="Concepto"
                            variant="outlined"
                            density="compact"
                            class="flex-1 min-w-[12rem]"
                        />
                        <v-text-field
                            v-model.number="line.amount_pesos"
                            label="Monto"
                            type="number"
                            prefix="$"
                            variant="outlined"
                            density="compact"
                            class="w-36"
                        />
                        <v-btn icon size="small" variant="text" color="error" @click="newRecibo.items.splice(idx, 1)">
                            <span class="text-lg leading-none">×</span>
                        </v-btn>
                    </div>
                    <v-btn size="small" variant="text" color="teal-darken-3" @click="newRecibo.items.push({ concept: '', amount_pesos: 0 })">
                        + Agregar concepto
                    </v-btn>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showNewRecibo = false">Cancelar</v-btn>
                    <v-btn color="teal-darken-3" :loading="savingRecibo" @click="createRecibo">Generar recibo</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Detalle recibo -->
        <v-dialog v-model="showInvoiceDetail" max-width="720" scrollable>
            <v-card v-if="selectedInvoice" class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800 font-serif-svc text-lg">
                    Recibo {{ selectedInvoice.invoice?.public_number }}
                </v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm mb-4">
                        <p><strong class="text-stone-600">Tipo:</strong> {{ selectedInvoice.invoice?.tipo }}</p>
                        <p><strong class="text-stone-600">Medio:</strong> {{ selectedInvoice.invoice?.payment_method }}</p>
                        <p><strong class="text-stone-600">Fecha:</strong> {{ selectedInvoice.invoice?.fecha }}</p>
                    </div>
                    <div class="rounded-lg border border-teal-200 bg-teal-50/40 p-3 mb-4 text-sm">
                        <p class="mb-1">
                            <strong class="text-teal-900">Total:</strong>
                            {{ formatCurrency(selectedInvoice.invoice?.total_pesos) }}
                        </p>
                        <p class="text-stone-700">
                            <strong class="text-stone-600">En letras:</strong> {{ selectedInvoice.total_in_words }}
                        </p>
                    </div>
                    <v-data-table
                        v-if="selectedInvoice.invoice?.items"
                        :headers="itemHeaders"
                        :items="selectedInvoice.invoice.items"
                        density="compact"
                        items-per-page="-1"
                        hide-default-footer
                    >
                        <template #item.amount_pesos="{ item }">
                            {{ formatCurrency(item.amount_pesos) }}
                        </template>
                    </v-data-table>
                </v-card-text>
                <v-card-actions class="border-t border-stone-200/90">
                    <v-spacer />
                    <v-btn variant="outlined" color="stone" @click="showInvoiceDetail = false">Cerrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Anular recibo -->
        <v-dialog v-model="showCancelInvoice" max-width="520">
            <v-card class="rounded-xl border border-stone-200/90">
                <v-card-title class="text-stone-800">Anular recibo</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="cancelInvoiceForm.cancellation_reason"
                        label="Causal"
                        variant="outlined"
                        density="comfortable"
                    />
                    <v-textarea v-model="cancelInvoiceForm.cancellation_motive" label="Motivo" rows="3" variant="outlined" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="outlined" @click="showCancelInvoice = false">Cancelar</v-btn>
                    <v-btn color="error" :loading="cancellingInvoice" @click="submitCancelInvoice">Anular</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="bottom">
            {{ snackbar.text }}
        </v-snackbar>
    </div>
</template>
