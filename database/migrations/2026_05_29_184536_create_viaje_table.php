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
        Schema::create('viaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->date('fecha_salida');
            $table->date('fecha_llegada')->nullable();
            $table->string('origen', 200);
            $table->string('destino', 200);
            $table->text('motivo_viaje')->nullable();
            $table->decimal('km_salida', 10, 2)->nullable();
            $table->decimal('km_llegada', 10, 2)->nullable();
            $table->decimal('distancia_recorrida', 10, 2)->nullable();
            $table->enum('estado_viaje', ['PROGRAMADO', 'EN_CURSO', 'FINALIZADO', 'CANCELADO'])->default('PROGRAMADO');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viaje');
    }
};
