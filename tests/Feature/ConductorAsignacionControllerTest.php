<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConductorAsignacionControllerTest extends TestCase
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

    public function test_asignar_vehiculo_crea_la_asignacion_y_registra_el_usuario_automaticamente(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 15000,
            'detalle' => 'Asignación inicial',
        ]);

        $response->assertRedirect(route('conductores.index'));

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $conductor->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 15000,
            'id_usuario' => auth()->id(),
        ]);
    }

    public function test_asignar_un_nuevo_vehiculo_reasigna_la_anterior_del_mismo_conductor(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculoAnterior = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $asignacionAnterior = Asignacion::create([
            'id_vehiculo' => $vehiculoAnterior->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 1000,
        ]);

        $vehiculoNuevo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculoNuevo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
        ]);

        $response->assertRedirect(route('conductores.index'));

        $asignacionAnterior->refresh();
        $this->assertSame('REASIGNADO', $asignacionAnterior->estado_asignacion);
        $this->assertNotNull($asignacionAnterior->fecha_culminacion);

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $conductor->id,
            'id_vehiculo' => $vehiculoNuevo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_asignar_rechaza_un_vehiculo_ya_asignado_activamente_a_otro_conductor(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $otroConductor = Conductor::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 1000,
        ]);

        $conductor = Conductor::factory()->create();

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
        ]);

        $response->assertSessionHasErrors('id_vehiculo');
        $this->assertDatabaseMissing('asignacion', ['id_conductor' => $conductor->id]);
    }

    public function test_asignar_exige_el_kilometraje_inicial_para_un_vehiculo_medido_en_kilometraje(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('kilometraje_inicial');
    }

    public function test_asignar_exige_el_horometro_inicial_para_un_vehiculo_medido_en_horometro(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('horometro_inicial');
    }

    public function test_asignar_provisional_acepta_una_fecha_de_culminacion_planificada(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $fechaFin = now()->addDays(5)->format('Y-m-d');

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'PROVISIONAL',
            'kilometraje_inicial' => 500,
            'fecha_culminacion' => $fechaFin,
        ]);

        $response->assertRedirect(route('conductores.index'));

        $asignacion = Asignacion::where('id_conductor', $conductor->id)->where('estado_asignacion', 'PROVISIONAL')->firstOrFail();
        $this->assertSame($vehiculo->id, $asignacion->id_vehiculo);
        $this->assertSame($fechaFin, $asignacion->fecha_culminacion->format('Y-m-d'));
    }

    public function test_asignar_rechaza_una_fecha_de_culminacion_si_no_es_provisional(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
            'fecha_culminacion' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fecha_culminacion');
    }

    public function test_asignar_esta_bloqueado_para_un_conductor(): void
    {
        $conductorUser = User::factory()->create();
        $conductorUser->assignRole('conductor');

        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->actingAs($conductorUser)->post(route('conductores.asignaciones.asignar', $conductor->id), [
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('asignacion', ['id_conductor' => $conductor->id]);
    }

    public function test_finalizar_asignacion_la_marca_inactiva_sin_reemplazarla(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $asignacion = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
        ]);

        $response = $this->patch(route('conductores.asignaciones.finalizar', [$conductor->id, $asignacion->id]));

        $response->assertRedirect(route('conductores.index'));
        $asignacion->refresh();
        $this->assertSame('INACTIVO', $asignacion->estado_asignacion);
        $this->assertNotNull($asignacion->fecha_culminacion);
    }

    public function test_finalizar_asignacion_esta_bloqueado_para_quien_no_es_administrador_ni_jefe_de_area(): void
    {
        $conductorUser = User::factory()->create();
        $conductorUser->assignRole('conductor');

        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $asignacion = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 500,
        ]);

        $response = $this->actingAs($conductorUser)->patch(route('conductores.asignaciones.finalizar', [$conductor->id, $asignacion->id]));

        $response->assertForbidden();
        $this->assertSame('ACTIVO', $asignacion->fresh()->estado_asignacion);
    }
}
