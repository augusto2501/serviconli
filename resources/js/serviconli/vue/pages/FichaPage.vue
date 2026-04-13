<script setup>
import { computed, onMounted, ref } from 'vue';
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

const personName = computed(() => {
    const p = data.value?.person;
    if (!p) return '';
    return [p.firstName, p.secondName, p.firstSurname, p.secondSurname].filter(Boolean).join(' ');
});

async function loadFicha() {
    if (!props.affiliateId) return;
    loading.value = true;
    error.value = '';
    data.value = null;
    try {
        const res = await apiFetch(`/affiliates/${props.affiliateId}/ficha-360`);
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            error.value = body.message || 'No se pudo cargar la ficha.';
            return;
        }
        data.value = body;
    } catch {
        error.value = 'No se pudo cargar la ficha.';
    } finally {
        loading.value = false;
    }
}

async function downloadPaymentCertificatePdf() {
    try {
        const res = await apiFetch(`/affiliates/${props.affiliateId}/payment-certificate/pdf`, {
            headers: { Accept: 'application/pdf' },
        });
        if (!res.ok) return;
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `certificado-pago-${props.affiliateId}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        /* ignore */
    }
}

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
            <p class="mt-1 text-sm text-stone-600">Afiliado #{{ affiliateId }} · RF-015</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                :href="`/afiliados/${affiliateId}/aporte`"
                class="inline-flex items-center rounded-xl border border-teal-800 bg-white px-4 py-2 text-sm font-medium text-teal-900 shadow-sm hover:bg-teal-50"
            >
                Registrar aporte
            </a>
            <button
                type="button"
                class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50"
                @click="downloadPaymentCertificatePdf"
            >
                Certificado de pago (PDF)
            </button>
        </div>

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
        <div v-if="loading" class="h-1 w-full animate-pulse rounded bg-teal-200" />

        <template v-if="data && !loading">
            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                    <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Persona</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Documento</dt>
                            <dd class="font-mono text-stone-900">
                                {{ data.person?.documentType }} {{ data.person?.documentNumber || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Nombre</dt>
                            <dd class="text-right text-stone-900">{{ personName || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Correo</dt>
                            <dd class="text-right text-stone-900">{{ data.person?.email || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Celular</dt>
                            <dd class="text-right text-stone-900">{{ data.person?.cellphone || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Ciudad</dt>
                            <dd class="text-right text-stone-900">{{ data.person?.cityName || '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                    <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Afiliado</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Estado</dt>
                            <dd class="text-right text-stone-900">{{ data.affiliate?.statusName || data.affiliate?.statusCode || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Mora</dt>
                            <dd class="text-right text-stone-900">{{ data.affiliate?.moraStatus || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Tipo cliente</dt>
                            <dd class="text-right text-stone-900">{{ data.affiliate?.clientType || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-stone-500">Beneficiarios</dt>
                            <dd class="text-right font-semibold text-teal-900">
                                {{ data.counts?.beneficiaries ?? data.beneficiaries?.total ?? 0 }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section
                v-if="data.socialSecurity?.current"
                class="rounded-2xl border border-teal-200/80 bg-teal-50/40 p-5 shadow-sm"
            >
                <h2 class="font-serif-svc text-lg font-semibold text-teal-950">Seguridad social (vigente)</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500">EPS</p>
                        <p class="mt-1 font-medium text-stone-900">{{ data.socialSecurity.current.eps?.name || '—' }}</p>
                        <p class="text-xs text-stone-500">{{ data.socialSecurity.current.eps?.pilaCode }}</p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500">AFP</p>
                        <p class="mt-1 font-medium text-stone-900">{{ data.socialSecurity.current.afp?.name || '—' }}</p>
                        <p class="text-xs text-stone-500">{{ data.socialSecurity.current.afp?.pilaCode }}</p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500">ARL</p>
                        <p class="mt-1 font-medium text-stone-900">{{ data.socialSecurity.current.arl?.name || '—' }}</p>
                        <p class="text-xs text-stone-500">
                            Clase {{ data.socialSecurity.current.arlRiskClass ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3 text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-500">CCF</p>
                        <p class="mt-1 font-medium text-stone-900">{{ data.socialSecurity.current.ccf?.name || '—' }}</p>
                        <p class="text-xs text-stone-500">{{ data.socialSecurity.current.ccf?.pilaCode }}</p>
                    </div>
                </div>
            </section>

            <section v-if="data.payer?.current" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Pagador actual</h2>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Razón social</dt>
                        <dd class="font-medium text-stone-900">{{ data.payer.current.payer?.razonSocial || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">NIT</dt>
                        <dd class="font-mono text-stone-900">
                            {{ data.payer.current.payer?.nit }}-{{ data.payer.current.payer?.digitoVerificacion }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Cotizante</dt>
                        <dd>{{ data.payer.current.contributorTypeCode || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Salario declarado</dt>
                        <dd>{{ data.payer.current.salary != null ? `$${Number(data.payer.current.salary).toLocaleString('es-CO')}` : '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Aportes PILA</h2>
                <p v-if="data.contributions?.lastPaidPeriod" class="mt-2 text-sm text-stone-600">
                    Último período con aporte:
                    <strong>
                        {{ data.contributions.lastPaidPeriod.year }}-{{
                            String(data.contributions.lastPaidPeriod.month).padStart(2, '0')
                        }}
                    </strong>
                </p>
                <p v-else class="mt-2 text-sm text-stone-500">Sin liquidaciones PILA confirmadas.</p>
                <ul v-if="data.contributions?.pilaLiquidations?.length" class="mt-3 space-y-2 text-sm">
                    <li
                        v-for="(liq, idx) in data.contributions.pilaLiquidations"
                        :key="idx"
                        class="flex flex-wrap justify-between gap-2 rounded-lg border border-stone-100 bg-stone-50/80 px-3 py-2"
                    >
                        <span>{{ liq.paymentDate }} · Total SS {{ liq.totalSocialSecurityPesos?.toLocaleString?.('es-CO') }}</span>
                        <span class="font-mono text-xs text-stone-500">{{ liq.publicId }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="data.invoices?.length" class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Facturas recientes</h2>
                <ul class="mt-3 divide-y divide-stone-100 text-sm">
                    <li v-for="inv in data.invoices" :key="inv.id" class="flex flex-wrap justify-between gap-2 py-2">
                        <span>{{ inv.tipo }} · {{ inv.publicNumber }}</span>
                        <span class="text-stone-600">${{ inv.totalPesos?.toLocaleString?.('es-CO') }} · {{ inv.estado }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-stone-200/90 bg-white/90 p-5 shadow-sm">
                <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Beneficiarios (RF-017)</h2>
                <p v-if="!(data.beneficiaries?.items?.length)" class="mt-2 text-sm text-stone-500">
                    No hay beneficiarios registrados para este titular.
                </p>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-stone-700">Documento</th>
                                <th class="px-3 py-2 text-left font-medium text-stone-700">Nombre</th>
                                <th class="px-3 py-2 text-left font-medium text-stone-700">Parentesco</th>
                                <th class="px-3 py-2 text-left font-medium text-stone-700">Nacimiento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-for="b in data.beneficiaries.items" :key="b.id">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">
                                    {{ b.documentType }} {{ b.documentNumber }}
                                </td>
                                <td class="px-3 py-2">{{ beneficiaryFullName(b) }}</td>
                                <td class="px-3 py-2">{{ b.parentesco || '—' }}</td>
                                <td class="px-3 py-2">{{ b.birthDate || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="data.notes?.recent?.length" class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-5 shadow-sm">
                <h2 class="font-serif-svc text-lg font-semibold text-stone-900">Notas recientes</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="n in data.notes.recent" :key="n.id" class="rounded-lg border border-amber-100 bg-white/80 px-3 py-2">
                        <span class="text-xs text-stone-500">{{ n.noteType }} · {{ n.createdAt }}</span>
                        <p class="mt-1 text-stone-800">{{ n.note }}</p>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
