<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
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

const loadingDash = ref(true);
const dash = ref(null);
const dashError = ref('');

async function loadDashboard() {
    loadingDash.value = true;
    dashError.value = '';
    try {
        const res = await apiFetch('/dashboard');
        dash.value = await jsonOrMessage(res);
    } catch (e) {
        dashError.value = e.message || 'No se pudo cargar el dashboard.';
        notify(dashError.value, 'error');
    } finally {
        loadingDash.value = false;
    }
}

const reportTab = ref('daily');
const reportDate = ref(new Date().toISOString().slice(0, 10));
const reportLoading = ref(false);
const reportData = ref(null);
const reportError = ref('');

const reportTabs = [
    { id: 'daily', label: 'Relación diaria' },
    { id: 'mora', label: 'Cobro mora' },
    { id: 'advisors', label: 'Por asesor' },
    { id: 'employers', label: 'Por empresa' },
    { id: 'cash', label: 'Caja del día' },
    { id: 'eod', label: 'Fin de día' },
];

async function loadReport() {
    reportLoading.value = true;
    reportError.value = '';
    try {
        const d = reportDate.value;
        const paths = {
            daily: `/reports/daily-contributions?date=${encodeURIComponent(d)}`,
            mora: '/reports/mora',
            advisors: '/reports/affiliates-by-advisor',
            employers: '/reports/affiliates-by-employer',
            cash: `/reports/cash-reconciliation?date=${encodeURIComponent(d)}`,
            eod: `/reports/end-of-day?date=${encodeURIComponent(d)}`,
        };
        const res = await apiFetch(paths[reportTab.value]);
        reportData.value = await jsonOrMessage(res);
    } catch (e) {
        reportError.value = e.message || 'Error en reporte';
        reportData.value = null;
        notify(reportError.value, 'error');
    } finally {
        reportLoading.value = false;
    }
}

watch([reportTab, reportDate], () => {
    loadReport();
});

