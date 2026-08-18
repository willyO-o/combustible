<?php

namespace Tests\Feature;

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
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->actingAs($this->admin);
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

    private function crearParametrosEmpresa(): void
    {
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_muestra_el_detalle_de_una_carga_de_combustible_tipo_prepago(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50,
            'precio' => 10,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'nro_factura' => 'F-001',
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->getJson(route('cargas.detalle', $carga->id));

        $response->assertOk()
            ->assertJsonPath('id', $carga->id)
            ->assertJsonPath('tipo_carga', 'PREPAGO')
            ->assertJsonPath('nro_factura', 'F-001')
            ->assertJsonPath('vale', null)
            ->assertJsonPath('vehiculo.nro_placa', $vehiculo->nro_placa)
            ->assertJsonPath('conductor.ci', $conductor->persona->ci)
            ->assertJsonPath('grifo.razon_social', 'Grifo Central')
            ->assertJsonPath('registrado_por', $this->admin->name);
    }

    public function test_muestra_el_detalle_de_una_carga_de_combustible_tipo_vale_con_datos_del_vale(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $this->crearParametrosEmpresa();

        $vale = Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'USADO',
            'id_tipo_combustible' => $tipoCombustible->id,
        ]);

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 40,
            'precio' => 9.5,
            'kilometraje' => 1200,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->getJson(route('cargas.detalle', $carga->id));

        $response->assertOk()
            ->assertJsonPath('vale.id', $vale->id)
            ->assertJsonPath('vale.nro', $vale->nro)
            ->assertJsonPath('vale.estado_vale', 'USADO')
            ->assertJsonPath('vale.grifo.razon_social', 'Grifo Central');
    }

    public function test_actualiza_el_kilometraje_de_una_carga_prepago(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50,
            'precio' => 10,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'nro_factura' => 'F-001',
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->put(route('cargas.update', $carga->id), [
            'fecha_carga' => $carga->fecha_carga->format('Y-m-d'),
            'litros' => $carga->litros,
            'precio' => $carga->precio,
            'kilometraje' => 1500,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => null,
            'nro_factura' => 'F-001',
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('cargas.index'));
        $this->assertEquals(1500, (float) $carga->fresh()->kilometraje);
    }

    public function test_actualiza_el_horometro_de_una_carga_tipo_vale_reenviando_el_mismo_vale_ya_usado(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $this->crearParametrosEmpresa();

        $vale = Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'USADO',
            'id_tipo_combustible' => $tipoCombustible->id,
        ]);

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 40,
            'precio' => 9.5,
            'horometro' => 100,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'estado_carga' => 'REGISTRADO',
        ]);

        // El vale ya está USADO: reenviarlo sin cambios (campo bloqueado en el formulario)
        // no debe fallar la validación de "vale PENDIENTE".
        $response = $this->put(route('cargas.update', $carga->id), [
            'fecha_carga' => $carga->fecha_carga->format('Y-m-d'),
            'litros' => $carga->litros,
            'precio' => $carga->precio,
            'horometro' => 150,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('cargas.index'));
        $this->assertEquals(150, (float) $carga->fresh()->horometro);
        $this->assertSame('USADO', $vale->fresh()->estado_vale);
    }

    private function crearVale(Vehiculo $vehiculo, Conductor $conductor, Grifo $grifo, TipoCombustible $tipoCombustible, array $overrides = []): Vale
    {
        $this->crearParametrosEmpresa();

        return Vale::create(array_merge([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'PENDIENTE',
            'id_tipo_combustible' => $tipoCombustible->id,
        ], $overrides));
    }

    public function test_create_precarga_los_datos_del_vale_cuando_se_usa_desde_el_listado(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);

        $response = $this->get(route('cargas.create', ['vale' => $vale->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('CargasCombustible/Create')
            ->where('valePreseleccionado.id', $vale->id)
            ->where('valePreseleccionado.litros', fn ($litros) => (float) $litros === 40.0)
            ->where('valePreseleccionado.precio', fn ($precio) => (float) $precio === 9.5)
            ->where('valePreseleccionado.id_vehiculo', $vehiculo->id)
            ->where('valePreseleccionado.id_conductor', $conductor->id)
            ->where('valePreseleccionado.id_grifo', $grifo->id)
        );
    }

    public function test_create_redirige_a_vales_si_el_vale_no_esta_pendiente(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible, ['estado_vale' => 'USADO']);

        $response = $this->get(route('cargas.create', ['vale' => $vale->id]));

        $response->assertRedirect(route('vales.index'));
        $response->assertSessionHas('error');
    }

    public function test_create_redirige_a_vales_si_el_vale_no_existe(): void
    {
        $response = $this->get(route('cargas.create', ['vale' => 999999]));

        $response->assertRedirect(route('vales.index'));
        $response->assertSessionHas('error', 'El vale seleccionado no existe.');
    }

    public function test_create_redirige_a_vales_si_el_vale_ya_tiene_una_carga_registrada(): void
    {
        // Estado inconsistente: el vale sigue PENDIENTE pero ya existe una
        // carga de combustible que lo referencia (no debería pasar en el
        // flujo normal, pero se valida como defensa adicional).
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);

        CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => $vale->litros,
            'precio' => $vale->precio,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->get(route('cargas.create', ['vale' => $vale->id]));

        $response->assertRedirect(route('vales.index'));
        $response->assertSessionHas('error', "El vale #{$vale->nro} ya tiene una carga de combustible registrada.");
    }

    public function test_store_usando_un_vale_ignora_conductor_litros_y_precio_manipulados(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);

        // Conductor/litros/precio distintos a los del vale, simulando una
        // petición manipulada: el controlador debe ignorarlos y usar siempre
        // los datos del vale (id_vehiculo sí queda cross-validado contra el
        // vale por CargaCombustibleRequest, así que aquí se mantiene correcto).
        $otroConductor = $this->crearConductor();

        $response = $this->post(route('cargas.store'), [
            'fecha_carga' => now()->format('Y-m-d'),
            'litros' => 999,
            'precio' => 999,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_vale' => $vale->id,
            'nro_factura' => 'F-100',
            'tipo_carga' => 'VALE',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('cargas.index'));

        $carga = CargaCombustible::latest('id')->first();
        $this->assertSame($vehiculo->id, $carga->id_vehiculo);
        $this->assertSame($conductor->id, $carga->id_conductor);
        $this->assertEquals(40, (float) $carga->litros);
        $this->assertEquals(9.5, (float) $carga->precio);
        $this->assertSame('F-100', $carga->nro_factura);
        $this->assertSame('USADO', $vale->fresh()->estado_vale);
    }
}
