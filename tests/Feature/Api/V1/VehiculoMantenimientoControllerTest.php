<?php

namespace Tests\Feature\Api\V1;

use App\Models\Asignacion;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\DetalleMantenimiento;
use App\Models\Grifo;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehiculoMantenimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'conductor', 'jefe-area', 'tecnico-mantenimiento', 'super-admin'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@example.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1, 'digitos_serie' => 6],
            'estado' => 'ACTIVO',
        ]);

        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Central', 'nit' => '123', 'direccion' => 'Av', 'ciudad' => 'LP',
            'telefono' => '700', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);
        $this->tipoCombustible = TipoCombustible::factory()->create();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    private function vehiculoConTipo(): Vehiculo
    {
        return Vehiculo::factory()->create([
            'id_tipo_vehiculo' => TipoVehiculo::factory()->create()->id,
            'tipo_medicion' => 'kilometraje',
        ]);
    }

    private function tipoMantenimiento(string $nombre): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => $nombre,
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
    }

    private function intervalo(Vehiculo $vehiculo, TipoMantenimiento $tipo, int $frecuencia): void
    {
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'id_tipo_mantenimiento' => $tipo->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => $frecuencia,
            'estado' => 'ACTIVO',
        ]);
    }

    private function conductorParaCarga(): Conductor
    {
        return Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function carga(Vehiculo $vehiculo, Conductor $conductor, float $kilometraje): void
    {
        CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50, 'precio' => 7, 'kilometraje' => $kilometraje,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO', 'estado_carga' => 'REGISTRADO',
        ]);
    }

    private function mantenimientoRegistrado(Vehiculo $vehiculo, TipoMantenimiento $tipo, float $kilometraje): void
    {
        $this->actingAs($this->admin); // OrdenTrabajo::boot() usa auth()->id().

        $orden = OrdenTrabajo::create([
            'id_vehiculo' => $vehiculo->id,
            'id_usuario_ejecuta' => $this->admin->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        DetalleMantenimiento::create([
            'id_orden_trabajo' => $orden->id,
            'id_tipo_mantenimiento' => $tipo->id,
            'fecha' => now(),
            'kilometraje' => $kilometraje,
            'cantidad' => 1,
        ]);
    }

    public function test_requiere_autenticacion(): void
    {
        $vehiculo = $this->vehiculoConTipo();

        $this->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $vehiculo))
            ->assertUnauthorized();
    }

    public function test_vehiculo_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', 999999))
            ->assertNotFound();
    }

    public function test_devuelve_alertas_con_estado_etiqueta_y_descripcion(): void
    {
        $vehiculo = $this->vehiculoConTipo();
        $aceite = $this->tipoMantenimiento('Cambio de aceite');
        $this->intervalo($vehiculo, $aceite, 10000);
        $this->carga($vehiculo, $this->conductorParaCarga(), 12000); // vencido: objetivo 10000, restante -2000

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $vehiculo));

        $response->assertOk();
        $response->assertJsonPath('data.vehiculo.id', $vehiculo->id);
        $response->assertJsonPath('data.resumen.total', 1);
        $response->assertJsonPath('data.resumen.vencidos', 1);
        $response->assertJsonPath('data.resumen.requiere_atencion', true);
        $response->assertJsonPath('data.alertas.0.tipo_mantenimiento', 'Cambio de aceite');
        $response->assertJsonPath('data.alertas.0.estado', 'VENCIDO');
        $response->assertJsonPath('data.alertas.0.estado_label', 'Vencido');
        $response->assertJsonPath('data.alertas.0.unidad', 'km');
        $response->assertJsonPath('data.alertas.0.proximo_objetivo', 10000);
        $response->assertJsonPath('data.alertas.0.restante', -2000);
        $this->assertStringContainsString('2.000 km', $response->json('data.alertas.0.descripcion'));
    }

    public function test_ordena_por_urgencia_y_calcula_el_resumen(): void
    {
        $vehiculo = $this->vehiculoConTipo();
        $conductor = $this->conductorParaCarga();
        $this->carga($vehiculo, $conductor, 12000);

        $vencido = $this->tipoMantenimiento('Cambio de aceite');       // objetivo 10000 -> VENCIDO
        $this->intervalo($vehiculo, $vencido, 10000);
        $alDia = $this->tipoMantenimiento('Cambio de neumáticos');     // objetivo 60000 -> AL_DIA
        $this->intervalo($vehiculo, $alDia, 60000);
        $sinDatos = $this->tipoMantenimiento('Cambio de correa');      // intervalo por horómetro sin lecturas
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'id_tipo_mantenimiento' => $sinDatos->id,
            'tipo_medicion' => 'horometro',
            'frecuencia' => 2000,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $vehiculo));

        $response->assertOk();
        $this->assertSame(
            ['VENCIDO', 'AL_DIA', 'SIN_DATOS'],
            $response->json('data.alertas.*.estado'),
        );
        $response->assertJsonPath('data.resumen.total', 3);
        $response->assertJsonPath('data.resumen.vencidos', 1);
        $response->assertJsonPath('data.resumen.al_dia', 1);
        $response->assertJsonPath('data.resumen.sin_datos', 1);
    }

    public function test_el_proximo_objetivo_avanza_tras_un_mantenimiento_registrado(): void
    {
        $vehiculo = $this->vehiculoConTipo();
        $aceite = $this->tipoMantenimiento('Cambio de aceite');
        $this->intervalo($vehiculo, $aceite, 10000);
        $this->carga($vehiculo, $this->conductorParaCarga(), 15000);
        $this->mantenimientoRegistrado($vehiculo, $aceite, 10200); // cubre el ciclo de 10000

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $vehiculo));

        $response->assertOk();
        $response->assertJsonPath('data.alertas.0.ultimo_mantenimiento', 10200);
        $response->assertJsonPath('data.alertas.0.proximo_objetivo', 20000);
        $response->assertJsonPath('data.alertas.0.estado', 'AL_DIA');
    }

    public function test_vehiculo_sin_intervalos_devuelve_lista_vacia(): void
    {
        $vehiculo = $this->vehiculoConTipo();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $vehiculo));

        $response->assertOk();
        $response->assertJsonPath('data.alertas', []);
        $response->assertJsonPath('data.resumen.total', 0);
        $response->assertJsonPath('data.resumen.requiere_atencion', false);
    }

    public function test_conductor_solo_ve_los_vehiculos_que_tiene_asignados(): void
    {
        $persona = Persona::factory()->create();
        $conductorUser = User::factory()->create(['id_persona' => $persona->id]);
        $conductorUser->assignRole('conductor');
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        $asignado = $this->vehiculoConTipo();
        Asignacion::create([
            'id_vehiculo' => $asignado->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        $ajeno = $this->vehiculoConTipo();

        $this->actingAs($conductorUser, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $asignado))
            ->assertOk();

        $this->actingAs($conductorUser, 'api')
            ->getJson(route('api.v1.vehiculos.mantenimiento-sugerido', $ajeno))
            ->assertForbidden();
    }
}
