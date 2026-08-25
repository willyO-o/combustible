<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\VehiculoExterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehiculoExternoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $conductor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->conductor = User::factory()->create();
        $this->conductor->assignRole('conductor');
    }

    public function test_index_lista_los_vehiculos_externos(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '148-JLK']);
        VehiculoExterno::factory()->create(['nro_placa' => '200-ABC']);

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.vehiculos-externos.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filtra_por_placa(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '148-JLK']);
        VehiculoExterno::factory()->create(['nro_placa' => '200-ABC']);

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.vehiculos-externos.index', ['nro_placa' => '148']));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('148-JLK', $response->json('data.0.nro_placa'));
    }

    public function test_store_registra_un_vehiculo_externo(): void
    {
        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.vehiculos-externos.store'), [
            'nro_placa' => '148-JLK',
            'propietario' => 'Transportes Andina',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehiculo_externo', ['nro_placa' => '148-JLK', 'propietario' => 'Transportes Andina']);
    }

    public function test_store_rechaza_una_placa_duplicada(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '148-JLK']);

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.vehiculos-externos.store'), [
            'nro_placa' => '148-JLK',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nro_placa']);
    }

    public function test_store_requiere_la_placa(): void
    {
        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.vehiculos-externos.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nro_placa']);
    }
}
