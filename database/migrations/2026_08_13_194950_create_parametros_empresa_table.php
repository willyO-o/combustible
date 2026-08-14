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
        Schema::create('parametros_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa');
            $table->string('direccion_empresa');
            $table->string('telefono_empresa');
            $table->string('correo_empresa');
            $table->string('nit_empresa');
            $table->string('logo_empresa')->nullable();
            $table->json('parametros_vale')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros_empresa');
    }
};
