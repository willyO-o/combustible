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
        Schema::create('taller', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 200);
            $table->string('nit', 30)->unique();
            $table->string('direccion', 250)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('contacto', 250)->nullable();
            $table->enum('estado_taller', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taller');
    }
};
