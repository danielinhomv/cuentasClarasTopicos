<script setup>
import Modal from './Modal.vue';

const emit = defineEmits(['close']);

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div class="px-6 py-5">
            <div class="text-lg font-semibold text-zinc-100 flex items-center gap-2">
                <slot name="title" />
            </div>

            <div class="mt-4 text-sm text-zinc-300">
                <slot name="content" />
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-zinc-950/70 border-t border-zinc-800/80 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
