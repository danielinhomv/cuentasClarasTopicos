<?php

namespace App\Services;

class AjusteEfectivoService
{
    public const UNIDAD_CENTAVOS = 50;

    /**
     * @param  list<int>  $beneficiarioIds
     * @return array<int, int> participante_id => consumo en centavos
     */
    public function repartir(int $montoCentavos, array $beneficiarioIds, int $pagadorId): array
    {
        $ids = array_values(array_unique(array_map('intval', $beneficiarioIds)));
        $k = count($ids);

        if ($k === 0 || $montoCentavos <= 0) {
            return array_fill_keys($ids, 0);
        }

        if ($k === 1) {
            return [$ids[0] => $montoCentavos];
        }

        $unidad = self::UNIDAD_CENTAVOS;
        $unidades = intdiv($montoCentavos, $unidad);
        $residuoMenorUnidad = $montoCentavos % $unidad;
        $unidadesBase = intdiv($unidades, $k);
        $unidadesExtra = $unidades % $k;

        $cuotas = [];
        foreach ($ids as $id) {
            $cuotas[$id] = $unidadesBase * $unidad;
        }

        $deudores = $this->deudoresOrdenados($ids, $pagadorId);
        $nDeudores = count($deudores);
        for ($i = 0; $i < $unidadesExtra; $i++) {
            $destino = $nDeudores > 0
                ? $deudores[$i % $nDeudores]
                : $ids[$i % $k];
            $cuotas[$destino] += $unidad;
        }

        if ($residuoMenorUnidad > 0) {
            if (in_array($pagadorId, $ids, true)) {
                $cuotas[$pagadorId] += $residuoMenorUnidad;
            } elseif ($nDeudores > 0) {
                $cuotas[$deudores[0]] += $residuoMenorUnidad;
            } else {
                $cuotas[$ids[0]] += $residuoMenorUnidad;
            }
        }

        return $cuotas;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function deudoresOrdenados(array $ids, int $pagadorId): array
    {
        $deudores = array_values(array_filter($ids, fn (int $id) => $id !== $pagadorId));
        sort($deudores);

        return $deudores;
    }
}
