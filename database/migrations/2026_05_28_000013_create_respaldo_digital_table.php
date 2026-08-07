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
        Schema::create('respaldo_digital', function (Blueprint $table) {
            $table->id();
            $table->string('ruta_respaldo', 250);
            $table->string('tipo_respaldo', 30);
            $table->string('tipo_archivo', 30);
            $table->foreignId('id_carga_combustible')->nullable()->constrained('carga_combustible')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_incidencia')->nullable()->constrained('incidencia')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respaldo_digital');
    }
};
