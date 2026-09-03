<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-400 hover:to-emerald-400 text-zinc-950 font-bold rounded-lg text-xs uppercase tracking-widest shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50 transition-all duration-150 active:scale-95 cursor-pointer">
        <slot />
    </button>
</template>
