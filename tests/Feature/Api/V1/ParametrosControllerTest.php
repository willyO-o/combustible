<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conductor;
use App\Models\Material;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParametrosControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
    }

    private function crearUsuarioConductor(): User
    {
        $persona = Persona::factory()->create();
        Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('conductor');

        return $user;
    }

    public function test_index_devuelve_los_parametros_generales(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.index'));

        $response->assertOk();
        $response->assertJsonStructure(['api_version', 'app_name', 'app_env', 'app_url', 'timezone', 'locale']);
    }

    public function test_colecciones_incluye_los_materiales_y_los_estados_de_carga(): void
    {
        Material::factory()->create(['material' => 'Concentrado']);
        Material::factory()->create(['material' => 'Broza']);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data.control_cargas.materiales'));
        $this->assertSame(
            ['ABIERTA', 'CERRADA', 'PAGADA'],
            $response->json('data.control_cargas.estados_carga')
        );
    }
}
