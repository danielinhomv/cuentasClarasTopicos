<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    viajes: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: '' }),
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const loading = ref(false);
const modalUnirseOpen = ref(false);

const unirseForm = useForm({
    codigo_invitacion: '',
});

const rows = computed(() => props.viajes.data ?? []);
const pageLinks = computed(() => props.viajes.meta?.links ?? []);
const isEmpty = computed(() => !loading.value && rows.value.length === 0 && !props.filters.search && !props.filters.status);
const isFilteredEmpty = computed(() => !loading.value && rows.value.length === 0 && (props.filters.search || props.filters.status));

let debounceId = null;

const applyFilters = () => {
    loading.value = true;
    router.get(route('viajes.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

watch(search, () => {
    clearTimeout(debounceId);
    debounceId = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

const tripStatus = (viaje) => {
    return viaje.participantes_count > 0 ? 'Con participantes' : 'Sin participantes';
};

const tripStatusClass = (viaje) => {
    return viaje.participantes_count > 0
        ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-500/30'
        : 'bg-zinc-800/80 text-zinc-400 border border-zinc-700/60';
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('es-BO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const submitUnirse = () => {
    unirseForm.codigo_invitacion = unirseForm.codigo_invitacion.trim().toUpperCase();
    unirseForm.post(route('viajes.unirse'), {
        preserveScroll: true,
        onSuccess: () => {
            modalUnirseOpen.value = false;
            unirseForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout title="Mis Viajes">
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-zinc-100 tracking-tight flex items-center gap-3">
                        <span>Mis Viajes</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-cyan-950/60 text-cyan-300 border border-cyan-500/30">
                            {{ viajes.total ?? rows.length }} total
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-1">Viajes que has creado o en los que participas como invitado.</p>
                </div>

                <div class="flex items-center gap-3">
                    <SecondaryButton type="button" @click="modalUnirseOpen = true" class="gap-2">
                        <svg class="size-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <span>Unirme con código</span>
                    </SecondaryButton>

                    <Link :href="route('viajes.create')">
                        <PrimaryButton type="button" class="gap-2">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nuevo viaje</span>
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Filtros -->
                <div class="bg-zinc-900/80 border border-zinc-800/90 rounded-2xl p-5 backdrop-blur shadow-xl">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="search" class="block font-medium text-xs text-zinc-400 uppercase tracking-wider mb-1.5">Buscar por nombre</label>
                            <TextInput
                                id="search"
                                v-model="search"
                                type="search"
                                class="w-full"
                                placeholder="Ej. Samaipata, Cancún..."
                                autocomplete="off"
                            />
                        </div>
                        <div>
                            <label for="status" class="block font-medium text-xs text-zinc-400 uppercase tracking-wider mb-1.5">Filtrar por estado</label>
                            <select
                                id="status"
                                v-model="status"
                                class="w-full bg-zinc-900/90 border border-zinc-700 text-zinc-100 rounded-lg shadow-inner focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 text-sm"
                            >
                                <option value="">Todos los viajes</option>
                                <option value="con_participantes">Con participantes</option>
                                <option value="sin_participantes">Sin participantes</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Skeleton de Carga -->
                <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" aria-live="polite">
                    <div v-for="n in 3" :key="n" class="animate-pulse h-48 bg-zinc-900 border border-zinc-800 rounded-2xl p-6" />
                </div>

                <!-- Estado Vacío -->
                <div v-else-if="isEmpty" class="bg-zinc-900/60 border border-dashed border-zinc-800 rounded-2xl p-12 text-center">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-cyan-950/60 border border-cyan-500/30 flex items-center justify-center text-cyan-400 mb-4 shadow-lg shadow-cyan-950/50">
                        <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-zinc-100">Aún no participas en ningún viaje</h3>
                    <p class="mt-2 text-sm text-zinc-400 max-w-md mx-auto">
                        Crea tu propio viaje o únete al de un amigo ingresando su código de invitación.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <SecondaryButton @click="modalUnirseOpen = true" type="button">
                            Unirme con código
                        </SecondaryButton>
                        <Link :href="route('viajes.create')">
                            <PrimaryButton type="button">Crear mi primer viaje</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <!-- Estado Filtrado Vacío -->
                <div v-else-if="isFilteredEmpty" class="bg-zinc-900/60 border border-dashed border-zinc-800 rounded-2xl p-10 text-center">
                    <h3 class="text-base font-semibold text-zinc-200">No se encontraron viajes con esos criterios</h3>
                    <p class="mt-1 text-sm text-zinc-400">Intenta con otro término o limpia los filtros.</p>
                    <SecondaryButton class="mt-5" type="button" @click="search = ''; status = ''">
                        Limpiar filtros
                    </SecondaryButton>
                </div>

                <!-- Grid de Viajes -->
                <div v-else class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <article
                            v-for="viaje in rows"
                            :key="viaje.id"
                            class="bg-zinc-900/90 border border-zinc-800 hover:border-cyan-500/40 rounded-2xl p-6 transition-all duration-200 hover:shadow-xl hover:shadow-cyan-950/20 flex flex-col justify-between group"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="text-lg font-bold text-zinc-100 group-hover:text-cyan-300 transition-colors line-clamp-1">
                                        {{ viaje.nombre }}
                                    </h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap" :class="tripStatusClass(viaje)">
                                        {{ tripStatus(viaje) }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-500 mt-1">Creado: {{ formatDate(viaje.created_at) }}</p>

                                <p class="mt-3 text-sm text-zinc-300 line-clamp-2 leading-relaxed">
                                    {{ viaje.descripcion || 'Sin descripción adicional.' }}
                                </p>
                            </div>

                            <div class="mt-6 pt-4 border-t border-zinc-800/80 flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-xs text-zinc-400 font-medium">
                                    <svg class="size-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    <span>{{ viaje.participantes_count }} integrante{{ viaje.participantes_count === 1 ? '' : 's' }}</span>
                                </div>

                                <Link
                                    :href="route('viajes.show', viaje.id)"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-cyan-400 hover:text-cyan-300 group-hover:translate-x-0.5 transition-all"
                                >
                                    <span>Ver viaje</span>
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </Link>
                            </div>
                        </article>
                    </div>

                    <!-- Paginación -->
                    <nav v-if="pageLinks.length > 3" class="p-4 bg-zinc-900/60 border border-zinc-800 rounded-2xl flex flex-wrap justify-center gap-2" aria-label="Paginación">
                        <Link
                            v-for="link in pageLinks"
                            :key="link.label"
                            :href="link.url || ''"
                            :class="[
                                'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all',
                                link.active ? 'bg-cyan-500 text-zinc-950 shadow-md shadow-cyan-500/30' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white',
                                !link.url ? 'pointer-events-none opacity-40' : '',
                            ]"
                            preserve-scroll
                            preserve-state
                        >
                            <span v-html="link.label" />
                        </Link>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Modal Unirse con Código -->
        <DialogModal :show="modalUnirseOpen" @close="modalUnirseOpen = false" max-width="md">
            <template #title>
                <div class="flex items-center gap-2">
                    <span class="text-cyan-400">🔑</span>
                    <span>Unirme a un Viaje con Código</span>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="submitUnirse" class="space-y-4">
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Pídele a quien creó el viaje el código de 8 caracteres e ingrésalo a continuación para sumarte al grupo.
                    </p>

                    <div>
                        <InputLabel for="codigo_invitacion" value="Código de invitación" />
                        <TextInput
                            id="codigo_invitacion"
                            v-model="unirseForm.codigo_invitacion"
                            type="text"
                            class="w-full font-mono text-center tracking-widest text-lg uppercase font-bold text-cyan-300 placeholder-zinc-600"
                            placeholder="Ej. SAMA8X12"
                            maxlength="8"
                            required
                            autofocus
                        />
                        <InputError :message="unirseForm.errors.codigo_invitacion" class="mt-1.5" />
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="modalUnirseOpen = false" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    @click="submitUnirse"
                    :disabled="unirseForm.codigo_invitacion.trim().length !== 8 || unirseForm.processing"
                    type="button"
                >
                    <span v-if="unirseForm.processing">Uniéndote...</span>
                    <span v-else>Unirme al Viaje</span>
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
