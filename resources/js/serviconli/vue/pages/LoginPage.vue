<script setup>
import { ref } from 'vue';
import { getToken, setToken } from '../api';
import UiButton from '../components/UiButton.vue';

if (getToken()) {
    window.location.replace('/mis-afiliados');
}

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        const res = await fetch('/api/login', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email.value,
                password: password.value,
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            error.value = data.message || data.errors?.email?.[0] || 'Credenciales incorrectas.';
            return;
        }
        if (data.token) {
            setToken(data.token);
        }
        window.location.href = '/mis-afiliados';
    } catch {
        error.value = 'No se pudo conectar. Intente de nuevo.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto w-full max-w-md">
        <h1 class="font-serif-svc text-3xl font-bold text-stone-900">Iniciar sesión</h1>
        <p class="mt-2 text-sm text-stone-600">Accede con el usuario de la API (Laravel Sanctum).</p>

        <form
            class="mt-8 space-y-4 rounded-2xl border border-stone-200/90 bg-white/90 p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <label for="svc-email" class="mb-1 block text-sm font-medium text-stone-700">Correo</label>
                <input
                    id="svc-email"
                    v-model="email"
                    type="email"
                    autocomplete="username"
                    required
                    class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-stone-900 shadow-sm outline-none ring-teal-700/20 transition focus:border-teal-700 focus:ring-2"
                />
            </div>
            <div>
                <label for="svc-pass" class="mb-1 block text-sm font-medium text-stone-700">Contraseña</label>
                <input
                    id="svc-pass"
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-stone-900 shadow-sm outline-none ring-teal-700/20 transition focus:border-teal-700 focus:ring-2"
                />
            </div>
            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ error }}</p>
            <UiButton type="submit" variant="primary" size="lg" block :loading="loading">
                {{ loading ? 'Entrando…' : 'Entrar' }}
            </UiButton>
        </form>

        <p class="mt-8 text-center text-xs text-stone-500">
            <a href="/" class="text-teal-800 underline decoration-teal-800/30 hover:decoration-teal-800">Volver al inicio</a>
        </p>
    </div>
</template>
