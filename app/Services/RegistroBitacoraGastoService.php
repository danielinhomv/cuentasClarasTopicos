<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\GastoBitacora;
use App\Models\User;

class RegistroBitacoraGastoService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Gasto $gasto): array
    {
        $gasto->loadMissing(['pagador', 'participantes', 'excluidos']);

        return [
            'concepto' => $gasto->concepto,
            'monto' => number_format((float) $gasto->monto, 2, '.', ''),
            'moneda' => $gasto->moneda ?? 'BOB',
            'tipo_cambio' => $gasto->tipo_cambio,
            'fecha' => optional($gasto->fecha)?->toDateString(),
            'pagador_id' => $gasto->pagador_id,
            'pagador_nombre' => $gasto->pagador?->nombre,
            'incluidos' => $gasto->participantes
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre])
                ->values()
                ->all(),
            'excluidos' => $gasto->excluidos
                ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre])
                ->values()
                ->all(),
        ];
    }

    public function registrarCrear(Gasto $gasto, User $actor): GastoBitacora
    {
        $despues = $this->snapshot($gasto);

        return $this->insertar($gasto, $actor, 'crear', null, $despues);
    }

    /**
     * @param  array<string, mixed>  $antes
     */
    public function registrarEditar(Gasto $gasto, User $actor, array $antes): ?GastoBitacora
    {
        $despues = $this->snapshot($gasto);
        [$datosAntes, $datosDespues] = $this->delta($antes, $despues);

        if ($datosAntes === [] && $datosDespues === []) {
            return null;
        }

        return $this->insertar($gasto, $actor, 'editar', $datosAntes, $datosDespues);
    }

    /**
     * @param  array<string, mixed>  $antes
     */
    public function registrarEliminar(Gasto $gasto, User $actor, array $antes): GastoBitacora
    {
        return $this->insertar($gasto, $actor, 'eliminar', $antes, null);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function delta(array $antes, array $despues): array
    {
        $antesDelta = [];
        $despuesDelta = [];

        foreach ($antes as $clave => $valor) {
            if (in_array($clave, ['incluidos', 'excluidos'], true)) {
                $idsAntes = collect($valor)->pluck('id')->sort()->values()->all();
                $idsDespues = collect($despues[$clave] ?? [])->pluck('id')->sort()->values()->all();
                if ($idsAntes !== $idsDespues) {
                    $antesDelta[$clave] = $valor;
                    $despuesDelta[$clave] = $despues[$clave] ?? [];
                }

                continue;
            }

            if (($despues[$clave] ?? null) != $valor) {
                $antesDelta[$clave] = $valor;
                $despuesDelta[$clave] = $despues[$clave] ?? null;
            }
        }

        return [$antesDelta, $despuesDelta];
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private function insertar(
        Gasto $gasto,
        User $actor,
        string $accion,
        ?array $antes,
        ?array $despues
    ): GastoBitacora {
        return GastoBitacora::query()->create([
            'viaje_id' => $gasto->viaje_id,
            'gasto_id' => $gasto->id,
            'user_id' => $actor->id,
            'actor_nombre' => $actor->name,
            'accion' => $accion,
            'gasto_concepto' => $gasto->concepto,
            'datos_antes' => $antes,
            'datos_despues' => $despues,
            'created_at' => now(),
        ]);
    }
}
