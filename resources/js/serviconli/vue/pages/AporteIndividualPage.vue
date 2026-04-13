<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { apiFetch, requireAuth } from '../api';

const props = defineProps({
    affiliateId: { type: [String, Number], default: null },
});

const loading = ref(false);
const saving = ref(false);
const previewLoading = ref(false);
const error = ref('');
const success = ref('');

const affiliate = ref(null);
const paymentMethods = ref([]);

const form = reactive({
    period_year: new Date().getFullYear(),
    period_month: new Date().getMonth() + 1,
    salary_pesos: 0,
    days_eps: 30,
    days_afp: 30,
    days_arl: 30,
    days_ccf: 30,
    contributor_type_code: '01',
    subtipo: 0,
    arl_risk_class: 1,
    payment_method: 'EFECTIVO',
    admin_fee_pesos: 0,
    notes: '',
    bank_name: '',
    bank_reference: '',
    bank_amount: 0,
    bank_deposit_type: 'LOCAL',
});

const preview = ref(null);
const alerts = ref([]);
const saveResult = ref(null);

const periodLabel = computed(() => {
    const months = [
        'Enero','Febrero','Marzo','Abril','Mayo','Junio',
        'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
    ];
    return `${months[(form.period_month || 1) - 1]} ${form.period_year}`;
});

const contributorTypes = [
    { code: '01', label: '01 – Dependiente General' },
    { code: '02', label: '02 – Servicio Doméstico' },
    { code: '03', label: '03 – Independiente' },
    { code: '16', label: '16 – Independiente Agremiado' },
    { code: '40', label: '40 – Beneficiario UPC' },
    { code: '51', label: '51 – Tiempo Parcial Subsidiado' },
    { code: '57', label: '57 – Independiente Voluntario' },
    { code: '59', label: '59 – Contratista P.S.' },
];

const arlRiskClasses = [
    { value: 1, label: 'Clase I – Riesgo Mínimo' },
    { value: 2, label: 'Clase II – Riesgo Bajo' },
    { value: 3, label: 'Clase III – Riesgo Medio' },
    { value: 4, label: 'Clase IV – Riesgo Alto' },
    { value: 5, label: 'Clase V – Riesgo Máximo' },
];

const paymentMethodOptions = computed(() => {
    if (paymentMethods.value.length) {
        return paymentMethods.value;
    }
    return [
        { code: 'EFECTIVO', label: 'Efectivo en caja' },
        { code: 'CONSIGNACION', label: 'Consignación bancaria' },
        { code: 'CREDITO', label: 'Crédito (cartera)' },
        { code: 'CUENTA_COBRO', label: 'Cuenta de cobro (patronal)' },
    ];
});

const showBankFields = computed(() => form.payment_method === 'CONSIGNACION');

function formatCurrency(value) {
    if (value == null) return '$0';
    return '$' + Number(value).toLocaleString('es-CO');
}

async function loadPrepare() {
    if (!props.affiliateId) return;
    loading.value = true;
    error.value = '';
    try {
        const res = await apiFetch(`/contributions/prepare/${props.affiliateId}`);
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            error.value = body.message || 'Error cargando datos del afiliado.';
            return;
        }
        const data = await res.json();
        affiliate.value = data.affiliate;
        paymentMethods.value = data.payment_methods || [];

        form.period_year = data.suggested_period?.year || new Date().getFullYear();
        form.period_month = data.suggested_period?.month || new Date().getMonth() + 1;
        form.salary_pesos = data.salary_pesos || 0;
        form.days_eps = data.suggested_days || 30;
        form.days_afp = data.suggested_days || 30;
        form.days_arl = data.suggested_days || 30;
        form.days_ccf = data.suggested_days || 30;
        form.contributor_type_code = data.contributor_type_code || '01';
        form.arl_risk_class = data.arl_risk_class || 1;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

let previewTimer = null;
function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(fetchPreview, 600);
}

