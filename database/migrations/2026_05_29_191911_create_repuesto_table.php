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
        Schema::create('repuesto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_repuesto', 200);
            $table->string('codigo_repuesto', 100)->unique();
            $table->text('descripcion_repuesto')->nullable();
            $table->enum('unidad_medida', ['UNIDAD', 'LITRO', 'KILOGRAMO', 'METRO', 'JUEGO','CAJA','BOLSA','PAQUETE'])->default('UNIDAD');
            $table->integer('stock_actual')->default(0);
            $table->enum('estado_repuesto', ['ACTIVO', 'INACTIVO', 'AGOTADO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repuesto');
    }
};
