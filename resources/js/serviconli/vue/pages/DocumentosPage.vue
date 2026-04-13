<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';
import UiButton from '../components/UiButton.vue';

const affiliateId = ref('');
const templateCode = ref('operational_clauses');
const pcYear = ref(new Date().getFullYear());
const pcMonth = ref(new Date().getMonth() + 1);
const pcFormat = ref('full');
const loading = ref(false);
const message = ref('');

const templates = [
    { code: 'operational_clauses', label: 'Cláusulas operativas' },
    { code: 'legal_association', label: 'Contrato asociación legal' },
    { code: 'affiliate_declaration', label: 'Declaración del afiliado' },
    { code: 'affiliation_type_declaration', label: 'Declaración tipo vinculación' },
    { code: 'voluntary_withdrawal', label: 'Declaración retiro voluntario' },
    { code: 'affiliation_certificate', label: 'Certificado de afiliación' },
    { code: 'payment_certificate', label: 'Certificado de pago (requiere período)' },
];

onMounted(() => {
    if (!requireAuth()) return;
});

async function downloadPdf() {
    const id = Number(affiliateId.value);
    if (!id || id < 1) {
        message.value = 'Ingrese un ID de afiliado válido.';
        return;
    }
    message.value = '';
    loading.value = true;
    try {
        let path = `/affiliates/${id}/contract-documents/${templateCode.value}`;
        if (templateCode.value === 'payment_certificate') {
            const y = Number(pcYear.value);
            const m = Number(pcMonth.value);
            const f = pcFormat.value;
            path += `?year=${y}&month=${m}&format=${encodeURIComponent(f)}`;
        }
        const res = await apiFetch(path);
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || `Error ${res.status}`);
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${templateCode.value}-afiliado-${id}.pdf`;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        message.value = 'Descarga iniciada.';
    } catch (e) {
        message.value = e.message || 'No se pudo generar el PDF.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-lg space-y-6">
        <div>
            <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
            <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Documentos PDF</h1>
            <p class="mt-1 text-sm text-stone-600">RF-103 — Plantillas de contrato y certificados (dompdf).</p>
        </div>

        <div class="space-y-4 rounded-2xl border border-stone-200/90 bg-white/90 p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">ID afiliado</label>
                <input
                    v-model="affiliateId"
                    type="number"
                    min="1"
                    placeholder="Ej. 1"
                    class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">Plantilla</label>
                <select v-model="templateCode" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                    <option v-for="t in templates" :key="t.code" :value="t.code">{{ t.label }}</option>
                </select>
            </div>
            <div v-if="templateCode === 'payment_certificate'" class="grid grid-cols-3 gap-2">
                <div>
                    <label class="mb-1 block text-xs text-stone-600">Año</label>
                    <input v-model.number="pcYear" type="number" min="2000" max="2100" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-stone-600">Mes</label>
                    <input v-model.number="pcMonth" type="number" min="1" max="12" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-stone-600">Formato</label>
                    <select v-model="pcFormat" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        <option value="full">Completo</option>
                        <option value="summary">Resumido</option>
                    </select>
                </div>
            </div>
            <p v-if="message" class="text-sm" :class="message.includes('Error') || message.includes('No se') ? 'text-red-800' : 'text-emerald-800'">
                {{ message }}
            </p>
            <UiButton type="button" variant="primary" :loading="loading" @click="downloadPdf">Descargar PDF</UiButton>
        </div>
    </div>
</template>
