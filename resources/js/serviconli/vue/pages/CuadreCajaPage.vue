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
                <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Cuadre de caja</h1>
                <p class="mt-1 text-sm text-stone-600">Flujo 10 — Tres líneas y cierre del día.</p>
            </div>
            <button
                type="button"
                class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-50"
                @click="logoutAndRedirect"
            >
                Cerrar sesión
            </button>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200/90 bg-white/90 p-4 shadow-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">Fecha negocio</label>
                <input
                    v-model="businessDate"
                    type="date"
                    class="rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
            </div>
            <button
                type="button"
                class="rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50"
                :disabled="loading"
                @click="load"
            >
                Consultar
            </button>
            <button
                v-if="!isClosed"
                type="button"
                class="rounded-xl border border-teal-800 px-4 py-2 text-sm font-medium text-teal-900 hover:bg-teal-50 disabled:opacity-50"
                :disabled="loading"
                @click="recalculate"
            >
                Recalcular
            </button>
            <button
                v-if="!isClosed"
                type="button"
                class="rounded-xl bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900 disabled:opacity-50"
                :disabled="loading"
                @click="closeDay"
            >
                Cerrar día
            </button>
        </div>

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
        <div v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ success }}</div>

        <section v-if="data" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
            <h2 class="flex flex-wrap items-center gap-2 font-serif-svc text-lg font-semibold text-stone-900">
                Estado:
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase"
                    :class="isClosed ? 'bg-stone-200 text-stone-800' : 'bg-amber-100 text-amber-900'"
                >
                    {{ data.status }}
                </span>
            </h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-teal-200 bg-teal-50/40 p-4">
                    <h3 class="font-semibold text-teal-950">Línea 1 — Afiliaciones</h3>
                    <p class="mt-2 text-sm text-stone-600">Recibos: {{ data.affiliations_line?.total_receipts ?? 0 }}</p>
                    <p class="text-sm">Efectivo {{ fmt(data.affiliations_line?.total_efectivo) }}</p>
                    <p class="text-sm">Consignación {{ fmt(data.affiliations_line?.total_consignacion) }}</p>
                    <p class="text-sm">Crédito {{ fmt(data.affiliations_line?.total_credito) }}</p>
                    <p class="text-sm">Cuenta cobro {{ fmt(data.affiliations_line?.total_cuenta_cobro) }}</p>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-4">
                    <h3 class="font-semibold text-stone-800">Línea 2 — Aportes</h3>
                    <p class="mt-2 text-sm">Aporte POS {{ fmt(data.contributions_line?.total_aporte_pos) }}</p>
                    <p class="text-sm">Admin {{ fmt(data.contributions_line?.total_admin) }}</p>
                    <p class="text-sm">Intereses mora {{ fmt(data.contributions_line?.total_interest_mora) }}</p>
                    <p class="mt-2 text-sm">Efectivo {{ fmt(data.contributions_line?.total_efectivo) }}</p>
                    <p class="text-sm">Consignación {{ fmt(data.contributions_line?.total_consignacion) }}</p>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-4">
                    <h3 class="font-semibold text-stone-800">Línea 3 — Cuentas cobro</h3>
                    <p class="mt-2 text-sm">Efectivo {{ fmt(data.cuentas_line?.total_efectivo) }}</p>
                    <p class="text-sm">Consignación {{ fmt(data.cuentas_line?.total_consignacion) }}</p>
                    <p class="text-sm">Total afil. en cuenta {{ fmt(data.cuentas_line?.total_affiliations_cuentas) }}</p>
                </div>
            </div>

            <div v-if="data.daily_close" class="mt-6 rounded-xl border border-stone-200 p-4">
                <h3 class="font-semibold text-stone-800">Cierre</h3>
                <p class="mt-2 text-sm">
                    Total día: <strong>{{ fmt(data.daily_close.grand_total_pesos) }}</strong>
                </p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                    <div v-for="(v, k) in data.daily_close.concept_amounts" :key="k">
                        <span class="text-stone-500">{{ k }}:</span>
                        {{ fmt(v) }}
                    </div>
                </div>
            </div>
        </section>

        <div
            v-else-if="!loading"
            class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/50 p-6 text-sm text-stone-600"
        >
            No hay cuadre para esta fecha. Pulse <strong>Recalcular</strong> para generarlo desde los recibos del día.
        </div>
    </div>
</template>
