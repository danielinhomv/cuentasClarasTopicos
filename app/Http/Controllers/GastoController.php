<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gasto\StoreGastoRequest;
use App\Http\Requests\Gasto\UpdateGastoRequest;
use App\Models\Gasto;
use App\Models\Viaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class GastoController extends Controller
{
    public function index(Viaje $viaje): JsonResponse
    {
        $this->authorize('viewAny', [Gasto::class, $viaje]);

        $gastos = $viaje->gastos()
            ->with(['pagador', 'excluidos'])
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json($gastos);
    }

    public function store(StoreGastoRequest $request, Viaje $viaje): JsonResponse|RedirectResponse
    {
        $this->authorize('create', [Gasto::class, $viaje]);

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

        $gasto->update($validated);

        if ($hasExcluidos) {
            $gasto->excluidos()->sync($excluidos);
        }

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
        $gasto->delete();

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
