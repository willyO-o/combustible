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
        Schema::create('jornada_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->decimal('horometro_inicio', 10, 2);
            $table->decimal('horometro_fin', 10, 2)->nullable();
            $table->decimal('horas_trabajadas', 10, 2)->nullable();
            $table->enum('estado', ['PENDIENTE', 'EN_PROGRESO', 'FINALIZADO'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jornada_trabajo');
    }
};
