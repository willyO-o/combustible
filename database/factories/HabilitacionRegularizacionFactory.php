<?php

namespace Database\Factories;

use App\Models\HabilitacionRegularizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabilitacionRegularizacion>
 */
class HabilitacionRegularizacionFactory extends Factory
{
    protected $model = HabilitacionRegularizacion::class;

    /**
     * Por defecto: vigente 24 h, para los últimos 3 días anteriores a ayer.
     */
    public function definition(): array
    {
        return [
            'id_usuario' => User::factory(),
            'fecha_desde' => now()->subDays(4)->toDateString(),
            'fecha_hasta' => now()->subDays(2)->toDateString(),
            'vigente_hasta' => now()->addDay(),
            'motivo' => fake()->sentence(),
            'id_usuario_otorga' => User::factory(),
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn () => ['vigente_hasta' => now()->subMinute()]);
    }

    public function revocada(): static
    {
        return $this->state(fn () => ['revocada_at' => now()->subMinute()]);
    }
}
