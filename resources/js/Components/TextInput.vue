<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: [String, Number],
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        ref="input"
        class="bg-zinc-900/90 border border-zinc-700 text-zinc-100 placeholder-zinc-500 rounded-lg shadow-inner focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all duration-150"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
