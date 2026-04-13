<script setup>
import { onMounted, reactive, ref } from 'vue';
import { apiFetch, requireAuth } from '../api';
import UiModal from '../components/UiModal.vue';
import UiButton from '../components/UiButton.vue';
import UiToast from '../components/UiToast.vue';

const loading = ref(false);
const advisors = ref([]);
const error = ref('');

const toast = reactive({ show: false, text: '', variant: 'info' });
let toastTimer = null;
function notify(text, variant = 'info') {
    toast.text = text;
    if (variant === 'error') toast.variant = 'error';
    else if (variant === 'success') toast.variant = 'success';
    else toast.variant = 'info';
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

function fmtPesos(v) {
    if (v == null) return '$0';
    return '$' + Number(v).toLocaleString('es-CO');
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await apiFetch('/advisors?per_page=100');
        const data = await jsonOrMessage(res);
        advisors.value = data.data || [];
    } catch (e) {
        error.value = e.message || 'Error cargando asesores.';
    } finally {
        loading.value = false;
    }
}

const showModal = ref(false);
const saving = ref(false);
const editingId = ref(null);
const form = reactive({
    code: '',
    document_type: '',
    document_number: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    commission_new: 0,
    commission_recurring: 0,
    authorizes_credits: false,
});

function resetForm() {
    editingId.value = null;
    form.code = '';
    form.document_type = '';
    form.document_number = '';
    form.first_name = '';
    form.last_name = '';
    form.phone = '';
    form.email = '';
    form.commission_new = 0;
    form.commission_recurring = 0;
    form.authorizes_credits = false;
}

function openCreate() {
    resetForm();
    showModal.value = true;
}

function openEdit(row) {
    editingId.value = row.id;
    form.code = row.code;
    form.document_type = row.documentType || '';
    form.document_number = row.documentNumber || '';
    form.first_name = row.firstName || '';
    form.last_name = row.lastName || '';
    form.phone = row.phone || '';
    form.email = row.email || '';
    form.commission_new = row.commissionNew ?? 0;
    form.commission_recurring = row.commissionRecurring ?? 0;
    form.authorizes_credits = !!row.authorizesCredits;
    showModal.value = true;
}

async function submitForm() {
    saving.value = true;
    try {
        const payload = { ...form };
        if (editingId.value) {
            const res = await apiFetch(`/advisors/${editingId.value}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            await jsonOrMessage(res);
            notify('Asesor actualizado', 'success');
        } else {
            const res = await apiFetch('/advisors', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            await jsonOrMessage(res);
            notify('Asesor creado', 'success');
        }
        showModal.value = false;
        await load();
    } catch (e) {
        notify(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(row) {
    if (!confirm(`¿Eliminar al asesor ${row.code}?`)) return;
    try {
        const res = await apiFetch(`/advisors/${row.id}`, { method: 'DELETE' });
        if (res.status === 204) {
            notify('Asesor eliminado', 'success');
            await load();
            return;
        }
        await jsonOrMessage(res);
    } catch (e) {
        notify(e.message, 'error');
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
                <h1 class="mt-2 font-serif-svc text-2xl font-bold text-stone-900">Asesores comerciales</h1>
                <p class="mt-1 text-sm text-stone-600">RF-099 / RF-100 — Comisiones y autorización de crédito.</p>
            </div>
            <UiButton type="button" variant="primary" @click="openCreate">Nuevo asesor</UiButton>
        </div>

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>

        <div v-if="loading" class="py-12 text-center text-sm text-stone-500">Cargando…</div>
        <div v-else class="overflow-x-auto rounded-2xl border border-stone-200 bg-white/90 shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-600">
                    <tr>
                        <th class="px-3 py-2">Código</th>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Documento</th>
                        <th class="px-3 py-2">Contacto</th>
                        <th class="px-3 py-2 text-end">Com. nueva</th>
                        <th class="px-3 py-2 text-end">Com. recurrente</th>
                        <th class="px-3 py-2">Crédito</th>
                        <th class="px-3 py-2 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="a in advisors" :key="a.id" class="hover:bg-stone-50/80">
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ a.code }}</td>
                        <td class="px-3 py-2">{{ a.firstName }} {{ a.lastName || '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-xs">{{ a.documentType }} {{ a.documentNumber }}</td>
                        <td class="max-w-[10rem] truncate px-3 py-2 text-xs">{{ a.phone }} {{ a.email }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmtPesos(a.commissionNew) }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-end">{{ fmtPesos(a.commissionRecurring) }}</td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="a.authorizesCredits ? 'bg-teal-100 text-teal-900' : 'bg-stone-100 text-stone-600'"
                            >
                                {{ a.authorizesCredits ? 'Sí' : 'No' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-end">
                            <button type="button" class="mr-2 text-xs font-medium text-teal-800 hover:underline" @click="openEdit(a)">
                                Editar
                            </button>
                            <button type="button" class="text-xs font-medium text-red-700 hover:underline" @click="remove(a)">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!advisors.length" class="px-4 py-8 text-center text-sm text-stone-500">No hay asesores registrados.</p>
        </div>

        <UiModal v-model="showModal" :title="editingId ? 'Editar asesor' : 'Nuevo asesor'" max-width="max-w-lg">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-stone-600">Código *</label>
                    <input v-model="form.code" type="text" required class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Tipo doc.</label>
                    <input v-model="form.document_type" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Número doc.</label>
                    <input v-model="form.document_number" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Nombre *</label>
                    <input v-model="form.first_name" type="text" required class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Apellido</label>
                    <input v-model="form.last_name" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Teléfono</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Comisión afiliación nueva ($)</label>
                    <input v-model.number="form.commission_new" type="number" min="0" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-stone-600">Comisión recurrente ($)</label>
                    <input v-model.number="form.commission_recurring" type="number" min="0" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input id="svc-auth-credit" v-model="form.authorizes_credits" type="checkbox" class="rounded border-stone-300" />
                    <label for="svc-auth-credit" class="text-sm text-stone-700">Autoriza medio de pago crédito</label>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <UiButton type="button" variant="outline" @click="showModal = false">Cancelar</UiButton>
                    <UiButton type="button" variant="primary" :loading="saving" @click="submitForm">
                        {{ editingId ? 'Guardar' : 'Crear' }}
                    </UiButton>
                </div>
            </template>
        </UiModal>

        <UiToast :show="toast.show" :message="toast.text" :variant="toast.variant" />
    </div>
</template>
