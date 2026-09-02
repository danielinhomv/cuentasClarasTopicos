<?php

namespace App\Http\Controllers;

use App\Http\Requests\Viaje\StoreViajeRequest;
use App\Http\Requests\Viaje\UpdateViajeRequest;
use App\Models\Viaje;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViajeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Viaje::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $query = $request->user()
            ->viajes()
            ->withCount('participantes')
            ->latest();

        if ($search !== '') {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        if ($status === 'sin_participantes') {
            $query->doesntHave('participantes');
        } elseif ($status === 'con_participantes') {
            $query->has('participantes');
        }

        return Inertia::render('Viajes/Index', [
            'viajes' => $query->paginate(10)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('Viajes/Create');
    }

    public function store(StoreViajeRequest $request): RedirectResponse
    {
        $viaje = $request->user()->viajes()->create($request->validated());

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Viaje creado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function show(Viaje $viaje): Response
    {
        $this->authorize('view', $viaje);

        $viaje->load(['participantes' => fn ($query) => $query->orderBy('nombre')]);

        return Inertia::render('Viajes/Show', [
            'viaje' => $viaje,
        ]);
    }

    public function edit(Viaje $viaje): Response
    {
        $this->authorize('update', $viaje);

        return Inertia::render('Viajes/Edit', [
            'viaje' => $viaje,
        ]);
    }

    public function update(UpdateViajeRequest $request, Viaje $viaje): RedirectResponse
    {
        $viaje->update($request->validated());

        return redirect()
            ->route('viajes.show', $viaje)
            ->with('flash.banner', 'Viaje actualizado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }

    public function destroy(Viaje $viaje): RedirectResponse
    {
        $this->authorize('delete', $viaje);

        $viaje->delete();

        return redirect()
            ->route('viajes.index')
            ->with('flash.banner', 'Viaje eliminado correctamente.')
            ->with('flash.bannerStyle', 'success');
    }
}
