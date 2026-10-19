<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Topes de lectura por jornada de operación diaria, por tipo de vehículo,
     * para frenar errores de tipeo (ej. 5460.7 -> 55101 por un punto
     * olvidado). Se aplica el que corresponde al tipo_medicion de cada
     * vehículo. null = sin tope.
     *
     * Los valores por defecto (1000 km / 24 h) quedan muy por encima de lo
     * real (el máximo registrado era 286 km en una jornada) para no bloquear
     * jornadas válidas; cada tipo se ajusta desde Tipos de Vehículo. Al
     * agregar la columna con default, los tipos existentes los reciben.
     */
    public function up(): void
    {
        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->decimal('km_maximo_jornada', 10, 2)->nullable()->default(1000)->after('unidad_capacidad_sugerida');
            $table->decimal('horometro_maximo_jornada', 6, 2)->nullable()->default(24)->after('km_maximo_jornada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->dropColumn(['km_maximo_jornada', 'horometro_maximo_jornada']);
        });
    }
};
