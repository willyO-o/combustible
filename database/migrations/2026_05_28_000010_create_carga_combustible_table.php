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
        Schema::create('carga_combustible', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_carga');
            $table->decimal('litros', 10, 2);
            $table->decimal('precio', 10, 2);
            $table->decimal('kilometraje', 12, 2)->nullable();
            $table->decimal('horometro', 12, 2)->nullable();
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_grifo')->constrained('grifo')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_tipo_combustible')->constrained('tipo_combustible')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_vale')->nullable()->constrained('vale')->onDelete('restrict')->onUpdate('cascade');
            $table->string('nro_factura', 50)->nullable();
            $table->enum('tipo_carga', ['VALE', 'PREPAGO'])->default('VALE');
            $table->string('estado_carga', 30)->nullable();
            $table->integer('id_usuario')->nullable();
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carga_combustible');
    }
};
