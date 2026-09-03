<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form @submit.prevent="$emit('submitted')">
                <div
                    class="px-5 py-6 bg-zinc-900 border border-zinc-800 text-zinc-100 shadow-xl"
                    :class="hasActions ? 'sm:rounded-t-2xl' : 'sm:rounded-2xl'"
                >
                    <div class="grid grid-cols-6 gap-6">
                        <slot name="form" />
                    </div>
                </div>

                <div v-if="hasActions" class="flex items-center justify-end px-6 py-4 bg-zinc-950/70 border-x border-b border-zinc-800 text-end shadow-xl sm:rounded-b-2xl">
                    <slot name="actions" />
                </div>
            </form>
        </div>
    </div>
</template>
