<?php

namespace App\Services;

class AlgoritmoLiquidacionService
{
    /**
     * Calcula la lista mínima de transferencias directas para saldar todas las deudas.
     *
     * @param  array<int, array{
     *     participante_id: int,
     *     nombre: string,
     *     balance: float
     * }>  $balances
     * @return array<int, array{
     *     deudor_id: int,
     *     deudor_nombre: string,
     *     deudor: string,
     *     acreedor_id: int,
     *     acreedor_nombre: string,
     *     acreedor: string,
     *     monto: float
     * }>
     */
    public function calcularLiquidacion(array $balances): array
    {
        $deudores = [];
        $acreedores = [];

        foreach ($balances as $b) {
            $balanceCentavos = (int) round(((float) $b['balance']) * 100);

            if ($balanceCentavos < 0) {
                $deudores[] = [
                    'participante_id' => $b['participante_id'],
                    'nombre' => $b['nombre'],
                    'deuda' => abs($balanceCentavos),
                ];
            } elseif ($balanceCentavos > 0) {
                $acreedores[] = [
                    'participante_id' => $b['participante_id'],
                    'nombre' => $b['nombre'],
                    'credito' => $balanceCentavos,
                ];
            }
        }

        $transferencias = [];

        // Fase 1: Emparejamiento prioritario de montos exactos (|deuda| == credito)
        // Se ordenan de mayor a menor para priorizar deudas exactas más significativas
        usort($deudores, fn ($a, $b) => $b['deuda'] <=> $a['deuda']);
        usort($acreedores, fn ($a, $b) => $b['credito'] <=> $a['credito']);

        foreach ($deudores as $dIndex => $deudor) {
            if ($deudor['deuda'] === 0) {
                continue;
            }

            foreach ($acreedores as $aIndex => $acreedor) {
                if ($acreedor['credito'] === 0) {
                    continue;
                }

                if ($deudor['deuda'] === $acreedor['credito']) {
                    $monto = $deudor['deuda'];
                    $transferencias[] = [
                        'deudor_id' => $deudor['participante_id'],
                        'deudor_nombre' => $deudor['nombre'],
                        'deudor' => $deudor['nombre'],
                        'acreedor_id' => $acreedor['participante_id'],
                        'acreedor_nombre' => $acreedor['nombre'],
                        'acreedor' => $acreedor['nombre'],
                        'monto' => round($monto / 100, 2),
                    ];

                    $deudores[$dIndex]['deuda'] = 0;
                    $acreedores[$aIndex]['credito'] = 0;
                    break;
                }
            }
        }

        // Filtrar los que ya fueron completamente saldados
        $deudores = array_values(array_filter($deudores, fn ($d) => $d['deuda'] > 0));
        $acreedores = array_values(array_filter($acreedores, fn ($a) => $a['credito'] > 0));

        // Fase 2: Algoritmo voraz (Greedy matching) para las deudas restantes
        while (! empty($deudores) && ! empty($acreedores)) {
            usort($deudores, fn ($a, $b) => $b['deuda'] <=> $a['deuda']);
            usort($acreedores, fn ($a, $b) => $b['credito'] <=> $a['credito']);

            $deudor = &$deudores[0];
            $acreedor = &$acreedores[0];

            $transferenciaCentavos = min($deudor['deuda'], $acreedor['credito']);

            $transferencias[] = [
                'deudor_id' => $deudor['participante_id'],
                'deudor_nombre' => $deudor['nombre'],
                'deudor' => $deudor['nombre'],
                'acreedor_id' => $acreedor['participante_id'],
                'acreedor_nombre' => $acreedor['nombre'],
                'acreedor' => $acreedor['nombre'],
                'monto' => round($transferenciaCentavos / 100, 2),
            ];

            $deudor['deuda'] -= $transferenciaCentavos;
            $acreedor['credito'] -= $transferenciaCentavos;

            if ($deudor['deuda'] === 0) {
                array_shift($deudores);
            }

            if ($acreedor['credito'] === 0) {
                array_shift($acreedores);
            }
        }

        return $transferencias;
    }
}
