<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo de la persona (opcional: las personas ya registradas quedan sin
     * cargo hasta que se les asigne). Un cargo en uso no se puede eliminar
     * (restrict); se desactiva desde el módulo Cargos.
     */
    public function up(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->foreignId('id_cargo')
                ->nullable()
                ->after('estado_persona')
                ->constrained('cargo')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_cargo');
        });
    }
};
