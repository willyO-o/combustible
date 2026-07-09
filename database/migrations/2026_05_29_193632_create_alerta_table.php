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
        Schema::create('alerta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->string('tipo_alerta',150)->nullable();
            $table->text('mensaje')->nullable();
            $table->dateTime('fecha_alerta')->nullable();
            $table->enum('estado', ['PENDIENTE', 'RESUELTO', 'IGNORADO'])->default('PENDIENTE');
            $table->tinyInteger('prioridad')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta');
    }
};
