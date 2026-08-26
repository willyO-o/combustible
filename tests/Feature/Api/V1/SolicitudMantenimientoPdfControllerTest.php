<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conductor;
use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\Repuesto;
use App\Models\SolicitudMantenimiento;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudMantenimientoPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        // SolicitudMantenimiento/OrdenTrabajo::calcularGestion()/getNroAttribute() leen este parámetro.
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

    private function crearConductor(): Conductor
    {
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function crearSolicitud(array $overrides = []): SolicitudMantenimiento
    {
        return SolicitudMantenimiento::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'fecha_solicitud' => now(),
            'estado' => 'PENDIENTE',
        ], $overrides));
    }

    public function test_un_conductor_puede_descargar_su_propia_solicitud_en_pdf(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        // El modelo asigna id_conductor a partir del usuario autenticado al crear
        // (ver boot() de SolicitudMantenimiento), de ahí el actingAs() previo.
        $this->actingAs($user, 'api');
        $solicitud = $this->crearSolicitud();

        $response = $this->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="solicitud_mantenimiento_'.str_replace('/', '-', $solicitud->nro).'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_un_administrador_puede_descargar_cualquier_solicitud(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin, 'api');

        $solicitud = $this->crearSolicitud();

        $response = $this->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_un_conductor_no_puede_descargar_la_solicitud_de_otro_conductor(): void
    {
        // Los assignRole() van antes de cualquier actingAs(..., 'api'): Spatie
        // resuelve el rol contra el guard activo en ese momento, así que
        // cambiar de guard antes de asignar el rol del segundo conductor
        // lo buscaría (y fallaría) bajo el guard "api".
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $otroConductor = $this->crearConductor();
        $otroUser = User::factory()->create(['id_persona' => $otroConductor->id]);
        $otroUser->assignRole('conductor');

        $this->actingAs($user, 'api');
        $solicitud = $this->crearSolicitud();

        $response = $this->actingAs($otroUser, 'api')
            ->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id));

        $response->assertStatus(403);
    }

    public function test_el_pdf_lista_los_trabajos_realizados_cuando_la_orden_de_trabajo_tiene_detalle(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin, 'api');

        $solicitud = $this->crearSolicitud();

        $tipoMantenimiento = TipoMantenimiento::create(['tipo_mantenimiento' => 'Cambio de aceite']);
        $repuesto = Repuesto::create([
            'nombre_repuesto' => 'Filtro de aceite',
            'codigo_repuesto' => 'FA-001',
            'unidad_medida' => 'UNIDAD',
        ]);

        $orden = OrdenTrabajo::create([
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_vehiculo' => $solicitud->id_vehiculo,
            'id_usuario_ejecuta' => $admin->id,
            'fecha_ejecucion' => now(),
            'horometro_actual' => 1500,
        ]);

        DetalleMantenimiento::create([
            'id_orden_trabajo' => $orden->id,
            'id_repuesto' => $repuesto->id,
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 2,
        ]);

        $response = $this->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_el_pdf_no_falla_cuando_la_orden_de_trabajo_aun_no_tiene_detalle(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin, 'api');

        $solicitud = $this->crearSolicitud();

        OrdenTrabajo::create([
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_vehiculo' => $solicitud->id_vehiculo,
            'id_usuario_ejecuta' => $admin->id,
        ]);

        $response = $this->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_requiere_autenticacion(): void
    {
        // El boot() del modelo toma id_conductor/id_usuario_registra de auth(),
        // así que para armar la fixture sin autenticar a nadie se crea sin
        // disparar ese evento (withoutEvents) y se completan esos campos a mano.
        $solicitud = SolicitudMantenimiento::withoutEvents(fn () => $this->crearSolicitud([
            'id_conductor' => $this->crearConductor()->id,
            'nro_solicitud' => 1,
            'gestion' => now()->year,
        ]));

        // Sin token: la ruta exige auth:api aunque la solicitud ya exista.
        $response = $this->get(route('api.v1.solicitudes-mantenimiento.pdf', $solicitud->id), [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }
}
