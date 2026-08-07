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
        Schema::create('grupo_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('grupo_vehiculo', 150);
            $table->enum('estado_grupo_vehiculo', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_vehiculo');

    }
};
