<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    viaje: {
        type: Object,
        required: true,
    },
});

const participantSearch = ref('');
const confirmingTripDeletion = ref(false);
const confirmingParticipantDeletion = ref(false);
const participantToDelete = ref(null);
const editingParticipant = ref(null);

const addForm = useForm({
    nombre: '',
});

const editForm = useForm({
    nombre: '',
});

const deleteTripForm = useForm({});
const deleteParticipantForm = useForm({});

const participantes = computed(() => props.viaje.participantes ?? []);

const filteredParticipantes = computed(() => {
    const term = participantSearch.value.trim().toLowerCase();

    if (!term) {
        return participantes.value;
    }

    return participantes.value.filter((participante) => (
        participante.nombre.toLowerCase().includes(term)
    ));
});

const addNameNormalized = computed(() => addForm.nombre.trim().toLowerCase());

const isDuplicateAdd = computed(() => {
    if (!addNameNormalized.value) {
        return false;
    }

    return participantes.value.some((participante) => (
        participante.nombre.toLowerCase() === addNameNormalized.value
    ));
});

const addClientError = computed(() => {
    const nombre = addForm.nombre.trim();

    if (nombre.length === 0) {
        return 'El nombre del participante es obligatorio.';
    }

    if (nombre.length < 2) {
        return 'El nombre del participante debe tener al menos 2 caracteres.';
    }

    if (isDuplicateAdd.value) {
        return 'Ya existe un participante con ese nombre en este viaje.';
    }

    return '';
});

const addError = computed(() => {
    if (addForm.errors.nombre) {
        return addForm.errors.nombre;
    }

    if (addForm.nombre.trim().length === 0) {
        return '';
    }

    return addClientError.value;
});

const submitParticipant = () => {
    if (addClientError.value) {
        return;
    }

    addForm.post(route('viajes.participantes.store', props.viaje.id), {
        preserveScroll: true,
        onSuccess: () => addForm.reset('nombre'),
    });
};

const openEdit = (participante) => {
    editingParticipant.value = participante;
    editForm.nombre = participante.nombre;
    editForm.clearErrors();
};

const closeEdit = () => {
    editingParticipant.value = null;
    editForm.reset();
};

const editClientError = computed(() => {
    const nombre = editForm.nombre.trim();

    if (nombre.length === 0) {
        return 'El nombre del participante es obligatorio.';
    }

    if (nombre.length < 2) {
        return 'El nombre del participante debe tener al menos 2 caracteres.';
    }

    const duplicate = participantes.value.some((participante) => (
        participante.id !== editingParticipant.value?.id
        && participante.nombre.toLowerCase() === nombre.toLowerCase()
    ));

    if (duplicate) {
        return 'Ya existe un participante con ese nombre en este viaje.';
    }

    return '';
});

