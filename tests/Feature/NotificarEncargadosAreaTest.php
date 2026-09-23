<?php

namespace Tests\Feature;

use App\Events\CargaCombustibleRegistrada;
use App\Events\MantenimientoSolicitado;
use App\Models\Area;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use App\Notifications\CargaCombustibleAreaNotification;
use App\Notifications\CargaCombustibleRegistradaNotification;
use App\Notifications\NotificacionFormatter;
use App\Notifications\SolicitudMantenimientoRegistradaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificarEncargadosAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 30],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearJefe(Area $area, array $overrides = [], array $encargo = []): User
    {
        $persona = Persona::factory()->create();

        EncargadoArea::create(array_merge([
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ], $encargo));

        return User::factory()->create(array_merge(['id_persona' => $persona->id], $overrides));
    }

    private function vehiculoEnArea(Area $area): Vehiculo
    {
        $vehiculo = Vehiculo::factory()->create();

        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return $vehiculo;
    }

    private function crearCarga(Vehiculo $vehiculo, ?Vale $vale = null, ?User $registra = null): CargaCombustible
    {
        $this->actingAs($registra ?? User::factory()->create());

        return CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 40,
            'precio' => 9.5,
            'kilometraje' => 1200,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $vale?->id_grifo ?? $this->crearGrifo()->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_vale' => $vale?->id,
            'tipo_carga' => $vale ? 'VALE' : 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);
    }

    private function crearGrifo(): Grifo
    {
        return Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);
    }

    private function crearVale(Vehiculo $vehiculo, User $emisor): Vale
    {
        $this->actingAs($emisor);

        return Vale::create([
            'litros' => 40,
            'precio' => 9.5,
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $this->crearGrifo()->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'estado_vale' => 'USADO',
        ]);
    }

    private function crearSolicitud(Vehiculo $vehiculo, ?User $registra = null): SolicitudMantenimiento
    {
        $this->actingAs($registra ?? User::factory()->create());

        return SolicitudMantenimiento::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'fecha_solicitud' => now(),
            'estado' => 'PENDIENTE',
        ]);
    }

    public function test_area_encargados_user_une_por_id_persona_y_no_por_id_de_usuario(): void
    {
        $area = Area::factory()->create();

        // Desfasa los ids para que users.id nunca coincida con persona.id.
        User::factory()->count(3)->create();
        $jefe = $this->crearJefe($area);

        $this->assertNotEquals($jefe->id, $jefe->id_persona);
        $this->assertEquals([$jefe->id], $area->encargadosUserActivos()->pluck('users.id')->all());
    }

    public function test_carga_sin_vale_notifica_a_los_jefes_del_area_del_vehiculo(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $jefe1 = $this->crearJefe($area);
        $jefe2 = $this->crearJefe($area);
        $carga = $this->crearCarga($this->vehiculoEnArea($area));

        CargaCombustibleRegistrada::dispatch($carga);

        Notification::assertSentTo($jefe1, CargaCombustibleAreaNotification::class);
        Notification::assertSentTo($jefe2, CargaCombustibleAreaNotification::class);
        Notification::assertNothingSentTo(User::factory()->create(), CargaCombustibleAreaNotification::class);
    }

    public function test_carga_con_vale_avisa_tu_vale_al_emisor_y_area_a_los_demas_jefes(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $vehiculo = $this->vehiculoEnArea($area);
        $emisor = $this->crearJefe($area);
        $otroJefe = $this->crearJefe($area);
        $vale = $this->crearVale($vehiculo, $emisor);
        $carga = $this->crearCarga($vehiculo, $vale);

        CargaCombustibleRegistrada::dispatch($carga);

        Notification::assertSentTo($emisor, CargaCombustibleRegistradaNotification::class);
        Notification::assertNotSentTo($emisor, CargaCombustibleAreaNotification::class);
        Notification::assertSentTo($otroJefe, CargaCombustibleAreaNotification::class);
        Notification::assertNotSentTo($otroJefe, CargaCombustibleRegistradaNotification::class);
    }

    public function test_quien_registra_la_carga_no_se_notifica_a_si_mismo(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $jefe = $this->crearJefe($area);
        $carga = $this->crearCarga($this->vehiculoEnArea($area), null, $jefe);

        CargaCombustibleRegistrada::dispatch($carga);

        Notification::assertNothingSent();
    }

    public function test_no_notifica_a_jefes_inactivos_ni_con_encargo_finalizado_ni_de_otra_area(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $this->crearJefe($area, ['estado_usuario' => 'INACTIVO']);
        $this->crearJefe($area, [], ['estado_encargo' => 'INACTIVO']);
        $this->crearJefe(Area::factory()->create());
        $carga = $this->crearCarga($this->vehiculoEnArea($area));

        CargaCombustibleRegistrada::dispatch($carga);

        Notification::assertNothingSent();
    }

    public function test_vehiculo_sin_area_no_falla_ni_notifica(): void
    {
        Notification::fake();
        $carga = $this->crearCarga(Vehiculo::factory()->create());

        CargaCombustibleRegistrada::dispatch($carga);

        Notification::assertNothingSent();
    }

    public function test_vehiculo_en_dos_areas_notifica_a_los_jefes_de_ambas_una_sola_vez(): void
    {
        Notification::fake();
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();
        $jefe1 = $this->crearJefe($area1);
        $jefe2 = $this->crearJefe($area2);
        $vehiculo = $this->vehiculoEnArea($area1);
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area2->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);

        MantenimientoSolicitado::dispatch($this->crearSolicitud($vehiculo));

        Notification::assertSentToTimes($jefe1, SolicitudMantenimientoRegistradaNotification::class, 1);
        Notification::assertSentToTimes($jefe2, SolicitudMantenimientoRegistradaNotification::class, 1);
    }

    public function test_solicitud_de_mantenimiento_notifica_a_los_jefes_del_area(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $jefe = $this->crearJefe($area);
        $solicitud = $this->crearSolicitud($this->vehiculoEnArea($area));

        MantenimientoSolicitado::dispatch($solicitud);

        Notification::assertSentTo($jefe, SolicitudMantenimientoRegistradaNotification::class, function ($notificacion) use ($jefe, $solicitud) {
            $data = $notificacion->toArray($jefe);

            return $data['tipo'] === 'solicitud_mantenimiento_registrada'
                && $data['id_solicitud'] === $solicitud->id
                && $notificacion->toFcm($jefe)['title'] === 'Nueva solicitud de mantenimiento';
        });
    }

    public function test_solicitud_no_notifica_al_jefe_que_la_registro(): void
    {
        Notification::fake();
        $area = Area::factory()->create();
        $jefe = $this->crearJefe($area);
        $otroJefe = $this->crearJefe($area);
        $solicitud = $this->crearSolicitud($this->vehiculoEnArea($area), $jefe);

        MantenimientoSolicitado::dispatch($solicitud);

        Notification::assertNotSentTo($jefe, SolicitudMantenimientoRegistradaNotification::class);
        Notification::assertSentTo($otroJefe, SolicitudMantenimientoRegistradaNotification::class);
    }

    public function test_formatter_redacta_los_tipos_nuevos_y_el_texto_de_tu_vale(): void
    {
        $emisor = NotificacionFormatter::formatear(['tipo' => 'carga_combustible_registrada', 'nro' => '1/2026', 'litros' => 40]);
        $area = NotificacionFormatter::formatear(['tipo' => 'carga_combustible_area', 'nro' => '1/2026', 'litros' => 40, 'placa' => 'ABC-123']);
        $solicitud = NotificacionFormatter::formatear(['tipo' => 'solicitud_mantenimiento_registrada', 'nro' => '2/2026', 'placa' => 'ABC-123']);

        $this->assertStringContainsString('de tu vale', $emisor['descripcion']);
        $this->assertSame('Carga de combustible en tu área', $area['titulo']);
        $this->assertStringContainsString('ABC-123', $area['descripcion']);
        $this->assertStringContainsString('ABC-123', $solicitud['descripcion']);
    }
}
