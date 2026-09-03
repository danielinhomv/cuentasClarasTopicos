<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Liquidacion;
use App\Models\Viaje;

class ExportarViajePdfService
{
    public function __construct(
        private CalculoBalanceService $balanceService,
        private AlgoritmoLiquidacionService $liquidacionService,
        private RegistroLiquidacionService $registroService,
    ) {}

    /**
     * @return array{
     *     viaje: array{nombre: string, descripcion: ?string, generado_en: string},
     *     participantes: list<array{nombre: string, anfitrion: bool, sin_cuenta: bool}>,
     *     resumen: array{total_gastado_bs: float, cantidad_participantes: int, cantidad_gastos: int, suma_pendientes: float, sin_gastos: bool},
     *     gastos: list<array<string, mixed>>,
     *     saldos: list<array<string, mixed>>,
     *     liquidaciones: list<array<string, mixed>>
     * }
     */
    public function armar(Viaje $viaje): array
    {
        $viaje->load([
            'participantes' => fn ($query) => $query->orderBy('nombre'),
            'gastos' => fn ($query) => $query
                ->with(['pagador', 'participantes'])
                ->orderBy('fecha')
                ->orderBy('id'),
        ]);

        $gastosValidos = $viaje->gastos->filter(
            fn (Gasto $gasto) => (int) round(((float) $gasto->monto) * 100) > 0
        );

        $saldosBrutos = $this->balanceService->calcularBalances($viaje);
        $transferencias = $this->liquidacionService->calcularLiquidacion($saldosBrutos);
        $this->registroService->reconciliar($viaje, $transferencias);
        $saldosExpuestos = $this->registroService->aplicarPagosABalances($viaje, $saldosBrutos);

        $expuestosPorNombre = collect($saldosExpuestos)->keyBy('nombre');

        $saldos = [];
        foreach ($saldosBrutos as $saldo) {
            $saldos[] = [
                'nombre' => $saldo['nombre'],
                'total_pagado' => $saldo['total_pagado'],
                'total_consumido' => $saldo['total_consumido'],
                'balance' => $saldo['balance'],
                'balance_expuesto' => $expuestosPorNombre[$saldo['nombre']]['balance'] ?? $saldo['balance'],
            ];
        }

        $liquidaciones = $this->mapearLiquidaciones($viaje);
        $gastos = $gastosValidos->map(fn (Gasto $gasto) => $this->mapearGasto($gasto, $viaje))->values()->all();

        $totalGastado = round($gastosValidos->sum(fn (Gasto $gasto) => $this->montoConsolidadoBs($gasto, $viaje)), 2);

        return [
            'viaje' => [
                'nombre' => $viaje->nombre,
                'descripcion' => $viaje->descripcion,
                'generado_en' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ],
            'participantes' => $viaje->participantes->map(fn ($participante) => [
                'nombre' => $participante->nombre,
                'anfitrion' => $participante->user_id !== null && $participante->user_id === $viaje->user_id,
                'sin_cuenta' => $participante->user_id === null,
            ])->values()->all(),
            'resumen' => [
                'total_gastado_bs' => $totalGastado,
                'cantidad_participantes' => $viaje->participantes->count(),
                'cantidad_gastos' => $gastosValidos->count(),
                'suma_pendientes' => round(collect($liquidaciones)->sum('monto_pendiente'), 2),
                'sin_gastos' => $gastosValidos->isEmpty(),
            ],
            'gastos' => $gastos,
            'saldos' => $saldos,
            'liquidaciones' => $liquidaciones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearGasto(Gasto $gasto, Viaje $viaje): array
    {
        $desglose = $this->balanceService->desgloseEfectivo($gasto, $viaje);
        $moneda = $gasto->moneda ?? 'BOB';

        return [
            'concepto' => $gasto->concepto,
            'fecha' => optional($gasto->fecha)->toDateString() ?? (string) $gasto->fecha,
            'pagador' => $gasto->pagador?->nombre,
            'monto_original' => $desglose['monto_original'],
            'moneda' => $moneda,
            'monto_bs' => $this->montoConsolidadoBs($gasto, $viaje),
            'incluidos' => $gasto->participantes->pluck('nombre')->values()->all(),
            'cuotas_efectivo' => collect($desglose['cuotas_efectivo'])->map(fn (array $cuota) => [
                'nombre' => $cuota['nombre'],
                'cuota_teorica' => $cuota['cuota_teorica'],
                'cuota_final' => $cuota['cuota_final'],
                'ajuste' => $cuota['ajuste'],
            ])->values()->all(),
            'tiene_ajuste_efectivo' => $desglose['tiene_ajuste_efectivo'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapearLiquidaciones(Viaje $viaje): array
    {
        $viaje->unsetRelation('liquidaciones');

        return $viaje->liquidaciones()
            ->with(['deudor', 'acreedor', 'pagos'])
            ->orderBy('id')
            ->get()
            ->filter(function (Liquidacion $liquidacion) {
                $originalCentavos = (int) round(((float) $liquidacion->monto_original) * 100);
                $pendienteCentavos = (int) round(((float) $liquidacion->monto_pendiente) * 100);

                return $originalCentavos > 0 || $pendienteCentavos > 0;
            })
            ->map(function (Liquidacion $liquidacion) {
                $original = round((float) $liquidacion->monto_original, 2);
                $pagado = round((float) $liquidacion->monto_pagado, 2);
                $pendiente = round((float) $liquidacion->monto_pendiente, 2);

                return [
                    'deudor' => $liquidacion->deudor?->nombre,
                    'acreedor' => $liquidacion->acreedor?->nombre,
                    'monto_original' => $original,
                    'monto_pagado' => $pagado,
                    'monto_pendiente' => $pendiente,
                    'estado' => $this->estadoVisible($pagado, $pendiente),
                    'abonos' => $liquidacion->pagos
                        ->sortBy('fecha_pago')
                        ->map(fn ($pago) => [
                            'monto' => round((float) $pago->monto, 2),
                            'fecha' => optional($pago->fecha_pago)->toDateString() ?? (string) $pago->fecha_pago,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function estadoVisible(float $pagado, float $pendiente): string
    {
        if ((int) round($pendiente * 100) === 0) {
            return 'hecha';
        }

        if ((int) round($pagado * 100) > 0) {
            return 'parcial';
        }

        return 'pendiente';
    }

    private function montoConsolidadoBs(Gasto $gasto, Viaje $viaje): float
    {
        $tasa = 1.0;
        $moneda = $gasto->moneda ?? 'BOB';

        if ($moneda === 'USD') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usd ?: 6.9600));
        } elseif ($moneda === 'USDT') {
            $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usdt ?: 10.5000));
        }

        return round(((float) $gasto->monto) * $tasa, 2);
    }
}
