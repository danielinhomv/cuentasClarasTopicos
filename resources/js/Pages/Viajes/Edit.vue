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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editar viaje
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <ViajeForm :viaje="viaje" />
            </div>
        </div>
    </AppLayout>
</template>
