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
        Schema::create('vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('nro_placa', 20)->unique();
            $table->string('anio', 4)->nullable();
            $table->string('marca', 50)->nullable();
            $table->enum('estado_vehiculo', ['ACTIVO', 'RETIRADO', 'VENDIDO'])->default('ACTIVO');
            $table->foreignId('id_tipo_combustible')->constrained('tipo_combustible')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_tipo_vehiculo')->constrained('tipo_vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->string('fotografia', 250)->nullable();
            $table->text('detalles')->nullable();
            $table->enum('tipo_medicion', ['kilometraje', 'horometro'])->default('kilometraje');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculo');
    }
};
