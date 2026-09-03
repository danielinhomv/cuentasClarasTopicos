<?php

namespace App\Http\Controllers;

use App\Http\Requests\Liquidacion\StoreLiquidacionPagoRequest;
use App\Models\Liquidacion;
use App\Models\Viaje;
use App\Services\AlgoritmoLiquidacionService;
use App\Services\CalculoBalanceService;
use App\Services\RegistroLiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LiquidacionController extends Controller
{
    public function saldos(
        Viaje $viaje,
        CalculoBalanceService $balanceService,
        AlgoritmoLiquidacionService $liquidacionService,
        RegistroLiquidacionService $registroService
    ): JsonResponse {
        $this->authorize('view', $viaje);

        $balances = $balanceService->calcularBalances($viaje);
        $registroService->reconciliar(
            $viaje,
            $liquidacionService->calcularLiquidacion($balances)
        );

        $saldos = $registroService->aplicarPagosABalances($viaje, $balances);

        return response()->json($saldos);
    }

    public function liquidacion(
        Viaje $viaje,
        CalculoBalanceService $balanceService,
        AlgoritmoLiquidacionService $liquidacionService,
        RegistroLiquidacionService $registroService
    ): JsonResponse {
        $this->authorize('view', $viaje);

        $balances = $balanceService->calcularBalances($viaje);
        $transferencias = $liquidacionService->calcularLiquidacion($balances);
        $deudas = $registroService->reconciliar($viaje, $transferencias);

        return response()->json($deudas);
    }

    public function registrarPago(
        StoreLiquidacionPagoRequest $request,
        Liquidacion $liquidacion,
        RegistroLiquidacionService $registroService
    ): JsonResponse|RedirectResponse {
        $validated = $request->validated();
        $liquidacion = $registroService->registrarPago(
            $liquidacion,
            (float) $validated['monto'],
            $validated['fecha_pago'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $liquidacion->id,
                'deudor_id' => $liquidacion->deudor_id,
                'deudor_nombre' => $liquidacion->deudor?->nombre,
                'deudor' => $liquidacion->deudor?->nombre,
                'acreedor_id' => $liquidacion->acreedor_id,
                'acreedor_nombre' => $liquidacion->acreedor?->nombre,
                'acreedor' => $liquidacion->acreedor?->nombre,
                'monto' => round((float) $liquidacion->monto_pendiente, 2),
                'monto_original' => round((float) $liquidacion->monto_original, 2),
                'monto_pagado' => round((float) $liquidacion->monto_pagado, 2),
                'monto_pendiente' => round((float) $liquidacion->monto_pendiente, 2),
                'liquidada' => $liquidacion->estaLiquidada(),
                'estado' => $liquidacion->estado,
            ], 201);
        }

        return redirect()
            ->route('viajes.show', $liquidacion->viaje_id)
            ->with('flash.banner', $liquidacion->estaLiquidada()
                ? 'Deuda liquidada completamente.'
                : 'Pago parcial registrado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }
}
