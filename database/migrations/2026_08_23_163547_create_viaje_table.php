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
            $table->foreignId('id_carga_material')->constrained('carga_material')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_material')->constrained('material')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_usuario_registro')->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->string('foto', 255)->nullable();
            $table->string('origen', 255)->nullable();
            $table->string('destino', 255)->nullable();
            $table->string('detalle', 255)->nullable();
            $table->dateTime('fecha_hora_carga');
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
