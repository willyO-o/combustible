<?php

namespace Tests\Feature;

use App\Libraries\Reportes;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        // OperacionDiaria::getNroAttribute() lee digitos_serie de este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);

        Storage::fake('public');
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

    // vehiculosDisponibles() no filtra por área/conductor para roles que no
    // son "conductor". El técnico de mantenimiento ya NO tiene acceso a
    // operación diaria (se le quitó el permiso, ver UserSeederTest); el gating
    // es sólo de frontend (v-can), así que la ruta sigue respondiendo. Se
    // mantiene el caso para cubrir esa rama con un rol sin área a cargo.
    public function test_un_rol_sin_conductor_asignado_ve_todos_los_vehiculos_en_el_alta(): void
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

    private function crearTipoMantenimientoOperacion(string $nombre, string $tipoValor = 'cantidad', ?string $unidad = 'L'): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => $nombre,
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => $tipoValor,
            'unidad_medida' => $tipoValor === 'cantidad' ? $unidad : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadOperacionValida(Vehiculo $vehiculo, array $extra = []): array
    {
        return array_merge([
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
        ], $extra);
    }

    public function test_create_y_edit_exponen_el_catalogo_de_materiales(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        Material::factory()->create(['material' => 'Concentrado']);
        Material::factory()->create(['material' => 'Broza']);

        $this->actingAs($user)->get(route('operacion-diaria.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Operacion/Create')->has('materiales', 2));
    }

    public function test_store_guarda_el_material_trasladado_de_la_actividad(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $material = Material::factory()->create(['material' => 'Concentrado']);

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $payload);

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::firstOrFail();
        $this->assertDatabaseHas('actividad_realizada', [
            'id_operacion_diaria' => $operacion->id,
            'id_material' => $material->id,
        ]);
    }

    public function test_store_acepta_una_actividad_sin_material(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));

        $response->assertRedirect(route('operacion-diaria.index'));
        $this->assertDatabaseHas('actividad_realizada', [
            'id_operacion_diaria' => OperacionDiaria::firstOrFail()->id,
            'id_material' => null,
        ]);
    }

    public function test_store_rechaza_un_material_inexistente(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['id_material'] = 99999;

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $payload);

        $response->assertSessionHasErrors('actividades_realizadas.0.id_material');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_edit_precarga_el_material_de_cada_actividad(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $material = Material::factory()->create(['material' => 'Broza']);

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;
        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload);

        $operacion = OperacionDiaria::firstOrFail();

        $response = $this->actingAs($user)->get(route('operacion-diaria.edit', $operacion));

        $response->assertOk();
        $actividades = collect($response->viewData('page')['props']['operacion']['actividades_realizadas_edit']);
        $this->assertSame($material->id, $actividades->first()['id_material']);
    }

    public function test_genera_el_pdf_del_reporte_de_operacion(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $material = Material::factory()->create(['material' => 'Concentrado']);
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Aceite de motor',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'cantidad',
            'unidad_medida' => 'Litros',
        ]);

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;
        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload);

        $response = $this->actingAs($user)->get(route('operacion-diaria.reporte.pdf', OperacionDiaria::firstOrFail()));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_el_reporte_lista_los_tipos_de_mantenimiento_de_la_base_aunque_no_se_pasen(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Nivel de refrigerante',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
            'unidad_medida' => null,
        ]);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));

        $operacion = OperacionDiaria::firstOrFail()->load([
            'conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas', 'mantenimientosOperacion',
        ]);

        // Sin pasar el catálogo: el reporte lo consulta directo de la base.
        $pdf = (new Reportes)->generarReporteOperacionDiaria($operacion, null, 'S');

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_update_no_permite_cambiar_el_vehiculo_de_la_operacion(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $otroVehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $this->asignarVehiculoAConductor($otroVehiculo, $conductor);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));
        $operacion = OperacionDiaria::firstOrFail();

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['id_vehiculo'] = $otroVehiculo->id;

        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $payload)
            ->assertRedirect(route('operacion-diaria.index'));

        $this->assertSame($vehiculo->id, $operacion->fresh()->id_vehiculo);
    }

    public function test_show_expone_el_material_de_las_actividades(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $material = Material::factory()->create(['material' => 'Concentrado']);
        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;
        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload);

        $response = $this->actingAs($user)->get(route('operacion-diaria.show', OperacionDiaria::firstOrFail()));

        $response->assertOk();
        $actividades = collect($response->viewData('page')['props']['operacion']['actividades_realizadas']);
        $this->assertSame('Concentrado', $actividades->first()['pivot']['material']['material']);
    }

    public function test_show_expone_los_controles_de_mantenimiento_registrados(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $response = $this->actingAs($user)->get(route('operacion-diaria.show', OperacionDiaria::firstOrFail()));

        $response->assertOk();
        $controles = collect($response->viewData('page')['props']['operacion']['mantenimientos_operacion'])
            ->keyBy('tipo_mantenimiento');

        $this->assertSame('12.50', $controles['Combustible cargado']['pivot']['valor']);
        $this->assertSame('SI', $controles['Nivel de aceite']['pivot']['realizado']);
    }

    public function test_create_expone_solo_los_tipos_de_mantenimiento_de_operacion_diaria(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculo, $conductor);

        $opDiaria = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');
        $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        // De taller y/o inactivo: no deben aparecer.
        TipoMantenimiento::create(['tipo_mantenimiento' => 'Cambio de aceite', 'estado_tipo_mantenimiento' => 'ACTIVO', 'ambito' => 'taller']);
        TipoMantenimiento::create(['tipo_mantenimiento' => 'Inactivo', 'estado_tipo_mantenimiento' => 'INACTIVO', 'ambito' => 'operacion_diaria', 'tipo_valor' => 'booleano']);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['tiposMantenimiento'])->pluck('tipo_mantenimiento');
        $this->assertEqualsCanonicalizing(['Nivel de aceite', 'Combustible cargado'], $ids->all());
        $this->assertNotNull($opDiaria);
    }

    public function test_store_guarda_solo_los_controles_de_mantenimiento_cargados(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');
        $agua = $this->crearTipoMantenimientoOperacion('Nivel de agua', 'booleano');

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
                // Sin cargar nada: no debe registrarse (y no exige evidencia).
                ['id_tipo_mantenimiento' => $agua->id, 'valor' => null, 'realizado' => null],
            ],
        ]));

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::firstOrFail();
        $this->assertSame(2, $operacion->mantenimientosOperacion()->count());
        $this->assertDatabaseHas('mantenimiento_operacion_diaria', [
            'id_operacion_diaria' => $operacion->id,
            'id_tipo_mantenimiento' => $combustible->id,
            'valor' => 12.5,
            'realizado' => null,
        ]);
        $this->assertDatabaseHas('mantenimiento_operacion_diaria', [
            'id_operacion_diaria' => $operacion->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'realizado' => 'SI',
        ]);
        $this->assertDatabaseMissing('mantenimiento_operacion_diaria', [
            'id_operacion_diaria' => $operacion->id,
            'id_tipo_mantenimiento' => $agua->id,
        ]);
    }

    public function test_store_exige_evidencia_para_un_control_con_valor_cargado(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null],
            ],
        ]));

        $response->assertSessionHasErrors('mantenimientos.0.evidencia');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_store_exige_evidencia_para_un_control_booleano_marcado_si(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI'],
            ],
        ]));

        $response->assertSessionHasErrors('mantenimientos.0.evidencia');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    /**
     * Marcar "NO" (no se realizó el control) no exige evidencia — no hay
     * nada que fotografiar.
     */
    public function test_store_no_exige_evidencia_para_un_control_booleano_marcado_no(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'NO'],
            ],
        ]));

        $response->assertRedirect(route('operacion-diaria.index'));
        $this->assertDatabaseHas('mantenimiento_operacion_diaria', [
            'id_tipo_mantenimiento' => $aceite->id,
            'realizado' => 'NO',
            'evidencia' => null,
        ]);
    }

    public function test_edit_precarga_los_controles_de_mantenimiento_ya_guardados(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 20, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();

        $response = $this->actingAs($user)->get(route('operacion-diaria.edit', $operacion));

        $response->assertOk();
        $guardados = collect($response->viewData('page')['props']['operacion']['mantenimientos_edit']);
        $this->assertCount(1, $guardados);
        $this->assertSame($combustible->id, $guardados->first()['id_tipo_mantenimiento']);
        $this->assertSame('20.00', (string) $guardados->first()['valor']);
    }

    public function test_store_rechaza_un_control_de_mantenimiento_que_no_es_de_operacion_diaria(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $taller = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $taller->id, 'valor' => 5, 'realizado' => null],
            ],
        ]));

        $response->assertSessionHasErrors('mantenimientos.0.id_tipo_mantenimiento');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_update_reemplaza_los_controles_de_mantenimiento(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 10, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();

        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $this->assertSame(1, $operacion->mantenimientosOperacion()->count());
        $this->assertDatabaseMissing('mantenimiento_operacion_diaria', [
            'id_operacion_diaria' => $operacion->id,
            'id_tipo_mantenimiento' => $combustible->id,
        ]);
        $this->assertDatabaseHas('mantenimiento_operacion_diaria', [
            'id_operacion_diaria' => $operacion->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'realizado' => 'SI',
        ]);
    }

    public function test_store_guarda_la_evidencia_del_mantenimiento_convertida_a_webp(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg', 800, 600)],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $pivote = $operacion->mantenimientosOperacion()->first()->pivot;

        $this->assertNotNull($pivote->evidencia);
        $this->assertStringEndsWith('.webp', $pivote->evidencia);
        Storage::disk('public')->assertExists($pivote->evidencia);
    }

    public function test_update_conserva_la_evidencia_si_no_llega_un_archivo_nuevo(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $rutaOriginal = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        // Reenvía el mismo control sin archivo nuevo (edición típica: sólo se
        // toca otro campo de la operación).
        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI'],
            ],
        ]));

        $pivote = $operacion->mantenimientosOperacion()->first()->pivot;
        $this->assertSame($rutaOriginal, $pivote->evidencia);
        Storage::disk('public')->assertExists($rutaOriginal);
    }

    public function test_update_reemplaza_la_evidencia_y_borra_la_anterior(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $rutaOriginal = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite-nuevo.jpg')],
            ],
        ]));

        $rutaNueva = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;
        $this->assertNotSame($rutaOriginal, $rutaNueva);
        Storage::disk('public')->assertMissing($rutaOriginal);
        Storage::disk('public')->assertExists($rutaNueva);
    }

    /**
     * La evidencia es obligatoria mientras el control siga "activo"
     * (realizado = SI): no se puede quitar sólo la foto y dejar el control
     * marcado como hecho sin evidencia.
     */
    public function test_update_no_permite_eliminar_la_evidencia_de_un_control_marcado_si(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $rutaOriginal = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        $response = $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'eliminar_evidencia' => true],
            ],
        ]));

        $response->assertSessionHasErrors('mantenimientos.0.evidencia');
        $this->assertSame($rutaOriginal, $operacion->mantenimientosOperacion()->first()->pivot->evidencia);
        Storage::disk('public')->assertExists($rutaOriginal);
    }

    /**
     * `eliminar_evidencia` sí tiene efecto cuando además se destilda/vacía el
     * control por completo: al no quedar valor/realizado ni evidencia, el
     * control deja de registrarse (mismo criterio que cualquier control sin
     * datos) y su foto se borra del disco.
     */
    public function test_update_eliminar_evidencia_junto_con_destildar_el_control_lo_quita_por_completo(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $rutaOriginal = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        $response = $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => null, 'eliminar_evidencia' => true],
            ],
        ]));

        $response->assertRedirect(route('operacion-diaria.index'));
        $this->assertSame(0, $operacion->mantenimientosOperacion()->count());
        Storage::disk('public')->assertMissing($rutaOriginal);
    }

    public function test_destroy_borra_los_archivos_de_evidencia_de_mantenimiento(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $ruta = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        $this->actingAs($user)->delete(route('operacion-diaria.destroy', $operacion));

        Storage::disk('public')->assertMissing($ruta);
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
