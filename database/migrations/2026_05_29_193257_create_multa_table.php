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
        Schema::create('multa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict');
            $table->decimal('monto', 10, 2);
            $table->date('fecha_multa');
            $table->text('tipo_infraccion')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('lugar')->nullable();
            $table->string('nro_infraccion')->nullable();
            $table->string('comprobante')->nullable();
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'APELADO','ANULADO'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multa');
    }
};
