<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100 antialiased selection:bg-cyan-500 selection:text-zinc-950">
        <Head :title="title" />

        <Banner />

        <nav class="bg-zinc-900/90 backdrop-blur-md border-b border-zinc-800/80 sticky top-0 z-40">
            <!-- Primary Navigation Menu -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Logo -->
                        <Link :href="route('viajes.index')" class="flex items-center gap-2.5 group">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-emerald-400 flex items-center justify-center font-black text-zinc-950 text-base shadow-lg shadow-cyan-500/20 group-hover:shadow-cyan-400/40 transition-all duration-200">
                                CC
                            </div>
                            <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-cyan-300 via-emerald-300 to-teal-200 bg-clip-text text-transparent">
                                Cuentas Claras
                            </span>
                        </Link>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                            <NavLink :href="route('viajes.index')" :active="route().current('viajes.*')">
                                Viajes
                            </NavLink>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <!-- Settings Dropdown -->
                        <div class="ms-3 relative">
                            <Dropdown align="right" width="48" :content-classes="['py-1', 'bg-zinc-900', 'border', 'border-zinc-800', 'shadow-2xl', 'text-zinc-200', 'rounded-xl']">
                                <template #trigger>
                                    <button class="inline-flex items-center gap-2 px-3 py-1.5 border border-zinc-700/80 text-sm font-medium rounded-lg text-zinc-200 bg-zinc-800/70 hover:bg-zinc-700/70 hover:text-cyan-300 focus:outline-none transition ease-in-out duration-150">
                                        <span>{{ $page.props.auth.user.name }}</span>
                                        <svg class="size-4 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="block px-4 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                        Cuenta
                                    </div>

                                    <DropdownLink :href="route('profile.show')">
                                        Mi Perfil
                                    </DropdownLink>

                                    <div class="border-t border-zinc-800" />

                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button">
                                            Cerrar sesión
                                        </DropdownLink>
                                    </form>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Hamburger -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button class="inline-flex items-center justify-center p-2 rounded-lg text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 focus:outline-none transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                            <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Responsive Navigation Menu -->
            <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden border-b border-zinc-800 bg-zinc-900/95">
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink :href="route('viajes.index')" :active="route().current('viajes.*')">
                        Viajes
                    </ResponsiveNavLink>
                </div>

                <div class="pt-4 pb-3 border-t border-zinc-800">
                    <div class="flex items-center px-4">
                        <div>
                            <div class="font-medium text-base text-zinc-200">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="font-medium text-sm text-zinc-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                            Mi Perfil
                        </ResponsiveNavLink>

                        <form method="POST" @submit.prevent="logout">
                            <ResponsiveNavLink as="button">
                                Cerrar sesión
                            </ResponsiveNavLink>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header v-if="$slots.header" class="bg-zinc-900/40 border-b border-zinc-800/60 shadow-sm">
            <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
