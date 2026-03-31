<script setup>
import { onMounted, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';

const props = defineProps({
    affiliateId: {
        type: [String, Number, null],
        default: null,
    },
});

const loading = ref(false);
const error = ref('');
const data = ref(null);

async function loadFicha() {
    if (!props.affiliateId) return;
    loading.value = true;
    error.value = '';
    try {
        const res = await apiFetch(`/affiliates/${props.affiliateId}/ficha-360`);
        data.value = await res.json();
    } catch {
        error.value = 'No se pudo cargar la ficha.';
    } finally {
        loading.value = false;
    }
}

function quickAction() {
    window.alert('Próximamente: este flujo se conectará al módulo correspondiente (RF-016).');
}

const beneficiaryHeaders = [
    { title: 'Documento', key: 'documentNumber' },
    { title: 'Nombre', key: 'fullName' },
    { title: 'Parentesco', key: 'parentesco' },
    { title: 'Nacimiento', key: 'birthDate' },
    { title: 'Género', key: 'gender' },
];

function beneficiaryFullName(item) {
    const parts = [item.firstName, item.surnames].filter(Boolean);
    return parts.length ? parts.join(' ') : '—';
}

onMounted(() => {
    if (!requireAuth()) return;
    loadFicha();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center gap-4">
            <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
        </div>
        <div>
            <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Ficha 360°</h1>
            <p class="text-sm text-stone-600 mt-1">Afiliado #{{ affiliateId }} · RF-015 / acciones RF-016</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <v-btn variant="outlined" color="teal-darken-3" :href="`/afiliados/${affiliateId}/aporte`">Registrar aporte</v-btn>
            <v-btn variant="outlined" @click="quickAction">Generar certificado</v-btn>
            <v-btn variant="outlined" @click="quickAction">Ver historial</v-btn>
            <v-btn variant="outlined" @click="quickAction">Registrar novedad</v-btn>
        </div>

        <v-alert v-if="error" type="error" variant="tonal">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="teal-darken-3" />

        <div v-if="data && !loading" class="grid gap-6 md:grid-cols-2">
            <v-card>
                <v-card-title>Persona</v-card-title>
                <v-card-text>
                    <p><strong>Documento:</strong> {{ data.person?.documentNumber || '—' }}</p>
                    <p>
                        <strong>Nombre:</strong>
                        {{
                            [
                                data.person?.firstName,
                                data.person?.secondName,
                                data.person?.firstSurname,
                                data.person?.secondSurname,
                            ].filter(Boolean).join(' ') || '—'
                        }}
                    </p>
                </v-card-text>
            </v-card>

            <v-card>
                <v-card-title>Afiliado</v-card-title>
                <v-card-text>
                    <p><strong>Estado:</strong> {{ data.affiliate?.statusName || data.affiliate?.statusCode || '—' }}</p>
                    <p><strong>Mora:</strong> {{ data.affiliate?.moraStatus || '—' }}</p>
                    <p><strong>Tipo cliente:</strong> {{ data.affiliate?.clientType || '—' }}</p>
                    <p class="text-stone-600 text-sm mt-2">
                        Beneficiarios vinculados (titular): <strong>{{ data.counts?.beneficiaries ?? data.beneficiaries?.total ?? 0 }}</strong>
                    </p>
                </v-card-text>
            </v-card>
        </div>

        <v-card v-if="data && !loading" class="border border-stone-200/90">
            <v-card-title class="d-flex align-center justify-space-between flex-wrap gap-2">
                <span>Beneficiarios (RF-017)</span>
                <v-chip size="small" color="teal-darken-2" variant="flat">
                    {{ data.beneficiaries?.total ?? 0 }} registro(s)
                </v-chip>
            </v-card-title>
            <v-card-text>
                <p v-if="!(data.beneficiaries?.items?.length)" class="text-stone-600 text-body-2">
                    No hay beneficiarios registrados para este afiliado titular.
                </p>
                <v-data-table
                    v-else
                    :headers="beneficiaryHeaders"
                    :items="data.beneficiaries.items"
                    items-per-page="-1"
                    hide-default-footer
                    density="comfortable"
                >
                    <template #item.documentNumber="{ item }">
                        <span class="font-mono text-body-2">
                            {{ item.documentType ? `${item.documentType} ` : '' }}{{ item.documentNumber || '—' }}
                        </span>
                    </template>
                    <template #item.fullName="{ item }">{{ beneficiaryFullName(item) }}</template>
                    <template #item.parentesco="{ item }">{{ item.parentesco || '—' }}</template>
                    <template #item.birthDate="{ item }">{{ item.birthDate || '—' }}</template>
                    <template #item.gender="{ item }">{{ item.gender || '—' }}</template>
                </v-data-table>
            </v-card-text>
        </v-card>
    </div>
</template>
