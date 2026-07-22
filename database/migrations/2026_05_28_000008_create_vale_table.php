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
        Schema::create('vale', function (Blueprint $table) {
            $table->id();
            $table->integer('nro_vale')->unique();
            $table->date('fecha_emision');
            $table->decimal('litros', 10, 2);
            $table->decimal('precio', 10, 2);
            $table->foreignId('id_vehiculo')->constrained('vehiculo')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_conductor')->constrained('conductor')->onDelete('restrict')->onUpdate('cascade');
            $table->foreignId('id_grifo')->constrained('grifo')->onDelete('restrict')->onUpdate('cascade');
            $table->enum('estado_vale', ['PENDIENTE', 'USADO', 'ANULADO'])->default('PENDIENTE');
            $table->foreignId('id_user')->nullable()->constrained('users')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vale');
    }
};
