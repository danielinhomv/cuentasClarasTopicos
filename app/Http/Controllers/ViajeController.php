<?php

namespace App\Http\Controllers;

use App\Http\Requests\Viaje\StoreViajeRequest;
use App\Http\Requests\Viaje\UpdateViajeRequest;
use App\Models\GastoBitacora;
use App\Models\Viaje;
use App\Services\AlgoritmoLiquidacionService;
use App\Services\CalculoBalanceService;
use App\Services\RegistroLiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViajeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Viaje::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $user = $request->user();

        $query = Viaje::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participantes', fn ($p) => $p->where('user_id', $user->id));
            })
            ->withCount('participantes')
            ->latest();

        if ($search !== '') {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        if ($status === 'sin_participantes') {
            $query->doesntHave('participantes');
        } elseif ($status === 'con_participantes') {
            $query->has('participantes');
        }

        return Inertia::render('Viajes/Index', [
            'viajes' => $query->paginate(10)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('Viajes/Create');
    }

    public function store(StoreViajeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $viaje = $user->viajes()->create($request->validated());

        // Registrar al creador automáticamente como el primer participante
        $viaje->participantes()->create([
            'user_id' => $user->id,
            'nombre' => $user->name,
        ]);

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Viaje creado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function unirse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_invitacion' => ['required', 'string', 'size:8'],
        ], [
            'codigo_invitacion.required' => 'El código de invitación es obligatorio.',
            'codigo_invitacion.size' => 'El código de invitación debe tener 8 caracteres.',
        ]);

        $codigo = strtoupper(trim($validated['codigo_invitacion']));
        $viaje = Viaje::where('codigo_invitacion', $codigo)->first();

        if (! $viaje) {
            return back()->withErrors([
                'codigo_invitacion' => 'El código de invitación ingresado no existe.',
            ]);
        }

        $user = $request->user();

        if ($viaje->participantes()->where('user_id', $user->id)->exists()) {
            return back()->withErrors([
                'codigo_invitacion' => 'Ya eres participante de este viaje.',
            ]);
        }

        $viaje->participantes()->create([
            'user_id' => $user->id,
            'nombre' => $user->name,
        ]);

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', "¡Te has unido exitosamente al viaje {$viaje->nombre}!")
            ->with('flash.bannerStyle', 'success');
    }

    public function show(
        Viaje $viaje,
        CalculoBalanceService $balanceService,
        AlgoritmoLiquidacionService $liquidacionService,
        RegistroLiquidacionService $registroService
    ): Response {
        $this->authorize('view', $viaje);

        $viaje->load([
            'participantes' => fn ($query) => $query->orderBy('nombre'),
            'gastos' => fn ($query) => $query->with(['pagador', 'excluidos', 'participantes'])->orderBy('fecha', 'desc'),
        ]);

        $saldosBrutos = $balanceService->calcularBalances($viaje);
        $transferencias = $liquidacionService->calcularLiquidacion($saldosBrutos);
        $liquidacion = $registroService->reconciliar($viaje, $transferencias);
        $saldos = $registroService->aplicarPagosABalances($viaje, $saldosBrutos);

        $viaje->gastos->each(function ($gasto) use ($balanceService, $viaje) {
            $desglose = $balanceService->desgloseEfectivo($gasto, $viaje);
            $gasto->setAttribute('cuotas_efectivo', $desglose['cuotas_efectivo']);
            $gasto->setAttribute('tiene_ajuste_efectivo', $desglose['tiene_ajuste_efectivo']);
            $gasto->setAttribute('monto_original', $desglose['monto_original']);
        });

        $esAnfitrion = request()->user()?->id === $viaje->user_id;
        $bitacora = $esAnfitrion
            ? $viaje->bitacoras()->orderByDesc('created_at')->orderByDesc('id')->get()
            : [];

        return Inertia::render('Viajes/Show', [
            'viaje' => $viaje,
            'saldos' => $saldos,
            'liquidacion' => $liquidacion,
            'bitacora' => $bitacora,
        ]);
    }

    public function bitacora(Viaje $viaje): JsonResponse
    {
        $this->authorize('viewAny', [GastoBitacora::class, $viaje]);

        return response()->json(
            $viaje->bitacoras()->orderByDesc('created_at')->orderByDesc('id')->get()
        );
    }

    public function edit(Viaje $viaje): Response
    {
        $this->authorize('update', $viaje);

        return Inertia::render('Viajes/Edit', [
            'viaje' => $viaje,
        ]);
    }

    public function update(UpdateViajeRequest $request, Viaje $viaje): RedirectResponse
    {
        $viaje->update($request->validated());

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Viaje actualizado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function destroy(Viaje $viaje): RedirectResponse
    {
        $this->authorize('delete', $viaje);

        $viaje->delete();

        return redirect()
            ->route('viajes.index')
            ->with('flash.banner', 'Viaje eliminado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function actualizarTipoCambio(Request $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('update', $viaje);

        $validated = $request->validate([
            'tipo_cambio_usd' => ['required', 'numeric', 'min:0.0001', 'max:9999.9999'],
            'tipo_cambio_usdt' => ['required', 'numeric', 'min:0.0001', 'max:9999.9999'],
        ], [
            'tipo_cambio_usd.required' => 'El tipo de cambio para USD es requerido.',
            'tipo_cambio_usd.numeric' => 'El tipo de cambio para USD debe ser numérico.',
            'tipo_cambio_usd.min' => 'El tipo de cambio para USD debe ser mayor a 0.',
            'tipo_cambio_usdt.required' => 'El tipo de cambio para USDT es requerido.',
            'tipo_cambio_usdt.numeric' => 'El tipo de cambio para USDT debe ser numérico.',
            'tipo_cambio_usdt.min' => 'El tipo de cambio para USDT debe ser mayor a 0.',
        ]);

        $viaje->update($validated);

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Tipos de cambio actualizados correctamente.')
            ->with('flash.bannerStyle', 'success');
    }
}
