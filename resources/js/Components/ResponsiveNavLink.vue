<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active: Boolean,
    href: String,
    as: String,
});

const classes = computed(() => {
    return props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-cyan-400 text-start text-base font-semibold text-cyan-300 bg-cyan-950/40 focus:outline-none transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 hover:border-zinc-700 focus:outline-none transition duration-150 ease-in-out';
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" :class="classes" class="w-full text-start">
            <slot />
        </button>

        <a v-else-if="as == 'a'" :class="classes" class="w-full text-start" :href="href">
            <slot />
        </a>

        <Link v-else :href="href" :class="classes">
            <slot />
        </Link>
    </div>
</template>
