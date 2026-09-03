<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="flex flex-col items-center gap-2 group">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-cyan-500 to-emerald-400 flex items-center justify-center font-black text-zinc-950 text-2xl shadow-xl shadow-cyan-500/25 group-hover:shadow-cyan-400/50 transition-all duration-200">
            CC
        </div>
        <span class="font-extrabold text-xl tracking-tight bg-gradient-to-r from-cyan-300 via-emerald-300 to-teal-200 bg-clip-text text-transparent">
            Cuentas Claras
        </span>
    </Link>
</template>
