<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Grifo;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\OperacionDiaria;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $permisosWidgets = [
        'dashboard.tarjeta-cargas.ver',
        'dashboard.tarjeta-vales.ver',
        'dashboard.tarjeta-vehiculos.ver',
        'dashboard.tarjeta-conductores.ver',
        'dashboard.grafico-combustible.ver',
    ];

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->permisosWidgets as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Permission::firstOrCreate(['name' => 'dashboard.grafico-ordenes.ver', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'dashboard.grafico-horas.ver', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'dashboard.mantenimiento-alertas.ver', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'])
            ->givePermissionTo([...$this->permisosWidgets, 'dashboard.grafico-ordenes.ver', 'dashboard.grafico-horas.ver', 'dashboard.mantenimiento-alertas.ver']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web'])
            ->givePermissionTo([...$this->permisosWidgets, 'dashboard.mantenimiento-alertas.ver']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web'])
            ->givePermissionTo(['dashboard.grafico-horas.ver', 'dashboard.mantenimiento-alertas.ver']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web'])
            ->givePermissionTo('dashboard.grafico-ordenes.ver');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // OrdenTrabajo::boot() asigna id_usuario_emite = auth()->id() al crear.
        $this->actingAs(User::factory()->create());

        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Test',
            'nit' => '123456',
            'direccion' => 'Av. Test',
            'ciudad' => 'La Paz',
            'telefono' => '70000000',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);
        $this->tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Test',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearVehiculoEnArea(Area $area): Vehiculo
    {
        $vehiculo = Vehiculo::factory()->create();

        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return $vehiculo;
    }

    private function crearConductorAsignado(Vehiculo $vehiculo): Conductor
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return $conductor;
    }

    /**
     * @return array{0: User, 1: Conductor}
     */
    private function crearConductorConUsuario(): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('conductor');

        return [$user, $conductor];
    }

    private function crearOperacion(Conductor $conductor, Carbon $inicio, float $horas): void
    {
        OperacionDiaria::create([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => $conductor->id,
            'id_area' => Area::factory()->create()->id,
            'turno' => 'DIA',
            'fecha_inicio' => $inicio,
            // OperacionDiaria::booted() calcula horas_trabajadas = (fecha_fin - fecha_inicio).
            'fecha_fin' => $inicio->copy()->addMinutes((int) round($horas * 60)),
            'estado' => 'FINALIZADO',
        ]);
    }

    private function crearCarga(Vehiculo $vehiculo, Conductor $conductor, float $litros, bool $conVale = false): CargaCombustible
    {
        $idVale = null;

        if ($conVale) {
            $idVale = Vale::create([
                'litros' => $litros,
                'precio' => 7.0,
                'id_vehiculo' => $vehiculo->id,
                'id_conductor' => $conductor->id,
                'id_grifo' => $this->grifo->id,
                'id_tipo_combustible' => $this->tipoCombustible->id,
                'estado_vale' => 'USADO',
            ])->id;
        }

        return CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => $litros,
            'precio' => 7.0,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $idVale,
            'tipo_carga' => $conVale ? 'VALE' : 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);
    }

    private function crearOrden(string $estado, User $ejecutor): void
    {
        $orden = OrdenTrabajo::create([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => $ejecutor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        // boot() fuerza estado_orden = PENDIENTE al crear.
        $orden->update(['estado_orden' => $estado]);
    }

    /**
     * Configura un intervalo de mantenimiento para el tipo del vehículo y una
     * carga de combustible cuya lectura deja ese mantenimiento VENCIDO.
     */
    private function crearAlertaVencida(Vehiculo $vehiculo, Conductor $conductor): void
    {
        $tipoMantenimiento = TipoMantenimiento::firstOrCreate(
            ['tipo_mantenimiento' => 'Cambio de aceite'],
            ['estado_tipo_mantenimiento' => 'ACTIVO', 'ambito' => 'taller'],
        );

        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => 10000,
            'estado' => 'ACTIVO',
        ]);

        CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50,
            'precio' => 7,
            'kilometraje' => 12000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);
    }

    private function litrosDelGrafico($response): float
    {
        $props = $response->original->getData()['page']['props'];

        return collect($props['reporteMes'])->sum('total_litros');
    }

    public function test_administrador_ve_las_metricas_globales(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $areaA = Area::factory()->create();
        $areaB = Area::factory()->create();
        $vehiculoA = $this->crearVehiculoEnArea($areaA);
        $vehiculoB = $this->crearVehiculoEnArea($areaB);
        $conductorA = $this->crearConductorAsignado($vehiculoA);
        $conductorB = $this->crearConductorAsignado($vehiculoB);

        $this->crearCarga($vehiculoA, $conductorA, 100, conVale: true);
        $this->crearCarga($vehiculoB, $conductorB, 40);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('cargas.total', 2)
            ->where('vehiculos.total', 2)
            ->where('conductores.total', 2)
            ->where('vales.total', 1)
        );

        $this->assertEquals(140, $this->litrosDelGrafico($response));
    }

    public function test_jefe_de_area_solo_ve_metricas_de_los_vehiculos_de_sus_areas(): void
    {
        $persona = Persona::factory()->create();
        $jefe = User::factory()->create(['id_persona' => $persona->id]);
        $jefe->assignRole('jefe-area');

        $areaPropia = Area::factory()->create();
        $areaAjena = Area::factory()->create();

        EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $areaPropia->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $vehiculoPropio = $this->crearVehiculoEnArea($areaPropia);
        $vehiculoAjeno = $this->crearVehiculoEnArea($areaAjena);
        $conductorPropio = $this->crearConductorAsignado($vehiculoPropio);
        $conductorAjeno = $this->crearConductorAsignado($vehiculoAjeno);

        $this->crearCarga($vehiculoPropio, $conductorPropio, 80, conVale: true);
        $this->crearCarga($vehiculoAjeno, $conductorAjeno, 500, conVale: true);

        $response = $this->actingAs($jefe)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cargas.total', 1)
            ->where('vehiculos.total', 1)
            ->where('conductores.total', 1)
            ->where('vales.total', 1)
        );

        $this->assertEquals(80, $this->litrosDelGrafico($response));
    }

    public function test_no_se_envia_la_metrica_de_un_widget_sin_permiso(): void
    {
        $persona = Persona::factory()->create();
        $jefe = User::factory()->create(['id_persona' => $persona->id]);
        $jefe->assignRole('jefe-area');

        Role::findByName('jefe-area', 'web')->revokePermissionTo('dashboard.tarjeta-conductores.ver');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->actingAs($jefe)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('cargas')
            ->missing('conductores')
        );
    }

    public function test_un_conductor_solo_recibe_el_grafico_de_horas_trabajadas(): void
    {
        [$conductorUser] = $this->crearConductorConUsuario();

        $response = $this->actingAs($conductorUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('horasTrabajadas.dia.series', 7)
            ->has('horasTrabajadas.semana.series', 6)
            ->missing('cargas')
            ->missing('vales')
            ->missing('vehiculos')
            ->missing('conductores')
            ->missing('reporteMes')
            ->missing('ordenesPorEstado')
        );
    }

    public function test_un_conductor_ve_solo_sus_horas_trabajadas_agregadas_por_dia_y_semana(): void
    {
        [$conductorUser, $conductor] = $this->crearConductorConUsuario();
        [, $otroConductor] = $this->crearConductorConUsuario();

        $this->crearOperacion($conductor, now()->startOfDay()->addHours(6), 3.0);
        $this->crearOperacion($conductor, now()->startOfDay()->addHours(12), 2.0);
        $this->crearOperacion($conductor, now()->startOfDay()->subDays(3)->addHours(7), 4.0);
        $this->crearOperacion($otroConductor, now()->startOfDay()->addHours(6), 10.0); // fuera de su alcance

        $response = $this->actingAs($conductorUser)->get(route('dashboard'));

        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $this->assertEqualsWithDelta(9.0, array_sum($props['horasTrabajadas']['dia']['series']), 0.01);
        $this->assertEqualsWithDelta(9.0, array_sum($props['horasTrabajadas']['semana']['series']), 0.01);
        // El último día del eje "día" es hoy: 3h + 2h.
        $this->assertEqualsWithDelta(5.0, end($props['horasTrabajadas']['dia']['series']), 0.01);
    }

    public function test_un_administrador_ve_las_horas_trabajadas_de_todos_los_conductores(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        [, $conductorA] = $this->crearConductorConUsuario();
        [, $conductorB] = $this->crearConductorConUsuario();

        $this->crearOperacion($conductorA, now()->startOfDay()->addHours(6), 5.0);
        $this->crearOperacion($conductorB, now()->startOfDay()->addHours(6), 8.0);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $this->assertEqualsWithDelta(13.0, end($props['horasTrabajadas']['dia']['series']), 0.01);
    }

    public function test_un_tecnico_ve_el_grafico_de_torta_solo_con_sus_ordenes_por_estado(): void
    {
        $tecnico = User::factory()->create();
        $tecnico->assignRole('tecnico-mantenimiento');

        $otroTecnico = User::factory()->create();
        $otroTecnico->assignRole('tecnico-mantenimiento');

        $this->crearOrden('PENDIENTE', $tecnico);
        $this->crearOrden('PENDIENTE', $tecnico);
        $this->crearOrden('CULMINADO', $tecnico);
        $this->crearOrden('EN_EJECUCION', $otroTecnico); // fuera de su alcance

        $response = $this->actingAs($tecnico)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('ordenesPorEstado.labels', ['Pendiente', 'Culminado'])
            ->where('ordenesPorEstado.series', [2, 1])
            ->missing('cargas')
        );
    }

    public function test_un_administrador_ve_el_grafico_de_torta_con_todas_las_ordenes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $tecnico = User::factory()->create();
        $tecnico->assignRole('tecnico-mantenimiento');

        $this->crearOrden('PENDIENTE', $tecnico);
        $this->crearOrden('VERIFICADO', $tecnico);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('ordenesPorEstado.labels', ['Pendiente', 'Verificado'])
            ->where('ordenesPorEstado.series', [1, 1])
        );
    }

    public function test_un_rol_sin_permiso_no_recibe_las_alertas_de_mantenimiento(): void
    {
        Role::findByName('jefe-area', 'web')->revokePermissionTo('dashboard.mantenimiento-alertas.ver');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $persona = Persona::factory()->create();
        $jefe = User::factory()->create(['id_persona' => $persona->id]);
        $jefe->assignRole('jefe-area');

        $response = $this->actingAs($jefe)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->missing('alertasMantenimiento'));
    }

    public function test_el_administrador_ve_las_alertas_de_mantenimiento_de_todos_los_vehiculos(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $area = Area::factory()->create();
        $vehiculoA = $this->crearVehiculoEnArea($area);
        $vehiculoB = $this->crearVehiculoEnArea($area);
        $this->crearAlertaVencida($vehiculoA, $this->crearConductorAsignado($vehiculoA));
        $this->crearAlertaVencida($vehiculoB, $this->crearConductorAsignado($vehiculoB));

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->has('alertasMantenimiento', 2));
    }

    public function test_el_jefe_de_area_solo_ve_alertas_de_los_vehiculos_de_sus_areas(): void
    {
        $persona = Persona::factory()->create();
        $jefe = User::factory()->create(['id_persona' => $persona->id]);
        $jefe->assignRole('jefe-area');

        $areaPropia = Area::factory()->create();
        $areaAjena = Area::factory()->create();
        EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $areaPropia->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $vehiculoPropio = $this->crearVehiculoEnArea($areaPropia);
        $vehiculoAjeno = $this->crearVehiculoEnArea($areaAjena);
        $this->crearAlertaVencida($vehiculoPropio, $this->crearConductorAsignado($vehiculoPropio));
        $this->crearAlertaVencida($vehiculoAjeno, $this->crearConductorAsignado($vehiculoAjeno));

        $response = $this->actingAs($jefe)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('alertasMantenimiento', 1)
            ->where('alertasMantenimiento.0.id_vehiculo', $vehiculoPropio->id)
        );
    }

    public function test_un_conductor_solo_ve_alertas_de_los_vehiculos_que_tiene_asignados(): void
    {
        [$conductorUser, $conductor] = $this->crearConductorConUsuario();

        $vehiculoAsignado = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculoAsignado->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        $this->crearAlertaVencida($vehiculoAsignado, $conductor);

        $vehiculoAjeno = Vehiculo::factory()->create();
        $this->crearAlertaVencida($vehiculoAjeno, $this->crearConductorAsignado($vehiculoAjeno));

        $response = $this->actingAs($conductorUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('alertasMantenimiento', 1)
            ->where('alertasMantenimiento.0.id_vehiculo', $vehiculoAsignado->id)
            ->where('alertasMantenimiento.0.vencidos', 1)
        );
    }
}
