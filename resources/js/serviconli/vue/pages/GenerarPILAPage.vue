<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch, getToken, requireAuth } from '../api';

const loading = ref(false);
const generating = ref(false);
const error = ref('');
const success = ref('');

const batches = ref([]);
const generations = ref([]);
const selectedBatchId = ref(null);
const selectedFormat = ref('PLANO_ARUS');

const formats = [
    { value: 'PLANO_ARUS', label: 'Plano ARUS (TXT — 359+687 chars)' },
    { value: 'XLSX', label: 'Excel/CSV (42 columnas)' },
];

function fmt(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
}

async function loadBatches() {
    try {
        const res = await apiFetch('/batches?status=LIQUIDADO&per_page=50');
        const data = await res.json();
        batches.value = (data.data || []).map(b => ({
            ...b,
            label: `${b.payer?.razon_social || 'Sin pagador'} — ${b.period} (${b.cant_affiliates} afil. · ${fmt(b.grand_total)})`,
        }));
    } catch { /* ignore */ }
}

async function loadGenerations() {
    loading.value = true;
    try {
        const res = await apiFetch('/pila-files');
        const data = await res.json();
        generations.value = data.data || [];
    } catch { /* ignore */ }
    finally { loading.value = false; }
}

async function generate() {
    if (!selectedBatchId.value) {
        error.value = 'Seleccione un lote.';
        return;
    }
    generating.value = true;
    error.value = '';
    success.value = '';

    try {
        const res = await apiFetch('/pila-files/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                batch_id: selectedBatchId.value,
                format: selectedFormat.value,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'Error al generar.';
            return;
        }
        success.value = `Archivo generado: ${data.planilla_number} (${data.affiliates_count} afiliados)`;
        loadGenerations();
    } catch (e) {
        error.value = e.message;
    } finally {
        generating.value = false;
    }
}

function downloadUrl(genId) {
    const token = getToken();
    return `/api/pila-files/${genId}/download?token=${encodeURIComponent(token || '')}`;
}

async function downloadFile(genId) {
    try {
        const res = await apiFetch(`/pila-files/${genId}/download`, {
            headers: { Accept: '*/*' },
        });
        if (!res.ok) return;
        const blob = await res.blob();
        const cd = res.headers.get('Content-Disposition');
        let filename = 'planilla_pila.txt';
        if (cd && cd.includes('filename=')) {
            const m = cd.match(/filename="?([^";]+)"?/);
            if (m) filename = m[1];
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    } catch { /* ignore */ }
}

const genHeaders = [
    { title: 'Pagador', key: 'payer_name' },
    { title: 'Período', key: 'period' },
    { title: 'Formato', key: 'file_format' },
    { title: 'Afiliados', key: 'affiliates_count', align: 'center' },
    { title: 'No. Planilla', key: 'planilla_number' },
    { title: 'Fecha', key: 'created_at' },
    { title: 'Acciones', key: 'actions', sortable: false },
];

onMounted(() => {
    if (!requireAuth()) return;
    loadBatches();
    loadGenerations();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Generación Archivo PILA</h1>
            <p class="text-sm text-stone-600 mt-1">Flujo 8 — Generar plano ARUS o archivo Excel desde lotes confirmados.</p>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" closable @click:close="success = ''">{{ success }}</v-alert>

        <!-- Generador -->
        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 text-lg">Generar Archivo</v-card-title>
            <v-card-text>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <v-select
                        v-model="selectedBatchId"
                        :items="batches"
                        item-value="id"
                        item-title="label"
                        label="Lote confirmado"
                        variant="outlined"
                        density="comfortable"
                        class="md:col-span-2"
                    />
                    <v-select
                        v-model="selectedFormat"
                        :items="formats"
                        item-value="value"
                        item-title="label"
                        label="Formato"
                        variant="outlined"
                        density="comfortable"
                    />
                </div>
                <div class="mt-4">
                    <v-btn color="teal-darken-3" :loading="generating" @click="generate">
                        Generar Archivo PILA
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>

        <!-- Historial de generaciones -->
        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-title class="text-stone-800 text-lg">Archivos Generados</v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="teal-darken-3" />
                <v-data-table
                    v-if="generations.length"
                    :headers="genHeaders"
                    :items="generations"
                    items-per-page="-1"
                    hide-default-footer
                    density="comfortable"
                >
                    <template #item.file_format="{ item }">
                        <v-chip :color="item.file_format === 'PLANO_ARUS' ? 'blue' : 'green'" size="x-small">
                            {{ item.file_format }}
                        </v-chip>
                    </template>
                    <template #item.created_at="{ item }">
                        {{ item.created_at ? new Date(item.created_at).toLocaleString('es-CO') : '—' }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-btn size="small" variant="text" color="teal-darken-3" @click="downloadFile(item.id)">
                            Descargar
                        </v-btn>
                    </template>
                </v-data-table>
                <p v-else-if="!loading" class="text-sm text-stone-500">No hay archivos generados aún.</p>
            </v-card-text>
        </v-card>
    </div>
</template>
