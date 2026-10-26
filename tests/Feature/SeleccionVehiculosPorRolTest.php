<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

/**
 * Regla común de vales, cargas de combustible y operación diaria:
 * - Cualquier rol o combinación de roles (administrador, jefe de área,
 *   jefe + conductor, ...) ve y puede usar TODOS los vehículos y operarios
 *   ACTIVOS.
 * - El conductor puro sólo ve y usa sus vehículos asignados y ACTIVOS, y no
 *   elige operario: el registro queda a su nombre.
 */
class SeleccionVehiculosPorRolTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    private Grifo $grifo;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'jefe-area', 'conductor'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);

        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Central', 'nit' => '123456', 'direccion' => 'Av. 1',
            'ciudad' => 'Oruro', 'telefono' => '70000000', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $roles
     * @return array{0: User, 1: Conductor}
     */
    private function usuarioConductor(array $roles = ['conductor']): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole($roles);

        return [$user, $conductor];
    }

    private function asignar(Vehiculo $vehiculo, Conductor $conductor): void
    {
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    private function enArea(Vehiculo $vehiculo): void
    {
        Area::factory()->create()->vehiculos()->attach($vehiculo->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    /**
     * Escenario: un vehículo asignado al conductor, uno asignado pero dado
     * de baja, y uno activo de otro conductor.
     *
     * @return array{propio: Vehiculo, propioRetirado: Vehiculo, ajeno: Vehiculo, otroOperario: Conductor}
     */
    private function flota(Conductor $conductor): array
    {
        $propio = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignar($propio, $conductor);
        $this->enArea($propio);

        $propioRetirado = Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);
        $this->asignar($propioRetirado, $conductor);

        $otroOperario = Conductor::factory()->create();
        $ajeno = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->asignar($ajeno, $otroOperario);
        $this->enArea($ajeno);

        return compact('propio', 'propioRetirado', 'ajeno', 'otroOperario');
    }

    private function ids(iterable $opciones): array
    {
        return collect($opciones)->pluck('id')->sort()->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Listas de los formularios */
    /* ------------------------------------------------------------------ */

    public function test_conductor_puro_ve_solo_sus_vehiculos_activos_y_no_elige_operario_en_los_tres_modulos(): void
    {
        [$user, $conductor] = $this->usuarioConductor();
        $user->givePermissionTo('vales.crear');
        $f = $this->flota($conductor);

        $this->actingAs($user)->get(route('vales.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculos', fn ($v) => $this->ids($v) === [$f['propio']->id])
                ->where('operarios', [])
                ->where('operarioPropio.id', $conductor->id)
                ->where('sinFichaConductor', false));

        $this->actingAs($user)->get(route('cargas.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculosAsignados', fn ($v) => $this->ids($v) === [$f['propio']->id])
                ->where('vehiculos', [])
                ->where('operarios', [])
                ->where('conductor.id', $conductor->id));

        $this->actingAs($user)->get(route('operacion-diaria.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculosAsignados', fn ($v) => $this->ids($v) === [$f['propio']->id])
                ->where('mostrarSelectorConductor', false));
    }

    /**
     * @return array<string, array{0: array<int, string>}>
     */
    public static function combinacionesDeGestion(): array
    {
        return [
            'administrador' => [['administrador']],
            'jefe de área' => [['jefe-area']],
            'jefe de área + conductor' => [['jefe-area', 'conductor']],
            'administrador + conductor' => [['administrador', 'conductor']],
            'administrador + jefe + conductor' => [['administrador', 'jefe-area', 'conductor']],
        ];
    }

    /**
     * @param  array<int, string>  $roles
     */
    #[DataProvider('combinacionesDeGestion')]
    public function test_cualquier_combinacion_de_gestion_ve_todos_los_vehiculos_y_operarios_activos(array $roles): void
    {
        [$user, $conductor] = $this->usuarioConductor($roles);
        $f = $this->flota($conductor);
        $activos = $this->ids([$f['propio'], $f['ajeno']]);

        $this->actingAs($user)->get(route('vales.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculos', fn ($v) => $this->ids($v) === $activos)
                ->where('operarios', fn ($o) => collect($o)->pluck('id')->contains($f['otroOperario']->id))
                ->where('operarioPropio', null));

        $this->actingAs($user)->get(route('cargas.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculos', fn ($v) => $this->ids($v) === $activos)
                ->where('operarios', fn ($o) => collect($o)->pluck('id')->contains($f['otroOperario']->id))
                ->where('conductor', null));

        $this->actingAs($user)->get(route('operacion-diaria.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculosAsignados', fn ($v) => $this->ids($v) === $activos)
                ->where('mostrarSelectorConductor', true));
    }

    public function test_rol_conductor_sin_ficha_recibe_el_aviso_en_vez_de_listas_vacias(): void
    {
        $user = User::factory()->create(['id_persona' => Persona::factory()->create()->id]);
        $user->assignRole('conductor');
        $user->givePermissionTo('vales.crear');

        foreach (['vales.create', 'cargas.create', 'operacion-diaria.create'] as $ruta) {
            $this->actingAs($user)->get(route($ruta))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('sinFichaConductor', true));
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Validación del servidor al registrar */
    /* ------------------------------------------------------------------ */

    public function test_conductor_puro_no_puede_emitir_un_vale_con_un_vehiculo_ajeno_y_el_suyo_queda_a_su_nombre(): void
    {
        [$user, $conductor] = $this->usuarioConductor();
        $user->givePermissionTo('vales.crear');
        $f = $this->flota($conductor);

        $datos = [
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'id_conductor' => $f['otroOperario']->id,
            'id_grifo' => $this->grifo->id,
            'litros' => 20,
            'precio' => 6.97,
        ];

        $this->actingAs($user)->post(route('vales.store'), $datos + ['id_vehiculo' => $f['ajeno']->id])
            ->assertSessionHasErrors(['id_vehiculo' => 'Sólo puedes registrar con un vehículo que tengas asignado.']);
        $this->assertSame(0, Vale::count());

        // Con su vehículo se emite, siempre a su nombre aunque envíe otro operario.
        $this->actingAs($user)->post(route('vales.store'), $datos + ['id_vehiculo' => $f['propio']->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($conductor->id, Vale::firstOrFail()->id_conductor);
    }

    public function test_un_vehiculo_inactivo_no_se_acepta_en_ningun_modulo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $operario = Conductor::factory()->create();
        $retirado = Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO', 'tipo_medicion' => 'kilometraje']);
        $this->enArea($retirado);

        $this->actingAs($admin)->post(route('vales.store'), [
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'id_vehiculo' => $retirado->id,
            'id_conductor' => $operario->id,
            'id_grifo' => $this->grifo->id,
            'litros' => 20,
            'precio' => 6.97,
        ])->assertSessionHasErrors(['id_vehiculo' => 'El vehículo seleccionado no está activo.']);

        $this->actingAs($admin)->post(route('cargas.store'), [
            'litros' => 30,
            'precio' => 3.74,
            'kilometraje' => 1000,
            'id_vehiculo' => $retirado->id,
            'id_conductor' => $operario->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'tipo_carga' => 'PREPAGO',
        ])->assertSessionHasErrors(['id_vehiculo' => 'El vehículo seleccionado no está activo.']);

        $this->actingAs($admin)->post(route('operacion-diaria.store'), [
            'id_vehiculo' => $retirado->id,
            'id_conductor' => $operario->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [['actividad' => 'Traslado', 'origen' => 'A', 'destino' => 'B']],
        ])->assertSessionHasErrors(['id_vehiculo' => 'El vehículo seleccionado no está activo.']);

        $this->assertSame(0, Vale::count() + CargaCombustible::count() + OperacionDiaria::count());
    }

    public function test_conductor_puro_no_puede_registrar_una_carga_sin_vale_con_un_vehiculo_ajeno(): void
    {
        [$user, $conductor] = $this->usuarioConductor();
        $f = $this->flota($conductor);

        $datos = [
            'litros' => 30,
            'precio' => 3.74,
            'kilometraje' => 1000,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'tipo_carga' => 'PREPAGO',
        ];

        $this->actingAs($user)->post(route('cargas.store'), $datos + ['id_vehiculo' => $f['ajeno']->id])
            ->assertSessionHasErrors('id_vehiculo');

        $this->actingAs($user)->post(route('cargas.store'), $datos + ['id_vehiculo' => $f['propio']->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($conductor->id, CargaCombustible::firstOrFail()->id_conductor);
    }

    /* ------------------------------------------------------------------ */
    /*  API (app Flutter) */
    /* ------------------------------------------------------------------ */

    public function test_api_colecciones_aplica_la_misma_regla(): void
    {
        [$conductorPuro, $conductor] = $this->usuarioConductor();
        // Antes de la primera petición a la API: después el guard por
        // defecto pasa a ser `api` y los roles viven en `web`.
        [$jefe] = $this->usuarioConductor(['jefe-area', 'conductor']);
        $f = $this->flota($conductor);

        $data = $this->actingAs($conductorPuro, 'api')
            ->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'vehiculos,cargas_combustible']))
            ->assertOk()
            ->json('data');
        $this->assertSame([$f['propio']->id], $this->ids($data['vehiculos']));
        $this->assertSame([], $data['cargas_combustible']['operarios']);

        $data = $this->actingAs($jefe, 'api')
            ->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'vehiculos,cargas_combustible']))
            ->assertOk()
            ->json('data');
        $this->assertSame($this->ids([$f['propio'], $f['ajeno']]), $this->ids($data['vehiculos']));
        $this->assertContains($f['otroOperario']->id, collect($data['cargas_combustible']['operarios'])->pluck('id')->all());
    }

    public function test_api_carga_offline_acepta_un_vehiculo_que_luego_se_dio_de_baja(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $operario = Conductor::factory()->create();
        $retirado = Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO', 'tipo_medicion' => 'kilometraje']);

        $this->actingAs($admin, 'api')->postJson(route('api.v1.cargas.store'), [
            'is_offline' => true,
            'fecha_carga' => now()->subDay()->format('Y-m-d H:i:s'),
            'litros' => 30,
            'precio' => 3.74,
            'kilometraje' => 1000,
            'id_vehiculo' => $retirado->id,
            'id_conductor' => $operario->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'tipo_carga' => 'PREPAGO',
        ])->assertCreated();
    }
}