async function fetchPreview() {
    if (!form.salary_pesos || form.salary_pesos < 1) return;
    previewLoading.value = true;
    try {
        const res = await apiFetch('/contributions/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                affiliate_id: props.affiliateId,
                period_year: form.period_year,
                period_month: form.period_month,
                salary_pesos: form.salary_pesos,
                days_eps: form.days_eps,
                days_afp: form.days_afp || form.days_eps,
                days_arl: form.days_arl || form.days_eps,
                days_ccf: form.days_ccf || form.days_eps,
                contributor_type_code: form.contributor_type_code,
                subtipo: form.subtipo,
                arl_risk_class: form.arl_risk_class,
                admin_fee_pesos: form.admin_fee_pesos,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error en preview.';
            preview.value = null;
            return;
        }
        preview.value = data;
        error.value = '';
    } catch {
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
}

async function submit() {
    saving.value = true;
    error.value = '';
    success.value = '';
    saveResult.value = null;
    alerts.value = [];

    try {
        const payload = {
            affiliate_id: Number(props.affiliateId),
            period_year: form.period_year,
            period_month: form.period_month,
            salary_pesos: form.salary_pesos,
            days_eps: form.days_eps,
            days_afp: form.days_afp || form.days_eps,
            days_arl: form.days_arl || form.days_eps,
            days_ccf: form.days_ccf || form.days_eps,
            contributor_type_code: form.contributor_type_code,
            subtipo: form.subtipo,
            arl_risk_class: form.arl_risk_class,
            payment_method: form.payment_method,
            admin_fee_pesos: form.admin_fee_pesos,
            notes: form.notes,
        };

        if (form.payment_method === 'CONSIGNACION') {
            payload.bank_name = form.bank_name;
            payload.bank_reference = form.bank_reference;
            payload.bank_amount = form.bank_amount || form.salary_pesos;
            payload.bank_deposit_type = form.bank_deposit_type;
        }

        const res = await apiFetch('/contributions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error al guardar el aporte.';
            return;
        }
        saveResult.value = data;
        alerts.value = data.alerts || [];
        success.value = `Aporte guardado exitosamente. Recibo: ${data.payment?.receipt_id || 'N/A'}`;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

watch(
    () => [
        form.salary_pesos, form.days_eps, form.days_afp, form.days_arl, form.days_ccf,
        form.contributor_type_code, form.arl_risk_class, form.admin_fee_pesos,
        form.period_year, form.period_month,
    ],
    () => schedulePreview(),
    { deep: true },
);

onMounted(() => {
    if (!requireAuth()) return;
    loadPrepare();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Aporte individual</h1>
            <p class="mt-1 text-sm text-stone-600">Flujo 3 — Liquidación y pago de seguridad social.</p>
        </div>

        <div v-if="loading" class="h-1 w-full animate-pulse rounded bg-teal-200" />

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
        <div v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ success }}</div>
        <div v-for="(alert, i) in alerts" :key="i" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-950">
            {{ alert }}
        </div>

        <section v-if="affiliate" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-stone-800">Afiliado</h2>
            <div class="mt-3 flex flex-wrap gap-6 text-sm">
                <div><strong class="text-stone-600">Documento:</strong> {{ affiliate.document_number }}</div>
                <div><strong class="text-stone-600">Nombre:</strong> {{ affiliate.full_name }}</div>
                <div><strong class="text-stone-600">Estado:</strong> {{ affiliate.status_code }}</div>
                <div><strong class="text-stone-600">Mora:</strong> {{ affiliate.mora_status || 'N/A' }}</div>
            </div>
        </section>

        <template v-if="!loading && affiliate">
            <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-stone-800">Datos de cotización</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Tipo de cotizante</label>
                        <select
                            v-model="form.contributor_type_code"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                        >
                            <option v-for="t in contributorTypes" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Clase de riesgo ARL</label>
                        <select
                            v-model.number="form.arl_risk_class"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                        >
                            <option v-for="c in arlRiskClasses" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Salario / IBC (pesos)</label>
                        <input
                            v-model.number="form.salary_pesos"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Año</label>
                        <input v-model.number="form.period_year" type="number" min="2020" max="2100" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Mes</label>
                        <input v-model.number="form.period_month" type="number" min="1" max="12" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Días EPS</label>
                        <input v-model.number="form.days_eps" type="number" min="1" max="30" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Días AFP</label>
                        <input v-model.number="form.days_afp" type="number" min="0" max="30" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Días ARL</label>
                        <input v-model.number="form.days_arl" type="number" min="0" max="30" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Días CCF</label>
                        <input v-model.number="form.days_ccf" type="number" min="0" max="30" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Tarifa administración ($)</label>
                        <input v-model.number="form.admin_fee_pesos" type="number" min="0" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-teal-200 bg-teal-50/40 p-5 shadow-sm">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-teal-900">
                    Liquidación en tiempo real
                    <span v-if="previewLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-teal-800 border-t-transparent" />
                </h2>
                <template v-if="preview">
                    <p class="mt-2 text-sm text-stone-600">Período: {{ periodLabel }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">IBC redondeado</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.ibc_rounded_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">Salud</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.health_total_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">Pensión</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.pension_total_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">ARL</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.arl_total_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">CCF</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.ccf_total_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">Fondo solidaridad</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.solidarity_fund_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">Mora</div>
                            <div
                                class="text-lg font-semibold"
                                :class="preview.subsystems?.mora_interest_pesos > 0 ? 'text-red-600' : 'text-stone-900'"
                            >
                                {{ formatCurrency(preview.subsystems?.mora_interest_pesos) }}
                            </div>
                        </div>
                        <div class="rounded-lg border border-stone-200 bg-white p-3">
                            <div class="text-stone-500">Admin</div>
                            <div class="text-lg font-semibold">{{ formatCurrency(preview.subsystems?.admin_fee_pesos) }}</div>
                        </div>
                        <div class="rounded-lg border border-teal-300 bg-teal-100 p-3">
                            <div class="font-semibold text-teal-800">Total a pagar</div>
                            <div class="text-xl font-bold text-teal-950">{{ formatCurrency(preview.total_pesos) }}</div>
                        </div>
                    </div>
                </template>
                <p v-else class="mt-3 text-sm text-stone-500">Ingrese salario y período para ver la liquidación.</p>
            </section>

            <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-stone-800">Medio de pago</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Medio</label>
                        <select
                            v-model="form.payment_method"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                        >
                            <option v-for="pm in paymentMethodOptions" :key="pm.code" :value="pm.code">{{ pm.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Observaciones</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                        />
                    </div>
                </div>
                <div v-if="showBankFields" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Banco</label>
                        <input v-model="form.bank_name" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Referencia</label>
                        <input v-model="form.bank_reference" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Monto consignado</label>
                        <input v-model.number="form.bank_amount" type="number" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-600">Tipo depósito</label>
                        <select v-model="form.bank_deposit_type" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                            <option value="LOCAL">Local</option>
                            <option value="NACIONAL">Nacional</option>
                        </select>
                    </div>
                </div>
            </section>

            <section v-if="saveResult" class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5">
                <h2 class="text-lg font-semibold text-emerald-900">Resultado</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div><strong>Liquidación:</strong> {{ saveResult.liquidation?.public_id }}</div>
                    <div><strong>Total pagado:</strong> {{ formatCurrency(saveResult.liquidation?.total_pesos) }}</div>
                    <div><strong>Recibo:</strong> {{ saveResult.payment?.receipt_id }}</div>
                    <div><strong>Estado pago:</strong> {{ saveResult.payment?.message }}</div>
                </dl>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a
                    :href="`/afiliados/${affiliateId}/ficha`"
                    class="inline-flex rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-medium text-stone-800 hover:bg-stone-50"
                >
                    Volver a ficha
                </a>
                <button
                    type="button"
                    class="inline-flex rounded-xl bg-teal-800 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-900 disabled:opacity-50"
                    :disabled="!preview || saving"
                    @click="submit"
                >
                    {{ saving ? 'Guardando…' : 'Guardar aporte' }}
                </button>
            </div>
        </template>
    </div>
</template>
