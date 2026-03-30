<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiFetch, logoutAndRedirect, requireAuth } from '../api';

const q = ref('');
const loading = ref(false);
const rows = ref([]);
const meta = ref({ total: 0, currentPage: 1, lastPage: 1 });

const headers = [
    { title: 'Documento', key: 'documentNumber' },
    { title: 'Nombre', key: 'fullName' },
    { title: 'Tipo cliente', key: 'clientType' },
    { title: 'Mora', key: 'moraStatus' },
    { title: 'EPS', key: 'epsName' },
    { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
];

const metaText = computed(
    () => `Total: ${meta.value.total ?? 0} · Página ${meta.value.currentPage ?? 1} de ${meta.value.lastPage ?? 1}`
);

async function load(page = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (q.value.trim()) {
            params.set('q', q.value.trim());
        }
        const res = await apiFetch(`/affiliates?${params.toString()}`);
        const data = await res.json();
        rows.value = data.data || [];
        meta.value = data.meta || { total: 0, currentPage: 1, lastPage: 1 };
    } catch {
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

async function downloadExport(format) {
    const res = await apiFetch(`/affiliates/export?format=${encodeURIComponent(format)}`, {
        headers: { Accept: '*/*' },
    });
    if (!res.ok) {
        return;
    }
    const blob = await res.blob();
    const cd = res.headers.get('Content-Disposition');
    let filename = `export-afiliados.${format === 'xlsx' ? 'xlsx' : 'csv'}`;
    if (cd && cd.includes('filename=')) {
        const m = cd.match(/filename="?([^";]+)"?/);
        if (m) {
            filename = m[1];
        }
    }
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

onMounted(() => {
    if (!requireAuth()) return;
    load(1);
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-serif-svc text-2xl font-bold text-stone-900">Mis afiliados</h1>
                <p class="text-sm text-stone-600 mt-1">Vista RF-020 en Vue 3 + Vuetify.</p>
            </div>
            <v-btn variant="outlined" color="teal-darken-3" @click="logoutAndRedirect">Cerrar sesión</v-btn>
        </div>

        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-text class="flex flex-wrap gap-3">
                <v-text-field
                    v-model="q"
                    hide-details
                    variant="outlined"
                    density="comfortable"
                    label="Documento o nombre"
                    class="min-w-[14rem] flex-1"
                    @keyup.enter="load(1)"
                />
                <v-btn color="teal-darken-3" @click="load(1)">Buscar</v-btn>
                <v-btn variant="outlined" @click="downloadExport('csv')">Exportar CSV</v-btn>
                <v-btn variant="outlined" @click="downloadExport('xlsx')">Exportar Excel</v-btn>
            </v-card-text>
        </v-card>

        <p class="text-sm text-stone-500">{{ metaText }}</p>

        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-data-table :headers="headers" :items="rows" :loading="loading" items-per-page="-1" hide-default-footer>
                <template #item.documentNumber="{ item }">
                    <span class="font-mono text-sm">{{ item.documentNumber || '—' }}</span>
                </template>
                <template #item.fullName="{ item }">
                    {{ item.fullName || [item.firstName, item.lastName].filter(Boolean).join(' ') || '—' }}
                </template>
                <template #item.clientType="{ item }">{{ item.clientType || '—' }}</template>
                <template #item.moraStatus="{ item }">{{ item.moraStatus || '—' }}</template>
                <template #item.epsName="{ item }">{{ item.epsName || '—' }}</template>
                <template #item.actions="{ item }">
                    <a class="text-teal-800 font-medium hover:underline" :href="`/afiliados/${item.id}/ficha`">Ficha 360°</a>
                </template>
            </v-data-table>
        </v-card>
    </div>
</template>
