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
const showPassword = ref(false);

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
        error.value = 'No se pudo conectar con el servidor. Intente de nuevo.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Panel izquierdo: branding -->
        <div class="hidden w-[45%] flex-col justify-between bg-teal-900 p-10 text-white lg:flex xl:w-[42%]">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold backdrop-blur-sm">
                        S
                    </div>
                    <span class="font-serif-svc text-xl font-bold tracking-tight">Serviconli</span>
                </div>
            </div>

            <div class="space-y-8">
                <h2 class="font-serif-svc text-3xl font-bold leading-tight xl:text-4xl">
                    Gestión integral de seguridad social
                </h2>
                <p class="max-w-sm text-base leading-relaxed text-teal-100/80">
                    Afiliaciones, liquidación PILA, facturación y cuadre de caja
                    en un solo sistema. Rápido, confiable y siempre actualizado.
                </p>
                <div class="flex items-center gap-6 text-sm text-teal-200/70">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        ~891 afiliados
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Formato ARUS
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Normativa 2026
                    </div>
                </div>
            </div>

            <div class="text-xs text-teal-300/50">
                <p>NIT 900966567-4 &middot; Armenia, Quindío</p>
            </div>
        </div>

        <!-- Panel derecho: formulario -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 sm:px-10">
            <!-- Logo móvil -->
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-900 text-sm font-bold text-white">
                    S
                </div>
                <span class="font-serif-svc text-lg font-bold text-stone-900">Serviconli</span>
            </div>

            <div class="w-full max-w-sm">
                <div class="mb-8">
                    <h1 class="font-serif-svc text-2xl font-bold text-stone-900 sm:text-3xl">Bienvenido</h1>
                    <p class="mt-2 text-sm text-stone-500">Ingresa tus credenciales para acceder al sistema.</p>
                </div>

                <!-- Error global -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-1 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <div v-if="error" class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <p class="text-sm font-medium text-red-800">{{ error }}</p>
                    </div>
                </Transition>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label for="svc-email" class="mb-1.5 block text-sm font-medium text-stone-700">Correo electrónico</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg class="h-[1.125rem] w-[1.125rem] text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <input
                                id="svc-email"
                                v-model="email"
                                type="email"
                                autocomplete="username"
                                required
                                placeholder="usuario@empresa.com"
                                class="w-full rounded-xl border border-stone-300 bg-white py-2.5 pl-10 pr-4 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                            />
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="svc-pass" class="mb-1.5 block text-sm font-medium text-stone-700">Contraseña</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg class="h-[1.125rem] w-[1.125rem] text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input
                                id="svc-pass"
                                v-model="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                placeholder="••••••••"
                                class="w-full rounded-xl border border-stone-300 bg-white py-2.5 pl-10 pr-11 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-stone-400 transition hover:text-stone-600"
                                @click="showPassword = !showPassword"
                                tabindex="-1"
                            >
                                <svg v-if="!showPassword" class="h-[1.125rem] w-[1.125rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg v-else class="h-[1.125rem] w-[1.125rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="relative mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-800 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700/40 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading"
                    >
                        <span
                            v-if="loading"
                            class="absolute left-1/2 -translate-x-1/2"
                        >
                            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                        </span>
                        <span :class="loading ? 'invisible' : ''">Iniciar sesión</span>
                    </button>
                </form>

                <p class="mt-10 text-center text-xs text-stone-400">
                    Serviconli &copy; {{ new Date().getFullYear() }} &middot; NIT 900966567-4
                </p>
            </div>
        </div>
    </div>
</template>
