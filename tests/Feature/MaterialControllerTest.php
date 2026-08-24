<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);
    }

    public function test_index_lista_los_materiales(): void
    {
        Material::factory()->create(['material' => 'Arena']);

        $response = $this->get(route('materiales.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Materiales/Index')
            ->where('materiales.data.0.material', 'Arena')
        );
    }

    public function test_store_crea_un_material(): void
    {
        $response = $this->post(route('materiales.store'), [
            'material' => 'Grava',
        ]);

        $response->assertRedirect(route('materiales.index'));
        $this->assertDatabaseHas('material', ['material' => 'Grava']);
    }

    public function test_store_por_ajax_responde_en_json_con_el_material_creado(): void
    {
        // El componente reutilizable MaterialFormModal usa axios (AJAX), no
        // Inertia: al invocarse desde otro formulario no debe redirigir.
        $response = $this->postJson(route('materiales.store'), [
            'material' => 'Cemento',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.material', 'Cemento');

        $this->assertDatabaseHas('material', ['material' => 'Cemento']);
    }

    public function test_store_rechaza_un_material_duplicado(): void
    {
        Material::factory()->create(['material' => 'Arena']);

        $response = $this->post(route('materiales.store'), [
            'material' => 'Arena',
        ]);

        $response->assertSessionHasErrors('material');
    }

    public function test_store_requiere_el_nombre_del_material(): void
    {
        $response = $this->post(route('materiales.store'), []);

        $response->assertSessionHasErrors('material');
    }

    public function test_update_actualiza_el_material(): void
    {
        $material = Material::factory()->create(['material' => 'Original']);

        $response = $this->put(route('materiales.update', $material->id), [
            'material' => 'Renombrado',
        ]);

        $response->assertRedirect(route('materiales.index'));
        $this->assertSame('Renombrado', $material->fresh()->material);
    }

    public function test_update_permite_conservar_el_mismo_nombre(): void
    {
        $material = Material::factory()->create(['material' => 'Arena']);

        $response = $this->put(route('materiales.update', $material->id), [
            'material' => 'Arena',
        ]);

        $response->assertRedirect(route('materiales.index'));
    }

    public function test_destroy_elimina_un_material_sin_cargas_asociadas(): void
    {
        $material = Material::factory()->create();

        $response = $this->delete(route('materiales.destroy', $material->id));

        $response->assertRedirect(route('materiales.index'));
        $this->assertDatabaseMissing('material', ['id' => $material->id]);
    }

    public function test_destroy_no_elimina_si_tiene_viajes_registrados(): void
    {
        $material = Material::factory()->create();

        $idCargaMaterial = DB::table('carga_material')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'id_vehiculo_externo' => DB::table('vehiculo_externo')->insertGetId(['nro_placa' => 'ABC-123']),
            'id_usuario_apertura' => $this->admin->id,
            'fecha_apertura' => now(),
            'estado_carga' => 'ABIERTA',
        ]);

        DB::table('viaje')->insert([
            'id_carga_material' => $idCargaMaterial,
            'id_material' => $material->id,
            'id_usuario_registro' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete(route('materiales.destroy', $material->id));

        $response->assertRedirect(route('materiales.index'));
        $this->assertDatabaseHas('material', ['id' => $material->id]);
    }
}
