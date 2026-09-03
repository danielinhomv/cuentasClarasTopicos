<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2.5 bg-rose-600/90 hover:bg-rose-500 border border-rose-500/40 rounded-lg font-semibold text-xs text-white uppercase tracking-widest shadow-lg shadow-rose-600/20 hover:shadow-rose-600/35 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50 transition-all duration-150 active:scale-95 cursor-pointer">
        <slot />
    </button>
</template>
