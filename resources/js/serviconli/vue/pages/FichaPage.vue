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
            <v-btn variant="outlined" @click="quickAction">Registrar aporte</v-btn>
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
                </v-card-text>
            </v-card>
        </div>
    </div>
</template>
