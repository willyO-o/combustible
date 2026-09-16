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
        Schema::table('vale', function (Blueprint $table) {
            // Marca cuándo se le avisó al conductor que el vale está por
            // vencer (ver NotificarValesPorVencerCommand), para no notificar
            // el mismo vale más de una vez en cada corrida diaria.
            $table->dateTime('notificado_vencimiento_at')->nullable()->after('fecha_vencimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vale', function (Blueprint $table) {
            $table->dropColumn('notificado_vencimiento_at');
        });
    }
};
