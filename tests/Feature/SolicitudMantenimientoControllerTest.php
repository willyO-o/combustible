<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudMantenimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);

        // SolicitudMantenimiento::calcularGestion()/getNroAttribute() leen este parámetro.
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

    private function crearUsuarioConductor(): User
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        return $user;
    }

    public function test_index_lista_las_solicitudes(): void
    {
        $this->crearSolicitud();
        $this->crearSolicitud();

        $response = $this->get(route('mantenimiento.solicitudes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Index')
            ->has('solicitudes.data', 2)
        );
    }

    public function test_show_muestra_el_detalle_sin_orden_de_trabajo(): void
    {
        $solicitud = $this->crearSolicitud();

        $response = $this->get(route('mantenimiento.solicitudes.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Show')
            ->where('solicitud.id', $solicitud->id)
            ->where('solicitud.orden_trabajo', null)
        );
    }

    public function test_show_incluye_la_orden_de_trabajo_generada(): void
    {
        $solicitud = $this->crearSolicitud();
        $orden = OrdenTrabajo::create([
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_vehiculo' => $solicitud->id_vehiculo,
            'id_usuario_ejecuta' => User::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        $response = $this->get(route('mantenimiento.solicitudes.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Show')
            ->where('solicitud.orden_trabajo.id', $orden->id)
        );
    }

    public function test_create_esta_bloqueado_para_usuarios_que_no_son_conductor(): void
    {
        $response = $this->get(route('mantenimiento.solicitudes.create'));

        $response->assertForbidden();
    }

    public function test_store_esta_bloqueado_para_usuarios_que_no_son_conductor(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->post(route('mantenimiento.solicitudes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('solicitud_mantenimiento', 0);
    }

    public function test_create_es_accesible_para_un_usuario_conductor(): void
    {
        $conductor = $this->crearUsuarioConductor();

        $response = $this->actingAs($conductor)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
        );
    }
}
