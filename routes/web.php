<?php

use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\ViajeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/viajes', [ViajeController::class, 'index'])->name('viajes.index');
    Route::get('/viajes/create', [ViajeController::class, 'create'])->name('viajes.create');
    Route::post('/viajes', [ViajeController::class, 'store'])->name('viajes.store');
    Route::get('/viajes/{viaje}', [ViajeController::class, 'show'])->name('viajes.show');
    Route::get('/viajes/{viaje}/edit', [ViajeController::class, 'edit'])->name('viajes.edit');
    Route::put('/viajes/{viaje}', [ViajeController::class, 'update'])->name('viajes.update');
    Route::delete('/viajes/{viaje}', [ViajeController::class, 'destroy'])->name('viajes.destroy');

    Route::get('/viajes/{viaje}/participantes', [ParticipanteController::class, 'index'])->name('viajes.participantes.index');
    Route::post('/viajes/{viaje}/participantes', [ParticipanteController::class, 'store'])->name('viajes.participantes.store');
    Route::put('/participantes/{participante}', [ParticipanteController::class, 'update'])->name('participantes.update');
    Route::delete('/participantes/{participante}', [ParticipanteController::class, 'destroy'])->name('participantes.destroy');
});
