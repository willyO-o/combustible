<?php

namespace Tests\Feature\Api\V1;

use App\Models\Area;
use App\Models\Conductor;
use App\Models\Material;
use App\Models\Persona;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoExterno;
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
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
    }

    private function crearUsuario(): User
    {
        $persona = Persona::factory()->create();

        return User::factory()->create(['id_persona' => $persona->id]);
    }

    private function crearUsuarioConductor(): User
    {
        $user = $this->crearUsuario();
        Conductor::create(['id' => $user->id_persona, 'estado_conductor' => 'ACTIVO']);
        $user->assignRole('conductor');

        return $user;
    }

    private function asignarVehiculoAConductor(Conductor $conductor, Vehiculo $vehiculo): void
    {
        $conductor->asignacionesActivas()->attach($vehiculo->id, [
            'estado_asignacion' => 'ACTIVO',
            'fecha_asignacion' => now()->toDateString(),
        ]);
    }

    private function ponerVehiculoEnArea(Vehiculo $vehiculo, Area $area): void
    {
        $vehiculo->areas()->attach($area->id, [
            'estado_asignacion' => 'ACTIVO',
            'fecha_asignacion' => now()->toDateString(),
        ]);
    }

    private function ponerPersonaACargoDeArea(Persona $persona, Area $area): void
    {
        $persona->areas()->attach($area->id, [
            'tipo_encargo' => 'TITULAR',
            'estado_encargo' => 'ACTIVO',
            'fecha_inicio' => now()->toDateString(),
        ]);
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

    public function test_colecciones_solo_trae_tipos_de_mantenimiento_de_taller(): void
    {
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Nivel de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
        ]);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $tipos = $response->json('data.ordenes_trabajo.tipos_mantenimiento');
        $this->assertCount(1, $tipos);
        $this->assertSame('Cambio de aceite', $tipos[0]['tipo_mantenimiento']);
    }

    public function test_colecciones_incluye_los_vehiculos_externos(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '148-JLK']);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data.control_cargas.vehiculos_externos'));
        $this->assertSame('148-JLK', $response->json('data.control_cargas.vehiculos_externos.0.nro_placa'));
    }

    public function test_colecciones_devuelve_los_vehiculos_asignados_al_conductor(): void
    {
        $user = $this->crearUsuarioConductor();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($user->persona->conductor, $vehiculo);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertSame([$vehiculo->id], $response->json('data.vehiculos.*.id'));
    }

    public function test_colecciones_no_falla_para_un_jefe_de_area_sin_registro_de_conductor(): void
    {
        $user = $this->crearUsuario();
        $user->assignRole('jefe-area');

        $area = Area::factory()->create();
        $this->ponerPersonaACargoDeArea($user->persona, $area);

        $vehiculoArea = Vehiculo::factory()->create();
        $this->ponerVehiculoEnArea($vehiculoArea, $area);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertSame([$vehiculoArea->id], $response->json('data.vehiculos.*.id'));
    }

    public function test_colecciones_unifica_sin_duplicados_los_vehiculos_propios_y_los_del_area_a_cargo(): void
    {
        $user = $this->crearUsuarioConductor();
        $user->assignRole('jefe-area');

        $area = Area::factory()->create();
        $this->ponerPersonaACargoDeArea($user->persona, $area);

        // Vehículo que conduce y que además pertenece al área que administra:
        // debe aparecer una sola vez.
        $vehiculoCompartido = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($user->persona->conductor, $vehiculoCompartido);
        $this->ponerVehiculoEnArea($vehiculoCompartido, $area);

        // Otro vehículo del área que no conduce.
        $vehiculoSoloArea = Vehiculo::factory()->create();
        $this->ponerVehiculoEnArea($vehiculoSoloArea, $area);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $ids = $response->json('data.vehiculos.*.id');
        sort($ids);
        $this->assertSame([$vehiculoCompartido->id, $vehiculoSoloArea->id], $ids);
    }

    public function test_colecciones_devuelve_vehiculos_vacios_para_un_usuario_sin_conductor_ni_area(): void
    {
        $user = $this->crearUsuario();

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertSame([], $response->json('data.vehiculos'));
    }
}
