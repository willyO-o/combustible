<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConductorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    public function test_show_incluye_los_datos_personales_el_vehiculo_actual_y_el_historial(): void
    {
        $conductor = Conductor::factory()->create();

        $vehiculoAnterior = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculoAnterior->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'fecha_culminacion' => now()->subDay(),
            'estado_asignacion' => 'REASIGNADO',
            'detalle' => 'Asignación anterior',
        ]);

        $vehiculoActual = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculoActual->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'detalle' => 'Asignación vigente',
        ]);

        $response = $this->actingAs($this->admin)->get(route('conductores.show', $conductor->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Show')
            ->where('conductor.id', $conductor->id)
            ->where('conductor.persona.ci', $conductor->persona->ci)
            ->has('conductor.asignaciones_activas', 1)
            ->where('conductor.asignaciones_activas.0.id', $vehiculoActual->id)
            ->has('historialAsignaciones', 2)
            ->where('historialAsignaciones.0.vehiculo.id', $vehiculoActual->id)
            ->where('historialAsignaciones.0.estado_asignacion', 'ACTIVO')
            ->where('historialAsignaciones.1.vehiculo.id', $vehiculoAnterior->id)
            ->where('historialAsignaciones.1.estado_asignacion', 'REASIGNADO')
        );
    }

    public function test_show_de_un_conductor_sin_asignaciones_no_falla(): void
    {
        $conductor = Conductor::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('conductores.show', $conductor->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Show')
            ->has('conductor.asignaciones_activas', 0)
            ->has('historialAsignaciones', 0)
        );
    }
}
