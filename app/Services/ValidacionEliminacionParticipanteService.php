<?php

namespace App\Services;

use App\Models\Liquidacion;
use App\Models\Participante;

class ValidacionEliminacionParticipanteService
{
    public function __construct(
        private CalculoBalanceService $calculoBalance
    ) {}

    public function motivoBloqueo(Participante $participante): ?string
    {
        if ($this->tieneDeudaPendiente($participante)) {
            return 'No se puede eliminar a este participante porque tiene una deuda pendiente.';
        }

        if ($this->participoEnGasto($participante)) {
            return 'No se puede eliminar a este participante porque ya participó en un gasto.';
        }

        return null;
    }

    private function tieneDeudaPendiente(Participante $participante): bool
    {
        $tieneLiquidacionPendiente = Liquidacion::query()
            ->where('viaje_id', $participante->viaje_id)
            ->where(function ($query) use ($participante) {
                $query->where('deudor_id', $participante->id)
                    ->orWhere('acreedor_id', $participante->id);
            })
            ->where('monto_pendiente', '>', 0)
            ->exists();

        if ($tieneLiquidacionPendiente) {
            return true;
        }

        $viaje = $participante->viaje()->with(['participantes', 'gastos.participantes'])->first();
        if ($viaje === null) {
            return false;
        }

        $balances = collect($this->calculoBalance->calcularBalances($viaje));
        $saldo = $balances->firstWhere('participante_id', $participante->id);

        if ($saldo === null) {
            return false;
        }

        return (int) round(abs((float) $saldo['balance']) * 100) !== 0;
    }

    private function participoEnGasto(Participante $participante): bool
    {
        return $participante->gastosPagados()->exists()
            || $participante->gastosParticipados()->exists();
    }
}
