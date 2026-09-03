<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Recuperar Contraseña" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-center">
            <h2 class="text-xl font-bold text-zinc-100 tracking-tight">Recuperar Contraseña</h2>
            <p class="text-xs text-zinc-400 mt-1">
                Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
            </p>
        </div>

        <div v-if="status" class="mb-4 font-medium text-sm text-emerald-400 bg-emerald-950/50 border border-emerald-500/40 p-3 rounded-xl">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    placeholder="tu@correo.com"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <PrimaryButton class="w-full justify-center py-3 text-sm" :disabled="form.processing">
                    <span v-if="form.processing">Enviando enlace...</span>
                    <span v-else>Enviar enlace de recuperación</span>
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-xs text-zinc-400">
            <Link
                :href="route('login')"
                class="font-bold text-cyan-400 hover:text-cyan-300 transition underline underline-offset-4"
            >
                &larr; Volver al inicio de sesión
            </Link>
        </p>
    </AuthenticationCard>
</template>
