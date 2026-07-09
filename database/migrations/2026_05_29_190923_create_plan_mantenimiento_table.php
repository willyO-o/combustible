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
        Schema::create('plan_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_tipo_mantenimiento')->constrained('tipo_mantenimiento')->onDelete('restrict');
            $table->integer('kilometraje_programado')->nullable();
            $table->date('fecha_programada')->nullable();
            $table->integer('frecuencia_km')->nullable();
            $table->integer('frecuencia_mes')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->enum('estado_plan', ['PENDIENTE', 'EJECUTADO', 'VENCIDO','ANULADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_mantenimiento');
    }
};
