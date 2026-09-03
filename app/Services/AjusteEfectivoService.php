<?php

namespace App\Services;

class AjusteEfectivoService
{
    public const UNIDAD_CENTAVOS = 50;

    /**
     * @param  list<int>  $beneficiarioIds
     * @param  array<int, array{deuda: int, desde: ?string}>  $contextoDeuda
     * @return array<int, int> participante_id => consumo en centavos
     */
    public function repartir(
        int $montoCentavos,
        array $beneficiarioIds,
        int $anfitrionId = 0,
        array $contextoDeuda = [],
        int $sorteoSeed = 0
    ): array {
        $ids = array_values(array_unique(array_map('intval', $beneficiarioIds)));
        $k = count($ids);

        if ($k === 0 || $montoCentavos <= 0) {
            return array_fill_keys($ids, 0);
        }

        if ($k === 1) {
            return [$ids[0] => $montoCentavos];
        }

        $unidad = self::UNIDAD_CENTAVOS;
        $teorica = intdiv($montoCentavos, $k);
        $piso = $teorica - ($teorica % $unidad);

        $cuotas = [];
        foreach ($ids as $id) {
            $cuotas[$id] = $piso;
        }

        $gap = $montoCentavos - ($piso * $k);
        $unidadesExtra = $gap > 0 ? (int) ceil($gap / $unidad) : 0;
        if ($unidadesExtra === 0) {
            return $cuotas;
        }

        $candidatos = $this->ordenarCandidatos($ids, $anfitrionId, $contextoDeuda, $sorteoSeed);
        if ($candidatos === []) {
            return $cuotas;
        }

        $n = count($candidatos);
        for ($i = 0; $i < $unidadesExtra; $i++) {
            $cuotas[$candidatos[$i % $n]] += $unidad;
        }

        return $cuotas;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array{deuda: int, desde: ?string}>  $contextoDeuda
     * @return list<int>
     */
    public function ordenarCandidatos(
        array $ids,
        int $anfitrionId,
        array $contextoDeuda,
        int $sorteoSeed
    ): array {
        $candidatos = array_values(array_filter($ids, fn (int $id) => $id !== $anfitrionId));
        if ($candidatos === []) {
            return [];
        }

        usort($candidatos, function (int $a, int $b) use ($contextoDeuda, $sorteoSeed): int {
            $deudaA = (int) ($contextoDeuda[$a]['deuda'] ?? 0);
            $deudaB = (int) ($contextoDeuda[$b]['deuda'] ?? 0);
            if ($deudaA !== $deudaB) {
                return $deudaB <=> $deudaA;
            }

            $desdeA = $contextoDeuda[$a]['desde'] ?? null;
            $desdeB = $contextoDeuda[$b]['desde'] ?? null;
            if ($desdeA !== $desdeB) {
                if ($desdeA === null) {
                    return 1;
                }
                if ($desdeB === null) {
                    return -1;
                }

                return $desdeA <=> $desdeB;
            }

            $claveA = $this->claveSorteo($sorteoSeed, $a);
            $claveB = $this->claveSorteo($sorteoSeed, $b);

            return $claveA <=> $claveB;
        });

        return $candidatos;
    }

    public function claveSorteo(int $sorteoSeed, int $participanteId): int
    {
        return crc32($sorteoSeed.'|'.$participanteId);
    }
}
