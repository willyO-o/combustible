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
        // capacidad_unidad no es enum() a propósito: además de los valores
        // fijos del selector (m³, yd³, L, gal, t, kg, lb) admite texto libre
        // cuando el usuario elige "Otro" en el frontend — mismo criterio que
        // tipo_mantenimiento.unidad_medida.
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->decimal('capacidad', 8, 2)->nullable()->after('detalles');
            $table->string('capacidad_unidad', 20)->nullable()->after('capacidad');
        });

        // Unidad sugerida para precargar el select de capacidad del
        // formulario de Vehiculo al elegir este tipo (no obliga nada, sólo
        // evita que cada vehículo del mismo tipo termine con una unidad
        // distinta por descuido).
        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->string('unidad_capacidad_sugerida', 20)->nullable()->after('id_grupo_vehiculo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculo', function (Blueprint $table) {
            $table->dropColumn(['capacidad', 'capacidad_unidad']);
        });

        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->dropColumn('unidad_capacidad_sugerida');
        });
    }
};
