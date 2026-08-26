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
        Schema::create('detalle_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_orden_trabajo')->constrained('orden_trabajo')->onDelete('cascade');
            $table->foreignId('id_repuesto')->nullable()->constrained('repuesto')->onDelete('restrict');
            $table->foreignId('id_tipo_mantenimiento')->constrained('tipo_mantenimiento')->onDelete('restrict');
            $table->date('fecha');
            $table->decimal('horometro', 10, 2)->nullable();
            $table->decimal('kilometraje', 10, 2)->nullable();
            $table->integer('cantidad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_mantenimiento');
    }
};
