<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Paso 1 del flujo de mantenimiento:
     * El CHOFER registra una solicitud o alarma de mantenimiento
     * con los datos del vehículo y la posible falla o mantenimiento preventivo.
     */
    public function up(): void
    {
        Schema::create('solicitud_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->integer('nro_solicitud');
            $table->integer('gestion');

            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->nullable()->constrained('conductor')->onDelete('set null');
            $table->foreignId('id_usuario_registra')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('tipo_mantenimiento', ['PREVENTIVO', 'CORRECTIVO'])->default('PREVENTIVO');

            $table->text('descripcion_problema');

            $table->integer('kilometraje_actual')->nullable();
            $table->integer('horometro_actual')->nullable();
            $table->dateTime('fecha_solicitud');

            $table->enum('estado', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA'])->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['nro_solicitud', 'gestion'], 'unique_nro_solicitud_gestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_mantenimiento');
    }
};
