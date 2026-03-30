<script setup>
import { ref } from 'vue';
import { getToken, setToken } from '../api';

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
    <v-container class="max-w-xl">
        <h1 class="font-serif-svc text-3xl font-bold text-stone-900 mb-2">Iniciar sesión</h1>
        <p class="text-sm text-stone-600 mb-6">Accede con el mismo usuario que usa la API (Sanctum).</p>

        <v-card class="rounded-xl border border-stone-200/90 bg-white/90">
            <v-card-text class="space-y-4">
                <v-text-field v-model="email" label="Correo" type="email" variant="outlined" density="comfortable" />
                <v-text-field v-model="password" label="Contraseña" type="password" variant="outlined" density="comfortable" />
                <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
                <v-btn block color="teal-darken-3" :loading="loading" @click="submit">Entrar</v-btn>
            </v-card-text>
        </v-card>

        <p class="mt-6 text-center text-xs text-stone-500">
            <a href="/" class="text-teal-800 hover:underline">Volver al inicio</a>
        </p>
    </v-container>
</template>
