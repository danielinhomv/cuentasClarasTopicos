<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gasto_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->constrained('gastos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gasto_id', 'participante_id']);
        });

        $now = now()->toDateTimeString();

        DB::table('gastos')
            ->select('gastos.id as gasto_id', 'participantes.id as participante_id')
            ->join('participantes', 'participantes.viaje_id', '=', 'gastos.viaje_id')
            ->leftJoin('gasto_exclusiones', function ($join) {
                $join->on('gasto_exclusiones.gasto_id', '=', 'gastos.id')
                     ->on('gasto_exclusiones.participante_id', '=', 'participantes.id');
            })
            ->whereNull('gasto_exclusiones.id')
            ->orderBy('gastos.id')
            ->chunk(500, function ($rows) use ($now) {
                $inserts = $rows->map(fn ($r) => [
                    'gasto_id' => $r->gasto_id,
                    'participante_id' => $r->participante_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                DB::table('gasto_participantes')->insert($inserts);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_participantes');
    }
};
