<?php

use App\Http\Controllers\GastoController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\ViajeController;
use App\Http\Controllers\ViajePdfController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/viajes');
Route::redirect('/dashboard', '/viajes');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::get('/viajes', [ViajeController::class, 'index'])->name('viajes.index');
    Route::get('/viajes/create', [ViajeController::class, 'create'])->name('viajes.create');
    Route::post('/viajes', [ViajeController::class, 'store'])->name('viajes.store');
    Route::post('/viajes/unirse', [ViajeController::class, 'unirse'])->name('viajes.unirse');
    Route::get('/viajes/{viaje}/bitacora', [ViajeController::class, 'bitacora'])->name('viajes.bitacora.index');
    Route::get('/viajes/{viaje}/exportar-pdf', [ViajePdfController::class, 'exportar'])->name('viajes.exportar-pdf');
    Route::get('/viajes/{viaje}', [ViajeController::class, 'show'])->name('viajes.show');
    Route::get('/viajes/{viaje}/edit', [ViajeController::class, 'edit'])->name('viajes.edit');
    Route::put('/viajes/{viaje}', [ViajeController::class, 'update'])->name('viajes.update');
    Route::put('/viajes/{viaje}/tipo-cambio', [ViajeController::class, 'actualizarTipoCambio'])->name('viajes.tipo-cambio.update');
    Route::delete('/viajes/{viaje}', [ViajeController::class, 'destroy'])->name('viajes.destroy');

    Route::get('/viajes/{viaje}/participantes', [ParticipanteController::class, 'index'])->name('viajes.participantes.index');
    Route::post('/viajes/{viaje}/participantes', [ParticipanteController::class, 'store'])->name('viajes.participantes.store');
    Route::put('/participantes/{participante}', [ParticipanteController::class, 'update'])->name('participantes.update');
    Route::delete('/participantes/{participante}', [ParticipanteController::class, 'destroy'])->name('participantes.destroy');

    Route::get('/viajes/{viaje}/gastos', [GastoController::class, 'index'])->name('viajes.gastos.index');
    Route::post('/viajes/{viaje}/gastos', [GastoController::class, 'store'])->name('viajes.gastos.store');
    Route::get('/gastos/{gasto}', [GastoController::class, 'show'])->name('gastos.show');
    Route::put('/gastos/{gasto}', [GastoController::class, 'update'])->name('gastos.update');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->name('gastos.destroy');

    Route::get('/viajes/{viaje}/saldos', [LiquidacionController::class, 'saldos'])->name('viajes.saldos');
    Route::get('/viajes/{viaje}/liquidacion', [LiquidacionController::class, 'liquidacion'])->name('viajes.liquidacion');
    Route::post('/liquidaciones/{liquidacion}/pagos', [LiquidacionController::class, 'registrarPago'])->name('liquidaciones.pagos.store');
});
