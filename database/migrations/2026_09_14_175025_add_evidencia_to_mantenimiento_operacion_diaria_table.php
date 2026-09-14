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
        // Ruta relativa (disco 'public'), igual convención que vehiculo.fotografia
        // / persona.foto. Se guarda ya convertida a .webp (ver
        // convertirImagenAWebp() en app/Helpers/helpers.php) para que la
        // evidencia no pese lo que pesa la foto original de un celular.
        Schema::table('mantenimiento_operacion_diaria', function (Blueprint $table) {
            $table->string('evidencia', 255)->nullable()->after('realizado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mantenimiento_operacion_diaria', function (Blueprint $table) {
            $table->dropColumn('evidencia');
        });
    }
};
