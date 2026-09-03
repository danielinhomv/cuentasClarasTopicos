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
        <div class="px-6 py-5 bg-zinc-900 text-zinc-100">
            <div class="sm:flex sm:items-start gap-4">
                <div class="mx-auto shrink-0 flex items-center justify-center size-12 rounded-xl bg-rose-950/60 border border-rose-500/30 sm:mx-0 sm:size-10 shadow-lg shadow-rose-950/50">
                    <svg class="size-5 text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:text-start flex-1">
                    <h3 class="text-lg font-semibold text-zinc-100">
                        <slot name="title" />
                    </h3>

                    <div class="mt-2 text-sm text-zinc-400 leading-relaxed">
                        <slot name="content" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-zinc-950/70 border-t border-zinc-800/80 text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
