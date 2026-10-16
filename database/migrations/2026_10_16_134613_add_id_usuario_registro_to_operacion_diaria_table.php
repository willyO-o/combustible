<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usuario que registró la operación diaria. Desde que cualquier usuario
     * puede registrar cualquier vehículo, el registrador ya no se deduce del
     * área ni del conductor: sin esta columna, una operación registrada fuera
     * de las áreas del usuario no le aparecía en su listado. Nullable: las
     * operaciones ya existentes quedan sin registrador.
     */
    public function up(): void
    {
        Schema::table('operacion_diaria', function (Blueprint $table) {
            $table->foreignId('id_usuario_registro')
                ->nullable()
                ->after('id_area')
                ->constrained('users')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacion_diaria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_usuario_registro');
        });
    }
};
