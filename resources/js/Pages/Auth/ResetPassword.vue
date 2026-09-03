<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Restablecer Contraseña" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-6 text-center">
            <h2 class="text-xl font-bold text-zinc-100 tracking-tight">Restablecer Contraseña</h2>
            <p class="text-xs text-zinc-400 mt-1">Crea una nueva contraseña segura para tu cuenta.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Nueva contraseña" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmar nueva contraseña" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <PrimaryButton class="w-full justify-center py-3 text-sm" :disabled="form.processing">
                    <span v-if="form.processing">Restableciendo...</span>
                    <span v-else>Restablecer Contraseña</span>
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
