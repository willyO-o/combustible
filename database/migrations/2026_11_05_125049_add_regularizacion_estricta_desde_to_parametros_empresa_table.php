<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Día desde el que la operación diaria pasa a "modo estricto" (sólo el
     * día actual, o la noche de ayer en turno NOCHE, salvo habilitación del
     * encargado). null = modo transición, el comportamiento anterior. Cambio
     * aditivo: la fila existente queda en null, sin tocar ningún dato.
     */
    public function up(): void
    {
        Schema::table('parametros_empresa', function (Blueprint $table) {
            $table->date('regularizacion_estricta_desde')->nullable()->after('parametros_vale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parametros_empresa', function (Blueprint $table) {
            $table->dropColumn('regularizacion_estricta_desde');
        });
    }
};
