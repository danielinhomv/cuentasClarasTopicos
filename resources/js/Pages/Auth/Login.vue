<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-6 text-center">
            <h2 class="text-xl font-bold text-zinc-100 tracking-tight">Iniciar Sesión</h2>
            <p class="text-xs text-zinc-400 mt-1">Ingresa a tus viajes y administra tus cuentas claras.</p>
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

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Contraseña" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-zinc-400 hover:text-cyan-300 transition"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="text-xs text-zinc-300">Mantener sesión iniciada</span>
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton class="w-full justify-center py-3 text-sm" :disabled="form.processing">
                    <span v-if="form.processing">Iniciando sesión...</span>
                    <span v-else>Iniciar Sesión</span>
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-xs text-zinc-400">
            ¿No tienes una cuenta?
            <Link
                :href="route('register')"
                class="font-bold text-cyan-400 hover:text-cyan-300 transition underline underline-offset-4 ms-1"
            >
                Regístrate gratis
            </Link>
        </p>
    </AuthenticationCard>
</template>
