<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
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
    saldos: {
        type: Array,
        default: () => [],
    },
    liquidacion: {
        type: Array,
        default: () => [],
    },
    bitacora: {
        type: Array,
        default: () => [],
    },
});

// Pestaña Activa
const activeTab = ref('gastos'); // por defecto mostrar gastos para acceso rápido

// Estado Participantes
const participantSearch = ref('');
const confirmingTripDeletion = ref(false);
const confirmingParticipantDeletion = ref(false);
const participantToDelete = ref(null);
const editingParticipant = ref(null);

const addParticipantForm = useForm({
    nombre: '',
});

const editParticipantForm = useForm({
    nombre: '',
});

const deleteTripForm = useForm({});
const deleteParticipantForm = useForm({});

// Estado Gastos
const modalGastoOpen = ref(false);
const editingGasto = ref(null);
const confirmingGastoDeletion = ref(false);
const gastoToDelete = ref(null);
const deleteGastoForm = useForm({});

const gastoForm = useForm({
    concepto: '',
    monto: '',
    moneda: 'BOB',
    fecha: new Date().toISOString().split('T')[0],
    pagador_id: '',
    excluidos: [],
});

const pagoForm = useForm({
    monto: '',
});
const payingDebt = ref(null);

// Estado Tipo de Cambio
const modalTipoCambioOpen = ref(false);
const tipoCambioForm = useForm({
    tipo_cambio_usd: props.viaje.tipo_cambio_usd ?? 6.9600,
    tipo_cambio_usdt: props.viaje.tipo_cambio_usdt ?? 10.5000,
});

const page = usePage();
const isCreator = computed(() => page.props.auth.user?.id === props.viaje.user_id);

const submitTipoCambio = () => {
    tipoCambioForm.put(route('viajes.tipo-cambio.update', props.viaje.id), {
        preserveScroll: true,
        onSuccess: () => {
            modalTipoCambioOpen.value = false;
        },
    });
};

const currentRate = computed(() => {
    if (gastoForm.moneda === 'USD') return Number(props.viaje.tipo_cambio_usd ?? 6.9600);
    if (gastoForm.moneda === 'USDT') return Number(props.viaje.tipo_cambio_usdt ?? 10.5000);
    return 1.0;
});

const estimatedConsolidado = computed(() => {
    if (gastoForm.moneda === 'BOB' || !gastoForm.monto) return null;
    const val = Number(gastoForm.monto) * currentRate.value;
    if (isNaN(val) || val <= 0) return null;
    return val.toFixed(2);
});

const currencySymbols = {
    BOB: 'Bs',
    USD: '$',
    USDT: '₮',
};

