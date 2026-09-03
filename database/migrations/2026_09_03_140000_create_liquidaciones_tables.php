<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viaje_id')->constrained('viajes')->cascadeOnDelete();
            $table->foreignId('deudor_id')->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('acreedor_id')->constrained('participantes')->cascadeOnDelete();
            $table->decimal('monto_original', 12, 2);
            $table->decimal('monto_pagado', 12, 2)->default(0);
            $table->decimal('monto_pendiente', 12, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();

            $table->unique(['viaje_id', 'deudor_id', 'acreedor_id']);
        });

        Schema::create('liquidacion_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_pagos');
        Schema::dropIfExists('liquidaciones');
    }
};
