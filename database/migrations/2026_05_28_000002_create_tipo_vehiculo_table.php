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
        Schema::create('tipo_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_vehiculo', 150);
            $table->enum('estado_tipo_vehiculo', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->foreignId('id_grupo_vehiculo')->constrained('grupo_vehiculo')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_vehiculo');
    }
};
