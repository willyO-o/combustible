<?php

namespace Tests\Feature\Api\V1;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
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

    public function test_index_lista_los_materiales(): void
    {
        Material::factory()->create(['material' => 'Concentrado']);
        Material::factory()->create(['material' => 'Broza']);

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.materiales.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filtra_por_nombre(): void
    {
        Material::factory()->create(['material' => 'Cola']);
        Material::factory()->create(['material' => 'Concentrado']);

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.materiales.index', ['material' => 'Con']));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Concentrado', $response->json('data.0.material'));
    }

    public function test_store_registra_un_material(): void
    {
        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.materiales.store'), [
            'material' => 'Broza',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('material', ['material' => 'Broza']);
    }

    public function test_store_rechaza_un_material_duplicado(): void
    {
        Material::factory()->create(['material' => 'Cola']);

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.materiales.store'), [
            'material' => 'Cola',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['material']);
    }

    public function test_store_requiere_el_nombre_del_material(): void
    {
        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.materiales.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['material']);
    }
}
