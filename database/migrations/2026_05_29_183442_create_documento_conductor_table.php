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
        Schema::create('documento_conductor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('cascade');
            $table->enum('tipo_documento', ['LICENCIA_DE_CONDUCIR','CI','CERTIFICADO_MEDICO',' OTRO'])->default('LICENCIA_DE_CONDUCIR');
            $table->string('numero_documento', 100);
            $table->string('categoria', 10)->nullable();
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->string('archivo')->nullable();
            $table->enum('estado_documento', ['VIGENTE', 'VENCIDO', 'OBSERVADO'])->default('VIGENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_conductor');
    }
};
