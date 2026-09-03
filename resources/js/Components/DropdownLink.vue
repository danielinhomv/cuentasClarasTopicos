<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-4 py-2.5 text-start text-sm leading-5 text-zinc-300 hover:bg-zinc-800 hover:text-cyan-300 focus:outline-none focus:bg-zinc-800 transition duration-150 ease-in-out cursor-pointer">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-4 py-2.5 text-sm leading-5 text-zinc-300 hover:bg-zinc-800 hover:text-cyan-300 focus:outline-none focus:bg-zinc-800 transition duration-150 ease-in-out">
            <slot />
        </a>

        <Link v-else :href="href" class="block px-4 py-2.5 text-sm leading-5 text-zinc-300 hover:bg-zinc-800 hover:text-cyan-300 focus:outline-none focus:bg-zinc-800 transition duration-150 ease-in-out">
            <slot />
        </Link>
    </div>
</template>
