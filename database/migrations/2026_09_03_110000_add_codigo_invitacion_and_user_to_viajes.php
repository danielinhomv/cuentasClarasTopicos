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
            $table->string('codigo_invitacion', 8)->nullable()->unique()->after('descripcion');
        });

        Schema::table('participantes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('viaje_id')->constrained('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn('codigo_invitacion');
        });
    }
};
