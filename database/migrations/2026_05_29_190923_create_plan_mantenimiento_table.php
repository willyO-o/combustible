<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla central del flujo de mantenimiento (Pasos 2 y 3):
     *
     * Paso 2 – JEFE DE TRANSPORTES: Verifica la solicitud y genera una
     *          orden de trabajo (interna o externa) con descripción del
     *          trabajo a realizar.
     *
     * Paso 3 – JEFE DE TRANSPORTES: Una vez ejecutado el mantenimiento,
     *          registra el trabajo realizado y todos los insumos utilizados
     *          (repuestos, aceites, llantas, etc.) en la tabla
     *          mantenimiento_repuesto.
     */
    public function up(): void
    {
        Schema::create('plan_mantenimiento', function (Blueprint $table) {
            $table->id();

            // ── Referencias principales ───────────────────────────────────────
            $table->foreignId('id_vehiculo')
                ->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('id_tipo_mantenimiento')
                ->constrained('tipo_mantenimiento')->onDelete('restrict');

            // Solicitud de origen (Paso 1 – generada por el chofer, opcional)
            $table->foreignId('id_solicitud_mantenimiento')
                ->nullable()
                ->constrained('solicitud_mantenimiento')->onDelete('set null');

            // Taller externo (sólo cuando tipo_orden = EXTERNO)
            $table->foreignId('id_taller')
                ->nullable()
                ->constrained('taller')->onDelete('set null');

            // ── Datos de la orden de trabajo (Paso 2) ────────────────────────
            $table->foreignId('id_usuario_jefe')
                ->nullable()
                ->constrained('users')->onDelete('set null');

            $table->enum('tipo_mantenimiento', ['PREVENTIVO', 'CORRECTIVO'])
                ->default('PREVENTIVO');
            $table->enum('tipo_orden', ['INTERNO', 'EXTERNO'])
                ->default('INTERNO');

            $table->text('descripcion_trabajo_ordenado')->nullable();
            $table->date('fecha_orden')->nullable();

            // Programación / planificación
            $table->integer('kilometraje_programado')->nullable();
            $table->date('fecha_programada')->nullable();
            $table->integer('frecuencia_km')->nullable();
            $table->integer('frecuencia_mes')->nullable();

            // ── Registro de ejecución (Paso 3) ───────────────────────────────
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->integer('kilometraje_al_mantenimiento')->nullable();
            $table->text('trabajo_realizado')->nullable();
            $table->decimal('costo_mano_obra', 10, 2)->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();

            $table->foreignId('id_usuario_ejecuta')
                ->nullable()
                ->constrained('users')->onDelete('set null');

            // ── Estado del ciclo de vida ──────────────────────────────────────
            $table->enum('estado_plan', [
                'BORRADOR',     // Orden creada, pendiente de aprobación
                'PENDIENTE',    // Orden aprobada, esperando ejecución
                'EN_PROCESO',   // Mantenimiento en curso
                'COMPLETADO',   // Mantenimiento finalizado y registrado
                'VENCIDO',      // No se ejecutó en la fecha programada
                'ANULADO',      // Cancelado
            ])->default('BORRADOR');

            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_mantenimiento');
    }
};
