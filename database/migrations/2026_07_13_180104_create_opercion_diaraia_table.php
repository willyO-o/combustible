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
        Schema::create('operacion_diaria', function (Blueprint $table) {
            $table->id();
            $table->integer('nro_operacion')->unique();
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_area')->constrained('area')->onDelete('restrict')->onUpdate('cascade');
            $table->enum('turno', ['DIA', 'NOCHE'])->default('DIA');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->decimal('horometro_inicio', 10, 2)->nullable();
            $table->decimal('horometro_fin', 10, 2)->nullable();
            $table->decimal('kilometraje_inicio', 10, 2)->nullable();
            $table->decimal('kilometraje_fin', 10, 2)->nullable();
            $table->decimal('horas_trabajadas', 10, 2)->nullable();
            $table->foreignId('id_verificador')->nullable()->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->enum('estado', ['PENDIENTE', 'EN_PROGRESO', 'FINALIZADO', 'VERIFICADO','OBSERVADO'])->default('PENDIENTE');
            $table->timestamps();
            $table->index(['id_conductor', 'estado']);
            $table->index(['id_vehiculo', 'estado']);


            $table->text('observaciones')->nullable();

            $table->index(['id_area', 'fecha_inicio', 'estado']); // "¿qué operaciones se realizaron en esta área en este día?"
        });

        Schema::create('actividad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_actividad', 255);
            $table->string('nombre_normalizado', 255)->nullable(); // nombre normalizado para búsquedas
            $table->integer('usos')->default(0); // contador de usos para determinar popularidad
            $table->timestamp('ultimo_uso')->nullable(); // fecha del último uso para determinar popularidad
            $table->foreignId('id_area')->constrained('area')->onDelete('restrict')->onUpdate('cascade');
            $table->string('unidad_medida', 30)->nullable(); // 'm3', 'viajes', 'horas', etc.
            $table->enum('estado_actividad', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');

            $table->timestamps();
        });


        Schema::create('actividad_realizada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_operacion_diaria')->constrained('operacion_diaria')->onDelete('cascade');
            $table->foreignId('id_actividad')->constrained('actividad')->onDelete('restrict');

            // Campos genéricos que cubren la mayoría de los casos
            $table->string('origen', 150)->nullable();       // 'Cancha de acopio'
            $table->string('destino', 150)->nullable();       // 'Chancado primario'
            $table->string('lugar',255)->nullable();          // 'Cancha de acopio', 'Chancado primario', 'Planta de procesos', etc.
            $table->decimal('cantidad', 10, 2)->nullable();    // 4 (viajes), 120 (m3), 8 (horas)...
            $table->string('unidad_medida', 30)->nullable();   // copia editable de la unidad, por si difiere del default
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();

            // Lo específico de cada equipo/actividad que no vale la pena modelar en columnas
            $table->index('id_operacion_diaria');
            $table->index('id_actividad');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacion_diaria');
        Schema::dropIfExists('actividad_realizada');
        Schema::dropIfExists('actividad');
    }
};
