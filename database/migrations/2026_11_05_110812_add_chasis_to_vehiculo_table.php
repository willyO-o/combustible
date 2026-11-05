<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Número de chasis del vehículo (opcional). Cambio aditivo: los vehículos
     * existentes quedan con chasis null, no se modifica ningún dato. La
     * unicidad se valida en VehiculoRequest (igual que nro_placa, ignorando
     * los eliminados), sin índice único en la BD.
     */
    public function up(): void
    {
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->string('chasis', 50)->nullable()->after('nro_placa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->dropColumn('chasis');
        });
    }
};
