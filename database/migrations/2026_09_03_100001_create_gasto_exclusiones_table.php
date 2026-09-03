<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gasto_exclusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->constrained('gastos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gasto_id', 'participante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto_exclusiones');
    }
};
