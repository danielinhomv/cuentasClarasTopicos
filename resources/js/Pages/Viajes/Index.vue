<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
        ? 'bg-indigo-100 text-indigo-800'
        : 'bg-gray-100 text-gray-700';
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
</script>

<template>
    <AppLayout title="Viajes">
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Viajes
                </h2>
                <Link :href="route('viajes.create')">
                    <PrimaryButton type="button">
                        Nuevo viaje
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 border-b border-gray-200">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="search" class="block font-medium text-sm text-gray-700">Buscar</label>
                                <TextInput
                                    id="search"
                                    v-model="search"
                                    type="search"
                                    class="mt-1 block w-full"
                                    placeholder="Nombre del viaje"
                                    autocomplete="off"
                                />
                            </div>
                            <div>
                                <label for="status" class="block font-medium text-sm text-gray-700">Estado</label>
                                <select
                                    id="status"
                                    v-model="status"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="con_participantes">Con participantes</option>
                                    <option value="sin_participantes">Sin participantes</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="loading" class="p-6 lg:p-8 space-y-4" aria-live="polite" aria-busy="true">
                        <div v-for="n in 4" :key="n" class="animate-pulse h-16 bg-gray-100 rounded-md" />
                    </div>

                    <div v-else-if="isEmpty" class="p-10 text-center">
                        <h3 class="text-lg font-medium text-gray-900">Todavía no tienes viajes</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Crea tu primer viaje para empezar a agregar participantes y, más adelante, gastos.
                        </p>
                        <Link :href="route('viajes.create')" class="inline-block mt-6">
                            <PrimaryButton type="button">Crear viaje</PrimaryButton>
                        </Link>
                    </div>

                    <div v-else-if="isFilteredEmpty" class="p-10 text-center">
                        <h3 class="text-lg font-medium text-gray-900">No hay viajes con esos filtros</h3>
                        <p class="mt-2 text-sm text-gray-600">Prueba con otro nombre o limpia el estado.</p>
                        <SecondaryButton class="mt-6" type="button" @click="search = ''; status = ''">
                            Limpiar filtros
                        </SecondaryButton>
                    </div>

                    <div v-else>
                        <div class="md:hidden divide-y divide-gray-200">
                            <article v-for="viaje in rows" :key="viaje.id" class="p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <Link :href="route('viajes.show', viaje.id)" class="text-base font-semibold text-gray-900 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                            {{ viaje.nombre }}
                                        </Link>
                                        <p class="mt-1 text-sm text-gray-500">{{ formatDate(viaje.created_at) }}</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="tripStatusClass(viaje)">
                                        {{ tripStatus(viaje) }}
                                    </span>
                                </div>
                                <p class="mt-3 text-sm text-gray-600">
                                    {{ viaje.participantes_count }} participante{{ viaje.participantes_count === 1 ? '' : 's' }}
                                </p>
                                <Link :href="route('viajes.show', viaje.id)" class="mt-3 inline-block text-sm font-semibold text-indigo-700">
                                    Ver detalle
                                </Link>
                            </article>
                        </div>

                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Participantes</th>
                                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">Creado</th>
                                        <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="viaje in rows" :key="viaje.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ viaje.nombre }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="tripStatusClass(viaje)">
                                                {{ tripStatus(viaje) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            {{ viaje.participantes_count }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            {{ formatDate(viaje.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                                            <Link :href="route('viajes.show', viaje.id)" class="font-semibold text-indigo-700 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                                Ver
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <nav v-if="pageLinks.length > 3" class="px-6 py-4 border-t border-gray-200 flex flex-wrap gap-2" aria-label="Paginación">
                            <Link
                                v-for="link in pageLinks"
                                :key="link.label"
                                :href="link.url || ''"
                                :class="[
                                    'px-3 py-1 text-sm rounded-md',
                                    link.active ? 'bg-gray-800 text-white' : 'text-gray-700 hover:bg-gray-100',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
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
        </div>
    </AppLayout>
</template>
