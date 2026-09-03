<?php

namespace App\Services;

use App\Models\Viaje;
use UnexpectedValueException;

class CalculoBalanceService
{
    /**
     * Calcula para un viaje: total pagado, total consumido y balance neto por participante,
     * consolidando cualquier gasto en USD o USDT a la divisa base con su respectivo tipo de cambio.
     *
     * @param  Viaje  $viaje
     * @return array<int, array{
     *     participante_id: int,
     *     nombre: string,
     *     total_pagado: float,
     *     total_consumido: float,
     *     balance: float
     * }>
     */
    public function calcularBalances(Viaje $viaje): array
    {
        $participantes = $viaje->relationLoaded('participantes')
            ? $viaje->participantes
            : $viaje->participantes()->get();

        if ($participantes->isEmpty()) {
            return [];
        }

        $pagadoCentavos = [];
        $consumoCentavos = [];

        foreach ($participantes as $p) {
            $pagadoCentavos[$p->id] = 0;
            $consumoCentavos[$p->id] = 0;
        }

        $gastos = $viaje->relationLoaded('gastos')
            ? $viaje->gastos
            : $viaje->gastos()->with('excluidos')->get();

        foreach ($gastos as $gasto) {
            $tasa = 1.0;
            $moneda = $gasto->moneda ?? 'BOB';

            if ($moneda === 'USD') {
                $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usd ?: 6.9600));
            } elseif ($moneda === 'USDT') {
                $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usdt ?: 10.5000));
            }

            $montoConsolidado = round(((float) $gasto->monto) * $tasa, 2);
            $montoCentavos = (int) round($montoConsolidado * 100);

            if (isset($pagadoCentavos[$gasto->pagador_id])) {
                $pagadoCentavos[$gasto->pagador_id] += $montoCentavos;
            }

            $excluidosCollection = $gasto->relationLoaded('excluidos')
                ? $gasto->excluidos
                : $gasto->excluidos()->get();

            $excluidosIds = $excluidosCollection->pluck('id')->all();

            $beneficiarios = $participantes->filter(
                fn ($p) => ! in_array($p->id, $excluidosIds, true)
            );

            $k = $beneficiarios->count();
            if ($k === 0) {
                continue;
            }

            $cuotaBase = intdiv($montoCentavos, $k);
            $residuo = $montoCentavos % $k;

            $pagadorEsBeneficiario = $beneficiarios->contains('id', $gasto->pagador_id);

            foreach ($beneficiarios as $beneficiario) {
                $cuota = $cuotaBase;
                if ($pagadorEsBeneficiario && $beneficiario->id === $gasto->pagador_id) {
                    $cuota += $residuo;
                } elseif (! $pagadorEsBeneficiario && $beneficiario->id === $beneficiarios->first()->id) {
                    $cuota += $residuo;
                }

                $consumoCentavos[$beneficiario->id] += $cuota;
            }
        }

        $balances = [];
        $sumaBalancesCentavos = 0;

        foreach ($participantes as $p) {
            $pagado = $pagadoCentavos[$p->id];
            $consumo = $consumoCentavos[$p->id];
            $balanceCentavos = $pagado - $consumo;
            $sumaBalancesCentavos += $balanceCentavos;

            $balances[] = [
                'participante_id' => $p->id,
                'nombre' => $p->nombre,
                'total_pagado' => round($pagado / 100, 2),
                'total_consumido' => round($consumo / 100, 2),
                'balance' => round($balanceCentavos / 100, 2),
            ];
        }

        if ($sumaBalancesCentavos !== 0) {
            throw new UnexpectedValueException('Error crítico: la suma de balances no es igual a cero.');
        }

        return $balances;
    }
}
