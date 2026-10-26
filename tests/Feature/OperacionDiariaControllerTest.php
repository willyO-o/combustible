<?php

namespace Tests\Feature;

use App\Libraries\Reportes;
use App\Models\ActividadRealizada;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\MantenimientoOperacionDiaria;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

class OperacionDiariaControllerTest extends TestCase
{
    use ConPermisosReales;
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

    public function test_un_conductor_puro_ve_solo_sus_vehiculos_asignados_y_activos(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();

        $vehiculoPropio = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje', 'codigo' => 'ZZZ-999']);
        $this->asignarVehiculoAConductor($vehiculoPropio, $conductor);

        // Asignado pero dado de baja: no se ofrece.
        $retiradoAsignado = Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);
        $this->asignarVehiculoAConductor($retiradoAsignado, $conductor);

        // Otro vehículo activo sin asignación al conductor: tampoco aparece.
        $otro = Vehiculo::factory()->create(['codigo' => 'AAA-001']);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->where('mostrarSelectorConductor', false)
            ->has('vehiculosAsignados', 1)
            ->where('vehiculosAsignados.0.id', $vehiculoPropio->id)
            ->where('vehiculosAsignados', fn ($v) => ! collect($v)->pluck('id')->contains($otro->id))
        );
    }

    public function test_un_jefe_de_area_ve_todos_los_vehiculos_con_los_de_su_area_marcados(): void
    {
        $area = Area::factory()->create();
        $otraArea = Area::factory()->create();
        $user = $this->crearJefeAreaConUsuario($area);

        $vehiculoDelArea = Vehiculo::factory()->create(['codigo' => 'ZZZ-999']);
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);

        $vehiculoDeOtraArea = Vehiculo::factory()->create(['codigo' => 'AAA-001']);
        $this->asignarVehiculoAArea($vehiculoDeOtraArea, $otraArea);

        $sinArea = Vehiculo::factory()->create(['codigo' => 'BBB-002']);

        $response = $this->actingAs($user)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Operacion/Create')
            ->has('vehiculosAsignados', 3)
            ->where('vehiculosAsignados.0.id', $vehiculoDelArea->id)
            ->where('vehiculosAsignados.0.meta.propio', true)
            ->where('vehiculosAsignados.1.id', $vehiculoDeOtraArea->id)
            ->where('vehiculosAsignados.1.meta.sin_area', false)
            ->where('vehiculosAsignados.2.id', $sinArea->id)
            ->where('vehiculosAsignados.2.meta.sin_area', true)
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

    /**
     * Un usuario con los 3 roles (administrador, jefe-area, conductor) ve
     * todas las operaciones y todos los conductores en el filtro: el rol
     * administrador prevalece (ver ListOperacionesDiariasAction).
     */
    public function test_index_un_usuario_con_los_tres_roles_ve_todas_las_operaciones(): void
    {
        [$user] = $this->crearConductorConUsuario();
        $user->assignRole(['administrador', 'jefe-area']);

        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);
        [, $otroConductor] = $this->crearConductorConUsuario();
        $this->asignarVehiculoAConductor($vehiculo, $otroConductor);

        $operacionDeOtro = OperacionDiaria::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
            'id_area' => $area->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4),
            'fecha_fin' => now(),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'estado' => 'PENDIENTE',
        ]);

        $response = $this->actingAs($user)->get(route('operacion-diaria.index'));

        $props = $response->original->getData()['page']['props'];
        $this->assertTrue(collect($props['actividades']['data'])->pluck('id')->contains($operacionDeOtro->id));
        $this->assertTrue(collect($props['conductores'])->pluck('id')->contains($otroConductor->id));
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
    // son "conductor" ni "jefe-area". La ruta exige operacion-diaria.crear
    // (routes/web.php; el técnico de mantenimiento ya no lo tiene, ver
    // PermisosRutasWebTest), así que la rama se cubre con un rol creado desde
    // el módulo de Roles que sí lo tiene y no tiene área a cargo.
    public function test_un_rol_sin_conductor_asignado_ve_todos_los_vehiculos_en_el_alta(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(
            Role::create(['name' => 'supervisor', 'guard_name' => 'web'])->givePermissionTo('operacion-diaria.crear')
        );

        Vehiculo::factory()->count(2)->create(['estado_vehiculo' => 'ACTIVO']);

        $response = $this->actingAs($supervisor)->get(route('operacion-diaria.create'));

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
                ],
            ],
        ]);

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::first();
        $this->assertNotNull($operacion);
        $this->assertSame($conductorDelVehiculo->id, $operacion->id_conductor);
        $this->assertSame($area->id, $operacion->id_area);
    }

    public function test_store_permite_un_operario_activo_aunque_el_vehiculo_no_tenga_conductor_asignado(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($vehiculo, $area);
        // Sin ninguna asignación a un conductor.

        // La asignación conductor-vehículo todavía no es confiable: cualquier
        // operario activo vale aunque el vehículo no tenga ninguno asignado.
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
            ]],
        ]);

        $response->assertRedirect(route('operacion-diaria.index'));
        $this->assertSame($conductorSinAsignar->id, OperacionDiaria::firstOrFail()->id_conductor);
    }

    public function test_store_permite_un_operario_no_asignado_al_vehiculo_pero_no_uno_inactivo(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $conductorDelVehiculo] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductorDelVehiculo);
        $this->asignarVehiculoAArea($vehiculo, $area);

        // Otro conductor cualquiera, no asignado a este vehículo.
        [, $conductorAjeno] = $this->crearConductorConUsuario();
        [, $conductorInactivo] = $this->crearConductorConUsuario();
        $conductorInactivo->update(['estado_conductor' => 'INACTIVO']);

        $this->actingAs($jefeArea)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $conductorInactivo->id])
        )->assertSessionHasErrors('id_conductor');
        $this->assertDatabaseCount('operacion_diaria', 0);

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
            ]],
        ]);

        $response->assertRedirect(route('operacion-diaria.index'));
        $this->assertSame($conductorAjeno->id, OperacionDiaria::firstOrFail()->id_conductor);
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
            ->where('operarios', [])
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

    /**
     * owen-it/laravel-auditing no audita nada cuando la app corre en consola
     * (config/audit.php -> 'console'), así que este bug sólo se reproduce
     * fuera de una prueba "normal" — de ahí el config(['audit.console' =>
     * true]) explícito. ActividadRealizada y MantenimientoOperacionDiaria
     * extienden Pivot, que por defecto trae $incrementing = false; como
     * ambas tablas tienen su propia columna `id` autoincremental, sin ese
     * override Eloquent nunca recupera el id tras el insert y el
     * auditable_id queda null, violando el NOT NULL de `audits` — el 500
     * que se veía al crear una operación diaria desde la web.
     */
    public function test_store_audita_actividades_y_mantenimientos_sin_romper_por_auditable_id_nulo(): void
    {
        config(['audit.console' => true]);

        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');

        $response = $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
            ],
        ]));

        $response->assertRedirect(route('operacion-diaria.index'));
        $response->assertSessionDoesntHaveErrors();

        $operacion = OperacionDiaria::firstOrFail();
        $actividadRealizada = $operacion->actividadesRealizadas()->firstOrFail()->pivot;
        $mantenimientoOperacion = $operacion->mantenimientosOperacion()->firstOrFail()->pivot;

        $this->assertDatabaseHas('audits', [
            'auditable_type' => ActividadRealizada::class,
            'auditable_id' => $actividadRealizada->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => MantenimientoOperacionDiaria::class,
            'auditable_id' => $mantenimientoOperacion->id,
            'event' => 'created',
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

    public function test_store_registra_las_actividades_sin_horas(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo))
            ->assertRedirect(route('operacion-diaria.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('actividad_realizada', [
            'id_operacion_diaria' => OperacionDiaria::firstOrFail()->id,
            'hora_inicio' => null,
            'hora_fin' => null,
        ]);
    }

    public function test_store_ignora_las_horas_si_un_cliente_antiguo_las_envia(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $payload = $this->payloadOperacionValida($vehiculo);
        $payload['actividades_realizadas'][0]['hora_inicio'] = '08:00';
        $payload['actividades_realizadas'][0]['hora_fin'] = 'no-es-hora';

        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload)
            ->assertRedirect(route('operacion-diaria.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('actividad_realizada', [
            'id_operacion_diaria' => OperacionDiaria::firstOrFail()->id,
            'hora_inicio' => null,
            'hora_fin' => null,
        ]);
    }

    public function test_update_conserva_las_horas_historicas_de_las_actividades_ya_guardadas(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));
        $operacion = OperacionDiaria::firstOrFail();

        // Operación registrada antes del cambio: tenía horas capturadas.
        ActividadRealizada::query()->update(['hora_inicio' => '07:15:00', 'hora_fin' => '11:00:00']);

        // El formulario de edición reenvía lo precargado (incluye el id del
        // registro) y agrega una actividad nueva, sin horas.
        $actividadesEdit = $operacion->actividadesRealizadasEdit()->all();
        $actividadesEdit[0]['cantidad'] = 3;
        $actividadesEdit[] = [
            'actividad' => 'Retorno a base',
            'origen' => 'Planta',
            'destino' => 'Base',
            'cantidad' => 1,
            'unidad_medida' => 'viajes',
        ];

        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'actividades_realizadas' => $actividadesEdit,
        ]))->assertRedirect(route('operacion-diaria.index'));

        $filas = ActividadRealizada::where('id_operacion_diaria', $operacion->id)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertSame('07:15', $filas[0]->hora_inicio->format('H:i'));
        $this->assertSame('11:00', $filas[0]->hora_fin->format('H:i'));
        $this->assertSame('3.00', $filas[0]->cantidad);
        $this->assertNull($filas[1]->hora_inicio);
        $this->assertNull($filas[1]->hora_fin);
    }

    public function test_update_no_copia_horas_de_actividades_de_otra_operacion(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        // Sólo se permite una operación por vehículo y por día: la segunda es de ayer.
        $datosSegunda = [
            'fecha_inicio' => now()->subDay()->setTime(8, 0)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->subDay()->setTime(18, 0)->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1100,
            'kilometraje_fin' => 1150,
        ];

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));
        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, $datosSegunda));
        [$primera, $segunda] = OperacionDiaria::orderBy('id')->get();

        $ajena = ActividadRealizada::where('id_operacion_diaria', $primera->id)->firstOrFail();
        ActividadRealizada::whereKey($ajena->id)->update(['hora_inicio' => '07:15:00', 'hora_fin' => '11:00:00']);

        $payload = $this->payloadOperacionValida($vehiculo, $datosSegunda);
        $payload['actividades_realizadas'][0]['id'] = $ajena->id;

        $this->actingAs($user)->put(route('operacion-diaria.update', $segunda), $payload)
            ->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseHas('actividad_realizada', [
            'id_operacion_diaria' => $segunda->id,
            'hora_inicio' => null,
            'hora_fin' => null,
        ]);
    }

    public function test_validar_actividad_del_modal_no_exige_horas(): void
    {
        [$user] = $this->crearConductorConUsuario();

        $this->actingAs($user)->post(route('operacion-diaria.agregar-actividad'), [
            'actividad' => 'Transporte de material',
            'origen' => 'Cantera',
            'destino' => 'Planta',
            'cantidad' => 2,
            'unidad_medida' => 'viajes',
        ])->assertRedirect(route('operacion-diaria.create'))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_validar_actividad_del_modal_no_exige_cantidad_ni_unidad_de_medida(): void
    {
        [$user] = $this->crearConductorConUsuario();

        $this->actingAs($user)->post(route('operacion-diaria.agregar-actividad'), [
            'actividad' => 'Transporte de material',
            'origen' => 'Cantera',
            'destino' => 'Planta',
            'cantidad' => '',
            'unidad_medida' => '',
        ])->assertRedirect(route('operacion-diaria.create'))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_store_guarda_actividades_sin_cantidad_ni_unidad_de_medida_y_genera_el_pdf(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'actividades_realizadas' => [
                ['actividad' => 'Traslado de personal', 'origen' => 'Base', 'destino' => 'Mina', 'cantidad' => '', 'unidad_medida' => ''],
                // Un cliente que directamente omite ambos campos.
                ['actividad' => 'Retorno a base', 'origen' => 'Mina', 'destino' => 'Base'],
            ],
        ]))->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::firstOrFail();
        $filas = ActividadRealizada::where('id_operacion_diaria', $operacion->id)->get();

        $this->assertCount(2, $filas);
        $this->assertTrue($filas->every(fn ($fila) => $fila->cantidad === null && $fila->unidad_medida === null));

        $this->actingAs($user)->get(route('operacion-diaria.reporte.pdf', $operacion))->assertOk();
    }

    public function test_store_rechaza_una_cantidad_no_numerica_o_menor_a_uno(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'actividades_realizadas' => [
                ['actividad' => 'Transporte de material', 'origen' => 'Cantera', 'destino' => 'Planta', 'cantidad' => 0, 'unidad_medida' => 'viajes'],
            ],
        ]))->assertSessionHasErrors('actividades_realizadas.0.cantidad');

        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_el_reporte_pdf_se_genera_con_actividades_historicas_con_horas(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));
        ActividadRealizada::query()->update(['hora_inicio' => '07:15:00', 'hora_fin' => '11:00:00']);

        $response = $this->actingAs($user)->get(route('operacion-diaria.reporte.pdf', OperacionDiaria::firstOrFail()));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_jefe_de_area_recibe_el_selector_con_todos_los_operarios_activos(): void
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $conductorDelVehiculo] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculo, $conductorDelVehiculo);
        $this->asignarVehiculoAArea($vehiculo, $area);

        // Un operario sin vehículo (debe aparecer) y uno inactivo (no).
        [, $operarioSinVehiculo] = $this->crearConductorConUsuario();
        [, $operarioInactivo] = $this->crearConductorConUsuario();
        $operarioInactivo->update(['estado_conductor' => 'INACTIVO']);

        $response = $this->actingAs($jefeArea)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculosAsignados.0.meta.conductoresAsignados', 1)
            ->where('vehiculosAsignados.0.meta.conductoresAsignados.0.id', $conductorDelVehiculo->id)
            ->where('operarios', fn ($operarios) => collect($operarios)->pluck('id')->sort()->values()->all()
                === collect([$conductorDelVehiculo->id, $operarioSinVehiculo->id])->sort()->values()->all())
        );
    }

    /**
     * Jefe de área que además tiene el rol conductor (y su propio registro
     * de conductor), a cargo de $area.
     *
     * @return array{0: User, 1: Conductor}
     */
    private function crearJefeAreaQueTambienEsConductor(Area $area): array
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $user->assignRole('jefe-area');

        $area->encargados()->attach($user->id_persona, [
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        return [$user, $conductor];
    }

    public function test_un_jefe_de_area_que_tambien_es_conductor_recibe_el_selector_con_los_operarios_del_vehiculo(): void
    {
        $area = Area::factory()->create();
        [$jefeArea] = $this->crearJefeAreaQueTambienEsConductor($area);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAConductor($vehiculo, $operario);
        $this->asignarVehiculoAArea($vehiculo, $area);

        $response = $this->actingAs($jefeArea)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculosAsignados', 1)
            ->where('vehiculosAsignados.0.id', $vehiculo->id)
            ->has('vehiculosAsignados.0.meta.conductoresAsignados', 1)
            ->where('vehiculosAsignados.0.meta.conductoresAsignados.0.id', $operario->id)
        );
    }

    public function test_un_administrador_que_tambien_es_conductor_ve_todos_los_vehiculos_y_el_selector_de_conductor(): void
    {
        [$admin, $conductorPropio] = $this->crearConductorConUsuario();
        $admin->assignRole('administrador');

        $vehiculoPropio = Vehiculo::factory()->create(['nro_placa' => 'AAA-001']);
        $this->asignarVehiculoAConductor($vehiculoPropio, $conductorPropio);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculoDeOperario = Vehiculo::factory()->create(['nro_placa' => 'BBB-002']);
        $this->asignarVehiculoAConductor($vehiculoDeOperario, $operario);

        $response = $this->actingAs($admin)->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculosAsignados', 2)
            ->where('vehiculosAsignados.1.id', $vehiculoDeOperario->id)
            ->where('vehiculosAsignados.1.meta.conductoresAsignados.0.id', $operario->id)
        );
    }

    public function test_store_de_un_jefe_de_area_que_tambien_es_conductor_respeta_el_operario_elegido(): void
    {
        $area = Area::factory()->create();
        [$jefeArea] = $this->crearJefeAreaQueTambienEsConductor($area);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $operario);
        $this->asignarVehiculoAArea($vehiculo, $area);

        // Antes se le forzaba id_conductor = su propio registro de conductor
        // (no asignado a este vehículo) y la Action rechazaba la operación.
        $response = $this->actingAs($jefeArea)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id])
        );

        $response->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::first();
        $this->assertNotNull($operacion);
        $this->assertSame($operario->id, $operacion->id_conductor);
        $this->assertSame($area->id, $operacion->id_area);
    }

    public function test_store_de_un_jefe_de_area_que_tambien_es_conductor_exige_elegir_el_operario(): void
    {
        $area = Area::factory()->create();
        [$jefeArea] = $this->crearJefeAreaQueTambienEsConductor($area);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $operario);
        $this->asignarVehiculoAArea($vehiculo, $area);

        $response = $this->actingAs($jefeArea)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo)
        );

        $response->assertSessionHasErrors('id_conductor');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    /**
     * Registra (vía el propio endpoint) una operación del vehículo hecha por
     * $operario, a cargo del jefe de $area, y luego le quita la asignación al
     * operario y le asigna $nuevoOperario (reasignación de conductor).
     *
     * @return array{0: User, 1: OperacionDiaria, 2: Conductor, 3: Conductor, 4: Vehiculo}
     */
    private function crearOperacionYReasignarConductor(): array
    {
        $area = Area::factory()->create();
        $jefeArea = $this->crearJefeAreaConUsuario($area);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $operario);
        $this->asignarVehiculoAArea($vehiculo, $area);

        $this->actingAs($jefeArea)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id])
        )->assertRedirect(route('operacion-diaria.index'));

        Asignacion::where('id_conductor', $operario->id)->update([
            'estado_asignacion' => 'INACTIVO',
            'fecha_culminacion' => now()->subDay(),
        ]);

        [, $nuevoOperario] = $this->crearConductorConUsuario();
        $this->asignarVehiculoAConductor($vehiculo, $nuevoOperario);

        return [$jefeArea, OperacionDiaria::firstOrFail(), $operario, $nuevoOperario, $vehiculo];
    }

    public function test_update_conserva_el_conductor_original_aunque_ya_no_este_asignado_al_vehiculo(): void
    {
        [$jefeArea, $operacion, $operario, , $vehiculo] = $this->crearOperacionYReasignarConductor();

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id, 'observaciones' => 'Lectura corregida por el jefe'])
        )->assertRedirect(route('operacion-diaria.index'));

        $operacion->refresh();
        $this->assertSame($operario->id, $operacion->id_conductor);
        $this->assertSame('Lectura corregida por el jefe', $operacion->observaciones);
    }

    public function test_update_sin_id_conductor_conserva_el_conductor_de_la_operacion(): void
    {
        [$jefeArea, $operacion, $operario, , $vehiculo] = $this->crearOperacionYReasignarConductor();

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => '', 'observaciones' => 'Lectura corregida por el jefe'])
        )->assertRedirect(route('operacion-diaria.index'));

        $operacion->refresh();
        $this->assertSame($operario->id, $operacion->id_conductor);
        $this->assertSame('Lectura corregida por el jefe', $operacion->observaciones);
    }

    public function test_update_permite_cambiar_a_cualquier_operario_activo_pero_no_a_uno_inactivo(): void
    {
        [$jefeArea, $operacion, , , $vehiculo] = $this->crearOperacionYReasignarConductor();
        [, $conductorAjeno] = $this->crearConductorConUsuario();
        [, $conductorInactivo] = $this->crearConductorConUsuario();
        $conductorInactivo->update(['estado_conductor' => 'INACTIVO']);

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $conductorInactivo->id])
        )->assertSessionHas('error');
        $this->assertNotSame($conductorInactivo->id, $operacion->fresh()->id_conductor);

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $conductorAjeno->id])
        )->assertRedirect(route('operacion-diaria.index'));
        $this->assertSame($conductorAjeno->id, $operacion->fresh()->id_conductor);
    }

    public function test_update_conserva_el_operario_original_aunque_ya_no_este_activo(): void
    {
        [$jefeArea, $operacion, $operario, , $vehiculo] = $this->crearOperacionYReasignarConductor();
        $operario->update(['estado_conductor' => 'INACTIVO']);

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id, 'observaciones' => 'Lectura corregida por el jefe'])
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertSame($operario->id, $operacion->fresh()->id_conductor);
    }

    public function test_un_conductor_puede_editar_su_operacion_aunque_ya_no_tenga_el_vehiculo_asignado(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo));
        $operacion = OperacionDiaria::firstOrFail();

        Asignacion::where('id_conductor', $conductor->id)->update([
            'estado_asignacion' => 'INACTIVO',
            'fecha_culminacion' => now()->subDay(),
        ]);

        $this->actingAs($user)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['observaciones' => 'Lectura corregida por el jefe'])
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertSame('Lectura corregida por el jefe', $operacion->fresh()->observaciones);
        $this->assertSame($conductor->id, $operacion->fresh()->id_conductor);
    }

    public function test_edit_incluye_al_operario_original_entre_las_opciones_aunque_este_inactivo(): void
    {
        [$jefeArea, $operacion, $operario, $nuevoOperario] = $this->crearOperacionYReasignarConductor();
        $operario->update(['estado_conductor' => 'INACTIVO']);

        $response = $this->actingAs($jefeArea)->get(route('operacion-diaria.edit', $operacion));

        $esperados = collect([$operario->id, $nuevoOperario->id])->sort()->values()->all();

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('vehiculosAsignados', 1)
            ->where('operarios', fn ($opciones) => collect($opciones)->pluck('id')->sort()->values()->all() === $esperados
                && str_ends_with(collect($opciones)->firstWhere('id', $operario->id)['label'], '— inactivo'))
        );
    }

    public function test_no_se_puede_editar_ni_actualizar_una_operacion_verificada(): void
    {
        [$jefeArea, $operacion, $operario, , $vehiculo] = $this->crearOperacionYReasignarConductor();
        $operacion->update(['estado' => 'VERIFICADO']);

        $this->actingAs($jefeArea)->get(route('operacion-diaria.edit', $operacion))
            ->assertRedirect(route('operacion-diaria.show', $operacion));

        $this->actingAs($jefeArea)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id, 'observaciones' => 'Cambio no permitido'])
        )->assertSessionHas('error');

        $this->assertNull($operacion->fresh()->observaciones);
    }

    /**
     * Jefe del área A que además es conductor titular de un vehículo que
     * pertenece al área B (que no tiene a su cargo), y un operario que maneja
     * un vehículo del área A.
     *
     * @return array{jefe: User, conductorJefe: Conductor, operario: Conductor, vehiculoArea: Vehiculo, vehiculoPropio: Vehiculo, areaJefe: Area, otraArea: Area}
     */
    private function escenarioJefeConductorConVehiculoEnOtraArea(): array
    {
        $areaJefe = Area::factory()->create();
        $otraArea = Area::factory()->create();
        [$jefe, $conductorJefe] = $this->crearJefeAreaQueTambienEsConductor($areaJefe);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculoArea = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje', 'nro_placa' => 'AAA-111']);
        $this->asignarVehiculoAConductor($vehiculoArea, $operario);
        $this->asignarVehiculoAArea($vehiculoArea, $areaJefe);

        $vehiculoPropio = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje', 'nro_placa' => 'BBB-222']);
        $this->asignarVehiculoAConductor($vehiculoPropio, $conductorJefe);
        $this->asignarVehiculoAArea($vehiculoPropio, $otraArea);

        return compact('jefe', 'conductorJefe', 'operario', 'vehiculoArea', 'vehiculoPropio', 'areaJefe', 'otraArea');
    }

    public function test_jefe_conductor_ve_todos_los_vehiculos_con_los_de_su_area_y_el_propio_primero(): void
    {
        $e = $this->escenarioJefeConductorConVehiculoEnOtraArea();

        // Vehículo de un área ajena que no conduce: ahora también aparece, al final.
        $ajeno = Vehiculo::factory()->create(['codigo' => 'AAA-000']);
        $this->asignarVehiculoAArea($ajeno, $e['otraArea']);

        $response = $this->actingAs($e['jefe'])->get(route('operacion-diaria.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculosAsignados', 3)
            ->where('vehiculosAsignados', fn ($v) => collect($v)->take(2)->pluck('id')->sort()->values()->all()
                === collect([$e['vehiculoArea']->id, $e['vehiculoPropio']->id])->sort()->values()->all()
                && collect($v)->take(2)->every(fn ($x) => $x['meta']['propio'] === true))
            ->where('vehiculosAsignados.2.id', $ajeno->id)
            ->where('vehiculosAsignados.2.meta.propio', false)
        );
    }

    public function test_store_rechaza_un_vehiculo_sin_area_con_error_en_el_campo(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $sinArea = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($sinArea, $conductor);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($sinArea))
            ->assertSessionHasErrors('id_vehiculo');

        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_un_jefe_registra_un_vehiculo_ajeno_pero_un_conductor_puro_solo_los_suyos(): void
    {
        // Jefe del área A registra un vehículo del área B (que no tiene a cargo).
        $areaJefe = Area::factory()->create();
        $otraArea = Area::factory()->create();
        $jefe = $this->crearJefeAreaConUsuario($areaJefe);
        [, $operario] = $this->crearConductorConUsuario();
        $vehiculoAjeno = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($vehiculoAjeno, $otraArea);

        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculoAjeno, ['id_conductor' => $operario->id])
        )->assertRedirect(route('operacion-diaria.index'));

        $operacion = OperacionDiaria::firstOrFail();
        $this->assertSame($jefe->id, $operacion->id_usuario_registro);
        $this->assertSame($otraArea->id, $operacion->id_area);

        $this->actingAs($jefe)->get(route('operacion-diaria.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('actividades.data', fn ($ops) => collect($ops)->pluck('id')->contains($operacion->id)));

        // Un conductor puro NO puede registrar un vehículo que no tiene
        // asignado; con el suyo, sí (a su nombre).
        [$userConductor, $conductor] = $this->crearConductorConUsuario();
        $otroVehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($otroVehiculo, $otraArea);

        $this->actingAs($userConductor)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($otroVehiculo)
        )->assertSessionHasErrors(['id_vehiculo' => 'Sólo puedes registrar con un vehículo que tengas asignado.']);
        $this->assertDatabaseMissing('operacion_diaria', ['id_vehiculo' => $otroVehiculo->id]);

        $this->asignarVehiculoAConductor($otroVehiculo, $conductor);
        $this->actingAs($userConductor)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($otroVehiculo)
        )->assertRedirect(route('operacion-diaria.index'));

        $propia = OperacionDiaria::where('id_vehiculo', $otroVehiculo->id)->firstOrFail();
        $this->assertSame($conductor->id, $propia->id_conductor);

        $this->actingAs($userConductor)->get(route('operacion-diaria.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('actividades.data', fn ($ops) => collect($ops)->pluck('id')->all() === [$propia->id]));
    }

    public function test_jefe_conductor_registra_operaciones_de_su_area_y_de_su_propio_vehiculo_y_las_ve_en_el_listado(): void
    {
        $e = $this->escenarioJefeConductorConVehiculoEnOtraArea();

        $this->actingAs($e['jefe'])->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($e['vehiculoArea'], ['id_conductor' => $e['operario']->id])
        )->assertRedirect(route('operacion-diaria.index'));

        $this->actingAs($e['jefe'])->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($e['vehiculoPropio'], ['id_conductor' => $e['conductorJefe']->id])
        )->assertRedirect(route('operacion-diaria.index'));

        $deSuArea = OperacionDiaria::where('id_vehiculo', $e['vehiculoArea']->id)->firstOrFail();
        $propia = OperacionDiaria::where('id_vehiculo', $e['vehiculoPropio']->id)->firstOrFail();

        $this->assertSame($e['operario']->id, $deSuArea->id_conductor);
        $this->assertSame($e['areaJefe']->id, $deSuArea->id_area);
        $this->assertSame($e['conductorJefe']->id, $propia->id_conductor);
        $this->assertSame($e['otraArea']->id, $propia->id_area);

        // Una operación de otra área y de otro conductor: no debe verla.
        [, $otroOperario] = $this->crearConductorConUsuario();
        $vehiculoAjeno = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculoAjeno, $otroOperario);
        $this->asignarVehiculoAArea($vehiculoAjeno, $e['otraArea']);
        $this->actingAs($otroOperario->persona->user)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculoAjeno)
        )->assertRedirect(route('operacion-diaria.index'));
        $ajena = OperacionDiaria::where('id_vehiculo', $vehiculoAjeno->id)->firstOrFail();

        $response = $this->actingAs($e['jefe'])->get(route('operacion-diaria.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('actividades.data', 2)
            ->where('actividades.data', fn ($operaciones) => collect($operaciones)->pluck('id')->sort()->values()->all()
                === collect([$deSuArea->id, $propia->id])->sort()->values()->all())
            ->where('conductores', fn ($conductores) => collect($conductores)->pluck('id')->contains($e['conductorJefe']->id)
                && collect($conductores)->pluck('id')->contains($e['operario']->id)
                && ! collect($conductores)->pluck('id')->contains($otroOperario->id))
        );

        $this->assertNotNull($ajena);
    }

    public function test_jefe_conductor_puede_registrar_su_vehiculo_de_otra_area_a_nombre_de_otro_operario(): void
    {
        $e = $this->escenarioJefeConductorConVehiculoEnOtraArea();

        $this->actingAs($e['jefe'])->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($e['vehiculoPropio'], ['id_conductor' => $e['operario']->id])
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertSame($e['operario']->id, OperacionDiaria::firstOrFail()->id_conductor);
    }

    /**
     * fecha_inicio/fecha_fin de una jornada 08:00–18:00 del día indicado.
     *
     * @return array{fecha_inicio: string, fecha_fin: string}
     */
    private function jornadaDelDia(Carbon $dia): array
    {
        return [
            'fecha_inicio' => $dia->copy()->setTime(8, 0)->format('Y-m-d\TH:i'),
            'fecha_fin' => $dia->copy()->setTime(18, 0)->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * Jefe del área, un operario y un vehículo (por kilometraje) de esa área.
     *
     * @return array{0: User, 1: Conductor, 2: Vehiculo, 3: Area}
     */
    private function escenarioJefeConVehiculo(): array
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeAreaConUsuario($area);

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $operario);
        $this->asignarVehiculoAArea($vehiculo, $area);

        return [$jefe, $operario, $vehiculo, $area];
    }

    public function test_no_permite_dos_operaciones_del_mismo_vehiculo_el_mismo_dia(): void
    {
        [$jefe, $operario, $vehiculo, $area] = $this->escenarioJefeConVehiculo();

        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()))
        )->assertRedirect(route('operacion-diaria.index'));

        // Mismo vehículo y mismo día, aunque a otra hora y por otro operario.
        [, $otroOperario] = $this->crearConductorConUsuario();
        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, [
                'id_conductor' => $otroOperario->id,
                'turno' => 'NOCHE',
                'fecha_inicio' => now()->setTime(19, 0)->format('Y-m-d\TH:i'),
                'fecha_fin' => now()->addDay()->setTime(5, 0)->format('Y-m-d\TH:i'),
            ])
        )->assertSessionHasErrors('fecha_inicio');

        $this->assertDatabaseCount('operacion_diaria', 1);

        // Otro vehículo el mismo día sí se puede.
        $otroVehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($otroVehiculo, $area);
        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($otroVehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()))
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseCount('operacion_diaria', 2);
    }

    public function test_update_no_permite_mover_la_operacion_a_un_dia_que_ya_tiene_otra_del_vehiculo(): void
    {
        [$jefe, $operario, $vehiculo] = $this->escenarioJefeConVehiculo();

        foreach ([now(), now()->subDay()] as $dia) {
            $this->actingAs($jefe)->post(
                route('operacion-diaria.store'),
                $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia($dia))
            )->assertRedirect(route('operacion-diaria.index'));
        }

        $deAyer = OperacionDiaria::whereDate('fecha_inicio', now()->subDay()->toDateString())->firstOrFail();

        $this->actingAs($jefe)->put(
            route('operacion-diaria.update', $deAyer),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()))
        )->assertSessionHasErrors('fecha_inicio');

        // Editarla sin cambiar de día sí se puede (no choca consigo misma).
        $this->actingAs($jefe)->put(
            route('operacion-diaria.update', $deAyer),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id, 'observaciones' => 'Corrección de lecturas'] + $this->jornadaDelDia(now()->subDay()))
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertTrue($deAyer->fresh()->fecha_inicio->isSameDay(now()->subDay()));
        $this->assertSame('Corrección de lecturas', $deAyer->fresh()->observaciones);
    }

    public function test_un_conductor_solo_puede_registrar_operaciones_de_hoy_o_de_ayer(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($user)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, $this->jornadaDelDia(now()->subDays(3)))
        )->assertSessionHasErrors('fecha_inicio');

        $this->actingAs($user)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, $this->jornadaDelDia(now()->addDay()))
        )->assertSessionHasErrors('fecha_inicio');

        $this->assertDatabaseCount('operacion_diaria', 0);

        $this->actingAs($user)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, $this->jornadaDelDia(now()->subDay()))
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_un_jefe_de_area_puede_registrar_cualquier_fecha_para_regularizar(): void
    {
        [$jefe, $operario, $vehiculo] = $this->escenarioJefeConVehiculo();

        foreach ([now()->subDays(45), now()->addDays(2)] as $dia) {
            $this->actingAs($jefe)->post(
                route('operacion-diaria.store'),
                $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia($dia))
            )->assertRedirect(route('operacion-diaria.index'));
        }

        $this->assertDatabaseCount('operacion_diaria', 2);
    }

    public function test_un_administrador_puede_registrar_cualquier_fecha_para_regularizar(): void
    {
        $admin = User::factory()->create(['id_persona' => Persona::factory()->create()->id]);
        $admin->assignRole('administrador');

        [, $operario] = $this->crearConductorConUsuario();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        $this->actingAs($admin)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()->subMonths(3)))
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_un_conductor_puede_editar_una_operacion_antigua_si_no_cambia_el_dia(): void
    {
        [$jefe, , $vehiculo] = $this->escenarioJefeConVehiculo();
        [$user, $conductor] = $this->crearConductorConUsuario();
        $this->asignarVehiculoAConductor($vehiculo, $conductor);

        // El jefe regulariza una jornada de hace 10 días a nombre del conductor.
        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $conductor->id] + $this->jornadaDelDia(now()->subDays(10)))
        )->assertRedirect(route('operacion-diaria.index'));
        $operacion = OperacionDiaria::firstOrFail();

        $this->actingAs($user)->put(
            route('operacion-diaria.update', $operacion),
            $this->payloadOperacionValida($vehiculo, ['observaciones' => 'Corrige el kilometraje'] + $this->jornadaDelDia(now()->subDays(10)))
        )->assertRedirect(route('operacion-diaria.index'));

        $this->assertSame('Corrige el kilometraje', $operacion->fresh()->observaciones);
    }

    public function test_al_regularizar_la_lectura_se_compara_con_la_ultima_carga_hasta_esa_fecha(): void
    {
        [$jefe, $operario, $vehiculo] = $this->escenarioJefeConVehiculo();

        // Carga de ayer con 5000 km: posterior a la jornada a regularizar.
        CargaCombustible::create([
            'fecha_carga' => now()->subDay(),
            'litros' => 50,
            'precio' => 10,
            'kilometraje' => 5000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => Grifo::create([
                'razon_social' => 'Grifo Central',
                'nit' => '123456',
                'direccion' => 'Av. Siempre Viva',
                'ciudad' => 'La Paz',
                'telefono' => '70000000',
                'estado_grifo' => 'ACTIVO',
                'es_principal' => true,
            ])->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'id_conductor' => $operario->id,
            'tipo_carga' => 'PREPAGO',
        ]);

        // Jornada de hace 10 días con 1000 km: válida, la carga es posterior.
        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()->subDays(10)))
        )->assertRedirect(route('operacion-diaria.index'));

        // Hoy, en cambio, 1000 km es menor a la última carga (5000 km).
        $this->actingAs($jefe)->post(
            route('operacion-diaria.store'),
            $this->payloadOperacionValida($vehiculo, ['id_conductor' => $operario->id] + $this->jornadaDelDia(now()))
        )->assertSessionHasErrors('kilometraje_inicio');

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_el_formulario_indica_si_el_usuario_puede_regularizar_fechas(): void
    {
        [$jefe] = $this->escenarioJefeConVehiculo();
        [$conductorUser] = $this->crearConductorConUsuario();

        $this->actingAs($jefe)->get(route('operacion-diaria.create'))
            ->assertInertia(fn (Assert $page) => $page->where('puedeRegularizar', true)->where('diasAtrasSinRegularizar', 1));

        $this->actingAs($conductorUser)->get(route('operacion-diaria.create'))
            ->assertInertia(fn (Assert $page) => $page->where('puedeRegularizar', false));
    }

    /**
     * Conductor con un vehículo de $tipoMedicion cuyo tipo tiene los topes
     * indicados, listo para registrar su operación.
     *
     * @return array{0: User, 1: Vehiculo}
     */
    private function escenarioConTope(string $tipoMedicion, ?float $kmMaximo = 1000, ?float $horasMaximas = 24): array
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $tipo = TipoVehiculo::factory()->create([
            'tipo_vehiculo' => 'Tractocamión',
            'km_maximo_jornada' => $kmMaximo,
            'horometro_maximo_jornada' => $horasMaximas,
        ]);
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => $tipoMedicion, 'id_tipo_vehiculo' => $tipo->id]);
        $this->asignarVehiculoAConductor($vehiculo, $conductor);
        $this->asignarVehiculoAArea($vehiculo, Area::factory()->create());

        return [$user, $vehiculo];
    }

    public function test_rechaza_un_recorrido_que_supera_el_tope_del_tipo_de_vehiculo(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('kilometraje', 900);

        // Punto decimal olvidado: 5460.7 -> 55101 = 49.640,3 km en un día.
        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => 5460.7,
            'kilometraje_fin' => 55101,
        ]))->assertSessionHasErrors([
            'kilometraje_fin' => 'El recorrido de la jornada (49.640,3 km) supera el máximo de 900 km para Tractocamión. Revisa el kilometraje final: ¿falta un punto decimal?',
        ]);

        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_acepta_un_recorrido_igual_al_tope(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('kilometraje', 900);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => 5460.7,
            'kilometraje_fin' => 6360.7,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_un_tipo_sin_tope_no_limita_el_recorrido(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('kilometraje', null);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => 5460.7,
            'kilometraje_fin' => 55101,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_los_tipos_nuevos_traen_los_topes_por_defecto(): void
    {
        $tipo = TipoVehiculo::factory()->create();

        $this->assertSame(1000.0, $tipo->fresh()->topeJornada('kilometraje'));
        $this->assertSame(24.0, $tipo->fresh()->topeJornada('horometro'));
    }

    public function test_rechaza_horas_de_horometro_que_superan_el_tope(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('horometro', 1000, 12);

        $payload = $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => null,
            'kilometraje_fin' => null,
            'horometro_inicio' => 1210.5,
            'horometro_fin' => 1230.5,
            'actividades_realizadas' => [['actividad' => 'Carguío', 'lugar' => 'Frente norte']],
        ]);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload)
            ->assertSessionHasErrors([
                'horometro_fin' => 'Las horas de horómetro de la jornada (20 h) superan el máximo de 12 h para Tractocamión. Revisa el horómetro final: ¿falta un punto decimal?',
            ]);

        $payload['horometro_fin'] = 1220;
        $this->actingAs($user)->post(route('operacion-diaria.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_el_tope_de_kilometraje_no_se_aplica_a_un_vehiculo_por_horometro(): void
    {
        // El tipo tiene un tope de km mínimo, pero el vehículo mide por
        // horómetro: sólo cuenta el tope de horas.
        [$user, $vehiculo] = $this->escenarioConTope('horometro', 1, 24);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => null,
            'kilometraje_fin' => null,
            'horometro_inicio' => 100,
            'horometro_fin' => 108,
            'actividades_realizadas' => [['actividad' => 'Carguío', 'lugar' => 'Frente norte']],
        ]))->assertSessionHasNoErrors();
    }

    public function test_al_editar_tambien_se_valida_el_tope(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('kilometraje', 900);

        $this->actingAs($user)->post(route('operacion-diaria.store'), $this->payloadOperacionValida($vehiculo))
            ->assertSessionHasNoErrors();
        $operacion = OperacionDiaria::firstOrFail();

        $this->actingAs($user)->put(route('operacion-diaria.update', $operacion), $this->payloadOperacionValida($vehiculo, [
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 10500,
        ]))->assertSessionHasErrors('kilometraje_fin');

        $this->assertEquals(1050, $operacion->fresh()->kilometraje_fin);
    }

    public function test_el_formulario_recibe_el_tope_por_jornada_del_vehiculo(): void
    {
        [$user, $vehiculo] = $this->escenarioConTope('kilometraje', 750);

        $this->actingAs($user)->get(route('operacion-diaria.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculosAsignados', fn ($opciones) => collect($opciones)->firstWhere('id', $vehiculo->id)['meta']['tope_jornada'] == 750)
            );
    }
}