onMounted(() => {
    if (!requireAuth()) return;
    loadDashboard();
    loadReport();
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <div>
            <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
            <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Dashboard gerencial</h1>
            <p class="mt-1 text-sm text-stone-600">RF-114 — Indicadores en tiempo real.</p>
        </div>

        <div v-if="dashError && !dash" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ dashError }}</div>

        <template v-if="dash">
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase text-stone-500">Afiliados activos</div>
                    <div class="mt-1 text-2xl font-bold text-teal-900">{{ dash.affiliates?.active ?? 0 }}</div>
                    <div class="mt-2 text-xs text-stone-600">
                        Total {{ dash.affiliates?.total }} · Mora {{ dash.affiliates?.mora }} · Inactivos {{ dash.affiliates?.inactive }}
                    </div>
                </div>
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase text-stone-500">Recaudo mes actual</div>
                    <div class="mt-1 text-2xl font-bold text-teal-900">{{ fmtPesos(dash.revenue?.current_month_pesos) }}</div>
                    <div class="mt-2 text-xs text-stone-600">
                        Mes ant. {{ fmtPesos(dash.revenue?.previous_month_pesos) }} · Δ {{ dash.revenue?.variation_percent }}%
                    </div>
                </div>
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase text-stone-500">PILA (mes)</div>
                    <div class="mt-1 text-lg font-semibold text-stone-900">
                        Liq. {{ dash.pila?.liquidations_total }} · Confirm. {{ dash.pila?.liquidations_confirmed }}
                    </div>
                    <div class="mt-1 text-xs text-stone-600">Archivos generados: {{ dash.pila?.files_generated }}</div>
                </div>
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <div class="text-xs font-medium uppercase text-stone-500">Afiliaciones completadas</div>
                    <div class="mt-1 text-2xl font-bold text-stone-900">{{ dash.enrollments?.current_month }}</div>
                    <div class="mt-1 text-xs text-stone-600">Mes anterior: {{ dash.enrollments?.previous_month }}</div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-stone-800">Distribución por tipo de cliente</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="(n, k) in dash.distribution?.by_client_type || {}" :key="k" class="flex justify-between">
                            <span class="text-stone-600">{{ k }}</span>
                            <span class="font-medium">{{ n }}</span>
                        </li>
                        <li v-if="!Object.keys(dash.distribution?.by_client_type || {}).length" class="text-stone-500">Sin datos.</li>
                    </ul>
                </div>
                <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-stone-800">Por operador PILA</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="(n, k) in dash.distribution?.by_pila_operator || {}" :key="k" class="flex justify-between">
                            <span class="text-stone-600">{{ k }}</span>
                            <span class="font-medium">{{ n }}</span>
                        </li>
                        <li v-if="!Object.keys(dash.distribution?.by_pila_operator || {}).length" class="text-stone-500">Sin datos.</li>
                    </ul>
                </div>
            </section>

            <section class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4">
                <h2 class="text-sm font-semibold text-amber-950">Alertas</h2>
                <div class="mt-2 flex flex-wrap gap-4 text-sm">
                    <span>Mora &gt; 90 días: <strong>{{ dash.alerts?.mora_over_90_days }}</strong></span>
                    <span>Beneficiarios próximos a 18 años: <strong>{{ dash.alerts?.beneficiaries_turning_18 }}</strong></span>
                    <span>Cert. estudiante por vencer: <strong>{{ dash.alerts?.student_certs_expiring }}</strong></span>
                </div>
                <p class="mt-2 text-xs text-stone-600">Generado: {{ dash.generated_at }}</p>
            </section>
        </template>

        <div v-else-if="loadingDash" class="py-12 text-center text-sm text-stone-500">Cargando indicadores…</div>

        <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-800">Reportes operativos</h2>
            <p class="mt-1 text-sm text-stone-600">RF-115 — Consultas por fecha cuando aplica.</p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Fecha</label>
                    <input v-model="reportDate" type="date" class="rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="t in reportTabs"
                        :key="t.id"
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-medium"
                        :class="reportTab === t.id ? 'bg-teal-800 text-white' : 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50'"
                        @click="reportTab = t.id"
                    >
                        {{ t.label }}
                    </button>
                </div>
            </div>

            <div v-if="reportLoading" class="mt-6 py-8 text-center text-sm text-stone-500">Cargando reporte…</div>
            <div v-else-if="reportError && !reportData" class="mt-4 text-sm text-red-800">{{ reportError }}</div>
            <div v-else-if="reportData" class="mt-6 space-y-4 text-sm">
                <!-- daily -->
                <template v-if="reportTab === 'daily'">
                    <p><strong>Fecha:</strong> {{ reportData.date }}</p>
                    <p>Afiliaciones / reingresos (pagos del día): {{ reportData.affiliations_count }} · Aportes: {{ reportData.contributions_count }}</p>
                    <p class="text-lg font-semibold text-teal-900">Total: {{ fmtPesos(reportData.total_pesos) }}</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="(v, k) in reportData.by_payment_method || {}" :key="k">{{ k }}: {{ fmtPesos(v) }}</li>
                    </ul>
                </template>

                <!-- mora -->
                <template v-if="reportTab === 'mora'">
                    <p>Total en mora: <strong>{{ reportData.total_in_mora }}</strong></p>
                    <ul class="mt-2 grid grid-cols-2 gap-1 sm:grid-cols-3">
                        <li v-for="(v, k) in reportData.by_level || {}" :key="k">{{ k }}: {{ v }}</li>
                    </ul>
                    <div class="mt-4 overflow-x-auto rounded-lg border border-stone-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-stone-50 text-left">
                                <tr>
                                    <th class="px-2 py-1">Id</th>
                                    <th class="px-2 py-1">Nombre</th>
                                    <th class="px-2 py-1">Estado</th>
                                    <th class="px-2 py-1">Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in reportData.top_delinquent || []" :key="row.affiliate_id">
                                    <td class="px-2 py-1 font-mono">{{ row.affiliate_id }}</td>
                                    <td class="px-2 py-1">{{ row.name }}</td>
                                    <td class="px-2 py-1">{{ row.status }}</td>
                                    <td class="px-2 py-1">{{ row.client_type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- advisors -->
                <template v-if="reportTab === 'advisors'">
                    <div class="overflow-x-auto rounded-lg border border-stone-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-600">
                                <tr>
                                    <th class="px-3 py-2">Código</th>
                                    <th class="px-3 py-2">Nombre</th>
                                    <th class="px-3 py-2 text-end">Comisiones (reg.)</th>
                                    <th class="px-3 py-2 text-end">Com. nueva</th>
                                    <th class="px-3 py-2 text-end">Com. recurrente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in reportData" :key="r.advisor_id" class="border-t border-stone-100">
                                    <td class="px-3 py-2 font-mono text-xs">{{ r.code }}</td>
                                    <td class="px-3 py-2">{{ r.name }}</td>
                                    <td class="px-3 py-2 text-end">{{ r.affiliates_count }}</td>
                                    <td class="px-3 py-2 text-end">{{ fmtPesos(r.commission_new) }}</td>
                                    <td class="px-3 py-2 text-end">{{ fmtPesos(r.commission_recurring) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- employers -->
                <template v-if="reportTab === 'employers'">
                    <div class="overflow-x-auto rounded-lg border border-stone-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-600">
                                <tr>
                                    <th class="px-3 py-2">Pagador</th>
                                    <th class="px-3 py-2 text-end">Activos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in reportData" :key="r.payer_id" class="border-t border-stone-100">
                                    <td class="px-3 py-2">{{ r.razon_social }}</td>
                                    <td class="px-3 py-2 text-end">{{ r.active_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- cash -->
                <template v-if="reportTab === 'cash'">
                    <p><strong>Fecha:</strong> {{ reportData.date }} · <strong>Estado:</strong> {{ reportData.status }}</p>
                    <p class="text-lg font-semibold">Total: {{ fmtPesos(reportData.grand_total_pesos) }}</p>
                    <div v-if="reportData.concepts && Object.keys(reportData.concepts).length" class="overflow-x-auto rounded-lg border border-stone-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-stone-50 text-left">
                                <tr>
                                    <th class="px-2 py-1">Concepto</th>
                                    <th class="px-2 py-1 text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(v, k) in reportData.concepts" :key="k">
                                    <td class="px-2 py-1 font-mono text-stone-700">{{ k }}</td>
                                    <td class="px-2 py-1 text-end">{{ fmtPesos(v) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- eod -->
                <template v-if="reportTab === 'eod'">
                    <p><strong>Fecha:</strong> {{ reportData.date }}</p>
                    <p>Planillas confirmadas (movimiento hoy): {{ reportData.liquidations_confirmed_today }}</p>
                    <div v-if="reportData.payments" class="mt-3 rounded-lg border border-stone-200 p-3">
                        <h3 class="font-semibold text-stone-800">Pagos del día</h3>
                        <p class="mt-1">Total: {{ fmtPesos(reportData.payments.total_pesos) }}</p>
                    </div>
                    <div v-if="reportData.cash_reconciliation" class="mt-3 rounded-lg border border-stone-200 p-3">
                        <h3 class="font-semibold text-stone-800">Caja</h3>
                        <p>Estado: {{ reportData.cash_reconciliation.status }} · Total {{ fmtPesos(reportData.cash_reconciliation.grand_total_pesos) }}</p>
                    </div>
                </template>
            </div>
        </section>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