const saveEdit = () => {
    if (!editingParticipant.value || editClientError.value) {
        return;
    }

    editForm.put(route('participantes.update', editingParticipant.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const confirmDeleteParticipant = (participante) => {
    participantToDelete.value = participante;
    confirmingParticipantDeletion.value = true;
};

const deleteParticipant = () => {
    if (!participantToDelete.value) {
        return;
    }

    deleteParticipantForm.delete(route('participantes.destroy', participantToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingParticipantDeletion.value = false;
            participantToDelete.value = null;
        },
    });
};

const deleteTrip = () => {
    deleteTripForm.delete(route('viajes.destroy', props.viaje.id));
};

const focusAddForm = () => {
    document.getElementById('participante-nombre')?.focus();
};
</script>

<template>
    <AppLayout :title="viaje.nombre">
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ viaje.nombre }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ participantes.length }} participante{{ participantes.length === 1 ? '' : 's' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link :href="route('viajes.index')">
                        <SecondaryButton type="button">Volver al listado</SecondaryButton>
                    </Link>
                    <Link :href="route('viajes.edit', viaje.id)">
                        <PrimaryButton type="button">Editar viaje</PrimaryButton>
                    </Link>
                    <DangerButton @click="confirmingTripDeletion = true">
                        Eliminar viaje
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <section class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 lg:p-8">
                    <h3 class="text-lg font-medium text-gray-900">Resumen</h3>
                    <p class="mt-3 text-sm text-gray-600">
                        {{ viaje.descripcion || 'Sin descripción.' }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <SecondaryButton type="button" @click="focusAddForm">
                            Agregar participante
                        </SecondaryButton>
                        <Link :href="route('viajes.edit', viaje.id)">
                            <SecondaryButton type="button">Editar datos</SecondaryButton>
                        </Link>
                    </div>
                </section>

                <section class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 lg:p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Participantes</h3>
                            <p class="mt-1 text-sm text-gray-600">Los nombres deben ser únicos dentro de este viaje.</p>
                        </div>
                        <div class="w-full sm:w-64">
                            <InputLabel for="buscar-participantes" value="Buscar participante" />
                            <TextInput
                                id="buscar-participantes"
                                v-model="participantSearch"
                                type="search"
                                class="mt-1 block w-full"
                                placeholder="Filtrar lista"
                            />
                        </div>
                    </div>

                    <form class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto] sm:items-end" @submit.prevent="submitParticipant">
                        <div>
                            <InputLabel for="participante-nombre" value="Nuevo participante" />
                            <TextInput
                                id="participante-nombre"
                                v-model="addForm.nombre"
                                type="text"
                                class="mt-1 block w-full"
                                maxlength="100"
                                autocomplete="off"
                            />
                            <p class="mt-1 text-xs text-gray-500">Mínimo 2 caracteres. No se permiten nombres repetidos.</p>
                            <InputError :message="addError" class="mt-2" />
                        </div>
                        <PrimaryButton :disabled="Boolean(addClientError) || addForm.processing">
                            <span v-if="addForm.processing">Agregando…</span>
                            <span v-else>Agregar</span>
                        </PrimaryButton>
                    </form>

                    <div v-if="filteredParticipantes.length === 0" class="mt-8 text-center py-8 border border-dashed border-gray-200 rounded-md">
                        <p v-if="participantes.length === 0" class="text-sm text-gray-600">
                            Este viaje aún no tiene participantes. Agrega el primero para continuar.
                        </p>
                        <p v-else class="text-sm text-gray-600">
                            Ningún participante coincide con la búsqueda.
                        </p>
                    </div>

                    <ul v-else class="mt-8 divide-y divide-gray-200" aria-label="Lista de participantes">
                        <li v-for="participante in filteredParticipantes" :key="participante.id" class="py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-sm font-medium text-gray-900">{{ participante.nombre }}</span>
                            <div class="flex gap-3">
                                <SecondaryButton type="button" @click="openEdit(participante)">
                                    Editar
                                </SecondaryButton>
                                <DangerButton type="button" @click="confirmDeleteParticipant(participante)">
                                    Quitar
                                </DangerButton>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <DialogModal :show="Boolean(editingParticipant)" @close="closeEdit">
            <template #title>
                Editar participante
            </template>
            <template #content>
                <InputLabel for="editar-nombre" value="Nombre" />
                <TextInput
                    id="editar-nombre"
                    v-model="editForm.nombre"
                    type="text"
                    class="mt-1 block w-full"
                    maxlength="100"
                />
                <InputError :message="editForm.errors.nombre || editClientError" class="mt-2" />
            </template>
            <template #footer>
                <SecondaryButton @click="closeEdit">Cancelar</SecondaryButton>
                <PrimaryButton
                    class="ms-3"
                    :disabled="Boolean(editClientError) || editForm.processing"
                    @click="saveEdit"
                >
                    <span v-if="editForm.processing">Guardando…</span>
                    <span v-else>Guardar</span>
                </PrimaryButton>
            </template>
        </DialogModal>

        <ConfirmationModal :show="confirmingParticipantDeletion" @close="confirmingParticipantDeletion = false">
            <template #title>
                Quitar participante
            </template>
            <template #content>
                ¿Quitar a {{ participantToDelete?.nombre }} de este viaje? Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingParticipantDeletion = false">Cancelar</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :disabled="deleteParticipantForm.processing"
                    @click="deleteParticipant"
                >
                    Quitar
                </DangerButton>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="confirmingTripDeletion" @close="confirmingTripDeletion = false">
            <template #title>
                Eliminar viaje
            </template>
            <template #content>
                Se eliminará el viaje y todos sus participantes. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingTripDeletion = false">Cancelar</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteTripForm.processing }"
                    :disabled="deleteTripForm.processing"
                    @click="deleteTrip"
                >
                    Eliminar viaje
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
