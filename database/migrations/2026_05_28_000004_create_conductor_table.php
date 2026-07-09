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
        Schema::create('conductor', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 20)->unique();
            $table->string('nombres', 150);
            $table->string('paterno', 150)->nullable();
            $table->string('materno', 150)->nullable();
            $table->string('foto', 250);
            $table->string('celular', 20)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('estado_conductor', ['ACTIVO', 'INACTIVO', 'RETIRADO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conductor');
    }
};
