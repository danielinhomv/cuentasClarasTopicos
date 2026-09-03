<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ViajeForm from '@/Pages/Viajes/ViajeForm.vue';
</script>

<template>
    <AppLayout title="Nuevo viaje">
        <template #header>
            <h2 class="font-bold text-2xl text-zinc-100 tracking-tight">
                Crear Nuevo Viaje
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <ViajeForm />
            </div>
        </div>
    </AppLayout>
</template>
