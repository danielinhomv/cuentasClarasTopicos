<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Viaje;
use UnexpectedValueException;

class CalculoBalanceService
{
    public function __construct(
        private AjusteEfectivoService $ajusteEfectivo = new AjusteEfectivoService()
    ) {}

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
            : $viaje->gastos()->with('participantes')->get();

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

            // Usar la tabla de participantes incluidos (snapshot al momento de creación)
            $participantesGasto = $gasto->relationLoaded('participantes')
                ? $gasto->participantes
                : $gasto->participantes()->get();

            $beneficiarios = $participantesGasto->filter(
                fn ($p) => isset($consumoCentavos[$p->id])
            );

            $k = $beneficiarios->count();
            if ($k === 0) {
                continue;
            }

            $cuotas = $this->ajusteEfectivo->repartir(
                $montoCentavos,
                $beneficiarios->pluck('id')->all(),
                (int) $gasto->pagador_id
            );

            foreach ($cuotas as $participanteId => $cuota) {
                if (isset($consumoCentavos[$participanteId])) {
                    $consumoCentavos[$participanteId] += $cuota;
                }
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

    /**
     * @return array{
     *     monto_original: float,
     *     tiene_ajuste_efectivo: bool,
     *     cuotas_efectivo: list<array{id: int, nombre: string, cuota_teorica: float, cuota_final: float, ajuste: float}>
     * }
     */
    public function desgloseEfectivo(Gasto $gasto, Viaje $viaje): array
    {
        $tasa = 1.0;
        $moneda = $gasto->moneda ?? 'BOB';
        if ($moneda === 'USD') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usd ?: 6.9600));
        } elseif ($moneda === 'USDT') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usdt ?: 10.5000));
        }

        $montoConsolidado = round(((float) $gasto->monto) * $tasa, 2);
        $montoCentavos = (int) round($montoConsolidado * 100);
        $beneficiarios = $gasto->relationLoaded('participantes')
            ? $gasto->participantes
            : $gasto->participantes()->get();
        $ids = $beneficiarios->pluck('id')->all();
        $k = count($ids);
        $teorica = $k > 0 ? intdiv($montoCentavos, $k) : 0;
        $cuotas = $k > 0
            ? $this->ajusteEfectivo->repartir($montoCentavos, $ids, (int) $gasto->pagador_id)
            : [];

        $desglose = [];
        foreach ($beneficiarios as $participante) {
            $final = $cuotas[$participante->id] ?? 0;
            $desglose[] = [
                'id' => $participante->id,
                'nombre' => $participante->nombre,
                'cuota_teorica' => round($teorica / 100, 2),
                'cuota_final' => round($final / 100, 2),
                'ajuste' => round(($final - $teorica) / 100, 2),
            ];
        }

        return [
            'monto_original' => round((float) $gasto->monto, 2),
            'tiene_ajuste_efectivo' => collect($desglose)->contains(fn (array $c) => $c['ajuste'] != 0.0),
            'cuotas_efectivo' => $desglose,
        ];
    }
}
