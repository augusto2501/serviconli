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
    } catch (e) {
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
        <!-- Header -->
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">
                Aporte Individual
            </h1>
            <p class="text-sm text-stone-600 mt-1">
                Flujo 3 — Liquidación y pago de seguridad social individual.
            </p>
        </div>

        <v-progress-linear v-if="loading" indeterminate color="teal-darken-3" />

        <!-- Error / Success -->
        <v-alert v-if="error" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" closable @click:close="success = ''">{{ success }}</v-alert>
        <v-alert v-for="(alert, i) in alerts" :key="i" type="warning" variant="tonal" class="mt-1">{{ alert }}</v-alert>

        <!-- Datos del afiliado -->
        <v-card v-if="affiliate" class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 text-lg">Afiliado</v-card-title>
            <v-card-text class="flex flex-wrap gap-6 text-sm">
                <div><strong>Documento:</strong> {{ affiliate.document_number }}</div>
                <div><strong>Nombre:</strong> {{ affiliate.full_name }}</div>
                <div><strong>Estado:</strong> {{ affiliate.status_code }}</div>
                <div><strong>Mora:</strong> {{ affiliate.mora_status || 'N/A' }}</div>
            </v-card-text>
        </v-card>

        <template v-if="!loading && affiliate">
            <!-- Formulario -->
            <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
                <v-card-title class="text-stone-800 text-lg">Datos de Cotización</v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <v-select
                            v-model="form.contributor_type_code"
                            :items="contributorTypes"
                            item-value="code"
                            item-title="label"
                            label="Tipo de cotizante"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-select
                            v-model="form.arl_risk_class"
                            :items="arlRiskClasses"
                            item-value="value"
                            item-title="label"
                            label="Clase de riesgo ARL"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-text-field
                            v-model.number="form.salary_pesos"
                            label="Salario / IBC (pesos)"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :rules="[v => v > 0 || 'Requerido']"
                        />
                        <v-text-field
                            v-model.number="form.period_year"
                            label="Año"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="2020"
                            :max="2100"
                        />
                        <v-text-field
                            v-model.number="form.period_month"
                            label="Mes"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="1"
                            :max="12"
                        />
                        <v-text-field
                            v-model.number="form.days_eps"
                            label="Días EPS"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="1"
                            :max="30"
                        />
                        <v-text-field
                            v-model.number="form.days_afp"
                            label="Días AFP"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="0"
                            :max="30"
                        />
                        <v-text-field
                            v-model.number="form.days_arl"
                            label="Días ARL"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="0"
                            :max="30"
                        />
                        <v-text-field
                            v-model.number="form.days_ccf"
                            label="Días CCF"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="0"
                            :max="30"
                        />
                        <v-text-field
                            v-model.number="form.admin_fee_pesos"
                            label="Tarifa administración ($)"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                            :min="0"
                        />
                    </div>
                </v-card-text>
            </v-card>

            <!-- Preview en tiempo real -->
            <v-card class="rounded-xl border border-teal-200 bg-teal-50/30">
                <v-card-title class="text-teal-800 text-lg flex items-center gap-2">
                    Liquidación en Tiempo Real
                    <v-progress-circular v-if="previewLoading" indeterminate size="18" width="2" color="teal-darken-3" />
                </v-card-title>
                <v-card-text v-if="preview">
                    <div class="text-sm text-stone-600 mb-2">Período: {{ periodLabel }}</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">IBC Redondeado</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.ibc_rounded_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Salud</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.health_total_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Pensión</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.pension_total_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">ARL</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.arl_total_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">CCF</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.ccf_total_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Fondo Solidaridad</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.solidarity_fund_pesos) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Mora</div>
                            <div class="text-lg font-semibold" :class="preview.subsystems?.mora_interest_pesos > 0 ? 'text-red-600' : 'text-stone-900'">
                                {{ formatCurrency(preview.subsystems?.mora_interest_pesos) }}
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Admin</div>
                            <div class="text-lg font-semibold text-stone-900">{{ formatCurrency(preview.subsystems?.admin_fee_pesos) }}</div>
                        </div>
                        <div class="bg-teal-100 rounded-lg p-3 border border-teal-300">
                            <div class="text-teal-700 font-semibold">TOTAL A PAGAR</div>
                            <div class="text-xl font-bold text-teal-900">{{ formatCurrency(preview.total_pesos) }}</div>
                        </div>
                    </div>
                </v-card-text>
                <v-card-text v-else class="text-sm text-stone-500">
                    Ingrese los datos de cotización para ver la liquidación.
                </v-card-text>
            </v-card>

            <!-- Medio de Pago -->
            <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
                <v-card-title class="text-stone-800 text-lg">Medio de Pago</v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <v-select
                            v-model="form.payment_method"
                            :items="paymentMethods.length ? paymentMethods : [
                                { code: 'EFECTIVO', label: 'Efectivo en caja' },
                                { code: 'CONSIGNACION', label: 'Consignación bancaria' },
                                { code: 'CREDITO', label: 'Crédito (cartera)' },
                                { code: 'CUENTA_COBRO', label: 'Cuenta de cobro (patronal)' },
                            ]"
                            item-value="code"
                            item-title="label"
                            label="Medio de pago"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-textarea
                            v-model="form.notes"
                            label="Observaciones"
                            variant="outlined"
                            density="comfortable"
                            rows="2"
                            auto-grow
                        />
                    </div>

                    <!-- Campos Consignación -->
                    <div v-if="showBankFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <v-text-field
                            v-model="form.bank_name"
                            label="Nombre del banco"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-text-field
                            v-model="form.bank_reference"
                            label="Referencia / No. consignación"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-text-field
                            v-model.number="form.bank_amount"
                            label="Monto consignado ($)"
                            type="number"
                            variant="outlined"
                            density="comfortable"
                        />
                        <v-select
                            v-model="form.bank_deposit_type"
                            :items="[{ value: 'LOCAL', title: 'Local' }, { value: 'NACIONAL', title: 'Nacional' }]"
                            label="Tipo de depósito"
                            variant="outlined"
                            density="comfortable"
                        />
                    </div>
                </v-card-text>
            </v-card>

            <!-- Resultado guardado -->
            <v-card v-if="saveResult" class="rounded-xl border border-green-200 bg-green-50/50">
                <v-card-title class="text-green-800 text-lg">Resultado</v-card-title>
                <v-card-text class="text-sm">
                    <div><strong>No. Liquidación:</strong> {{ saveResult.liquidation?.public_id }}</div>
                    <div><strong>Total Pagado:</strong> {{ formatCurrency(saveResult.liquidation?.total_pesos) }}</div>
                    <div><strong>Recibo:</strong> {{ saveResult.payment?.receipt_id }}</div>
                    <div><strong>Estado Pago:</strong> {{ saveResult.payment?.message }}</div>
                </v-card-text>
            </v-card>

            <!-- Botón Guardar -->
            <div class="flex justify-end gap-3">
                <v-btn variant="outlined" color="stone" :href="`/afiliados/${affiliateId}/ficha`">
                    Volver a ficha
                </v-btn>
                <v-btn
                    color="teal-darken-3"
                    size="large"
                    :loading="saving"
                    :disabled="!preview || saving"
                    @click="submit"
                >
                    Guardar Aporte
                </v-btn>
            </div>
        </template>
    </div>
</template>
