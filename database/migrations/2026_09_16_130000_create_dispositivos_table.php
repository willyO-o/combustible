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
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            // El token FCM es único a nivel global (no por usuario): si el mismo
            // dispositivo se reasigna a otra cuenta (logout + login de otro
            // usuario), updateOrCreate() por token reasigna el registro en vez
            // de duplicarlo, evitando que le sigan llegando push al usuario anterior.
            $table->string('token')->unique();
            $table->enum('plataforma', ['android', 'ios', 'web']);
            $table->timestamp('ultima_actividad')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
