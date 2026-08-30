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
        Schema::create('mantenimiento_operacion_diaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_operacion_diaria')->constrained('operacion_diaria')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_tipo_mantenimiento')->constrained('tipo_mantenimiento')->onDelete('restrict')->onUpdate('cascade');
            $table->decimal('valor', 10, 2)->nullable();
            $table->enum('realizado', ['SI', 'NO'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mantenimiento_operacion_diaria');
    }
};
