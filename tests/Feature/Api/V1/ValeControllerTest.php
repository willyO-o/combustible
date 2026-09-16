<?php

namespace Tests\Feature\Api\V1;

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
use App\Notifications\ValeEmitidoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ValeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        // Emitir un vale por la API exige este permiso (ver ValeRequest::authorize()).
        Permission::firstOrCreate(['name' => 'vales.crear', 'guard_name' => 'web']);

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
        $user->givePermissionTo('vales.crear');

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

    private function crearGrifo(): Grifo
    {
        return Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);
    }

    private function crearVale(Vehiculo $vehiculo, ?Conductor $conductor = null, ?Grifo $grifo = null): Vale
    {
        return Vale::create([
            'litros' => 10, 'precio' => 6, 'id_vehiculo' => $vehiculo->id,
            'id_conductor' => ($conductor ?? Conductor::factory()->create())->id,
            'id_grifo' => ($grifo ?? $this->crearGrifo())->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'estado_vale' => 'PENDIENTE',
        ]);
    }

    public function test_un_jefe_de_area_emite_un_vale_para_un_vehiculo_de_su_area(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $tipoCombustible = TipoCombustible::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['id_tipo_combustible' => $tipoCombustible->id]);
        $this->asignarVehiculoAArea($vehiculo, $area);
        $conductor = Conductor::factory()->create();

        $response = $this->actingAs($jefe, 'api')->postJson(route('api.v1.vales.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $this->crearGrifo()->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.estado_vale', 'PENDIENTE');
        $response->assertJsonPath('data.id_tipo_combustible', $tipoCombustible->id);

        $vale = Vale::firstOrFail();
        $this->assertSame($jefe->id, $vale->id_user);
        $this->assertSame($vehiculo->id, $vale->id_vehiculo);
        $this->assertNotNull($vale->fecha_vencimiento);
    }

    public function test_store_notifica_al_conductor_si_tiene_cuenta_de_usuario(): void
    {
        Notification::fake();

        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);
        $conductor = Conductor::factory()->create();
        $usuarioConductor = User::factory()->create(['id_persona' => $conductor->id]);
        $usuarioConductor->assignRole('conductor');

        $this->actingAs($jefe, 'api')->postJson(route('api.v1.vales.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $this->crearGrifo()->id,
            'litros' => 20,
            'precio' => 6.97,
        ])->assertCreated();

        Notification::assertSentTo($usuarioConductor, ValeEmitidoNotification::class);
    }

    public function test_un_jefe_de_area_no_puede_emitir_un_vale_para_un_vehiculo_de_otra_area(): void
    {
        $jefe = $this->crearJefeDeArea(Area::factory()->create());

        $vehiculoAjeno = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoAjeno, Area::factory()->create());

        $response = $this->actingAs($jefe, 'api')->postJson(route('api.v1.vales.store'), [
            'id_vehiculo' => $vehiculoAjeno->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $this->crearGrifo()->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id_vehiculo');
        $this->assertSame(0, Vale::count());
    }

    public function test_un_conductor_no_puede_emitir_vales(): void
    {
        $conductor = Conductor::factory()->create();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);

        $response = $this->actingAs($user, 'api')->postJson(route('api.v1.vales.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $this->crearGrifo()->id,
            'litros' => 20,
            'precio' => 6.97,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Vale::count());
    }

    public function test_un_administrador_emite_un_vale_para_cualquier_vehiculo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $admin->givePermissionTo('vales.crear');

        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($admin, 'api')->postJson(route('api.v1.vales.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $this->crearGrifo()->id,
            'litros' => 15,
            'precio' => 6.5,
        ]);

        $response->assertCreated();
        $this->assertSame(1, Vale::count());
    }

    public function test_emitir_vale_valida_los_campos_requeridos(): void
    {
        $jefe = $this->crearJefeDeArea(Area::factory()->create());

        $response = $this->actingAs($jefe, 'api')->postJson(route('api.v1.vales.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['litros', 'precio', 'id_vehiculo', 'id_conductor', 'id_grifo']);
    }

    public function test_show_devuelve_el_detalle_del_vale(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculo, $area);

        $this->actingAs($jefe);
        $vale = Vale::create([
            'litros' => 10, 'precio' => 6, 'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $this->crearGrifo()->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'estado_vale' => 'PENDIENTE',
        ]);

        $response = $this->actingAs($jefe, 'api')->getJson(route('api.v1.vales.show', $vale));

        $response->assertOk();
        $response->assertJsonPath('data.id', $vale->id);
        $response->assertJsonStructure(['data' => ['id', 'nro', 'estado_vale', 'vehiculo', 'conductor', 'grifo']]);
    }

    public function test_un_conductor_no_ve_el_vale_de_otro_en_show(): void
    {
        $this->actingAs(User::factory()->create());
        $vale = Vale::create([
            'litros' => 10, 'precio' => 6,
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $this->crearGrifo()->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'estado_vale' => 'PENDIENTE',
        ]);

        $intruso = Conductor::factory()->create();
        $user = User::factory()->create(['id_persona' => $intruso->id]);
        $user->assignRole('conductor');

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.vales.show', $vale));

        $response->assertStatus(403);
    }

    public function test_un_jefe_de_area_solo_ve_en_el_listado_los_vales_de_vehiculos_de_su_area(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $grifo = $this->crearGrifo();

        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);
        $valeDelArea = $this->crearVale($vehiculoDelArea, grifo: $grifo);

        $vehiculoAjeno = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoAjeno, Area::factory()->create());
        $this->crearVale($vehiculoAjeno, grifo: $grifo);

        $response = $this->actingAs($jefe, 'api')->getJson(route('api.v1.vales.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $valeDelArea->id);
    }

    public function test_un_conductor_con_rol_de_jefe_area_ve_en_el_listado_los_vales_de_su_area_no_solo_los_propios(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create();
        EncargadoArea::create([
            'id_persona' => $persona->id, 'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR', 'fecha_inicio' => now(), 'estado_encargo' => 'ACTIVO',
        ]);
        $conductorJefe = Conductor::factory()->create(['id' => $persona->id]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole(['conductor', 'jefe-area']);

        $grifo = $this->crearGrifo();

        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);
        // El vale es de OTRO conductor asignado al mismo vehículo, no del propio.
        $valeDeOtroConductor = $this->crearVale($vehiculoDelArea, Conductor::factory()->create(), $grifo);

        $vehiculoAjeno = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoAjeno, Area::factory()->create());
        $this->crearVale($vehiculoAjeno, grifo: $grifo);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.vales.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $valeDeOtroConductor->id);
    }

    public function test_un_conductor_puro_solo_ve_sus_propios_vales_en_el_listado(): void
    {
        $conductor = Conductor::factory()->create();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $grifo = $this->crearGrifo();
        $vehiculo = Vehiculo::factory()->create();
        $valePropio = $this->crearVale($vehiculo, $conductor, $grifo);
        $this->crearVale($vehiculo, Conductor::factory()->create(), $grifo);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.vales.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $valePropio->id);
    }

    public function test_un_administrador_ve_todos_los_vales_del_sistema_en_el_listado(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $grifo = $this->crearGrifo();
        $this->crearVale(Vehiculo::factory()->create(), grifo: $grifo);
        $this->crearVale(Vehiculo::factory()->create(), grifo: $grifo);

        $response = $this->actingAs($admin, 'api')->getJson(route('api.v1.vales.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
