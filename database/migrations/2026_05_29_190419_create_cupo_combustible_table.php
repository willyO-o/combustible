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
        Schema::create('cupo_combustible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_area')->constrained('area')->onDelete('restrict');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('litros_asignados', 10, 2);
            $table->decimal('litros_consumidos', 10, 2)->default(0);
            $table->decimal('monto_asignado', 10, 2);
            $table->decimal('monto_consumido', 10, 2)->default(0);
            $table->enum('estado_cupo', ['ACTIVO', 'CERRADO', 'EXCEDIDO', 'ANULADO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cupo_combustible');
    }
};
