<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use App\Notifications\CargaCombustibleRegistradaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    /**
     * El listado sin fecha_desde/fecha_hasta en el request debe llegar ya
     * filtrado por "Este mes" desde el servidor (1º del mes actual -> hoy):
     * evita que el frontend tenga que disparar una segunda petición para
     * aplicar el rango por defecto de DateRangeFilter.vue.
     */
    public function test_index_filtra_por_defecto_el_mes_actual(): void
    {
        $this->crearParametrosEmpresa();

        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $cargaDeEsteMes = CargaCombustible::create([
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

        $cargaDelMesPasado = CargaCombustible::create([
            'fecha_carga' => now()->subMonth(),
            'litros' => 30,
            'precio' => 10,
            'kilometraje' => 900,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->get(route('cargas.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.fecha_desde', now()->startOfMonth()->format('Y-m-d'))
                ->where('filters.fecha_hasta', now()->format('Y-m-d'))
            );

        $ids = collect($response->original->getData()['page']['props']['cargas']['data'])->pluck('id');
        $this->assertTrue($ids->contains($cargaDeEsteMes->id));
        $this->assertFalse($ids->contains($cargaDelMesPasado->id));

        // Limpiar el filtro (fecha_desde/fecha_hasta explícitos, no ausentes)
        // debe mostrar de nuevo la carga del mes pasado.
        $response = $this->get(route('cargas.index', ['fecha_desde' => '', 'fecha_hasta' => '']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.fecha_desde', null)
                ->where('filters.fecha_hasta', null)
            );

        $ids = collect($response->original->getData()['page']['props']['cargas']['data'])->pluck('id');
        $this->assertTrue($ids->contains($cargaDeEsteMes->id));
        $this->assertTrue($ids->contains($cargaDelMesPasado->id));
    }

    public function test_muestra_el_detalle_de_una_carga_de_combustible_tipo_prepago(): void
    {
        $this->crearParametrosEmpresa();

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
        $this->crearParametrosEmpresa();

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

    /**
     * is_offline sólo tiene efecto viniendo de la API (registro sin conexión
     * desde la app Flutter): en el formulario web, con un vale ya no
     * disponible, la validación debe seguir rechazando la petición aunque se
     * envíe is_offline=true.
     */
    public function test_is_offline_no_tiene_efecto_en_el_formulario_web(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible, ['estado_vale' => 'USADO']);

        $response = $this->post(route('cargas.store'), [
            'fecha_carga' => now()->format('Y-m-d'),
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'is_offline' => true,
        ]);

        $response->assertSessionHasErrors('id_vale');
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

    public function test_store_usando_un_vale_ignora_conductor_litros_precio_y_tipo_carga_manipulados(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);

        // Conductor/litros/precio/tipo_carga distintos a los del vale,
        // simulando una petición manipulada: el controlador debe ignorarlos
        // y usar siempre los datos del vale, incluyendo forzar tipo_carga a
        // VALE (id_vehiculo sí queda cross-validado contra el vale por
        // CargaCombustibleRequest, así que aquí se mantiene correcto).
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
            'tipo_carga' => 'PREPAGO',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('cargas.index'));

        $carga = CargaCombustible::latest('id')->first();
        $this->assertSame($vehiculo->id, $carga->id_vehiculo);
        $this->assertSame($conductor->id, $carga->id_conductor);
        $this->assertEquals(40, (float) $carga->litros);
        $this->assertEquals(9.5, (float) $carga->precio);
        $this->assertSame('VALE', $carga->tipo_carga);
        $this->assertSame('F-100', $carga->nro_factura);
        $this->assertSame('USADO', $vale->fresh()->estado_vale);
    }

    public function test_store_con_vale_notifica_a_quien_lo_emitio(): void
    {
        Notification::fake();

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        // El vale lo emite otro usuario (jefe de área); la carga la registra
        // $this->admin: la notificación debe ir al emisor del vale, no a
        // quien registra la carga.
        $jefe = User::factory()->create();
        $jefe->assignRole('administrador');
        $this->actingAs($jefe);
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);
        $this->assertSame($jefe->id, $vale->id_user);

        $this->actingAs($this->admin)->post(route('cargas.store'), [
            'fecha_carga' => now()->format('Y-m-d'),
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
        ])->assertSessionDoesntHaveErrors()->assertRedirect(route('cargas.index'));

        Notification::assertSentTo($jefe, CargaCombustibleRegistradaNotification::class);
    }

    public function test_store_sin_vale_no_genera_notificacion(): void
    {
        Notification::fake();

        $this->crearParametrosEmpresa();
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $this->post(route('cargas.store'), [
            'fecha_carga' => now()->format('Y-m-d'),
            'litros' => 30,
            'precio' => 7,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'tipo_carga' => 'PREPAGO',
        ])->assertSessionDoesntHaveErrors()->assertRedirect(route('cargas.index'));

        Notification::assertNothingSent();
    }

    public function test_update_con_vale_fuerza_tipo_carga_vale_aunque_se_envie_prepago(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible, ['estado_vale' => 'USADO']);

        $carga = CargaCombustible::create([
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

        $response = $this->put(route('cargas.update', $carga->id), [
            'fecha_carga' => $carga->fecha_carga->format('Y-m-d'),
            'litros' => $carga->litros,
            'precio' => $carga->precio,
            'kilometraje' => 1200,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('cargas.index'));
        $this->assertSame('VALE', $carga->fresh()->tipo_carga);
    }

    /**
     * El select de vales al elegir un vehículo (Create.vue::cambioVehiculo)
     * sólo debe ofrecer vales PENDIENTE y vigentes: ni USADO/ANULADO ni
     * vencidos, aunque sigan PENDIENTE (ver también CargaCombustibleRequest,
     * que ya exigía esto al guardar).
     */
    public function test_search_vales_excluye_vencidos_usados_y_anulados(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $valeVigente = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible);

        $valeVencido = Vale::create([
            'litros' => 40, 'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id, 'id_conductor' => $conductor->id, 'id_grifo' => $grifo->id,
            'estado_vale' => 'PENDIENTE', 'id_tipo_combustible' => $tipoCombustible->id,
        ]);
        $valeVencido->update(['fecha_vencimiento' => now()->subDay()]);

        $valeUsado = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible, ['estado_vale' => 'USADO']);

        $response = $this->getJson(route('search.vales-carga', ['id_vehiculo' => $vehiculo->id]));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($valeVigente->id));
        $this->assertFalse($ids->contains($valeVencido->id));
        $this->assertFalse($ids->contains($valeUsado->id));
    }

    public function test_destroy_elimina_una_carga_prepago(): void
    {
        $this->crearParametrosEmpresa();

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
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->delete(route('cargas.destroy', $carga->id));

        $response->assertRedirect(route('cargas.index'));
        $this->assertDatabaseMissing('carga_combustible', ['id' => $carga->id]);
    }

    /**
     * Una carga registrada con un vale no debe poder eliminarse, sin
     * importar el rol: dejaría el vale (ya USADO) huérfano/inconsistente.
     * Ver también el botón oculto en CargasCombustible/Index.vue.
     */
    public function test_destroy_rechaza_una_carga_registrada_con_un_vale(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();
        $vale = $this->crearVale($vehiculo, $conductor, $grifo, $tipoCombustible, ['estado_vale' => 'USADO']);

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->delete(route('cargas.destroy', $carga->id));

        $response->assertRedirect(route('cargas.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('carga_combustible', ['id' => $carga->id]);
    }

    public function test_imprime_el_comprobante_de_egreso_de_una_carga_de_combustible(): void
    {
        $this->crearParametrosEmpresa();

        $tipoVehiculo = TipoVehiculo::factory()->create(['tipo_vehiculo' => 'Camioneta']);
        $vehiculo = Vehiculo::factory()->create(['id_tipo_vehiculo' => $tipoVehiculo->id]);
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $area = Area::factory()->create();
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        EncargadoArea::create([
            'id_persona' => $conductor->persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 30,
            'precio' => 6.96,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
            'concepto' => 'Trabajos administrativos',
        ]);

        $response = $this->get(route('cargas.comprobante', $carga->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * Un vehículo sin área asignada no debe romper la generación del PDF:
     * los campos Área/Encargado de área quedan en "N/A".
     */
    public function test_imprime_el_comprobante_de_egreso_cuando_el_vehiculo_no_tiene_area_asignada(): void
    {
        $this->crearParametrosEmpresa();

        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->crearConductor();
        $grifo = $this->crearGrifo();
        $tipoCombustible = TipoCombustible::factory()->create();

        $carga = CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 30,
            'precio' => 6.96,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);

        $response = $this->get(route('cargas.comprobante', $carga->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
