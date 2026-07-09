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
        Schema::create('documento_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('cascade');
            $table->enum('tipo_documento', ['SOAT', 'SEGURO', 'INSPECCION_TECNICA', 'RUAT', 'POLIZA', 'TARJETA_DE_PROPIEDAD'])->default('SOAT');
            $table->string('numero_documento', 100);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->string('archivo')->nullable();
            $table->enum('estado_documento', ['VIGENTE', 'VENCIDO', 'ANULADO'])->default('VIGENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_vehiculo');
    }
};
