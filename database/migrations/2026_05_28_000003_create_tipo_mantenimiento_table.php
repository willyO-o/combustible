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
        Schema::create('tipo_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_mantenimiento', 150);
            $table->enum('estado_tipo_mantenimiento', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('intervalo_mantenimiento_tipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tipo_vehiculo')->constrained('tipo_vehiculo')->onDelete('restrict');
            $table->foreignId('id_tipo_mantenimiento')->constrained('tipo_mantenimiento')->onDelete('restrict');
            $table->enum('tipo_medicion', ['kilometraje', 'horometro'])->default('kilometraje');
            $table->integer('frecuencia')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();

            $table->unique(['id_tipo_vehiculo', 'id_tipo_mantenimiento'], 'unique_tipo_vehiculo_tipo_mantenimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervalo_mantenimiento_tipo');
        Schema::dropIfExists('tipo_mantenimiento');
    }
};
