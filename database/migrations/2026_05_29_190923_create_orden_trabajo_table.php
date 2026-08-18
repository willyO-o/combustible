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
        Schema::create('orden_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_solicitud_mantenimiento')->nullable()
                ->constrained('solicitud_mantenimiento')->onDelete('cascade');
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_conductor')->nullable()->constrained('conductor')->onDelete('set null');
            $table->foreignId('id_taller')->nullable()->constrained('taller')->onDelete('set null');
            $table->foreignId('id_usuario_emite')->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_usuario_ejecuta')->constrained('users')->onDelete('restrict')->onUpdate('cascade');

            $table->integer('nro_orden');
            $table->string('gestion', 4);
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_ejecucion')->nullable();
            $table->dateTime('fecha_culminacion')->nullable();
            $table->text('nota_emisor')->nullable();
            $table->text('observacion')->nullable();
            $table->enum('tipo_mantenimiento', ['PREVENTIVO', 'CORRECTIVO'])->default('PREVENTIVO');

            $table->integer('kilometraje_actual')->nullable();
            $table->integer('horometro_actual')->nullable();


            $table->enum('estado_orden', ['PENDIENTE', 'EN_EJECUCION', 'CULMINADO', 'CANCELADO', 'VERIFICADO'])->default('PENDIENTE');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_trabajo');
    }
};
