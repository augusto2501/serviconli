<script setup>
import { ref, computed, onMounted } from 'vue';
import { apiFetch, requireAuth, logoutAndRedirect } from '../api';

const businessDate = ref(new Date().toISOString().slice(0, 10));
const loading = ref(false);
const error = ref('');
const success = ref('');
const data = ref(null);

const isClosed = computed(() => data.value?.status === 'CERRADO');

function fmt(n) {
    if (n == null) return '$0';
    return '$' + Number(n).toLocaleString('es-CO');
}

async function load() {
    loading.value = true;
    error.value = '';
    success.value = '';
    try {
        const res = await apiFetch(`/cash-reconciliation?date=${encodeURIComponent(businessDate.value)}`);
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Error');
        if (json.status === 'NONE') {
            data.value = null;
            return;
        }
        data.value = json;
    } catch (e) {
        error.value = e.message || 'Error al cargar';
        data.value = null;
    } finally {
        loading.value = false;
    }
}

async function recalculate() {
    loading.value = true;
    error.value = '';
    success.value = '';
    try {
        const res = await apiFetch('/cash-reconciliation/recalculate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date: businessDate.value }),
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Error');
        data.value = json;
        success.value = 'Cuadre recalculado.';
    } catch (e) {
        error.value = e.message || 'Error';
    } finally {
        loading.value = false;
    }
}

async function closeDay() {
    if (!confirm('¿Cerrar el día? No se podrá recalcular después.')) return;
    loading.value = true;
    error.value = '';
    success.value = '';
    try {
        const res = await apiFetch('/cash-reconciliation/close', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date: businessDate.value }),
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Error');
        data.value = json.reconciliation;
        success.value = 'Día cerrado. Total: ' + fmt(json.close?.grand_total_pesos);
    } catch (e) {
        error.value = e.message || 'Error';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (!requireAuth()) return;
    load();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
                <h1 class="font-serif-svc text-2xl font-bold text-stone-900 mt-2">Cuadre de caja</h1>
                <p class="text-sm text-stone-600 mt-1">Flujo 10 — Tres líneas (afiliaciones, aportes, cuentas cobro) y cierre del día.</p>
            </div>
            <v-btn variant="outlined" color="teal-darken-3" @click="logoutAndRedirect">Cerrar sesión</v-btn>
        </div>

        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-text class="flex flex-wrap gap-4 items-end">
                <v-text-field
                    v-model="businessDate"
                    type="date"
                    label="Fecha negocio"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    class="max-w-xs"
                />
                <v-btn color="teal-darken-3" :loading="loading" @click="load">Consultar</v-btn>
                <v-btn
                    v-if="!isClosed"
                    variant="outlined"
                    color="teal-darken-3"
                    :loading="loading"
                    @click="recalculate"
                >
                    Recalcular
                </v-btn>
                <v-btn v-if="!isClosed" color="teal-darken-3" :loading="loading" @click="closeDay">Cerrar día</v-btn>
            </v-card-text>
        </v-card>

        <v-alert v-if="error" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" closable @click:close="success = ''">{{ success }}</v-alert>

        <v-card v-if="data" class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 flex items-center gap-2 flex-wrap">
                Estado:
                <v-chip :color="isClosed ? 'grey' : 'orange'" size="small" variant="flat">{{ data.status }}</v-chip>
            </v-card-title>
            <v-card-text>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-lg border border-teal-200 bg-teal-50/40 p-4">
                        <h3 class="text-teal-900 font-semibold mb-2">Línea 1 — Afiliaciones</h3>
                        <p class="text-sm text-stone-600">Recibos: {{ data.affiliations_line?.total_receipts ?? 0 }}</p>
                        <p class="text-sm">Efectivo {{ fmt(data.affiliations_line?.total_efectivo) }}</p>
                        <p class="text-sm">Consignación {{ fmt(data.affiliations_line?.total_consignacion) }}</p>
                        <p class="text-sm">Crédito {{ fmt(data.affiliations_line?.total_credito) }}</p>
                        <p class="text-sm">Cuenta cobro {{ fmt(data.affiliations_line?.total_cuenta_cobro) }}</p>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-white p-4">
                        <h3 class="text-stone-800 font-semibold mb-2">Línea 2 — Aportes</h3>
                        <p class="text-sm">Aporte POS {{ fmt(data.contributions_line?.total_aporte_pos) }}</p>
                        <p class="text-sm">Admin {{ fmt(data.contributions_line?.total_admin) }}</p>
                        <p class="text-sm">Intereses mora {{ fmt(data.contributions_line?.total_interest_mora) }}</p>
                        <p class="text-sm mt-2">Efectivo {{ fmt(data.contributions_line?.total_efectivo) }}</p>
                        <p class="text-sm">Consignación {{ fmt(data.contributions_line?.total_consignacion) }}</p>
                    </div>
                    <div class="rounded-lg border border-stone-200 bg-white p-4">
                        <h3 class="text-stone-800 font-semibold mb-2">Línea 3 — Cuentas cobro</h3>
                        <p class="text-sm">Efectivo {{ fmt(data.cuentas_line?.total_efectivo) }}</p>
                        <p class="text-sm">Consignación {{ fmt(data.cuentas_line?.total_consignacion) }}</p>
                        <p class="text-sm">Total afil. en cuenta {{ fmt(data.cuentas_line?.total_affiliations_cuentas) }}</p>
                    </div>
                </div>

                <div v-if="data.daily_close" class="mt-6 rounded-lg border border-stone-200 p-4">
                    <h3 class="text-stone-800 font-semibold mb-2">Cierre (13 conceptos)</h3>
                    <p class="text-sm mb-2">
                        Total día: <strong>{{ fmt(data.daily_close.grand_total_pesos) }}</strong>
                    </p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <div v-for="(v, k) in data.daily_close.concept_amounts" :key="k">
                            <span class="text-stone-500">{{ k }}:</span>
                            {{ fmt(v) }}
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-else-if="!loading" class="rounded-xl border border-dashed border-stone-300 bg-stone-50/50">
            <v-card-text class="text-stone-600 text-sm">
                No hay cuadre para esta fecha. Pulse <strong>Recalcular</strong> para generarlo desde los recibos del día.
            </v-card-text>
        </v-card>
    </div>
</template>
