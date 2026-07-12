<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('respaldo_digital', function (Blueprint $table) {
            // Ambos FK deben ser opcionales: un respaldo puede pertenecer
            // SOLO a una carga_combustible O SOLO a una incidencia.
            $table->foreignId('id_carga_combustible')
                  ->nullable()->change();
            $table->foreignId('id_incidencia')
                  ->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('respaldo_digital', function (Blueprint $table) {
            $table->foreignId('id_carga_combustible')
                  ->nullable(false)->change();
            $table->foreignId('id_incidencia')
                  ->nullable(false)->change();
        });
    }
};
