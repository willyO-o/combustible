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
        Schema::create('mantenimiento_repuesto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_plan_mantenimiento')
                ->constrained('plan_mantenimiento')->onDelete('cascade');

            // Repuesto del catálogo (puede ser null si se registra insumo libre)
            $table->foreignId('id_repuesto')
                ->nullable()
                ->constrained('repuesto')->onDelete('set null');

            // Tipo de ítem: permite registrar repuestos, aceites, llantas, etc.
            $table->enum('tipo_item', ['REPUESTO', 'ACEITE', 'LLANTA', 'INSUMO', 'OTRO'])
                ->default('REPUESTO');

            // Nombre libre cuando el ítem no está en el catálogo de repuestos
            $table->string('nombre_item', 200)->nullable();

            $table->enum('unidad_medida', ['UNIDAD', 'LITRO', 'KILOGRAMO', 'METRO', 'JUEGO', 'CAJA', 'BOLSA', 'PAQUETE'])
                ->default('UNIDAD');

            $table->decimal('cantidad_utilizada', 10, 2);
            $table->decimal('costo_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mantenimiento_repuesto');
    }
};
