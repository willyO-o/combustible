<?php

namespace Tests\Feature;

use App\Models\OrdenTrabajo;
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
}
