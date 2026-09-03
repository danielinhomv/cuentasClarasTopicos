<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ViajeForm from '@/Pages/Viajes/ViajeForm.vue';

defineProps({
    viaje: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="Editar viaje">
        <template #header>
            <h2 class="font-bold text-2xl text-zinc-100 tracking-tight">
                Editar Viaje
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <ViajeForm :viaje="viaje" />
            </div>
        </div>
    </AppLayout>
</template>
