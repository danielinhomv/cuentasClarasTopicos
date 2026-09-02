<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ViajeForm from '@/Pages/Viajes/ViajeForm.vue';
</script>

<template>
    <AppLayout title="Nuevo viaje">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Nuevo viaje
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <ViajeForm />
            </div>
        </div>
    </AppLayout>
</template>
