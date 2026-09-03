<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear Cuenta" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-6 text-center">
            <h2 class="text-xl font-bold text-zinc-100 tracking-tight">Crear Cuenta</h2>
            <p class="text-xs text-zinc-400 mt-1">Regístrate para crear viajes o unirte al grupo de tus amigos.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Tu nombre completo" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Ej. Ana García"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Correo electrónico" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    placeholder="tu@correo.com"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Contraseña" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2 text-xs text-zinc-400">
                            Acepto los <a target="_blank" :href="route('terms.show')" class="underline text-cyan-400 hover:text-cyan-300">Términos de Servicio</a> y la <a target="_blank" :href="route('policy.show')" class="underline text-cyan-400 hover:text-cyan-300">Política de Privacidad</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="pt-2">
                <PrimaryButton class="w-full justify-center py-3 text-sm" :disabled="form.processing">
                    <span v-if="form.processing">Creando cuenta...</span>
                    <span v-else>Crear Cuenta</span>
                </PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-center text-xs text-zinc-400">
            ¿Ya tienes una cuenta?
            <Link
                :href="route('login')"
                class="font-bold text-cyan-400 hover:text-cyan-300 transition underline underline-offset-4 ms-1"
            >
                Inicia sesión aquí
            </Link>
        </p>
    </AuthenticationCard>
</template>
