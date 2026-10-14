<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de cargos del personal (Técnico, Analista, Supervisor, ...).
     * Lista única para todas las personas: el cargo es independiente de los
     * roles del sistema (quien registra decide ambos por separado).
     */
    public function up(): void
    {
        Schema::create('cargo', function (Blueprint $table) {
            $table->id();
            $table->string('cargo', 150)->unique();
            $table->enum('estado_cargo', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });

        // Cargos iniciales para que el selector no arranque vacío; desde el
        // módulo Cargos se agregan, editan o desactivan los demás.
        $ahora = now();
        DB::table('cargo')->insertOrIgnore(
            collect(['Técnico', 'Analista', 'Supervisor'])
                ->map(fn (string $cargo) => [
                    'cargo' => $cargo,
                    'estado_cargo' => 'ACTIVO',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])
                ->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo');
    }
};
