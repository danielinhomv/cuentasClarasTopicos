<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Services\AlgoritmoLiquidacionService;
use App\Services\CalculoBalanceService;
use Illuminate\Http\JsonResponse;

class LiquidacionController extends Controller
{
    public function saldos(Viaje $viaje, CalculoBalanceService $balanceService): JsonResponse
    {
        $this->authorize('view', $viaje);

        $saldos = $balanceService->calcularBalances($viaje);

        return response()->json($saldos);
    }

    public function liquidacion(
        Viaje $viaje,
        CalculoBalanceService $balanceService,
        AlgoritmoLiquidacionService $liquidacionService
    ): JsonResponse {
        $this->authorize('view', $viaje);

        $balances = $balanceService->calcularBalances($viaje);
        $transferencias = $liquidacionService->calcularLiquidacion($balances);

        return response()->json($transferencias);
    }
}
