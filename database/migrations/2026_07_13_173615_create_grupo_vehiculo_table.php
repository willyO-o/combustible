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
        Schema::create('grupo_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_grupo', 150);
            $table->timestamps();
        });

        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->foreignId('id_grupo_vehiculo')->nullable()->constrained('grupo_vehiculo')->onDelete('RESTRICT')->onUpdate('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_vehiculo');
        Schema::table('tipo_vehiculo', function (Blueprint $table) {
            $table->dropForeign(['id_grupo_vehiculo']);
            $table->dropColumn('id_grupo_vehiculo');
        });
    }
};
