<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehiculoAreaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);
    }

    public function test_asignar_area_crea_la_asignacion(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();

        $response = $this->post(route('vehiculos.areas.asignar', $vehiculo->id), [
            'id_area' => $area->id,
            'estado_asignacion' => 'ACTIVO',
            'motivo_asignacion' => 'Asignación inicial',
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo_area', [
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_asignar_una_nueva_area_reasigna_la_anterior_del_mismo_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $areaAnterior = Area::factory()->create();
        $asignacionAnterior = VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaAnterior->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $areaNueva = Area::factory()->create();

        $response = $this->post(route('vehiculos.areas.asignar', $vehiculo->id), [
            'id_area' => $areaNueva->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $asignacionAnterior->refresh();
        $this->assertSame('REASIGNADO', $asignacionAnterior->estado_asignacion);
        $this->assertNotNull($asignacionAnterior->fecha_reasignacion);

        $this->assertDatabaseHas('vehiculo_area', [
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaNueva->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_asignar_provisional_acepta_una_fecha_de_culminacion_planificada(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();
        $fechaFin = now()->addDays(5)->format('Y-m-d');

        $response = $this->post(route('vehiculos.areas.asignar', $vehiculo->id), [
            'id_area' => $area->id,
            'estado_asignacion' => 'PROVISIONAL',
            'fecha_culminacion' => $fechaFin,
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $asignacion = VehiculoArea::where('id_vehiculo', $vehiculo->id)->where('estado_asignacion', 'PROVISIONAL')->firstOrFail();
        $this->assertSame($area->id, $asignacion->id_area);
        $this->assertSame($fechaFin, $asignacion->fecha_culminacion->format('Y-m-d'));
    }

    public function test_asignar_rechaza_una_fecha_de_culminacion_si_no_es_provisional(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();

        $response = $this->post(route('vehiculos.areas.asignar', $vehiculo->id), [
            'id_area' => $area->id,
            'estado_asignacion' => 'ACTIVO',
            'fecha_culminacion' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fecha_culminacion');
    }

    public function test_asignar_esta_bloqueado_para_un_conductor(): void
    {
        $conductorUser = User::factory()->create();
        $conductorUser->assignRole('conductor');

        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();

        $response = $this->actingAs($conductorUser)->post(route('vehiculos.areas.asignar', $vehiculo->id), [
            'id_area' => $area->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('vehiculo_area', ['id_vehiculo' => $vehiculo->id]);
    }

    public function test_finalizar_asignacion_la_marca_culminada_sin_reemplazarla(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();
        $asignacion = VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->patch(route('vehiculos.areas.finalizar', [$vehiculo->id, $asignacion->id]));

        $response->assertRedirect(route('vehiculos.index'));
        $asignacion->refresh();
        $this->assertSame('CULMINADO', $asignacion->estado_asignacion);
        $this->assertNotNull($asignacion->fecha_culminacion);
    }

    public function test_finalizar_asignacion_esta_bloqueado_para_quien_no_es_administrador_ni_jefe_de_area(): void
    {
        $conductorUser = User::factory()->create();
        $conductorUser->assignRole('conductor');

        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();
        $asignacion = VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($conductorUser)->patch(route('vehiculos.areas.finalizar', [$vehiculo->id, $asignacion->id]));

        $response->assertForbidden();
        $this->assertSame('ACTIVO', $asignacion->fresh()->estado_asignacion);
    }

    public function test_vehiculo_areas_asignadas_solo_cuenta_la_asignacion_realmente_activa(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $areaAntigua = Area::factory()->create();
        // Fila histórica ya reasignada, sin fecha_culminacion registrada
        // (el escenario que producía el falso positivo antes del fix).
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaAntigua->id,
            'fecha_asignacion' => now()->subMonths(2),
            'estado_asignacion' => 'REASIGNADO',
        ]);

        $this->assertNull($vehiculo->areasAsignadas()->first());

        $areaActual = Area::factory()->create();
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaActual->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $this->assertSame($areaActual->id, $vehiculo->areasAsignadas()->first()->id);
    }
}
