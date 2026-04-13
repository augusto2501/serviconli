<script setup>
/**
 * Botón primario del shell Serviconli (Tailwind).
 *
 * @see CLAUDE.md — Frontend Vue + Tailwind
 */
defineProps({
    type: { type: String, default: 'button' },
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'outline', 'danger', 'ghost'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const variantClass = {
    primary: 'bg-teal-800 text-white shadow-sm hover:bg-teal-900 disabled:opacity-60',
    outline: 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50 disabled:opacity-60',
    danger: 'bg-red-700 text-white shadow-sm hover:bg-red-800 disabled:opacity-60',
    ghost: 'text-teal-800 hover:bg-teal-50 disabled:opacity-60',
};

const sizeClass = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-6 py-3 text-sm',
};
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition"
        :class="[variantClass[variant], sizeClass[size], block ? 'w-full' : '']"
        :disabled="disabled || loading"
    >
        <span
            v-if="loading"
            class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
