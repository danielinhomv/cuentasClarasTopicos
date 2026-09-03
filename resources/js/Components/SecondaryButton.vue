<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2.5 bg-zinc-800/90 hover:bg-zinc-700/90 border border-zinc-700 hover:border-zinc-500 rounded-lg font-semibold text-xs text-zinc-200 uppercase tracking-widest shadow-sm hover:text-white focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-30 transition-all duration-150 cursor-pointer">
        <slot />
    </button>
</template>
