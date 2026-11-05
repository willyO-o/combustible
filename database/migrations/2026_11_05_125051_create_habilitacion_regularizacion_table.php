<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilitaciones temporales que otorga el encargado de regularización
     * (OPERACION_REGULARIZACION_ENCARGADOS del .env) para que UN usuario
     * registre operaciones diarias de días pasados: sólo dentro de
     * [fecha_desde, fecha_hasta] y mientras no venza (vigente_hasta) ni se
     * revoque. Tabla nueva: no modifica datos existentes.
     */
    public function up(): void
    {
        Schema::create('habilitacion_regularizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->dateTime('vigente_hasta');
            $table->string('motivo', 500);
            $table->foreignId('id_usuario_otorga')->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->dateTime('revocada_at')->nullable();
            $table->foreignId('id_usuario_revoca')->nullable()->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();

            $table->index(['id_usuario', 'vigente_hasta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habilitacion_regularizacion');
    }
};
