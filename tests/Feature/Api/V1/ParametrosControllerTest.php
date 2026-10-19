<?php

namespace Tests\Feature\Api\V1;

use App\Models\Area;
use App\Models\Conductor;
use App\Models\Material;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
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
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
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

    /**
     * La app usa `vales` para prellenar y limitar el selector de fecha de
     * vencimiento al emitir un vale (mismo cálculo que el formulario web).
     */
    public function test_index_devuelve_la_sugerencia_de_vencimiento_de_vales(): void
    {
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 3],
            'estado' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.index'));

        $response->assertOk()
            ->assertJsonPath('vales.tiempo_expiracion', 3)
            ->assertJsonPath('vales.fecha_vencimiento_sugerida', today()->addDays(3)->format('Y-m-d'))
            ->assertJsonPath('vales.fecha_vencimiento_minima', today()->addDay()->format('Y-m-d'));
    }

    /**
     * Sin parámetros de empresa cargados se usa el plazo por defecto de
     * ValeConfig en vez de fallar.
     */
    public function test_index_sin_parametros_de_empresa_usa_el_plazo_por_defecto(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.index'));

        $response->assertOk()
            ->assertJsonPath('vales.tiempo_expiracion', 1)
            ->assertJsonPath('vales.fecha_vencimiento_sugerida', today()->addDay()->format('Y-m-d'));
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

    public function test_colecciones_incluye_los_tipos_de_mantenimiento_de_operacion_diaria(): void
    {
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Combustible cargado',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'cantidad',
            'unidad_medida' => 'L',
        ]);
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Nivel de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
        ]);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $tipos = $response->json('data.operaciones_diarias.tipos_mantenimiento');
        $this->assertCount(2, $tipos);
        $this->assertEqualsCanonicalizing(
            ['Combustible cargado', 'Nivel de aceite'],
            array_column($tipos, 'tipo_mantenimiento')
        );
        $this->assertSame('L', collect($tipos)->firstWhere('tipo_mantenimiento', 'Combustible cargado')['unidad_medida']);
    }

    public function test_colecciones_incluye_todos_los_operarios_activos_para_un_jefe_de_area(): void
    {
        $jefe = $this->crearUsuario();
        $jefe->assignRole('jefe-area');

        // Uno asignado a un vehículo, otro sin ninguna asignación y uno inactivo.
        $asignado = Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'ACTIVO']);
        $this->asignarVehiculoAConductor($asignado, Vehiculo::factory()->create());
        $sinAsignar = Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'ACTIVO']);
        $inactivo = Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'INACTIVO']);

        $response = $this->actingAs($jefe, 'api')->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'operaciones_diarias']));

        $response->assertOk();
        $ids = array_column($response->json('data.operaciones_diarias.operarios'), 'id');
        $this->assertEqualsCanonicalizing([$asignado->id, $sinAsignar->id], $ids);
        $this->assertNotContains($inactivo->id, $ids);
        $response->assertJsonStructure(['data' => ['operaciones_diarias' => ['operarios' => [['id', 'label', 'meta' => ['ci', 'nombre_completo']]]]]]);
    }

    public function test_colecciones_no_envia_operarios_a_un_conductor_puro(): void
    {
        Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'ACTIVO']);

        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')
            ->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'operaciones_diarias']));

        $response->assertOk();
        $response->assertJsonPath('data.operaciones_diarias.operarios', []);
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

    public function test_colecciones_incluye_los_conductores_asignados_de_cada_vehiculo(): void
    {
        $user = $this->crearUsuarioConductor();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($user->persona->conductor, $vehiculo);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['vehiculos' => [['conductores_asignados' => [['id', 'label', 'meta']]]]],
        ]);
        $this->assertSame(
            [$user->id_persona],
            $response->json('data.vehiculos.0.conductores_asignados.*.id')
        );
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

    public function test_colecciones_devuelve_todos_los_vehiculos_activos_para_un_administrador(): void
    {
        $user = $this->crearUsuario();
        $user->assignRole('administrador');

        $vehiculos = Vehiculo::factory()->count(3)->create();
        Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $ids = $response->json('data.vehiculos.*.id');
        sort($ids);
        $this->assertSame($vehiculos->pluck('id')->sort()->values()->all(), $ids);
    }

    public function test_colecciones_no_repite_vehiculos_para_un_administrador_que_tambien_es_conductor_y_jefe_de_area(): void
    {
        $user = $this->crearUsuarioConductor();
        $user->assignRole('administrador');
        $user->assignRole('jefe-area');

        $area = Area::factory()->create();
        $this->ponerPersonaACargoDeArea($user->persona, $area);

        $vehiculoPropio = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($user->persona->conductor, $vehiculoPropio);
        $this->ponerVehiculoEnArea($vehiculoPropio, $area);

        $vehiculoSuelto = Vehiculo::factory()->create();

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $ids = $response->json('data.vehiculos.*.id');
        sort($ids);
        $this->assertSame([$vehiculoPropio->id, $vehiculoSuelto->id], $ids);
    }

    public function test_colecciones_devuelve_vehiculos_vacios_para_un_usuario_sin_conductor_ni_area(): void
    {
        $user = $this->crearUsuario();

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertSame([], $response->json('data.vehiculos'));
    }

    public function test_colecciones_sin_incluir_devuelve_todas_las_secciones(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['vehiculos', 'estaciones_servicio', 'tipos_combustible', 'cargas_combustible', 'solicitudes_mantenimiento', 'ordenes_trabajo', 'operaciones_diarias', 'control_cargas'],
            array_keys($response->json('data'))
        );
    }

    public function test_colecciones_devuelve_solo_las_secciones_pedidas_en_incluir(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(
            route('api.v1.parametros.colecciones', ['incluir' => ['vehiculos', 'estaciones_servicio']])
        );

        $response->assertOk();
        $this->assertSame(['vehiculos', 'estaciones_servicio'], array_keys($response->json('data')));
    }

    public function test_colecciones_acepta_incluir_como_lista_separada_por_comas(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(
            route('api.v1.parametros.colecciones', ['incluir' => 'tipos_combustible, vehiculos'])
        );

        $response->assertOk();
        $this->assertSame(['tipos_combustible', 'vehiculos'], array_keys($response->json('data')));
    }

    public function test_colecciones_rechaza_una_seccion_desconocida_en_incluir(): void
    {
        $response = $this->actingAs($this->crearUsuarioConductor(), 'api')->getJson(
            route('api.v1.parametros.colecciones', ['incluir' => ['vehiculos', 'inexistente']])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('incluir.1');
    }

    /**
     * A pedido del usuario (2026-10-17), igual que en la web: un jefe de área
     * que también es conductor recibe TODOS los vehículos activos, con los
     * suyos (que conduce o de su área) primero y marcados con `propio`.
     */
    public function test_colecciones_un_jefe_de_area_con_rol_conductor_recibe_toda_la_flota_con_los_propios_primero(): void
    {
        $user = $this->crearUsuarioConductor();
        $user->assignRole('jefe-area');
        $area = Area::factory()->create();
        $this->ponerPersonaACargoDeArea($user->persona, $area);

        $ajeno = Vehiculo::factory()->create(['codigo' => 'AAA-001']);
        $delArea = Vehiculo::factory()->create(['codigo' => 'MMM-001']);
        $this->ponerVehiculoEnArea($delArea, $area);
        $queConduce = Vehiculo::factory()->create(['codigo' => 'ZZZ-001']);
        $this->asignarVehiculoAConductor($user->persona->conductor, $queConduce);
        Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'vehiculos']));

        $response->assertOk();
        $this->assertSame([$delArea->id, $queConduce->id, $ajeno->id], $response->json('data.vehiculos.*.id'));
        $this->assertSame([true, true, false], $response->json('data.vehiculos.*.propio'));
    }

    public function test_colecciones_un_conductor_puro_no_recibe_vehiculos_ajenos(): void
    {
        $user = $this->crearUsuarioConductor();
        $propio = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($user->persona->conductor, $propio);
        Vehiculo::factory()->count(2)->create();

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'vehiculos']));

        $response->assertOk();
        $this->assertSame([$propio->id], $response->json('data.vehiculos.*.id'));
        $this->assertTrue($response->json('data.vehiculos.0.propio'));
    }

    public function test_colecciones_envia_el_tope_por_jornada_segun_la_medicion_del_vehiculo(): void
    {
        $user = $this->crearUsuarioConductor();
        $conductor = Conductor::findOrFail($user->id_persona);

        $tipo = TipoVehiculo::factory()->create(['km_maximo_jornada' => 650, 'horometro_maximo_jornada' => 14]);
        $porKm = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje', 'id_tipo_vehiculo' => $tipo->id]);
        $porHoras = Vehiculo::factory()->create(['tipo_medicion' => 'horometro', 'id_tipo_vehiculo' => $tipo->id]);
        $this->asignarVehiculoAConductor($conductor, $porKm);
        $this->asignarVehiculoAConductor($conductor, $porHoras);

        $vehiculos = collect(
            $this->actingAs($user, 'api')
                ->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'vehiculos']))
                ->assertOk()
                ->json('data.vehiculos')
        )->keyBy('id');

        $this->assertEquals(650, $vehiculos[$porKm->id]['tope_jornada']);
        $this->assertEquals(14, $vehiculos[$porHoras->id]['tope_jornada']);
    }
}
