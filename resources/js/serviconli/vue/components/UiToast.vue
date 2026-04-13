<script setup>
defineProps({
    show: { type: Boolean, default: false },
    message: { type: String, default: '' },
    variant: { type: String, default: 'info' },
});

const variantClass = {
    info: 'bg-teal-900 text-white',
    success: 'bg-emerald-800 text-white',
    error: 'bg-red-800 text-white',
    warning: 'bg-amber-800 text-white',
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show && message"
                class="fixed bottom-6 left-1/2 z-[60] max-w-md -translate-x-1/2 rounded-xl px-4 py-3 text-sm shadow-lg"
                :class="variantClass[variant] || variantClass.info"
            >
                {{ message }}
            </div>
        </Transition>
    </Teleport>
</template>
