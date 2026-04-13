<script setup>
import { onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['update:modelValue']);

function close() {
    emit('update:modelValue', false);
}

function onKey(e) {
    if (e.key === 'Escape' && props.modelValue) close();
}

watch(
    () => props.modelValue,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    },
);

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center sm:p-6"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-stone-900/50 backdrop-blur-sm" @click="close" />
            <div
                class="relative z-10 flex max-h-[90vh] w-full flex-col overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-xl"
                :class="maxWidth"
            >
                <div v-if="title || $slots.title" class="flex shrink-0 items-center justify-between border-b border-stone-200/80 px-5 py-4">
                    <h2 class="font-serif-svc text-lg font-semibold text-stone-900">
                        <slot name="title">{{ title }}</slot>
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800"
                        aria-label="Cerrar"
                        @click="close"
                    >
                        <span class="text-xl leading-none">×</span>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="shrink-0 border-t border-stone-200/80 px-5 py-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
