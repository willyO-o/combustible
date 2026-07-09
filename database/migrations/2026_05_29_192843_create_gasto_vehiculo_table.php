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
        Schema::create('gasto_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->enum('tipo_gasto', ['PEAJE', 'PARQUEO', 'LAVADO', 'MULTA','GRUA', 'REPARACION', 'MANTENIMIENTO','OTRO']);
            $table->decimal('monto', 10, 2);
            $table->date('fecha_gasto');
            $table->text('descripcion')->nullable();
            $table->string('nro_comprobante')->nullable();
            $table->string('foto_comprobante')->nullable();
            $table->enum('estado', ['REGISTRADO', 'APROBADO', 'RECHAZADO', 'OBSERVADO'])->default('REGISTRADO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto_vehiculo');
    }
};
