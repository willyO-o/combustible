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
        Schema::create('vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('nro_placa', 20)->nullable()->unique();
            $table->string('codigo', 50)->nullable();
            $table->string('anio', 4)->nullable();
            $table->string('modelo', 4)->nullable();
            $table->string('marca', 50)->nullable();
            $table->enum('estado_vehiculo', ['ACTIVO', 'RETIRADO', 'VENDIDO'])->default('ACTIVO');
            $table->foreignId('id_tipo_combustible')->constrained('tipo_combustible')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_tipo_vehiculo')->constrained('tipo_vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->string('fotografia', 250)->nullable();
            $table->text('detalles')->nullable();
            $table->enum('tipo_medicion', ['kilometraje', 'horometro'])->default('kilometraje');
            $table->uuid('uuid')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nro_placa', 'estado_vehiculo']);
            $table->index(['id_tipo_combustible', 'estado_vehiculo']);
            $table->index(['id_tipo_vehiculo', 'estado_vehiculo']);
        });




        Schema::create('vehiculo_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('cascade');
            $table->foreignId('id_area')->constrained('area')->onDelete('cascade');
            $table->text('motivo_asignacion')->nullable();
            $table->date('fecha_asignacion');
            $table->date('fecha_reasignacion')->nullable();
            $table->date('fecha_culminacion')->nullable();
            $table->enum('estado_asignacion', ['ACTIVO', 'REASIGNADO','PROVISIONAL','CULMINADO'])->default('ACTIVO');
            $table->timestamps();

            $table->index(['id_vehiculo', 'estado_asignacion']); // "¿cuál es la asignación activa de este vehículo?"
            $table->index(['id_area', 'estado_asignacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculo_area');
        Schema::dropIfExists('vehiculo');
    }
};
