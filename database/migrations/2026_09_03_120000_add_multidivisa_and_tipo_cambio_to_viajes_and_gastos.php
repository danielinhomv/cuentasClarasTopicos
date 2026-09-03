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
        Schema::table('viajes', function (Blueprint $table) {
            $table->decimal('tipo_cambio_usd', 10, 4)->default(6.9600)->after('codigo_invitacion');
            $table->decimal('tipo_cambio_usdt', 10, 4)->default(10.5000)->after('tipo_cambio_usd');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->string('moneda', 5)->default('BOB')->after('monto');
            $table->decimal('tipo_cambio', 10, 4)->nullable()->after('moneda');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'tipo_cambio']);
        });

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio_usd', 'tipo_cambio_usdt']);
        });
    }
};
