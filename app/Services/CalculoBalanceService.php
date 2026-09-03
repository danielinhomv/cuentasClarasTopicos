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

        $anfitrionId = $this->anfitrionId($viaje);
        $balanceCorrido = $pagadoCentavos;
        $enDeudaDesde = [];

        $gastosOrdenados = $gastos->sortBy([
            ['fecha', 'asc'],
            ['id', 'asc'],
        ]);

        foreach ($gastosOrdenados as $gasto) {
            $montoCentavos = $this->montoConsolidadoCentavos($gasto, $viaje);

            if (isset($pagadoCentavos[$gasto->pagador_id])) {
                $pagadoCentavos[$gasto->pagador_id] += $montoCentavos;
            }

            $participantesGasto = $gasto->relationLoaded('participantes')
                ? $gasto->participantes
                : $gasto->participantes()->get();

            $beneficiarios = $participantesGasto->filter(
                fn ($p) => isset($consumoCentavos[$p->id])
            );

            if ($beneficiarios->isEmpty()) {
                $this->actualizarDeudaCorrida($balanceCorrido, $enDeudaDesde, $pagadoCentavos, $consumoCentavos, (string) $gasto->fecha);
                continue;
            }

            $ids = $beneficiarios->pluck('id')->all();
            $cuotas = $this->ajusteEfectivo->repartir(
                $montoCentavos,
                $ids,
                $anfitrionId,
                $this->contextoDeuda($ids, $balanceCorrido, $enDeudaDesde),
                (int) $gasto->id
            );

            $sumaCuotas = 0;
            foreach ($cuotas as $participanteId => $cuota) {
                $sumaCuotas += $cuota;
                if (isset($consumoCentavos[$participanteId])) {
                    $consumoCentavos[$participanteId] += $cuota;
                }
            }

            $ajusteCaja = $sumaCuotas - $montoCentavos;
            if ($ajusteCaja !== 0) {
                $destinoCaja = $anfitrionId !== 0 && isset($pagadoCentavos[$anfitrionId])
                    ? $anfitrionId
                    : (int) $gasto->pagador_id;
                if (isset($pagadoCentavos[$destinoCaja])) {
                    $pagadoCentavos[$destinoCaja] += $ajusteCaja;
                }
            }

            $this->actualizarDeudaCorrida($balanceCorrido, $enDeudaDesde, $pagadoCentavos, $consumoCentavos, (string) $gasto->fecha);
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
        $montoCentavos = $this->montoConsolidadoCentavos($gasto, $viaje);
        $beneficiarios = $gasto->relationLoaded('participantes')
            ? $gasto->participantes
            : $gasto->participantes()->get();
        $ids = $beneficiarios->pluck('id')->all();
        $k = count($ids);
        $teorica = $k > 0 ? intdiv($montoCentavos, $k) : 0;
        $cuotas = $k > 0
            ? $this->ajusteEfectivo->repartir(
                $montoCentavos,
                $ids,
                $this->anfitrionId($viaje),
                $this->contextoDeudaParaDesglose($viaje, $gasto),
                (int) $gasto->id
            )
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

    private function anfitrionId(Viaje $viaje): int
    {
        $participantes = $viaje->relationLoaded('participantes')
            ? $viaje->participantes
            : $viaje->participantes()->get();

        $anfitrion = $participantes->firstWhere('user_id', $viaje->user_id);

        return $anfitrion?->id ?? 0;
    }

    private function montoConsolidadoCentavos(Gasto $gasto, Viaje $viaje): int
    {
        $tasa = 1.0;
        $moneda = $gasto->moneda ?? 'BOB';

        if ($moneda === 'USD') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usd ?: 6.9600));
        } elseif ($moneda === 'USDT') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usdt ?: 10.5000));
        }

        return (int) round(round(((float) $gasto->monto) * $tasa, 2) * 100);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $balanceCorrido
     * @param  array<int, string|null>  $enDeudaDesde
     * @return array<int, array{deuda: int, desde: ?string}>
     */
    private function contextoDeuda(array $ids, array $balanceCorrido, array $enDeudaDesde): array
    {
        $contexto = [];
        foreach ($ids as $id) {
            $balance = (int) ($balanceCorrido[$id] ?? 0);
            $contexto[$id] = [
                'deuda' => max(0, -$balance),
                'desde' => $enDeudaDesde[$id] ?? null,
            ];
        }

        return $contexto;
    }

    /**
     * @return array<int, array{deuda: int, desde: ?string}>
     */
    private function contextoDeudaParaDesglose(Viaje $viaje, Gasto $actual): array
    {
        $participantes = $viaje->relationLoaded('participantes')
            ? $viaje->participantes
            : $viaje->participantes()->get();

        $pagadoCentavos = [];
        $consumoCentavos = [];
        $enDeudaDesde = [];
        foreach ($participantes as $p) {
            $pagadoCentavos[$p->id] = 0;
            $consumoCentavos[$p->id] = 0;
        }

        $gastos = $viaje->relationLoaded('gastos')
            ? $viaje->gastos
            : $viaje->gastos()->with('participantes')->get();

        $anfitrionId = $this->anfitrionId($viaje);
        $balanceCorrido = $pagadoCentavos;

        foreach ($gastos->sortBy([['fecha', 'asc'], ['id', 'asc']]) as $gasto) {
            $esPosterior = ((string) $gasto->fecha) > ((string) $actual->fecha)
                || (((string) $gasto->fecha) === ((string) $actual->fecha) && (int) $gasto->id >= (int) $actual->id);
            if ($esPosterior) {
                break;
            }

            $montoCentavos = $this->montoConsolidadoCentavos($gasto, $viaje);
            if (isset($pagadoCentavos[$gasto->pagador_id])) {
                $pagadoCentavos[$gasto->pagador_id] += $montoCentavos;
            }

            $ids = $gasto->relationLoaded('participantes')
                ? $gasto->participantes->pluck('id')->all()
                : $gasto->participantes()->pluck('participantes.id')->all();

            $ids = array_values(array_filter($ids, fn ($id) => isset($consumoCentavos[$id])));
            if ($ids === []) {
                $this->actualizarDeudaCorrida($balanceCorrido, $enDeudaDesde, $pagadoCentavos, $consumoCentavos, (string) $gasto->fecha);
                continue;
            }

            $cuotas = $this->ajusteEfectivo->repartir(
                $montoCentavos,
                $ids,
                $anfitrionId,
                $this->contextoDeuda($ids, $balanceCorrido, $enDeudaDesde),
                (int) $gasto->id
            );

            $sumaCuotas = 0;
            foreach ($cuotas as $participanteId => $cuota) {
                $sumaCuotas += $cuota;
                if (isset($consumoCentavos[$participanteId])) {
                    $consumoCentavos[$participanteId] += $cuota;
                }
            }

            $ajusteCaja = $sumaCuotas - $montoCentavos;
            if ($ajusteCaja !== 0) {
                $destinoCaja = $anfitrionId !== 0 && isset($pagadoCentavos[$anfitrionId])
                    ? $anfitrionId
                    : (int) $gasto->pagador_id;
                if (isset($pagadoCentavos[$destinoCaja])) {
                    $pagadoCentavos[$destinoCaja] += $ajusteCaja;
                }
            }

            $this->actualizarDeudaCorrida($balanceCorrido, $enDeudaDesde, $pagadoCentavos, $consumoCentavos, (string) $gasto->fecha);
        }

        $idsActuales = $actual->relationLoaded('participantes')
            ? $actual->participantes->pluck('id')->all()
            : $actual->participantes()->pluck('participantes.id')->all();

        return $this->contextoDeuda($idsActuales, $balanceCorrido, $enDeudaDesde);
    }

    /**
     * @param  array<int, int>  $balanceCorrido
     * @param  array<int, string|null>  $enDeudaDesde
     * @param  array<int, int>  $pagadoCentavos
     * @param  array<int, int>  $consumoCentavos
     */
    private function actualizarDeudaCorrida(
        array &$balanceCorrido,
        array &$enDeudaDesde,
        array $pagadoCentavos,
        array $consumoCentavos,
        string $fecha
    ): void {
        foreach ($pagadoCentavos as $id => $pagado) {
            $balance = $pagado - ($consumoCentavos[$id] ?? 0);
            $balanceCorrido[$id] = $balance;
            if ($balance < 0) {
                $enDeudaDesde[$id] = $enDeudaDesde[$id] ?? $fecha;
            } else {
                $enDeudaDesde[$id] = null;
            }
        }
    }
}
