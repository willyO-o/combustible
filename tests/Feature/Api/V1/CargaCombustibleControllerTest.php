<?php

namespace Tests\Feature\Api\V1;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargaCombustibleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    private function crearConductor(): Conductor
    {
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function crearGrifo(): Grifo
    {
        return Grifo::create([
            'razon_social' => 'Grifo Central',
            'nit' => '123456',
            'direccion' => 'Av. Siempre Viva',
            'ciudad' => 'La Paz',
            'telefono' => '70000000',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);
    }

    public function test_registra_una_carga_de_combustible_tipo_prepago_con_respaldo_digital(): void
    {
        Storage::fake('public');

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->post(route('api.v1.cargas.store'), [
                'fecha_carga' => now()->format('Y-m-d H:i'),
                'litros' => 50,
                'precio' => 10,
                'kilometraje' => 15600,
                'id_vehiculo' => $vehiculo->id,
                'id_grifo' => $grifo->id,
                'id_tipo_combustible' => $tipoCombustible->id,
                'id_conductor' => $conductor->id,
                'nro_factura' => 'F-001',
                'tipo_carga' => 'PREPAGO',
                'respaldos' => [
                    ['archivo' => UploadedFile::fake()->image('factura.jpg'), 'tipo' => 'FACTURA'],
                ],
            ]);

        $response->assertCreated();
        $response->assertJson(['message' => 'Carga de combustible creada exitosamente']);

        $this->assertDatabaseHas('carga_combustible', [
            'id_vehiculo' => $vehiculo->id,
            'nro_factura' => 'F-001',
            'tipo_carga' => 'PREPAGO',
            // Sin enviar estado_carga, la carga se crea como REGISTRADO.
            'estado_carga' => 'REGISTRADO',
        ]);

        $carga = CargaCombustible::first();
        $this->assertSame(1, $carga->respaldosDigitales()->count());
        $this->assertSame('FACTURA', $carga->respaldosDigitales()->first()->tipo_respaldo);
        Storage::disk('public')->assertExists($carga->respaldosDigitales()->first()->ruta_respaldo);
    }

    public function test_registra_una_carga_de_combustible_a_partir_de_un_vale_y_lo_marca_como_usado(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);

        $vale = Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'PENDIENTE',
            'id_tipo_combustible' => $tipoCombustible->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->post(route('api.v1.cargas.store'), [
                'fecha_carga' => now()->format('Y-m-d H:i'),
                'kilometraje' => 15600,
                'id_vehiculo' => $vehiculo->id,
                'id_grifo' => $grifo->id,
                'id_tipo_combustible' => $tipoCombustible->id,
                'id_conductor' => $conductor->id,
                'id_vale' => $vale->id,
                'tipo_carga' => 'VALE',
            ]);

        $response->assertCreated();

        $carga = CargaCombustible::first();
        $this->assertNotNull($carga);
        $this->assertEquals(40, $carga->litros);
        $this->assertEquals(9.5, $carga->precio);
        $this->assertSame('USADO', $vale->fresh()->estado_vale);
    }

    /**
     * La app Flutter puede registrar cargas sin conexión: para cuando se
     * sincronizan, el vale usado ya pudo cambiar de estado o vencer. Con
     * is_offline=true (sólo respetado en la API) esa carga igual se acepta.
     */
    public function test_registra_una_carga_offline_aunque_el_vale_ya_no_este_pendiente(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);

        $vale = Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'USADO',
            'id_tipo_combustible' => $tipoCombustible->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->post(route('api.v1.cargas.store'), [
                'fecha_carga' => now()->format('Y-m-d H:i'),
                'kilometraje' => 15600,
                'id_vehiculo' => $vehiculo->id,
                'id_grifo' => $grifo->id,
                'id_tipo_combustible' => $tipoCombustible->id,
                'id_conductor' => $conductor->id,
                'id_vale' => $vale->id,
                'tipo_carga' => 'VALE',
                'is_offline' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('carga_combustible', ['id_vehiculo' => $vehiculo->id, 'id_vale' => $vale->id]);
    }

    /**
     * Contraparte del test anterior: sin is_offline, el mismo vale ya no
     * disponible sigue rechazando la petición (la excepción no aplica por defecto).
     */
    public function test_rechaza_un_vale_no_disponible_sin_is_offline(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);

        $vale = Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'USADO',
            'id_tipo_combustible' => $tipoCombustible->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson(route('api.v1.cargas.store'), [
                'fecha_carga' => now()->format('Y-m-d H:i'),
                'kilometraje' => 15600,
                'id_vehiculo' => $vehiculo->id,
                'id_grifo' => $grifo->id,
                'id_tipo_combustible' => $tipoCombustible->id,
                'id_conductor' => $conductor->id,
                'id_vale' => $vale->id,
                'tipo_carga' => 'VALE',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('id_vale');
    }

    public function test_lista_las_cargas_de_combustible(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);

        CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50,
            'precio' => 10,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.cargas.index'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
