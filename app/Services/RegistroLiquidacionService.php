<?php

namespace App\Services;

use App\Models\Liquidacion;
use App\Models\Viaje;
use InvalidArgumentException;

class RegistroLiquidacionService
{
    /**
     * Materializa el plan calculado como deudas persistidas y devuelve
     * el estado enriquecido (original / pagado / pendiente).
     *
     * @param  array<int, array{
     *     deudor_id: int,
     *     deudor_nombre: string,
     *     deudor: string,
     *     acreedor_id: int,
     *     acreedor_nombre: string,
     *     acreedor: string,
     *     monto: float
     * }>  $transferencias
     * @return array<int, array{
     *     id: int,
     *     deudor_id: int,
     *     deudor_nombre: string,
     *     deudor: string,
     *     acreedor_id: int,
     *     acreedor_nombre: string,
     *     acreedor: string,
     *     monto: float,
     *     monto_original: float,
     *     monto_pagado: float,
     *     monto_pendiente: float,
     *     liquidada: bool,
     *     estado: string
     * }>
     */
    public function reconciliar(Viaje $viaje, array $transferencias): array
    {
        $paresVigentes = [];

        foreach ($transferencias as $t) {
            $paresVigentes[$t['deudor_id'].':'.$t['acreedor_id']] = true;
            $originalCentavos = (int) round(((float) $t['monto']) * 100);

            $liquidacion = Liquidacion::query()->firstOrNew([
                'viaje_id' => $viaje->id,
                'deudor_id' => $t['deudor_id'],
                'acreedor_id' => $t['acreedor_id'],
            ]);

            $pagadoCentavos = $liquidacion->exists
                ? (int) round(((float) $liquidacion->monto_pagado) * 100)
                : 0;

            $pendienteCentavos = max(0, $originalCentavos - $pagadoCentavos);

            $liquidacion->fill([
                'monto_original' => round($originalCentavos / 100, 2),
                'monto_pagado' => round($pagadoCentavos / 100, 2),
                'monto_pendiente' => round($pendienteCentavos / 100, 2),
                'estado' => $pendienteCentavos === 0 ? 'liquidada' : 'pendiente',
            ]);
            $liquidacion->save();
        }

        $obsoletas = Liquidacion::query()
            ->where('viaje_id', $viaje->id)
            ->get()
            ->reject(fn (Liquidacion $liquidacion) => isset($paresVigentes[$liquidacion->deudor_id.':'.$liquidacion->acreedor_id]));

        foreach ($obsoletas as $liquidacion) {
            $pagadoCentavos = (int) round(((float) $liquidacion->monto_pagado) * 100);

            if ($pagadoCentavos === 0) {
                $liquidacion->delete();
                continue;
            }

            $liquidacion->update([
                'monto_original' => 0,
                'monto_pendiente' => 0,
                'estado' => 'liquidada',
            ]);
        }

        $viaje->unsetRelation('liquidaciones');

        return $this->listarDeudas($viaje);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarDeudas(Viaje $viaje): array
    {
        $liquidaciones = $viaje->liquidaciones()
            ->with(['deudor', 'acreedor'])
            ->orderBy('id')
            ->get();

        return $liquidaciones
            ->filter(function (Liquidacion $liquidacion) {
                $originalCentavos = (int) round(((float) $liquidacion->monto_original) * 100);
                $pendienteCentavos = (int) round(((float) $liquidacion->monto_pendiente) * 100);

                return $originalCentavos > 0 || $pendienteCentavos > 0;
            })
            ->map(function (Liquidacion $liquidacion) {
                $pendiente = round((float) $liquidacion->monto_pendiente, 2);

                return [
                    'id' => $liquidacion->id,
                    'deudor_id' => $liquidacion->deudor_id,
                    'deudor_nombre' => $liquidacion->deudor?->nombre,
                    'deudor' => $liquidacion->deudor?->nombre,
                    'acreedor_id' => $liquidacion->acreedor_id,
                    'acreedor_nombre' => $liquidacion->acreedor?->nombre,
                    'acreedor' => $liquidacion->acreedor?->nombre,
                    'monto' => $pendiente,
                    'monto_original' => round((float) $liquidacion->monto_original, 2),
                    'monto_pagado' => round((float) $liquidacion->monto_pagado, 2),
                    'monto_pendiente' => $pendiente,
                    'liquidada' => $liquidacion->estaLiquidada(),
                    'estado' => $liquidacion->estado,
                ];
            })
            ->values()
            ->all();
    }

    public function registrarPago(Liquidacion $liquidacion, float $monto, ?string $fechaPago = null): Liquidacion
    {
        $montoCentavos = (int) round($monto * 100);
        $pendienteCentavos = (int) round(((float) $liquidacion->monto_pendiente) * 100);

        if ($montoCentavos <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        if ($montoCentavos > $pendienteCentavos) {
            throw new InvalidArgumentException('El pago no puede superar el monto pendiente de la deuda.');
        }

        $liquidacion->pagos()->create([
            'monto' => round($montoCentavos / 100, 2),
            'fecha_pago' => $fechaPago ?? now()->toDateString(),
        ]);

        $pagadoCentavos = (int) round(((float) $liquidacion->monto_pagado) * 100) + $montoCentavos;
        $nuevoPendiente = max(0, (int) round(((float) $liquidacion->monto_original) * 100) - $pagadoCentavos);

        $liquidacion->update([
            'monto_pagado' => round($pagadoCentavos / 100, 2),
            'monto_pendiente' => round($nuevoPendiente / 100, 2),
            'estado' => $nuevoPendiente === 0 ? 'liquidada' : 'pendiente',
        ]);

        return $liquidacion->fresh(['deudor', 'acreedor', 'pagos']);
    }

    /**
     * Ajusta los saldos brutos restando los pagos persistidos.
     *
     * @param  array<int, array<string, mixed>>  $balances
     * @return array<int, array<string, mixed>>
     */
    public function aplicarPagosABalances(Viaje $viaje, array $balances): array
    {
        $porId = [];
        foreach ($balances as $index => $balance) {
            $porId[$balance['participante_id']] = $index;
        }

        $liquidaciones = $viaje->liquidaciones()->get();

        foreach ($liquidaciones as $liquidacion) {
            $pagadoCentavos = (int) round(((float) $liquidacion->monto_pagado) * 100);
            $originalCentavos = (int) round(((float) $liquidacion->monto_original) * 100);
            $aplicableCentavos = min($pagadoCentavos, $originalCentavos);
            if ($aplicableCentavos === 0) {
                continue;
            }

            $ajuste = $aplicableCentavos / 100;

            if (isset($porId[$liquidacion->deudor_id])) {
                $i = $porId[$liquidacion->deudor_id];
                $balances[$i]['balance'] = round(((float) $balances[$i]['balance']) + $ajuste, 2);
            }

            if (isset($porId[$liquidacion->acreedor_id])) {
                $i = $porId[$liquidacion->acreedor_id];
                $balances[$i]['balance'] = round(((float) $balances[$i]['balance']) - $ajuste, 2);
            }
        }

        return $balances;
    }
}