const formatGastoOriginal = (gasto) => {
    const symbol = currencySymbols[gasto.moneda] || 'Bs';
    return `${symbol} ${Number(gasto.monto).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const getConsolidadoBs = (gasto) => {
    if (!gasto.moneda || gasto.moneda === 'BOB') return null;
    const tasa = gasto.tipo_cambio || (gasto.moneda === 'USD' ? (props.viaje.tipo_cambio_usd || 6.9600) : (props.viaje.tipo_cambio_usdt || 10.5000));
    const conv = Math.round(Number(gasto.monto) * tasa * 100) / 100;
    return `≈ Bs. ${conv.toFixed(2)} (T/C ${Number(tasa).toFixed(2)})`;
};

// Getters de Participantes
const participantes = computed(() => props.viaje.participantes ?? []);
const gastos = computed(() => props.viaje.gastos ?? []);

const filteredParticipantes = computed(() => {
    const term = participantSearch.value.trim().toLowerCase();
    if (!term) return participantes.value;
    return participantes.value.filter((p) => p.nombre.toLowerCase().includes(term));
});

const isDuplicateParticipant = computed(() => {
    const name = addParticipantForm.nombre.trim().toLowerCase();
    if (!name) return false;
    return participantes.value.some((p) => p.nombre.toLowerCase() === name);
});

const addParticipantError = computed(() => {
    if (addParticipantForm.errors.nombre) return addParticipantForm.errors.nombre;
    const name = addParticipantForm.nombre.trim();
    if (!name) return '';
    if (name.length < 2) return 'El nombre debe tener al menos 2 caracteres.';
    if (isDuplicateParticipant.value) return 'Ya existe un participante con ese nombre.';
    return '';
});

// Métricas de Cabecera
const totalGastado = computed(() => {
    return gastos.value.reduce((acc, g) => {
        const tasa = g.moneda === 'USD' ? (g.tipo_cambio || props.viaje.tipo_cambio_usd || 6.9600)
                   : (g.moneda === 'USDT' ? (g.tipo_cambio || props.viaje.tipo_cambio_usdt || 10.5000)
                   : 1.0);
        return acc + Math.round(Number(g.monto) * tasa * 100) / 100;
    }, 0);
});

const balanceSum = computed(() => {
    return props.saldos.reduce((acc, s) => acc + Number(s.balance), 0);
});

const formatCurrency = (val) => {
    const num = Number(val || 0);
    return `Bs. ${num.toFixed(2)}`;
};

const formatDate = (val) => {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('es-BO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (val) => {
    if (!val) return '—';
    return new Date(val).toLocaleString('es-BO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const etiquetasBitacora = {
    concepto: 'Concepto',
    monto: 'Monto',
    moneda: 'Moneda',
    fecha: 'Fecha',
    pagador_nombre: 'Pagador',
    pagador_id: 'Pagador',
    incluidos: 'Incluidos',
    excluidos: 'Excluidos',
};

const camposOcultosBitacora = new Set(['pagador_id', 'tipo_cambio']);

const formatearValorBitacora = (clave, valor) => {
    if (valor === null || valor === undefined) return '—';
    if (clave === 'incluidos' || clave === 'excluidos') {
        if (!Array.isArray(valor) || valor.length === 0) return 'ninguno';
        return valor.map((p) => p.nombre).join(', ');
    }
    if (clave === 'monto') return formatCurrency(valor);
    return String(valor);
};

const clavesSnapshot = (datos) =>
    Object.keys(datos || {}).filter((clave) => !camposOcultosBitacora.has(clave));

const camposCambiados = (entrada) => {
    const keys = new Set([
        ...Object.keys(entrada.datos_antes || {}),
        ...Object.keys(entrada.datos_despues || {}),
    ]);
    camposOcultosBitacora.forEach((clave) => keys.delete(clave));
    return [...keys];
};

const openPagoForm = (deuda) => {
    payingDebt.value = deuda;
    pagoForm.monto = deuda.monto_pendiente;
    pagoForm.clearErrors();
};

const closePagoForm = () => {
    payingDebt.value = null;
    pagoForm.reset();
};

const submitPago = () => {
    if (!payingDebt.value) return;
    pagoForm.post(route('liquidaciones.pagos.store', payingDebt.value.id), {
        preserveScroll: true,
        onSuccess: () => closePagoForm(),
    });
};

const deudasPendientes = computed(() => (props.liquidacion ?? []).filter((d) => !d.liquidada && Number(d.monto_pendiente) > 0));

// Acciones Participantes
const submitParticipant = () => {
    if (addParticipantError.value || !addParticipantForm.nombre.trim()) return;
    addParticipantForm.post(route('viajes.participantes.store', props.viaje.id), {
        preserveScroll: true,
        onSuccess: () => addParticipantForm.reset('nombre'),
    });
};

const openEditParticipant = (p) => {
    editingParticipant.value = p;
    editParticipantForm.nombre = p.nombre;
    editParticipantForm.clearErrors();
};

const closeEditParticipant = () => {
    editingParticipant.value = null;
    editParticipantForm.reset();
};

const saveEditParticipant = () => {
    if (!editingParticipant.value || !editParticipantForm.nombre.trim()) return;
    editParticipantForm.put(route('participantes.update', editingParticipant.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditParticipant(),
    });
};

const confirmDeleteParticipant = (p) => {
    participantToDelete.value = p;
    confirmingParticipantDeletion.value = true;
};

const deleteParticipant = () => {
    if (!participantToDelete.value) return;
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

// Acciones Gastos
const openCreateGastoModal = () => {
    editingGasto.value = null;
    gastoForm.reset();
    gastoForm.moneda = 'BOB';
    gastoForm.fecha = new Date().toISOString().split('T')[0];
    if (participantes.value.length > 0) {
        gastoForm.pagador_id = participantes.value[0].id;
    }
    gastoForm.excluidos = [];
    gastoForm.clearErrors();
    modalGastoOpen.value = true;
};

const openEditGastoModal = (gasto) => {
    editingGasto.value = gasto;
    gastoForm.concepto = gasto.concepto;
    gastoForm.monto = String(gasto.monto);
    gastoForm.moneda = gasto.moneda || 'BOB';
    gastoForm.fecha = gasto.fecha ? gasto.fecha.split('T')[0] : '';
    gastoForm.pagador_id = gasto.pagador_id;
    gastoForm.excluidos = (gasto.excluidos || []).map(p => p.id);
    gastoForm.clearErrors();
    modalGastoOpen.value = true;
};

const closeGastoModal = () => {
    modalGastoOpen.value = false;
    editingGasto.value = null;
    gastoForm.reset();
};

const toggleExcluido = (id) => {
    const idx = gastoForm.excluidos.indexOf(id);
    if (idx > -1) {
        gastoForm.excluidos.splice(idx, 1);
    } else {
        // No permitir excluir a todos los participantes
        if (gastoForm.excluidos.length + 1 >= participantes.value.length) {
            return;
        }
        gastoForm.excluidos.push(id);
    }
};

const canSubmitGasto = computed(() => {
    const monto = Number(gastoForm.monto);
    return gastoForm.concepto.trim().length >= 2
        && monto > 0
        && Boolean(gastoForm.pagador_id)
        && Boolean(gastoForm.fecha)
        && gastoForm.excluidos.length < participantes.value.length
        && !gastoForm.processing;
});

const saveGasto = () => {
    if (!canSubmitGasto.value) return;

    if (editingGasto.value) {
        gastoForm.put(route('gastos.update', editingGasto.value.id), {
            preserveScroll: true,
            onSuccess: () => closeGastoModal(),
        });
    } else {
        gastoForm.post(route('viajes.gastos.store', props.viaje.id), {
            preserveScroll: true,
            onSuccess: () => closeGastoModal(),
        });
    }
};

const confirmDeleteGasto = (gasto) => {
    gastoToDelete.value = gasto;
    confirmingGastoDeletion.value = true;
};

const deleteGasto = () => {
    if (!gastoToDelete.value) return;
    deleteGastoForm.delete(route('gastos.destroy', gastoToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingGastoDeletion.value = false;
            gastoToDelete.value = null;
        },
    });
};

const copied = ref(false);
const copyCode = () => {
    if (!props.viaje.codigo_invitacion) return;
    navigator.clipboard.writeText(props.viaje.codigo_invitacion);
    copied.value = true;
    setTimeout(() => copied.value = false, 2500);
};
</script>

<template>
    <AppLayout :title="viaje.nombre">
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <Link :href="route('viajes.index')" class="text-zinc-400 hover:text-cyan-400 transition">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                        </Link>
                        <h2 class="font-extrabold text-2xl text-zinc-100 tracking-tight">
                            {{ viaje.nombre }}
                        </h2>
                        <button
                            v-if="viaje.codigo_invitacion"
                            @click="copyCode"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-800/90 hover:bg-zinc-800 border border-cyan-500/30 text-xs font-mono font-bold text-cyan-300 transition cursor-pointer"
                            title="Copiar código de invitación"
                        >
                            <span>{{ viaje.codigo_invitacion }}</span>
                            <span v-if="copied" class="text-emerald-400 text-[10px] font-sans font-semibold">¡Copiado!</span>
                            <svg v-else class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="viaje.descripcion" class="mt-1 text-xs text-zinc-400 line-clamp-1">
                        {{ viaje.descripcion }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    <a
                        :href="route('viajes.exportar-pdf', viaje.id)"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-zinc-800/90 hover:bg-zinc-700/90 border border-zinc-700 hover:border-zinc-500 rounded-lg font-semibold text-xs text-zinc-200 uppercase tracking-widest shadow-sm hover:text-white focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 focus:ring-offset-zinc-900 transition-all duration-150 cursor-pointer"
                    >
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Exportar a PDF</span>
                    </a>

                    <Link :href="route('viajes.edit', viaje.id)">
                        <SecondaryButton type="button" class="gap-1.5 text-xs py-2">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                            </svg>
                            <span>Editar</span>
                        </SecondaryButton>
                    </Link>

                    <DangerButton @click="confirmingTripDeletion = true" class="gap-1.5 text-xs py-2">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        <span>Eliminar</span>
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Métricas Clave en Dark Neon -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-5 flex items-center gap-4 shadow-xl">
                        <div class="w-12 h-12 rounded-xl bg-cyan-950/60 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shadow-md shadow-cyan-950/50">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider font-semibold text-zinc-400">Participantes</p>
                            <p class="text-2xl font-black text-zinc-100 mt-0.5">{{ participantes.length }}</p>
                        </div>
                    </div>

                    <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-5 flex items-center gap-4 shadow-xl">
                        <div class="w-12 h-12 rounded-xl bg-emerald-950/60 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-md shadow-emerald-950/50">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider font-semibold text-zinc-400">Total Consolidado</p>
                            <p class="text-2xl font-black text-emerald-400 mt-0.5">{{ formatCurrency(totalGastado) }}</p>
                        </div>
                    </div>

                    <!-- Tipo de Cambio Card -->
                    <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-5 flex items-center justify-between gap-4 shadow-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-cyan-950/60 border border-cyan-500/30 flex items-center justify-center text-cyan-400 shadow-md shadow-cyan-950/50">
                                <span class="text-xl font-black">💱</span>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wider font-semibold text-zinc-400">Tipo de Cambio</p>
                                <div class="text-xs font-mono text-zinc-200 mt-1 space-y-0.5">
                                    <p><span class="text-cyan-400 font-bold">1 USD</span> = {{ Number(viaje.tipo_cambio_usd || 6.96).toFixed(2) }} Bs</p>
                                    <p><span class="text-purple-400 font-bold">1 USDT</span> = {{ Number(viaje.tipo_cambio_usdt || 10.50).toFixed(2) }} Bs</p>
                                </div>
                            </div>
                        </div>
                        <button
                            v-if="isCreator"
                            @click="modalTipoCambioOpen = true"
                            class="p-2 rounded-xl bg-zinc-800/80 hover:bg-zinc-800 text-cyan-400 hover:text-cyan-300 border border-zinc-700 transition text-xs font-bold flex flex-col items-center gap-1 cursor-pointer"
                            title="Ajustar cotizaciones"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                            </svg>
                            <span class="text-[10px]">Ajustar</span>
                        </button>
                    </div>

                    <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-5 flex items-center gap-4 shadow-xl">
                        <div class="w-12 h-12 rounded-xl bg-violet-950/60 border border-violet-500/30 flex items-center justify-center text-violet-400 shadow-md shadow-violet-950/50">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider font-semibold text-zinc-400">Transferencias</p>
                            <p class="text-2xl font-black text-violet-400 mt-0.5">{{ liquidacion.length }} pendientes</p>
                        </div>
                    </div>
                </div>

                <!-- Selector de Pestañas (Tabs) en Dark Neon -->
                <div class="flex border-b border-zinc-800/80 gap-2 overflow-x-auto pb-1">
                    <button
                        @click="activeTab = 'gastos'"
                        :class="[
                            'px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'gastos'
                                ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/40 shadow-lg shadow-cyan-950/40'
                                : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        ]"
                    >
                        <span>💸 Gastos</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-300">{{ gastos.length }}</span>
                    </button>

                    <button
                        @click="activeTab = 'saldos'"
                        :class="[
                            'px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'saldos'
                                ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40 shadow-lg shadow-emerald-950/40'
                                : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        ]"
                    >
                        <span>📊 Saldos</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-300">{{ saldos.length }}</span>
                    </button>

                    <button
                        @click="activeTab = 'liquidacion'"
                        :class="[
                            'px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'liquidacion'
                                ? 'bg-violet-500/15 text-violet-300 border border-violet-500/40 shadow-lg shadow-violet-950/40'
                                : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        ]"
                    >
                        <span>⚡ Liquidación</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-300">{{ liquidacion.length }}</span>
                    </button>

                    <button
                        v-if="isCreator"
                        @click="activeTab = 'bitacora'"
                        :class="[
                            'px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'bitacora'
                                ? 'bg-amber-500/15 text-amber-300 border border-amber-500/40 shadow-lg shadow-amber-950/40'
                                : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        ]"
                    >
                        <span>📋 Bitácora</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-300">{{ (bitacora || []).length }}</span>
                    </button>

                    <button
                        @click="activeTab = 'participantes'"
                        :class="[
                            'px-5 py-2.5 rounded-xl font-bold text-sm transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer',
                            activeTab === 'participantes'
                                ? 'bg-zinc-800 text-zinc-100 border border-zinc-700 shadow-md'
                                : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900'
                        ]"
                    >
                        <span>👥 Participantes</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-300">{{ participantes.length }}</span>
                    </button>
                </div>

                <!-- ============================================================== -->
                <!-- TAB: GASTOS -->
                <!-- ============================================================== -->
                <div v-show="activeTab === 'gastos'" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-zinc-900/80 border border-zinc-800 rounded-2xl p-5 shadow-xl">
                        <div>
                            <h3 class="text-lg font-bold text-zinc-100">Registro de Gastos</h3>
                            <p class="text-xs text-zinc-400 mt-0.5">Ingresa gastos realizados por cualquier integrante y define exclusiones si aplica.</p>
                        </div>
                        <PrimaryButton
                            @click="openCreateGastoModal"
                            :disabled="participantes.length === 0"
                            type="button"
                            class="gap-2"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Registrar Gasto</span>
                        </PrimaryButton>
                    </div>

                    <div v-if="participantes.length === 0" class="bg-amber-950/40 border border-amber-500/30 rounded-2xl p-6 text-center">
                        <p class="text-sm font-semibold text-amber-300">
                            Primero necesitas agregar participantes para poder registrar gastos en este viaje.
                        </p>
                        <button
                            @click="activeTab = 'participantes'"
                            class="mt-3 inline-flex items-center text-xs font-bold text-amber-400 underline hover:text-amber-200 cursor-pointer"
                        >
                            Ir a la pestaña de Participantes &rarr;
                        </button>
                    </div>

                    <div v-else-if="gastos.length === 0" class="bg-zinc-900/60 border border-dashed border-zinc-800 rounded-2xl p-12 text-center">
                        <div class="mx-auto w-14 h-14 rounded-2xl bg-cyan-950/60 border border-cyan-500/30 flex items-center justify-center text-cyan-400 mb-4 shadow-lg">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-base font-bold text-zinc-100">No hay gastos registrados</h4>
                        <p class="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
                            Registra el primer gasto (alojamiento, comida, transporte) para calcular los saldos automáticos.
                        </p>
                        <PrimaryButton @click="openCreateGastoModal" type="button" class="mt-5">
                            + Registrar el primer gasto
                        </PrimaryButton>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="gasto in gastos"
                            :key="gasto.id"
                            class="bg-zinc-900/90 border border-zinc-800 hover:border-cyan-500/30 rounded-2xl p-5 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 transition-all group"
                        >
                            <div class="space-y-1.5 flex-1">
                                <div class="flex items-center gap-3">
                                    <h4 class="font-bold text-base text-zinc-100 group-hover:text-cyan-300 transition-colors">
                                        {{ gasto.concepto }}
                                    </h4>
                                    <span class="text-xs text-zinc-500">
                                        {{ formatDate(gasto.fecha) }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="text-zinc-400">Pagó:</span>
                                    <span class="font-bold text-zinc-200 px-2 py-0.5 rounded-lg bg-zinc-800 border border-zinc-700">
                                        {{ gasto.pagador?.nombre ?? 'Desconocido' }}
                                    </span>

                                    <!-- Badge de Exclusiones -->
                                    <span
                                        v-if="gasto.excluidos && gasto.excluidos.length > 0"
                                        class="px-2 py-0.5 rounded-lg bg-amber-950/60 border border-amber-500/30 text-amber-300 font-medium text-xs flex items-center gap-1"
                                    >
                                        <svg class="size-3 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Excluye a: {{ gasto.excluidos.map(e => e.nombre).join(', ') }}
                                    </span>
                                    <span v-else class="text-zinc-500 text-xs">
                                        (Participan todos)
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-5 pt-3 sm:pt-0 border-t sm:border-0 border-zinc-800/80">
                                <div class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span
                                            v-if="gasto.moneda && gasto.moneda !== 'BOB'"
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded border uppercase tracking-wider"
                                            :class="gasto.moneda === 'USD' ? 'bg-cyan-950/80 text-cyan-300 border-cyan-500/40' : 'bg-purple-950/80 text-purple-300 border-purple-500/40'"
                                        >
                                            {{ gasto.moneda }}
                                        </span>
                                        <p class="text-xl font-black text-cyan-400 tracking-tight font-mono">
                                            {{ formatGastoOriginal(gasto) }}
                                        </p>
                                    </div>
                                    <p v-if="gasto.moneda && gasto.moneda !== 'BOB'" class="text-[11px] text-zinc-400 font-mono mt-0.5">
                                        {{ getConsolidadoBs(gasto) }}
                                    </p>
                                    <p v-if="gasto.tiene_ajuste_efectivo" class="text-[11px] text-amber-300/90 mt-1">
                                        Original {{ formatGastoOriginal(gasto) }} · ajuste a efectivo Bs 0,50
                                    </p>
                                    <ul v-if="gasto.tiene_ajuste_efectivo && gasto.cuotas_efectivo?.length" class="mt-1 space-y-0.5">
                                        <li
                                            v-for="cuota in gasto.cuotas_efectivo"
                                            :key="cuota.id"
                                            class="text-[11px] text-zinc-400 font-mono"
                                        >
                                            {{ cuota.nombre }}: {{ formatCurrency(cuota.cuota_final) }}
                                            <span v-if="cuota.ajuste" class="text-amber-400/80">
                                                ({{ cuota.ajuste > 0 ? '+' : '' }}{{ formatCurrency(cuota.ajuste) }})
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="openEditGastoModal(gasto)"
                                        class="p-2 rounded-lg text-zinc-400 hover:text-cyan-300 hover:bg-zinc-800 transition cursor-pointer"
                                        title="Editar gasto"
                                    >
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>

                                    <button
                                        @click="confirmDeleteGasto(gasto)"
                                        class="p-2 rounded-lg text-zinc-400 hover:text-rose-400 hover:bg-zinc-800 transition cursor-pointer"
                                        title="Eliminar gasto"
                                    >
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB: SALDOS -->
                <!-- ============================================================== -->
                <div v-show="activeTab === 'saldos'" class="space-y-6">
                    <div class="bg-zinc-900/80 border border-zinc-800 rounded-2xl p-6 shadow-xl">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-5">
                            <div>
                                <h3 class="text-lg font-bold text-zinc-100">Balances Individuales</h3>
                                <p class="text-xs text-zinc-400 mt-0.5">
                                    Desglose de lo que pagó cada participante vs lo que consumió (valores consolidados en Bolivianos).
                                </p>
                            </div>

                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-400 text-xs font-semibold">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Invariante: &Sigma; balances = {{ formatCurrency(balanceSum) }}</span>
                            </div>
                        </div>

                        <div v-if="saldos.length === 0" class="py-10 text-center text-zinc-500 text-sm">
                            No hay información de saldos disponible todavía.
                        </div>

                        <div v-else class="mt-5 overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-zinc-800 text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                                        <th class="py-3 px-4">Participante</th>
                                        <th class="py-3 px-4 text-right">Total Pagado</th>
                                        <th class="py-3 px-4 text-right">Total Consumido</th>
                                        <th class="py-3 px-4 text-right">Balance Neto</th>
                                        <th class="py-3 px-4 text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800/60 text-sm">
                                    <tr
                                        v-for="saldo in saldos"
                                        :key="saldo.participante_id"
                                        class="hover:bg-zinc-800/40 transition"
                                    >
                                        <td class="py-4 px-4 font-bold text-zinc-100">
                                            {{ saldo.nombre }}
                                        </td>
                                        <td class="py-4 px-4 text-right text-zinc-300 font-mono">
                                            {{ formatCurrency(saldo.total_pagado) }}
                                        </td>
                                        <td class="py-4 px-4 text-right text-zinc-400 font-mono">
                                            {{ formatCurrency(saldo.total_consumido) }}
                                        </td>
                                        <td class="py-4 px-4 text-right font-black font-mono text-base">
                                            <span
                                                :class="[
                                                    saldo.balance > 0 ? 'text-emerald-400' : (saldo.balance < 0 ? 'text-rose-400' : 'text-zinc-400')
                                                ]"
                                            >
                                                {{ saldo.balance > 0 ? '+' : '' }}{{ formatCurrency(saldo.balance) }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <span
                                                v-if="saldo.balance > 0"
                                                class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 shadow-sm"
                                            >
                                                Le deben
                                            </span>
                                            <span
                                                v-else-if="saldo.balance < 0"
                                                class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-950/80 text-rose-300 border border-rose-500/40 shadow-sm"
                                            >
                                                Debe
                                            </span>
                                            <span
                                                v-else
                                                class="px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-800 text-zinc-400 border border-zinc-700"
                                            >
                                                Al día
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB: LIQUIDACIÓN -->
                <!-- ============================================================== -->
                <div v-show="activeTab === 'liquidacion'" class="space-y-6">
                    <div class="bg-zinc-900/80 border border-zinc-800 rounded-2xl p-6 shadow-xl space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-800 pb-5">
                            <div>
                                <h3 class="text-lg font-bold text-zinc-100 flex items-center gap-2">
                                    <span>Plan Óptimo de Liquidación</span>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-violet-950/80 text-violet-300 border border-violet-500/40">
                                        Mínimo de transferencias
                                    </span>
                                </h3>
                                <p class="text-xs text-zinc-400 mt-0.5">
                                    Transferencias calculadas para saldar todas las deudas con la menor cantidad de pasos.
                                </p>
                            </div>
                        </div>

                        <div v-if="liquidacion.length === 0" class="py-12 text-center">
                            <div class="mx-auto w-14 h-14 rounded-2xl bg-emerald-950/60 border border-emerald-500/40 flex items-center justify-center text-emerald-400 mb-4 shadow-lg shadow-emerald-950/50">
                                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <h4 class="text-base font-bold text-zinc-100">¡Cuentas saldadas!</h4>
                            <p class="text-xs text-zinc-400 mt-1 max-w-sm mx-auto">
                                No se requieren pagos ni transferencias en este momento. Todos los balances están al día.
                            </p>
                        </div>

                        <div v-else class="space-y-6">
                            <div v-if="deudasPendientes.length === 0" class="py-6 text-center rounded-2xl bg-emerald-950/30 border border-emerald-500/30">
                                <h4 class="text-sm font-bold text-emerald-300">Todas las deudas están liquidadas</h4>
                                <p class="text-xs text-zinc-400 mt-1">Los pagos registrados cubren el total de cada transferencia.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    v-for="trans in liquidacion"
                                    :key="trans.id || `${trans.deudor_id}-${trans.acreedor_id}`"
                                    class="bg-zinc-950/80 border rounded-2xl p-5 shadow-xl transition-all flex flex-col justify-between"
                                    :class="trans.liquidada ? 'border-emerald-500/30' : 'border-zinc-800 hover:border-violet-500/50'"
                                >
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <span class="text-[10px] uppercase font-bold text-rose-400 tracking-wider">Deudor (Paga)</span>
                                            <p class="text-base font-black text-zinc-100 mt-0.5 truncate">{{ trans.deudor_nombre }}</p>
                                        </div>
                                        <div class="flex flex-col items-center px-2">
                                            <div class="w-10 h-10 rounded-xl bg-violet-950/70 border border-violet-500/40 flex items-center justify-center text-violet-300 shadow-md">
                                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 text-right">
                                            <span class="text-[10px] uppercase font-bold text-emerald-400 tracking-wider">Acreedor (Recibe)</span>
                                            <p class="text-base font-black text-zinc-100 mt-0.5 truncate">{{ trans.acreedor_nombre }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-t border-zinc-800/80 space-y-2 text-xs">
                                        <div class="flex items-center justify-between text-zinc-400">
                                            <span>Original</span>
                                            <span class="font-mono text-zinc-200">{{ formatCurrency(trans.monto_original ?? trans.monto) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-zinc-400">
                                            <span>Pagado</span>
                                            <span class="font-mono text-cyan-300">{{ formatCurrency(trans.monto_pagado ?? 0) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-zinc-400">Pendiente</span>
                                            <span class="text-lg font-black text-cyan-300 font-mono">
                                                {{ formatCurrency(trans.monto_pendiente ?? trans.monto) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-2">
                                        <span
                                            v-if="trans.liquidada"
                                            class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40"
                                        >
                                            Liquidada
                                        </span>
                                        <PrimaryButton
                                            v-else
                                            type="button"
                                            class="w-full justify-center"
                                            @click="openPagoForm(trans)"
                                        >
                                            Registrar pago
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB: BITÁCORA (solo anfitrión) -->
                <!-- ============================================================== -->
                <div v-if="isCreator" v-show="activeTab === 'bitacora'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-white font-bold text-lg">Historial de cambios</h3>
                    </div>
                    <p class="text-zinc-500 text-sm">Registro de creación, edición y eliminación de gastos. Solo lectura.</p>

                    <div v-if="!(bitacora || []).length" class="bg-zinc-900/60 border border-dashed border-zinc-800 rounded-2xl p-10 text-center">
                        <p class="text-zinc-500 text-sm">Aún no hay movimientos en la bitácora de este viaje.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="entrada in bitacora"
                            :key="entrada.id"
                            class="bg-zinc-900/80 border border-zinc-800 rounded-2xl p-4 space-y-3"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-white font-semibold text-sm">
                                        {{ entrada.actor_nombre || entrada.user?.name || 'Usuario desconocido' }}
                                        <span class="text-zinc-400 font-normal">
                                            {{ entrada.accion === 'crear' ? 'creó' : entrada.accion === 'editar' ? 'editó' : 'eliminó' }}
                                            el gasto
                                            <span class="text-zinc-200">{{ entrada.gasto_concepto ?? 'sin concepto' }}</span>
                                        </span>
                                    </p>
                                    <p class="text-zinc-500 text-xs mt-1">{{ formatDateTime(entrada.created_at) }}</p>
                                </div>
                                <span
                                    class="text-xs font-bold px-2 py-1 rounded-lg uppercase"
                                    :class="{
                                        'bg-emerald-500/15 text-emerald-400': entrada.accion === 'crear',
                                        'bg-amber-500/15 text-amber-400': entrada.accion === 'editar',
                                        'bg-red-500/15 text-red-400': entrada.accion === 'eliminar',
                                    }"
                                >
                                    {{ entrada.accion }}
                                </span>
                            </div>

                            <div v-if="entrada.accion === 'crear'" class="text-sm text-zinc-400 space-y-1">
                                <p v-for="clave in clavesSnapshot(entrada.datos_despues)" :key="clave">
                                    <span class="text-zinc-500">{{ etiquetasBitacora[clave] || clave }}:</span>
                                    {{ formatearValorBitacora(clave, entrada.datos_despues[clave]) }}
                                </p>
                            </div>

                            <div v-else-if="entrada.accion === 'eliminar'" class="text-sm text-zinc-400 space-y-1">
                                <p v-for="clave in clavesSnapshot(entrada.datos_antes)" :key="clave">
                                    <span class="text-zinc-500">{{ etiquetasBitacora[clave] || clave }}:</span>
                                    {{ formatearValorBitacora(clave, entrada.datos_antes[clave]) }}
                                </p>
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-zinc-500 text-xs uppercase tracking-wide">
                                            <th class="text-left py-1 pr-3">Campo</th>
                                            <th class="text-left py-1 pr-3">Antes</th>
                                            <th class="text-left py-1">Después</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="clave in camposCambiados(entrada)"
                                            :key="clave"
                                            class="border-t border-zinc-800 text-zinc-300"
                                        >
                                            <td class="py-2 pr-3 text-zinc-500">{{ etiquetasBitacora[clave] || clave }}</td>
                                            <td class="py-2 pr-3">{{ formatearValorBitacora(clave, entrada.datos_antes?.[clave]) }}</td>
                                            <td class="py-2">{{ formatearValorBitacora(clave, entrada.datos_despues?.[clave]) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB: PARTICIPANTES -->
                <!-- ============================================================== -->
                <div v-show="activeTab === 'participantes'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Panel de Invitación con Código -->
                        <div class="bg-zinc-900/90 border border-zinc-800 rounded-2xl p-6 shadow-xl space-y-4">
                            <div class="flex items-center gap-2 text-cyan-400">
                                <span class="text-lg">🔑</span>
                                <h3 class="font-bold text-base text-zinc-100">Invitar Amigos</h3>
                            </div>
                            <p class="text-xs text-zinc-400 leading-relaxed">
                                Si tus amigos tienen cuenta, comparte este código para que se unan desde "Unirme con código". También puedes agregar participantes manualmente por nombre (sin necesidad de cuenta).
                            </p>

                            <div class="p-4 rounded-xl bg-zinc-950 border border-cyan-500/30 text-center space-y-1.5 shadow-inner">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Código de Invitación</span>
                                <div class="font-mono text-2xl font-black text-cyan-300 tracking-widest selection:bg-cyan-400 selection:text-zinc-950">
                                    {{ viaje.codigo_invitacion || '—' }}
                                </div>
                            </div>

                            <PrimaryButton @click="copyCode" type="button" class="w-full justify-center gap-2">
                                <svg v-if="!copied" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                </svg>
                                <span>{{ copied ? '¡Código Copiado!' : 'Copiar Código de Invitación' }}</span>
                            </PrimaryButton>
                        </div>

                        <!-- Lista de Participantes -->
                        <div class="md:col-span-2 bg-zinc-900/90 border border-zinc-800 rounded-2xl p-6 shadow-xl space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-zinc-800 pb-4">
                                <div>
                                    <h3 class="font-bold text-base text-zinc-100">
                                        Integrantes ({{ participantes.length }})
                                    </h3>
                                    <p class="text-xs text-zinc-400">Lista completa de participantes en este viaje.</p>
                                </div>

                                <TextInput
                                    v-model="participantSearch"
                                    type="search"
                                    class="text-xs py-1.5 px-3 w-full sm:w-48"
                                    placeholder="Buscar por nombre..."
                                    autocomplete="off"
                                />
                            </div>

                            <div v-if="filteredParticipantes.length === 0" class="py-8 text-center text-zinc-500 text-sm">
                                No se encontraron integrantes.
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div
                                    v-for="p in filteredParticipantes"
                                    :key="p.id"
                                    class="bg-zinc-950/80 border border-zinc-800 hover:border-zinc-700 rounded-xl p-3.5 flex items-center justify-between transition"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center font-bold text-xs text-cyan-300">
                                            {{ p.nombre.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="font-semibold text-sm text-zinc-200">{{ p.nombre }}</span>
                                        <span v-if="p.user_id === viaje.user_id" class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-950/80 text-cyan-300 border border-cyan-500/30 font-semibold">
                                            Creador
                                        </span>
                                        <span v-else-if="!p.user_id" class="text-[10px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700 font-semibold">
                                            Sin cuenta
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="openEditParticipant(p)"
                                            class="p-1.5 rounded-lg text-zinc-400 hover:text-cyan-300 hover:bg-zinc-800 transition cursor-pointer"
                                            title="Editar"
                                        >
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>

                                        <button
                                            @click="confirmDeleteParticipant(p)"
                                            class="p-1.5 rounded-lg text-zinc-400 hover:text-rose-400 hover:bg-zinc-800 transition cursor-pointer"
                                            title="Eliminar"
                                        >
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Alta manual por nombre (solo propietario) -->
                            <div v-if="isCreator" class="border-t border-zinc-800 pt-4 mt-2 space-y-3">
                                <div>
                                    <h4 class="text-sm font-bold text-zinc-100">Agregar participante</h4>
                                    <p class="text-xs text-zinc-400 mt-0.5">Ingresa el nombre de quien participará en el viaje. No necesita tener cuenta.</p>
                                </div>
                                <form @submit.prevent="submitParticipant" class="flex flex-col sm:flex-row gap-2">
                                    <div class="flex-1">
                                        <TextInput
                                            v-model="addParticipantForm.nombre"
                                            type="text"
                                            class="w-full text-sm"
                                            placeholder="Ej. Diego, Carla..."
                                            autocomplete="off"
                                            :disabled="addParticipantForm.processing"
                                        />
                                        <p v-if="addParticipantError" class="text-[11px] text-amber-400 mt-1">{{ addParticipantError }}</p>
                                    </div>
                                    <PrimaryButton
                                        type="submit"
                                        :disabled="!!addParticipantError || !addParticipantForm.nombre.trim() || addParticipantForm.processing"
                                        class="shrink-0 justify-center"
                                    >
                                        Agregar
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL: REGISTRAR / EDITAR GASTO -->
        <!-- ============================================================== -->
        <DialogModal :show="modalGastoOpen" @close="closeGastoModal" max-width="lg">
            <template #title>
                <div class="flex items-center gap-2">
                    <span class="text-cyan-400">💸</span>
                    <span>{{ editingGasto ? 'Editar Gasto' : 'Registrar Nuevo Gasto' }}</span>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="saveGasto" class="space-y-4">
                    <!-- Concepto -->
                    <div>
                        <InputLabel for="gasto-concepto" value="Concepto del gasto" />
                        <TextInput
                            id="gasto-concepto"
                            v-model="gastoForm.concepto"
                            type="text"
                            class="w-full"
                            placeholder="Ej. Cabaña Samaipata, Cena, Gasolina..."
                            required
                        />
                        <InputError :message="gastoForm.errors.concepto" class="mt-1" />
                    </div>

                    <!-- Selector de Divisa -->
                    <div>
                        <InputLabel value="Moneda del gasto" class="mb-1.5" />
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="gastoForm.moneda = 'BOB'"
                                :class="[
                                    'py-2 px-3 rounded-xl font-bold text-xs border transition-all flex flex-col items-center gap-0.5 cursor-pointer',
                                    gastoForm.moneda === 'BOB'
                                        ? 'bg-emerald-950/80 text-emerald-300 border-emerald-500 shadow-md shadow-emerald-950/50'
                                        : 'bg-zinc-800/60 text-zinc-400 border-zinc-700/60 hover:bg-zinc-800 hover:text-zinc-200'
                                ]"
                            >
                                <span class="text-sm font-black">🇧🇴 BOB</span>
                                <span class="text-[10px] opacity-70">Bolivianos</span>
                            </button>

                            <button
                                type="button"
                                @click="gastoForm.moneda = 'USD'"
                                :class="[
                                    'py-2 px-3 rounded-xl font-bold text-xs border transition-all flex flex-col items-center gap-0.5 cursor-pointer',
                                    gastoForm.moneda === 'USD'
                                        ? 'bg-cyan-950/80 text-cyan-300 border-cyan-500 shadow-md shadow-cyan-950/50'
                                        : 'bg-zinc-800/60 text-zinc-400 border-zinc-700/60 hover:bg-zinc-800 hover:text-zinc-200'
                                ]"
                            >
                                <span class="text-sm font-black">💵 USD</span>
                                <span class="text-[10px] opacity-70">T/C: {{ Number(viaje.tipo_cambio_usd || 6.96).toFixed(2) }}</span>
                            </button>

                            <button
                                type="button"
                                @click="gastoForm.moneda = 'USDT'"
                                :class="[
                                    'py-2 px-3 rounded-xl font-bold text-xs border transition-all flex flex-col items-center gap-0.5 cursor-pointer',
                                    gastoForm.moneda === 'USDT'
                                        ? 'bg-purple-950/80 text-purple-300 border-purple-500 shadow-md shadow-purple-950/50'
                                        : 'bg-zinc-800/60 text-zinc-400 border-zinc-700/60 hover:bg-zinc-800 hover:text-zinc-200'
                                ]"
                            >
                                <span class="text-sm font-black">₮ USDT</span>
                                <span class="text-[10px] opacity-70">T/C: {{ Number(viaje.tipo_cambio_usdt || 10.50).toFixed(2) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Monto y Fecha en 2 Columnas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="gasto-monto" :value="`Monto (${gastoForm.moneda})`" />
                                <span v-if="estimatedConsolidado" class="text-xs text-emerald-400 font-mono font-semibold">
                                    ≈ Bs. {{ estimatedConsolidado }}
                                </span>
                            </div>
                            <TextInput
                                id="gasto-monto"
                                v-model="gastoForm.monto"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full font-mono text-cyan-300 font-bold"
                                placeholder="0.00"
                                required
                            />
                            <InputError :message="gastoForm.errors.monto" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="gasto-fecha" value="Fecha" />
                            <TextInput
                                id="gasto-fecha"
                                v-model="gastoForm.fecha"
                                type="date"
                                class="w-full"
                                required
                            />
                            <InputError :message="gastoForm.errors.fecha" class="mt-1" />
                        </div>
                    </div>

                    <!-- Pagador -->
                    <div>
                        <InputLabel for="gasto-pagador" value="¿Quién pagó?" />
                        <select
                            id="gasto-pagador"
                            v-model="gastoForm.pagador_id"
                            class="w-full bg-zinc-900 border border-zinc-700 text-zinc-100 rounded-lg shadow-inner focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 text-sm"
                            required
                        >
                            <option value="" disabled>Selecciona al pagador</option>
                            <option
                                v-for="p in participantes"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nombre }}
                            </option>
                        </select>
                        <InputError :message="gastoForm.errors.pagador_id" class="mt-1" />
                    </div>

                    <!-- Exclusiones de Participantes -->
                    <div class="pt-2 border-t border-zinc-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                ¿Alguien NO participó de este gasto?
                            </span>
                            <span class="text-xs text-amber-400">
                                (Excluidos: {{ gastoForm.excluidos.length }})
                            </span>
                        </div>

                        <p class="text-xs text-zinc-500 mb-3 leading-relaxed">
                            Marca a los amigos que no deben pagar nada por este consumo (ej. quien no fue a la cena o no subió al auto).
                        </p>

                        <div class="grid grid-cols-2 gap-2 max-h-36 overflow-y-auto p-1 bg-zinc-950/50 rounded-xl border border-zinc-800">
                            <label
                                v-for="p in participantes"
                                :key="p.id"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-lg border text-xs cursor-pointer transition select-none',
                                    gastoForm.excluidos.includes(p.id)
                                        ? 'bg-amber-950/60 border-amber-500/50 text-amber-300 font-bold'
                                        : 'bg-zinc-900/60 border-zinc-800 text-zinc-300 hover:border-zinc-700'
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    :checked="gastoForm.excluidos.includes(p.id)"
                                    @change="toggleExcluido(p.id)"
                                    class="rounded bg-zinc-800 border-zinc-700 text-amber-400 focus:ring-amber-400/20"
                                />
                                <span class="truncate">{{ p.nombre }}</span>
                            </label>
                        </div>

                        <p v-if="gastoForm.excluidos.length >= participantes.length - 1" class="text-[11px] text-amber-400 mt-2">
                            &bull; Al menos un participante debe asumir el gasto.
                        </p>
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="closeGastoModal" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    @click="saveGasto"
                    :disabled="!canSubmitGasto"
                    type="button"
                >
                    <span v-if="gastoForm.processing">Guardando...</span>
                    <span v-else>{{ editingGasto ? 'Guardar Cambios' : 'Registrar Gasto' }}</span>
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- ============================================================== -->
        <!-- MODAL: EDITAR PARTICIPANTE -->
        <!-- ============================================================== -->
        <DialogModal :show="Boolean(editingParticipant)" @close="closeEditParticipant" max-width="sm">
            <template #title>Editar Integrante</template>
            <template #content>
                <form @submit.prevent="saveEditParticipant" class="space-y-4">
                    <div>
                        <InputLabel for="edit-nombre" value="Nombre" />
                        <TextInput
                            id="edit-nombre"
                            v-model="editParticipantForm.nombre"
                            type="text"
                            class="w-full"
                            required
                            autofocus
                        />
                        <InputError :message="editParticipantForm.errors.nombre" class="mt-1" />
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="closeEditParticipant" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    @click="saveEditParticipant"
                    :disabled="!editParticipantForm.nombre.trim() || editParticipantForm.processing"
                    type="button"
                >
                    Guardar
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- ============================================================== -->
        <!-- MODAL: ELIMINAR GASTO -->
        <!-- ============================================================== -->
        <ConfirmationModal :show="confirmingGastoDeletion" @close="confirmingGastoDeletion = false" max-width="md">
            <template #title>¿Eliminar este gasto?</template>
            <template #content>
                ¿Estás seguro de que deseas eliminar el gasto
                <span class="font-bold text-zinc-100">"{{ gastoToDelete?.concepto }}"</span> por
                <span class="font-bold text-cyan-400">{{ formatCurrency(gastoToDelete?.monto) }}</span>?
                Los saldos y liquidación se recalcularán automáticamente.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingGastoDeletion = false" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <DangerButton @click="deleteGasto" :disabled="deleteGastoForm.processing">
                    Eliminar Gasto
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- ============================================================== -->
        <!-- MODAL: ELIMINAR PARTICIPANTE -->
        <!-- ============================================================== -->
        <ConfirmationModal :show="confirmingParticipantDeletion" @close="confirmingParticipantDeletion = false" max-width="md">
            <template #title>¿Eliminar integrante?</template>
            <template #content>
                ¿Estás seguro de que deseas eliminar a
                <span class="font-bold text-zinc-100">"{{ participantToDelete?.nombre }}"</span> del viaje?
                Si tiene gastos pagados o asociados, no podrá ser eliminado directamente.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingParticipantDeletion = false" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <DangerButton @click="deleteParticipant" :disabled="deleteParticipantForm.processing">
                    Eliminar Integrante
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- ============================================================== -->
        <!-- MODAL: ELIMINAR VIAJE -->
        <!-- ============================================================== -->
        <ConfirmationModal :show="confirmingTripDeletion" @close="confirmingTripDeletion = false" max-width="md">
            <template #title>¿Eliminar viaje completo?</template>
            <template #content>
                Esta acción es irreversible y eliminará el viaje
                <span class="font-bold text-zinc-100">"{{ viaje.nombre }}"</span> junto con todos sus
                participantes, gastos y liquidaciones asociadas.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingTripDeletion = false" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <DangerButton @click="deleteTrip" :disabled="deleteTripForm.processing">
                    Eliminar Viaje
                </DangerButton>
            </template>
        </ConfirmationModal>

        <DialogModal :show="!!payingDebt" @close="closePagoForm" max-width="md">
            <template #title>
                <div class="flex items-center gap-2">
                    <span class="text-cyan-400">💳</span>
                    <span>Registrar pago de deuda</span>
                </div>
            </template>
            <template #content>
                <form v-if="payingDebt" @submit.prevent="submitPago" class="space-y-4">
                    <p class="text-xs text-zinc-400">
                        {{ payingDebt.deudor_nombre }} → {{ payingDebt.acreedor_nombre }}
                    </p>
                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div class="rounded-xl bg-zinc-950 border border-zinc-800 p-3">
                            <p class="text-zinc-500">Original</p>
                            <p class="font-mono font-bold text-zinc-100 mt-1">{{ formatCurrency(payingDebt.monto_original) }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-950 border border-zinc-800 p-3">
                            <p class="text-zinc-500">Pagado</p>
                            <p class="font-mono font-bold text-cyan-300 mt-1">{{ formatCurrency(payingDebt.monto_pagado) }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-950 border border-zinc-800 p-3">
                            <p class="text-zinc-500">Pendiente</p>
                            <p class="font-mono font-bold text-amber-300 mt-1">{{ formatCurrency(payingDebt.monto_pendiente) }}</p>
                        </div>
                    </div>
                    <div>
                        <InputLabel for="pago-monto" value="Monto a pagar" />
                        <TextInput
                            id="pago-monto"
                            v-model="pagoForm.monto"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="payingDebt.monto_pendiente"
                            class="w-full font-mono"
                            required
                        />
                        <InputError :message="pagoForm.errors.monto" class="mt-1" />
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="closePagoForm" type="button" class="me-3">Cancelar</SecondaryButton>
                <PrimaryButton @click="submitPago" :disabled="pagoForm.processing">Registrar pago</PrimaryButton>
            </template>
        </DialogModal>

        <!-- ============================================================== -->
        <!-- MODAL: AJUSTAR TIPO DE CAMBIO (EXCLUSIVO CREADOR) -->
        <!-- ============================================================== -->
        <DialogModal :show="modalTipoCambioOpen" @close="modalTipoCambioOpen = false" max-width="md">
            <template #title>
                <div class="flex items-center gap-2">
                    <span class="text-cyan-400">💱</span>
                    <span>Ajustar Cotizaciones del Viaje</span>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="submitTipoCambio" class="space-y-4">
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Como creador del viaje, puedes configurar manualmente el valor del Dólar y USDT en Bolivianos. Los saldos se consolidarán con estas tasas.
                    </p>

                    <div>
                        <InputLabel for="tc-usd" value="1 USD en Bolivianos (Bs.)" />
                        <TextInput
                            id="tc-usd"
                            v-model="tipoCambioForm.tipo_cambio_usd"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            class="w-full font-mono text-cyan-300 font-bold"
                            placeholder="6.9600"
                            required
                        />
                        <InputError :message="tipoCambioForm.errors.tipo_cambio_usd" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="tc-usdt" value="1 USDT en Bolivianos (Bs.)" />
                        <TextInput
                            id="tc-usdt"
                            v-model="tipoCambioForm.tipo_cambio_usdt"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            class="w-full font-mono text-purple-300 font-bold"
                            placeholder="10.5000"
                            required
                        />
                        <InputError :message="tipoCambioForm.errors.tipo_cambio_usdt" class="mt-1" />
                    </div>
                </form>
            </template>

            <template #footer>
                <SecondaryButton @click="modalTipoCambioOpen = false" type="button" class="me-3">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    @click="submitTipoCambio"
                    :disabled="tipoCambioForm.processing"
                    type="button"
                >
                    <span v-if="tipoCambioForm.processing">Guardando...</span>
                    <span v-else>Guardar Cotizaciones</span>
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
