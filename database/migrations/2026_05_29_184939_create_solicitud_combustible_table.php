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
        Schema::create('solicitud_combustible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->date('fecha_solicitud');
            $table->decimal('litros_solicitados', 10, 2);
            $table->text('motivo_solicitud')->nullable();
            $table->enum('estado_solicitud', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CANCELADA'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->date('fecha_aprobacion')->nullable();
            $table->foreignId('id_usuario_aprueba')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('id_usuario_solicita')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_combustible');
    }
};
