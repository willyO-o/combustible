<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * carga_material ya tenía nro_carga/gestion, pero gestion nunca se
     * llenaba (la numeración era un contador global, sin reiniciarse por
     * gestión). Ahora que el modelo calcula la gestión igual que
     * Vale/OrdenTrabajo/SolicitudMantenimiento, se agrega el mismo índice
     * único que usan esas tablas para impedir números repetidos dentro de
     * una misma gestión.
     */
    public function up(): void
    {
        Schema::table('carga_material', function (Blueprint $table) {
            $table->unique(['nro_carga', 'gestion'], 'unique_nro_carga_gestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carga_material', function (Blueprint $table) {
            $table->dropUnique('unique_nro_carga_gestion');
        });
    }
};
