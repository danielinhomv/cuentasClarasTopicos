<?php

namespace App\Http\Controllers;

use App\Http\Requests\Participante\StoreParticipanteRequest;
use App\Http\Requests\Participante\UpdateParticipanteRequest;
use App\Models\Participante;
use App\Models\Viaje;
use Illuminate\Http\RedirectResponse;

class ParticipanteController extends Controller
{
    public function index(Viaje $viaje): RedirectResponse
    {
        $this->authorize('viewAny', [Participante::class, $viaje]);

        return redirect()->route('viajes.show', $viaje);
    }

    public function store(StoreParticipanteRequest $request, Viaje $viaje): RedirectResponse
    {
        $viaje->participantes()->create($request->validated());

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Participante agregado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function update(UpdateParticipanteRequest $request, Participante $participante): RedirectResponse
    {
        $participante->update($request->validated());

        return redirect()
            ->route('viajes.show', $participante->viaje_id)
            ->with('flash.banner', 'Participante actualizado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function destroy(Participante $participante): RedirectResponse
    {
        $this->authorize('delete', $participante);

        $viajeId = $participante->viaje_id;
        $participante->delete();

        return redirect()
            ->route('viajes.show', $viajeId)
            ->with('flash.banner', 'Participante eliminado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }
}
