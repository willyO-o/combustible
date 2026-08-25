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
        Schema::create('carga_material', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->integer('nro_carga')->nullable();
            $table->string('gestion', 4)->nullable();
            $table->foreignId('id_vehiculo_externo')->constrained('vehiculo_externo')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_usuario_apertura')->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_usuario_cierre')->nullable()->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->boolean('es_al_exterior')->default(false);
            $table->string('pais', 50)->nullable();
            $table->string('nombre_conductor', 250)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->dateTime('fecha_pago')->nullable();
            $table->decimal('monto_pago', 10, 2)->nullable();
            $table->string('detalle', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado_carga', ['ABIERTA', 'CERRADA', 'PAGADA'])->default('ABIERTA');
            $table->timestamps();

            $table->unique(['nro_carga', 'gestion'], 'unique_nro_carga_gestion');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carga_material');
    }
};
