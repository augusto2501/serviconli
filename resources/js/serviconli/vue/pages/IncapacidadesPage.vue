<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';
import UiButton from '../components/UiButton.vue';
import UiToast from '../components/UiToast.vue';

const toast = reactive({ show: false, text: '', variant: 'info' });
let toastTimer;
function notify(text, variant = 'info') {
    toast.text = text;
    toast.variant = variant === 'error' ? 'error' : 'info';
    toast.show = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.show = false;
    }, 4000);
}

async function jsonOrMessage(res) {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg =
            data.message ||
            (data.errors && Object.values(data.errors).flat().join(' ')) ||
            `Error ${res.status}`;
        throw new Error(msg);
    }
    return data;
}

const affiliateId = ref('');
const loading = ref(false);
const disabilities = ref([]);

async function load() {
    const id = Number(affiliateId.value);
    if (!id || id < 1) {
        notify('Ingrese un ID de afiliado válido.', 'error');
        return;
    }
    loading.value = true;
    disabilities.value = [];
    try {
        const res = await apiFetch(`/affiliates/${id}/disabilities`);
        const data = await jsonOrMessage(res);
        disabilities.value = data.data || [];
        if (!disabilities.value.length) {
            notify('No hay incapacidades registradas para este afiliado.', 'info');
        }
    } catch (e) {
        notify(e.message || 'Error al cargar', 'error');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (!requireAuth()) return;
});
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="/mis-afiliados" class="text-sm font-medium text-teal-800 hover:underline">← Mis afiliados</a>
            <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Incapacidades</h1>
            <p class="mt-1 text-sm text-stone-600">RF-097 / RF-098 — Consulta por afiliado (EPS / ARL, CIE-10, prórrogas).</p>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white/90 p-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-stone-600">ID afiliado</label>
                <input v-model="affiliateId" type="number" min="1" class="w-40 rounded-xl border border-stone-300 px-3 py-2 text-sm" />
            </div>
            <UiButton type="button" variant="primary" :loading="loading" @click="load">Consultar</UiButton>
        </div>

        <div v-if="loading" class="py-10 text-center text-sm text-stone-500">Cargando…</div>
        <div v-else-if="disabilities.length" class="overflow-x-auto rounded-2xl border border-stone-200 bg-white/90 shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase text-stone-600">
                    <tr>
                        <th class="px-3 py-2">Origen</th>
                        <th class="px-3 py-2">Subtipo</th>
                        <th class="px-3 py-2">Diagnóstico</th>
                        <th class="px-3 py-2">Inicio / Fin</th>
                        <th class="px-3 py-2 text-end">Días</th>
                        <th class="px-3 py-2">&gt;180</th>
                        <th class="px-3 py-2">Prórrogas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="d in disabilities" :key="d.id" class="hover:bg-stone-50/80">
                        <td class="px-3 py-2">{{ d.source }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ d.subtypeCode }}</td>
                        <td class="max-w-[14rem] px-3 py-2 text-xs">
                            <span v-if="d.diagnosis">{{ d.diagnosis.code }} — {{ d.diagnosis.description }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-xs">{{ d.startDate }} → {{ d.endDate || '—' }}</td>
                        <td class="px-3 py-2 text-end">{{ d.cumulativeDays }}</td>
                        <td class="px-3 py-2">
                            <span v-if="d.over180Alert" class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-900">Sí</span>
                            <span v-else class="text-stone-400">—</span>
                        </td>
                        <td class="px-3 py-2 text-xs">{{ (d.extensions && d.extensions.length) || 0 }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
