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
        Schema::create('area', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_area', 200);
            $table->text('descripcion_area')->nullable();
            $table->enum('estado_area', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('encargado_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_persona')->constrained('persona')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_area')->constrained('area')->onDelete('restrict')->onUpdate('cascade');
            $table->enum('tipo_encargo', ['TITULAR', 'SUPLENTE'])->default('TITULAR');
            $table->date('fecha_inicio');
            $table->date('fecha_reasignacion')->nullable();
            $table->date('fecha_fin')->nullable(); // NULL = vigente
            $table->string('motivo', 255)->nullable(); // 'vacaciones', 'permiso', 'nombramiento', etc.
            $table->enum('estado_encargo', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();

            $table->index(['id_area', 'estado_encargo']);     // "¿quién(es) son encargados de esta área ahora?"
            $table->index(['id_persona', 'estado_encargo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area');
        Schema::dropIfExists('encargado_area');
    }
};
