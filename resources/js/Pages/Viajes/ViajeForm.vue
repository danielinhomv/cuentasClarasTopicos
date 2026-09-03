<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    viaje: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => Boolean(props.viaje));

const form = useForm({
    nombre: props.viaje?.nombre ?? '',
    descripcion: props.viaje?.descripcion ?? '',
});

const nombreTouched = ref(false);

const clientErrors = computed(() => {
    const errors = {};
    const nombre = form.nombre?.trim() ?? '';

    if (nombreTouched.value || nombre.length > 0) {
        if (nombre.length === 0) {
            errors.nombre = 'El nombre del viaje es obligatorio.';
        } else if (nombre.length < 2) {
            errors.nombre = 'El nombre del viaje debe tener al menos 2 caracteres.';
        } else if (nombre.length > 150) {
            errors.nombre = 'El nombre del viaje no puede superar los 150 caracteres.';
        }
    }

    if ((form.descripcion ?? '').length > 1000) {
        errors.descripcion = 'La descripción no puede superar los 1000 caracteres.';
    }

    return errors;
});

const nombreError = computed(() => form.errors.nombre || clientErrors.value.nombre);
const descripcionError = computed(() => form.errors.descripcion || clientErrors.value.descripcion);
const canSubmit = computed(() => {
    const nombre = form.nombre?.trim() ?? '';

    return nombre.length >= 2
        && nombre.length <= 150
        && !clientErrors.value.descripcion
        && !form.processing;
});

watch(() => form.nombre, () => {
    if (form.errors.nombre) {
        form.clearErrors('nombre');
    }
});

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    if (isEdit.value) {
        form.put(route('viajes.update', props.viaje.id));
        return;
    }

    form.post(route('viajes.store'));
};
</script>

<template>
    <FormSection @submitted="submit">
        <template #title>
            {{ isEdit ? 'Datos del viaje' : 'Nuevo viaje' }}
        </template>

        <template #description>
            El nombre es obligatorio. La descripción es opcional y ayuda a recordar el contexto del viaje.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="nombre" value="Nombre" />
                <TextInput
                    id="nombre"
                    v-model="form.nombre"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    maxlength="150"
                    @blur="nombreTouched = true"
                    autocomplete="off"
                />
                <InputError :message="nombreError" class="mt-2" />
            </div>

            <div class="col-span-6">
                <InputLabel for="descripcion" value="Descripción (opcional)" />
                <textarea
                    id="descripcion"
                    v-model="form.descripcion"
                    rows="4"
                    maxlength="1000"
                    placeholder="Detalles sobre el destino, fechas o notas compartidas..."
                    class="mt-1 block w-full bg-zinc-900 border border-zinc-700 text-zinc-100 placeholder-zinc-500 rounded-lg shadow-inner focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-sm"
                />
                <InputError :message="descripcionError" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3">
                <Link :href="isEdit ? route('viajes.show', viaje.id) : route('viajes.index')">
                    <SecondaryButton type="button">
                        Cancelar
                    </SecondaryButton>
                </Link>
                <PrimaryButton :disabled="!canSubmit">
                    <span v-if="form.processing">Guardando…</span>
                    <span v-else>{{ isEdit ? 'Guardar cambios' : 'Crear viaje' }}</span>
                </PrimaryButton>
            </div>
        </template>
    </FormSection>
</template>
