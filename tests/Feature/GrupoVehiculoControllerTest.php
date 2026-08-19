<?php

namespace Tests\Feature;

use App\Models\GrupoVehiculo;
use App\Models\TipoVehiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GrupoVehiculoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);
    }

    public function test_index_lista_los_grupos_con_su_cantidad_de_tipos_asignados(): void
    {
        $grupo = GrupoVehiculo::factory()->create();
        TipoVehiculo::factory()->create(['id_grupo_vehiculo' => $grupo->id]);

        $response = $this->get(route('grupos-vehiculo.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('GruposVehiculo/Index')
            ->where('grupos.data.0.tipos_vehiculos_count', 1)
        );
    }

    public function test_store_crea_un_grupo_de_vehiculo(): void
    {
        $response = $this->post(route('grupos-vehiculo.store'), [
            'grupo_vehiculo' => 'Vehículos Livianos',
            'estado_grupo_vehiculo' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('grupos-vehiculo.index'));
        $this->assertDatabaseHas('grupo_vehiculo', [
            'grupo_vehiculo' => 'Vehículos Livianos',
            'estado_grupo_vehiculo' => 'ACTIVO',
        ]);
    }

    public function test_store_rechaza_un_nombre_duplicado(): void
    {
        GrupoVehiculo::factory()->create(['grupo_vehiculo' => 'Maquinaria Pesada']);

        $response = $this->post(route('grupos-vehiculo.store'), [
            'grupo_vehiculo' => 'Maquinaria Pesada',
            'estado_grupo_vehiculo' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('grupo_vehiculo');
    }

    public function test_update_actualiza_el_grupo(): void
    {
        $grupo = GrupoVehiculo::factory()->create(['grupo_vehiculo' => 'Original']);

        $response = $this->put(route('grupos-vehiculo.update', $grupo->id), [
            'grupo_vehiculo' => 'Renombrado',
            'estado_grupo_vehiculo' => 'INACTIVO',
        ]);

        $response->assertRedirect(route('grupos-vehiculo.index'));
        $this->assertSame('Renombrado', $grupo->fresh()->grupo_vehiculo);
        $this->assertSame('INACTIVO', $grupo->fresh()->estado_grupo_vehiculo);
    }

    public function test_destroy_elimina_un_grupo_sin_tipos_asignados(): void
    {
        $grupo = GrupoVehiculo::factory()->create();

        $response = $this->delete(route('grupos-vehiculo.destroy', $grupo->id));

        $response->assertRedirect(route('grupos-vehiculo.index'));
        $this->assertDatabaseMissing('grupo_vehiculo', ['id' => $grupo->id]);
    }

    public function test_destroy_no_elimina_si_hay_tipos_de_vehiculo_asignados(): void
    {
        $grupo = GrupoVehiculo::factory()->create();
        TipoVehiculo::factory()->create(['id_grupo_vehiculo' => $grupo->id]);

        $response = $this->delete(route('grupos-vehiculo.destroy', $grupo->id));

        $response->assertRedirect(route('grupos-vehiculo.index'));
        $this->assertDatabaseHas('grupo_vehiculo', ['id' => $grupo->id]);
    }
}
