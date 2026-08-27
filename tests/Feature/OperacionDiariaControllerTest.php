<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionDiariaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);
    }

    private function crearConductorConUsuario(): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('conductor');

        return [$user, $conductor];
    }

    private function crearJefeAreaConUsuario(Area $area): User
    {
        $persona = Persona::factory()->create();
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('jefe-area');

        $area->encargados()->attach($persona->id, [
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        return $user;
    }

    private function asignarVehiculoAConductor(Vehiculo $vehiculo, Conductor $conductor): void
    {
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    private function asignarVehiculoAArea(Vehiculo $vehiculo, Area $area): void
    {
        $area->vehiculos()->attach($vehiculo->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_un_conductor_solo_ve_sus_propios_vehiculos_asignados(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();

        $vehiculoPropio = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculoPropio, $conductor);

        // Otro vehículo, sin ninguna asignación al conductor: no debe aparecer.
        Vehiculo::factory()->create();

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->has('vehiculosAsignados', 1)
            ->where('vehiculosAsignados.0.id', $vehiculoPropio->id)
        );
    }

    public function test_un_jefe_de_area_solo_ve_los_vehiculos_de_su_area(): void
    {
        $area = Area::factory()->create();
        $otraArea = Area::factory()->create();
        $user = $this->crearJefeAreaConUsuario($area);

        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);

        $vehiculoDeOtraArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDeOtraArea, $otraArea);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->has('vehiculosAsignados', 1)
            ->where('vehiculosAsignados.0.id', $vehiculoDelArea->id)
        );
    }

    public function test_un_usuario_con_ambos_roles_ve_la_union_sin_duplicar(): void
    {
        $area = Area::factory()->create();
        [$user, $conductor] = $this->crearConductorConUsuario();
        $user->assignRole('jefe-area');

        $persona = $user->persona;
        $area->encargados()->attach($persona->id, [
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $vehiculoPropio = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculoPropio, $conductor);

        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);

        // Asignado al conductor Y al área a la vez: no debe duplicarse.
        $vehiculoCompartido = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculoCompartido, $conductor);
        $this->asignarVehiculoAArea($vehiculoCompartido, $area);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->has('vehiculosAsignados', 3)
        );
    }

    /**
     * El listado sin fecha_desde/fecha_hasta en el request debe llegar ya
     * filtrado por "Este mes" desde el servidor (1º del mes actual -> hoy):
     * evita que el frontend tenga que disparar una segunda petición para
     * aplicar el rango por defecto de DateRangeFilter.vue.
     */
    public function test_index_filtra_por_defecto_el_mes_actual(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();
        [, $conductor] = $this->crearConductorConUsuario();

        $operacionDeEsteMes = OperacionDiaria::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_area' => $area->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4),
            'fecha_fin' => now(),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'estado' => 'PENDIENTE',
        ]);

        $operacionDelMesPasado = OperacionDiaria::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_area' => $area->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subMonth()->subHours(4),
            'fecha_fin' => now()->subMonth(),
            'kilometraje_inicio' => 900,
            'kilometraje_fin' => 950,
            'estado' => 'PENDIENTE',
        ]);

        $response = $this->actingAs($admin)->get(route('operacion-diaria.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.fecha_desde', now()->startOfMonth()->format('Y-m-d'))
                ->where('filters.fecha_hasta', now()->format('Y-m-d'))
            );

        $ids = collect($response->original->getData()['page']['props']['actividades']['data'])->pluck('id');
        $this->assertTrue($ids->contains($operacionDeEsteMes->id));
        $this->assertFalse($ids->contains($operacionDelMesPasado->id));

        // Limpiar el filtro (fecha_desde/fecha_hasta explícitos, no ausentes)
        // debe mostrar de nuevo la operación del mes pasado.
        $response = $this->actingAs($admin)
            ->get(route('operacion-diaria.index', ['fecha_desde' => '', 'fecha_hasta' => '']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.fecha_desde', null)
                ->where('filters.fecha_hasta', null)
            );

        $ids = collect($response->original->getData()['page']['props']['actividades']['data'])->pluck('id');
        $this->assertTrue($ids->contains($operacionDeEsteMes->id));
        $this->assertTrue($ids->contains($operacionDelMesPasado->id));
    }

    public function test_un_administrador_ve_todos_los_vehiculos_activos_sin_filtro(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        Vehiculo::factory()->count(3)->create(['estado_vehiculo' => 'ACTIVO']);
        Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);

        $response = $this->actingAs($admin)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->has('vehiculosAsignados', 3)
        );
    }

    public function test_un_tecnico_de_mantenimiento_tambien_puede_registrar_y_ve_todos_los_vehiculos(): void
    {
        $tecnico = User::factory()->create();
        $tecnico->assignRole('tecnico-mantenimiento');

        Vehiculo::factory()->count(2)->create(['estado_vehiculo' => 'ACTIVO']);

        $response = $this->actingAs($tecnico)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('vehiculosAsignados', 2)
        );
    }

    public function test_store_registra_la_operacion_con_el_conductor_asignado_al_vehiculo_no_con_el_usuario_autenticado(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $conductorDelVehiculo] = $this->crearConductorConUsuario();

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductorDelVehiculo);
        $this->asignarVehiculoAArea($vehiculo, $area);

        // El jefe de área registra la operación de un vehículo que él NO
        // conduce: como no tiene el rol conductor, el formulario le muestra
        // el combo y debe indicar explícitamente el conductor.
        $response = $this->actingAs($jefeArea)->post(route('operacion-diaria.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorDelVehiculo->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'observaciones' => null,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [
                [
                    'actividad' => 'Transporte de material',
                    'lugar' => null,
                    'origen' => 'Cantera',
                    'destino' => 'Planta',
                    'cantidad' => 2,
                    'unidad_medida' => 'viajes',
                    'hora_inicio' => '08:00',
                    'hora_fin' => '10:00',
                ],
            ],
        ]);

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::first();
        $this->assertNotNull($operacion);
        $this->assertSame($conductorDelVehiculo->id, $operacion->id_conductor);
        $this->assertSame($area->id, $operacion->id_area);
    }

    public function test_store_falla_si_el_vehiculo_no_tiene_conductor_asignado(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($vehiculo, $area);
        // Sin ninguna asignación a un conductor.

        // Un conductor real mero (pasa la validación exists:conductor,id)
        // pero no asignado a este vehículo: la regla de negocio debe fallar
        // igual, ya que el vehículo no tiene ningún conductor asignado.
        [, $conductorSinAsignar] = $this->crearConductorConUsuario();

        $response = $this->actingAs($jefeArea)->post(route('operacion-diaria.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorSinAsignar->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [[
                'actividad' => 'Transporte de material',
                'origen' => 'Cantera',
                'destino' => 'Planta',
                'cantidad' => 1,
                'unidad_medida' => 'viajes',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_store_falla_si_el_conductor_elegido_no_esta_asignado_al_vehiculo(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $conductorDelVehiculo] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductorDelVehiculo);
        $this->asignarVehiculoAArea($vehiculo, $area);

        // Otro conductor cualquiera, no asignado a este vehículo.
        [, $conductorAjeno] = $this->crearConductorConUsuario();

        $response = $this->actingAs($jefeArea)->post(route('operacion-diaria.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorAjeno->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [[
                'actividad' => 'Transporte de material',
                'origen' => 'Cantera',
                'destino' => 'Planta',
                'cantidad' => 1,
                'unidad_medida' => 'viajes',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_store_de_un_conductor_no_requiere_elegir_conductor_y_se_autoasigna(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        // El propio conductor no envía id_conductor: el formulario no le
        // muestra el combo (mostrarSelectorConductor = false para su rol).
        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), [
            'id_vehiculo' => $vehiculo->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [[
                'actividad' => 'Transporte de material',
                'lugar' => null,
                'origen' => 'Cantera',
                'destino' => 'Planta',
                'cantidad' => 1,
                'unidad_medida' => 'viajes',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
            ]],
        ]);

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::first();
        $this->assertNotNull($operacion);
        $this->assertSame($conductor->id, $operacion->id_conductor);
    }

    public function test_un_conductor_no_recibe_el_selector_de_conductor_ni_la_lista_de_asignados(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();

        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculo, $conductor);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', false)
            ->where('vehiculosAsignados.0.meta.conductoresAsignados', [])
        );
    }

    public function test_un_jefe_de_area_recibe_el_selector_de_conductor_con_los_asignados_al_vehiculo(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $conductorDelVehiculo] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculo, $conductorDelVehiculo);
        $this->asignarVehiculoAArea($vehiculo, $area);

        $response = $this->actingAs($jefeArea)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculosAsignados.0.meta.conductoresAsignados', 1)
            ->where('vehiculosAsignados.0.meta.conductoresAsignados.0.id', $conductorDelVehiculo->id)
        );
    }
}
