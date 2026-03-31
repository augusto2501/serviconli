<script setup>
import { computed, onMounted, ref } from 'vue';
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

const periodLabel = computed(() => `${months[(periodMonth.value || 1) - 1]} ${periodYear.value}`);

const lineHeaders = [
    { title: 'Documento', key: 'document_number', width: '110px' },
    { title: 'Nombre', key: 'affiliate_name' },
    { title: 'Tipo', key: 'contributor_type', width: '60px' },
    { title: 'Salario', key: 'salary', align: 'end' },
    { title: 'IBC', key: 'ibc', align: 'end' },
    { title: 'Días', key: 'days_eps', width: '60px', align: 'center' },
    { title: 'Salud', key: 'health_total', align: 'end' },
    { title: 'Pensión', key: 'pension_total', align: 'end' },
    { title: 'ARL', key: 'arl_total', align: 'end' },
    { title: 'CCF', key: 'ccf_total', align: 'end' },
    { title: 'Total', key: 'total_payable', align: 'end' },
    { title: 'Estado', key: 'line_status', width: '100px' },
    { title: 'Acciones', key: 'actions', sortable: false, width: '100px' },
];

function fmt(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
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
            <p class="text-sm text-stone-600 mt-1">Flujo 4 — Liquidación masiva por empresa/pagador.</p>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" closable @click:close="success = ''">{{ success }}</v-alert>

        <!-- Selector empresa + período -->
        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 text-lg">Crear Pre-Liquidación</v-card-title>
            <v-card-text>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <v-select
                        v-model="selectedPayerId"
                        :items="payers"
                        item-value="id"
                        :item-title="p => `${p.nit}-${p.dv} ${p.razon_social}`"
                        label="Pagador / Empresa"
                        variant="outlined"
                        density="comfortable"
                        class="md:col-span-2"
                    />
                    <v-text-field
                        v-model.number="periodYear"
                        label="Año"
                        type="number"
                        variant="outlined"
                        density="comfortable"
                        :min="2020"
                        :max="2100"
                    />
                    <v-text-field
                        v-model.number="periodMonth"
                        label="Mes"
                        type="number"
                        variant="outlined"
                        density="comfortable"
                        :min="1"
                        :max="12"
                    />
                </div>
                <div class="flex gap-3 mt-4">
                    <v-btn color="teal-darken-3" :loading="creating" @click="createBatch">
                        Generar Pre-Liquidación
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>

        <!-- Lotes existentes -->
        <v-card v-if="batches.length && !batch" class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 text-lg">Lotes Recientes</v-card-title>
            <v-card-text>
                <v-list>
                    <v-list-item
                        v-for="b in batches"
                        :key="b.id"
                        @click="loadBatch(b.id)"
                        class="cursor-pointer"
                    >
                        <v-list-item-title>
                            {{ b.payer?.razon_social || 'Sin pagador' }} — {{ b.period }}
                        </v-list-item-title>
                        <v-list-item-subtitle>
                            {{ b.cant_affiliates }} afiliados · {{ fmt(b.grand_total) }} ·
                            <v-chip :color="b.status === 'BORRADOR' ? 'orange' : b.status === 'LIQUIDADO' ? 'green' : 'grey'" size="x-small">
                                {{ b.status }}
                            </v-chip>
                        </v-list-item-subtitle>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>

        <!-- Detalle del lote -->
        <template v-if="batch">
            <v-card class="rounded-xl border border-teal-200 bg-teal-50/30">
                <v-card-title class="text-teal-800 text-lg flex items-center justify-between flex-wrap gap-2">
                    <span>
                        Lote #{{ batch.id }} — {{ batch.payer?.razon_social }}
                        ({{ months[(batch.period_month || 1) - 1] }} {{ batch.period_year }})
                    </span>
                    <v-chip :color="batch.status === 'BORRADOR' ? 'orange' : batch.status === 'LIQUIDADO' ? 'green' : 'grey'">
                        {{ batch.status }}
                    </v-chip>
                </v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Afiliados</div>
                            <div class="text-lg font-semibold">{{ batch.cant_affiliates }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Salud</div>
                            <div class="text-lg font-semibold">{{ fmt(batch.totals?.health) }}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-stone-200">
                            <div class="text-stone-500">Pensión</div>
                            <div class="text-lg font-semibold">{{ fmt(batch.totals?.pension) }}</div>
                        </div>
                        <div class="bg-teal-100 rounded-lg p-3 border border-teal-300">
                            <div class="text-teal-700 font-semibold">GRAN TOTAL</div>
                            <div class="text-xl font-bold text-teal-900">{{ fmt(batch.totals?.grand_total) }}</div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Tabla editable de líneas -->
            <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
                <v-card-title class="text-stone-800 text-lg">Detalle por Afiliado</v-card-title>
                <v-card-text>
                    <v-data-table
                        :headers="lineHeaders"
                        :items="batch.lines || []"
                        items-per-page="-1"
                        hide-default-footer
                        density="comfortable"
                        class="text-sm"
                    >
                        <template #item.document_number="{ item }">
                            <span class="font-mono text-xs">{{ item.document_number || '—' }}</span>
                        </template>
                        <template #item.salary="{ item }">{{ fmt(item.salary) }}</template>
                        <template #item.ibc="{ item }">{{ fmt(item.ibc) }}</template>
                        <template #item.health_total="{ item }">{{ fmt(item.health_total) }}</template>
                        <template #item.pension_total="{ item }">{{ fmt(item.pension_total) }}</template>
                        <template #item.arl_total="{ item }">{{ fmt(item.arl_total) }}</template>
                        <template #item.ccf_total="{ item }">{{ fmt(item.ccf_total) }}</template>
                        <template #item.total_payable="{ item }">
                            <strong>{{ fmt(item.total_payable) }}</strong>
                        </template>
                        <template #item.line_status="{ item }">
                            <v-chip :color="item.line_status === 'INCLUIDO' ? 'green' : 'grey'" size="x-small">
                                {{ item.line_status }}
                            </v-chip>
                        </template>
                        <template #item.actions="{ item }">
                            <v-btn
                                v-if="batch.status === 'BORRADOR'"
                                size="x-small"
                                variant="text"
                                :color="item.line_status === 'INCLUIDO' ? 'red' : 'green'"
                                @click="toggleLine(item)"
                            >
                                {{ item.line_status === 'INCLUIDO' ? 'Excluir' : 'Incluir' }}
                            </v-btn>
                        </template>
                    </v-data-table>
                </v-card-text>
            </v-card>

            <!-- Resumen por entidad -->
            <v-card v-if="batch.entity_summaries?.length" class="rounded-xl border border-stone-200/90 bg-white/90">
                <v-card-title class="text-stone-800 text-lg">Distribución por Entidad</v-card-title>
                <v-card-text>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <div v-for="es in batch.entity_summaries" :key="es.entity_code + es.subsystem"
                             class="bg-stone-50 rounded-lg p-2 border border-stone-200">
                            <div class="text-stone-500 text-xs">{{ es.entity_code }} · {{ es.subsystem }}</div>
                            <div class="font-semibold">{{ fmt(es.amount) }}</div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Botones -->
            <div class="flex justify-end gap-3">
                <v-btn variant="outlined" color="stone" @click="batch = null">
                    Cerrar
                </v-btn>
                <v-btn v-if="batch.status === 'BORRADOR'" variant="outlined" color="red" @click="cancelBatch">
                    Cancelar Lote
                </v-btn>
                <v-btn
                    v-if="batch.status === 'BORRADOR'"
                    color="teal-darken-3"
                    size="large"
                    :loading="confirming"
                    @click="confirmBatch"
                >
                    Confirmar Liquidación
                </v-btn>
            </div>
        </template>
    </div>
</template>
