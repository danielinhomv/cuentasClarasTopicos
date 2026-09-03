<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gasto\StoreGastoRequest;
use App\Http\Requests\Gasto\UpdateGastoRequest;
use App\Models\Gasto;
use App\Models\Viaje;
use App\Services\RegistroBitacoraGastoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class GastoController extends Controller
{
    public function index(Viaje $viaje): JsonResponse
    {
        $this->authorize('viewAny', [Gasto::class, $viaje]);

        $gastos = $viaje->gastos()
            ->with(['pagador', 'excluidos', 'participantes'])
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json($gastos);
    }

    public function store(StoreGastoRequest $request, Viaje $viaje): JsonResponse|RedirectResponse
    {
        $this->authorize('create', [Gasto::class, $viaje]);

        $gasto = DB::transaction(function () use ($request, $viaje) {
            $validated = $request->validated();
            $excluidos = $validated['excluidos'] ?? [];
            unset($validated['excluidos']);

            if (empty($validated['tipo_cambio'])) {
                $moneda = $validated['moneda'] ?? 'BOB';
                if ($moneda === 'USD') {
                    $validated['tipo_cambio'] = $viaje->tipo_cambio_usd ?? 6.9600;
                } elseif ($moneda === 'USDT') {
                    $validated['tipo_cambio'] = $viaje->tipo_cambio_usdt ?? 10.5000;
                } else {
                    $validated['tipo_cambio'] = 1.0000;
                }
            }

            /** @var Gasto $gasto */
            $gasto = $viaje->gastos()->create($validated);

            if (! empty($excluidos)) {
                $gasto->excluidos()->sync($excluidos);
            }

            $todosParticipanteIds = $viaje->participantes()->pluck('id')->all();
            $incluidosIds = array_values(array_diff($todosParticipanteIds, $excluidos));
            $gasto->participantes()->sync($incluidosIds);

            $actor = $request->user();
            if ($actor) {
                app(RegistroBitacoraGastoService::class)->registrarCrear(
                    $gasto->load(['pagador', 'participantes', 'excluidos']),
                    $actor
                );
            }

            return $gasto;
        });

        if ($request->wantsJson()) {
            return response()->json(
                $gasto->load(['pagador', 'excluidos']),
                201
            );
        }

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Gasto registrado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function show(Gasto $gasto): JsonResponse
    {
        $this->authorize('view', $gasto);

        return response()->json(
            $gasto->load(['pagador', 'excluidos'])
        );
    }

    public function update(UpdateGastoRequest $request, Gasto $gasto): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $gasto);

        DB::transaction(function () use ($request, $gasto) {
            $validated = $request->validated();
            $hasExcluidos = array_key_exists('excluidos', $validated);
            $excluidos = $validated['excluidos'] ?? [];
            unset($validated['excluidos']);

            if (isset($validated['moneda'])) {
                if ($validated['moneda'] === 'USD') {
                    $validated['tipo_cambio'] = $gasto->viaje->tipo_cambio_usd ?? 6.9600;
                } elseif ($validated['moneda'] === 'USDT') {
                    $validated['tipo_cambio'] = $gasto->viaje->tipo_cambio_usdt ?? 10.5000;
                } else {
                    $validated['tipo_cambio'] = 1.0000;
                }
            }

            $bitacora = app(RegistroBitacoraGastoService::class);
            $antes = $bitacora->snapshot($gasto->load(['pagador', 'participantes', 'excluidos']));

            $gasto->update($validated);

            if ($hasExcluidos) {
                $gasto->excluidos()->sync($excluidos);

                $participantesActuales = $gasto->participantes()->pluck('participantes.id')->all();
                $excluidosActuales = $gasto->excluidos()->pluck('participantes.id')->all();
                if (empty($participantesActuales)) {
                    $participantesActuales = $gasto->viaje->participantes()->pluck('id')->all();
                }
                $incluidosIds = array_values(array_diff($participantesActuales, $excluidosActuales));
                $gasto->participantes()->sync($incluidosIds);
            }

            $actor = $request->user();
            if ($actor) {
                $gasto->unsetRelation('pagador');
                $gasto->unsetRelation('participantes');
                $gasto->unsetRelation('excluidos');
                $bitacora->registrarEditar($gasto->fresh(['pagador', 'participantes', 'excluidos']), $actor, $antes);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(
                $gasto->load(['pagador', 'excluidos'])
            );
        }

        return redirect()
            ->route('viajes.show', $gasto->viaje_id)
            ->with('flash.banner', 'Gasto actualizado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function destroy(Gasto $gasto): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $gasto);

        $viajeId = $gasto->viaje_id;

        DB::transaction(function () use ($gasto) {
            $bitacora = app(RegistroBitacoraGastoService::class);
            $antes = $bitacora->snapshot($gasto->load(['pagador', 'participantes', 'excluidos']));
            $actor = request()->user();
            if ($actor) {
                $bitacora->registrarEliminar($gasto, $actor, $antes);
            }

            $gasto->delete();
        });

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Gasto eliminado correctamente.',
            ]);
        }

        return redirect()
            ->route('viajes.show', $viajeId)
            ->with('flash.banner', 'Gasto eliminado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }
}
