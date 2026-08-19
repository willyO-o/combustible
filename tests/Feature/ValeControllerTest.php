<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ValeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        // Vale::boot() calcula fecha_vencimiento a partir de este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearJefeDeArea(Area $area): User
    {
        $persona = Persona::factory()->create();
        EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('jefe-area');

        return $user;
    }

    private function asignarVehiculoAArea(Vehiculo $vehiculo, Area $area): void
    {
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_search_vehiculos_restringe_a_un_jefe_de_area_a_los_vehiculos_de_su_area(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculoDeSuArea = Vehiculo::factory()->create(['codigo' => 'DEL-AREA']);
        $this->asignarVehiculoAArea($vehiculoDeSuArea, $area);

        $vehiculoDeOtraArea = Vehiculo::factory()->create(['codigo' => 'OTRA-AREA']);
        $this->asignarVehiculoAArea($vehiculoDeOtraArea, Area::factory()->create());

        $vehiculoSinArea = Vehiculo::factory()->create(['codigo' => 'SIN-AREA']);

        $response = $this->actingAs($jefe)->getJson(route('search.vehiculos', ['q' => '']));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($vehiculoDeSuArea->id));
        $this->assertFalse($ids->contains($vehiculoDeOtraArea->id));
        $this->assertFalse($ids->contains($vehiculoSinArea->id));
    }

    public function test_search_vehiculos_no_restringe_a_un_administrador(): void
    {
        $area = Area::factory()->create();
        $vehiculoDeArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDeArea, $area);
        $vehiculoSinArea = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->getJson(route('search.vehiculos', ['q' => '']));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($vehiculoDeArea->id));
        $this->assertTrue($ids->contains($vehiculoSinArea->id));
    }

    public function test_store_rechaza_un_vehiculo_fuera_del_area_del_jefe(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculoDeOtraArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDeOtraArea, Area::factory()->create());

        $conductor = Conductor::factory()->create();
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        $response = $this->actingAs($jefe)->post(route('vales.store'), [
            'id_vehiculo' => $vehiculoDeOtraArea->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertSessionHasErrors('id_vehiculo');
        $this->assertDatabaseMissing('vale', ['id_vehiculo' => $vehiculoDeOtraArea->id]);
    }

    public function test_store_permite_un_vehiculo_del_area_del_jefe(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);

        $conductor = Conductor::factory()->create();
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        $response = $this->actingAs($jefe)->post(route('vales.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertRedirect(route('vales.index'));
        $this->assertDatabaseHas('vale', ['id_vehiculo' => $vehiculo->id, 'id_conductor' => $conductor->id]);
    }

    public function test_store_permite_a_un_administrador_cualquier_vehiculo(): void
    {
        $vehiculoSinArea = Vehiculo::factory()->create();
        $conductor = Conductor::factory()->create();
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('vales.store'), [
            'id_vehiculo' => $vehiculoSinArea->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertRedirect(route('vales.index'));
        $this->assertDatabaseHas('vale', ['id_vehiculo' => $vehiculoSinArea->id]);
    }

    public function test_update_no_reevalua_la_restriccion_de_area(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);
        $conductor = Conductor::factory()->create();
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        $this->actingAs($jefe);
        $vale = Vale::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'litros' => 20,
            'precio' => 6.97,
            'estado_vale' => 'PENDIENTE',
        ]);

        // El vehículo deja de estar asignado al área del jefe (simula un
        // préstamo a otra área después de emitido el vale).
        VehiculoArea::where('id_vehiculo', $vehiculo->id)->update(['estado_asignacion' => 'REASIGNADO']);

        $response = $this->actingAs($jefe)->put(route('vales.update', $vale->id), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'litros' => 25,
            'precio' => 7.00,
        ]);

        $response->assertRedirect(route('vales.index'));
        $this->assertEquals(25, $vale->fresh()->litros);
    }
}
