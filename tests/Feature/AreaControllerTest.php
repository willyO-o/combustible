<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AreaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);
    }

    public function test_index_lista_las_areas_con_su_cantidad_de_encargados_y_vehiculos(): void
    {
        $area = Area::factory()->create();
        EncargadoArea::create([
            'id_persona' => Persona::factory()->create()->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $response = $this->get(route('areas.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Areas/Index')
            ->where('areas.data.0.encargados_count', 1)
            ->where('areas.data.0.vehiculos_count', 0)
        );
    }

    private function asignarVehiculoAArea(Area $area, Vehiculo $vehiculo, array $pivot = []): void
    {
        VehiculoArea::create(array_merge([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ], $pivot));
    }

    public function test_show_muestra_el_area_con_sus_vehiculos_asignados_vigentes(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'AREA-VH-1']);
        $this->asignarVehiculoAArea($area, $vehiculo, ['motivo_asignacion' => 'Operaciones']);

        $response = $this->get(route('areas.show', $area->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Areas/Show')
            ->where('area.id', $area->id)
            ->where('resumen.vehiculos_vigentes', 1)
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculo->id)
            ->where('vehiculos.0.codigo', 'AREA-VH-1')
            ->where('vehiculos.0.estado_asignacion', 'ACTIVO')
        );
    }

    public function test_show_excluye_de_la_tabla_principal_las_asignaciones_no_vigentes_pero_las_deja_en_el_historial(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($area, $vehiculo, [
            'estado_asignacion' => 'REASIGNADO',
            'fecha_reasignacion' => now(),
        ]);

        $response = $this->get(route('areas.show', $area->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('resumen.vehiculos_vigentes', 0)
            ->has('vehiculos', 0)
            ->has('historialVehiculos', 1)
            ->where('historialVehiculos.0.estado_asignacion', 'REASIGNADO')
        );
    }

    public function test_show_trae_el_conductor_actual_de_cada_vehiculo_asignado(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($area, $vehiculo);

        $conductor = Conductor::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->get(route('areas.show', $area->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.0.conductor.nombre_completo', $conductor->persona->nombre_completo)
        );
    }

    public function test_show_no_incluye_vehiculos_de_otra_area(): void
    {
        $area = Area::factory()->create();
        $otraArea = Area::factory()->create();
        $this->asignarVehiculoAArea($otraArea, Vehiculo::factory()->create());

        $response = $this->get(route('areas.show', $area->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos', 0)
            ->has('historialVehiculos', 0)
        );
    }

    public function test_show_incluye_los_encargados_vigentes_e_historicos(): void
    {
        $area = Area::factory()->create();
        EncargadoArea::create([
            'id_persona' => Persona::factory()->create()->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);
        EncargadoArea::create([
            'id_persona' => Persona::factory()->create()->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subYear(),
            'fecha_reasignacion' => now()->subMonth(),
            'estado_encargo' => 'INACTIVO',
        ]);

        $response = $this->get(route('areas.show', $area->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('encargados', 2)
            ->where('resumen.encargados_vigentes', 1)
            ->where('encargados.0.vigente', true)
            ->where('encargados.1.vigente', false)
        );
    }

    public function test_reasignar_vehiculo_desde_la_ficha_cierra_la_asignacion_previa_y_crea_la_nueva(): void
    {
        $areaA = Area::factory()->create();
        $areaB = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($areaA, $vehiculo);

        $response = $this->post(route('areas.vehiculos.reasignar', [$areaA->id, $vehiculo->id]), [
            'id_area' => $areaB->id,
            'estado_asignacion' => 'PROVISIONAL',
            'fecha_culminacion' => now()->addWeek()->format('Y-m-d'),
            'motivo_asignacion' => 'Préstamo por campaña',
        ]);

        $response->assertRedirect(route('areas.show', $areaA->id));
        $this->assertDatabaseHas('vehiculo_area', [
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaA->id,
            'estado_asignacion' => 'REASIGNADO',
        ]);
        $this->assertDatabaseHas('vehiculo_area', [
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $areaB->id,
            'estado_asignacion' => 'PROVISIONAL',
            'motivo_asignacion' => 'Préstamo por campaña',
        ]);
    }

    public function test_reasignar_vehiculo_exige_area_y_tipo_de_asignacion(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($area, $vehiculo);

        $this->post(route('areas.vehiculos.reasignar', [$area->id, $vehiculo->id]), [])
            ->assertSessionHasErrors(['id_area', 'estado_asignacion']);
    }

    public function test_reasignar_vehiculo_bloqueado_para_quien_no_es_admin_ni_jefe_de_area(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($area, $vehiculo);

        $this->actingAs(User::factory()->create())
            ->post(route('areas.vehiculos.reasignar', [$area->id, $vehiculo->id]), [
                'id_area' => $area->id,
                'estado_asignacion' => 'ACTIVO',
            ])
            ->assertForbidden();
    }

    public function test_finalizar_asignacion_de_vehiculo_la_marca_culminada(): void
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($area, $vehiculo);
        $asignacion = VehiculoArea::where('id_vehiculo', $vehiculo->id)->first();

        $response = $this->patch(route('areas.vehiculos.finalizar', [$area->id, $asignacion->id]));

        $response->assertRedirect(route('areas.show', $area->id));
        $asignacion->refresh();
        $this->assertSame('CULMINADO', $asignacion->estado_asignacion);
        $this->assertNotNull($asignacion->fecha_culminacion);
    }

    public function test_finalizar_asignacion_de_vehiculo_de_otra_area_devuelve_404(): void
    {
        $area = Area::factory()->create();
        $otraArea = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($otraArea, $vehiculo);
        $asignacion = VehiculoArea::where('id_vehiculo', $vehiculo->id)->first();

        $this->patch(route('areas.vehiculos.finalizar', [$area->id, $asignacion->id]))
            ->assertNotFound();
    }

    public function test_store_crea_un_area(): void
    {
        $response = $this->post(route('areas.store'), [
            'nombre_area' => 'Mantenimiento',
            'descripcion_area' => 'Taller y mantenimiento de flota',
            'estado_area' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('areas.index'));
        $this->assertDatabaseHas('area', [
            'nombre_area' => 'Mantenimiento',
            'estado_area' => 'ACTIVO',
        ]);
    }

    public function test_store_rechaza_un_nombre_duplicado(): void
    {
        Area::factory()->create(['nombre_area' => 'Logística']);

        $response = $this->post(route('areas.store'), [
            'nombre_area' => 'Logística',
            'estado_area' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('nombre_area');
    }

    public function test_update_actualiza_el_area(): void
    {
        $area = Area::factory()->create(['nombre_area' => 'Original']);

        $response = $this->put(route('areas.update', $area->id), [
            'nombre_area' => 'Renombrada',
            'descripcion_area' => 'Nueva descripción',
            'estado_area' => 'INACTIVO',
        ]);

        $response->assertRedirect(route('areas.index'));
        $this->assertSame('Renombrada', $area->fresh()->nombre_area);
        $this->assertSame('INACTIVO', $area->fresh()->estado_area);
    }

    public function test_destroy_elimina_un_area_sin_encargados_ni_vehiculos(): void
    {
        $area = Area::factory()->create();

        $response = $this->delete(route('areas.destroy', $area->id));

        $response->assertRedirect(route('areas.index'));
        $this->assertDatabaseMissing('area', ['id' => $area->id]);
    }

    public function test_destroy_no_elimina_si_hay_encargados_asignados(): void
    {
        $area = Area::factory()->create();
        EncargadoArea::create([
            'id_persona' => Persona::factory()->create()->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $response = $this->delete(route('areas.destroy', $area->id));

        $response->assertRedirect(route('areas.index'));
        $this->assertDatabaseHas('area', ['id' => $area->id]);
    }

    public function test_asignar_encargado_crea_el_usuario_y_le_asigna_el_rol_jefe_area(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);

        $response = $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
            'email' => 'nuevo.encargado@example.com',
        ]);

        $response->assertRedirect(route('areas.index'));

        $this->assertDatabaseHas('encargado_area', [
            'id_area' => $area->id,
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
            'estado_encargo' => 'ACTIVO',
        ]);

        $usuario = $persona->fresh()->user;
        $this->assertNotNull($usuario);
        $this->assertSame('nuevo.encargado@example.com', $usuario->email);
        $this->assertTrue($usuario->hasRole('jefe-area'));
    }

    public function test_asignar_encargado_con_persona_que_ya_tiene_usuario_solo_sincroniza_el_rol(): void
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'email' => 'existente@example.com']);
        $usuario->assignRole('conductor');

        $response = $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
        ]);

        $response->assertRedirect(route('areas.index'));

        $this->assertSame(1, User::where('id_persona', $persona->id)->count());
        $usuario->refresh();
        $this->assertSame('existente@example.com', $usuario->email);
        $this->assertTrue($usuario->hasRole('jefe-area'));
        $this->assertFalse($usuario->hasRole('conductor'));
    }

    public function test_asignar_un_nuevo_titular_inactiva_al_titular_anterior_del_area(): void
    {
        $area = Area::factory()->create();
        $anterior = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        $encargoAnterior = EncargadoArea::create([
            'id_persona' => $anterior->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now()->subMonth(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $nuevo = Persona::factory()->create(['estado_persona' => 'ACTIVO']);

        $response = $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $nuevo->id,
            'tipo_encargo' => 'TITULAR',
            'email' => 'nuevo.titular@example.com',
        ]);

        $response->assertRedirect(route('areas.index'));

        $encargoAnterior->refresh();
        $this->assertSame('INACTIVO', $encargoAnterior->estado_encargo);
        $this->assertNotNull($encargoAnterior->fecha_reasignacion);

        $this->assertDatabaseHas('encargado_area', [
            'id_area' => $area->id,
            'id_persona' => $nuevo->id,
            'tipo_encargo' => 'TITULAR',
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_asignar_suplente_no_afecta_al_titular_activo(): void
    {
        $area = Area::factory()->create();
        $titular = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        $encargoTitular = EncargadoArea::create([
            'id_persona' => $titular->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $suplente = Persona::factory()->create(['estado_persona' => 'ACTIVO']);

        $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $suplente->id,
            'tipo_encargo' => 'SUPLENTE',
            'email' => 'suplente@example.com',
        ])->assertRedirect(route('areas.index'));

        $this->assertSame('ACTIVO', $encargoTitular->fresh()->estado_encargo);
        $this->assertDatabaseHas('encargado_area', [
            'id_area' => $area->id,
            'id_persona' => $suplente->id,
            'tipo_encargo' => 'SUPLENTE',
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_asignar_encargado_rechaza_una_persona_inactiva(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'INACTIVO']);

        $response = $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
            'email' => 'inactiva@example.com',
        ]);

        $response->assertSessionHasErrors('id_persona');
        $this->assertDatabaseMissing('encargado_area', ['id_persona' => $persona->id]);
    }

    public function test_asignar_encargado_exige_correo_si_la_persona_no_tiene_usuario(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);

        $response = $this->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('encargado_area', ['id_persona' => $persona->id]);
    }

    public function test_asignar_encargado_esta_bloqueado_para_quien_no_es_administrador(): void
    {
        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');

        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);

        $response = $this->actingAs($jefe)->post(route('areas.encargados.asignar', $area->id), [
            'id_persona' => $persona->id,
            'tipo_encargo' => 'TITULAR',
            'email' => 'bloqueado@example.com',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('encargado_area', ['id_persona' => $persona->id]);
    }

    public function test_finalizar_encargado_lo_marca_inactivo_sin_reemplazarlo(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        $encargo = EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $response = $this->patch(route('areas.encargados.finalizar', [$area->id, $encargo->id]));

        $response->assertRedirect(route('areas.index'));
        $encargo->refresh();
        $this->assertSame('INACTIVO', $encargo->estado_encargo);
        $this->assertNotNull($encargo->fecha_reasignacion);
    }

    public function test_finalizar_encargado_esta_bloqueado_para_quien_no_es_administrador(): void
    {
        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');

        $area = Area::factory()->create();
        $persona = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        $encargo = EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        $response = $this->actingAs($jefe)->patch(route('areas.encargados.finalizar', [$area->id, $encargo->id]));

        $response->assertForbidden();
        $this->assertSame('ACTIVO', $encargo->fresh()->estado_encargo);
    }

    public function test_search_personas_para_encargado_solo_devuelve_personas_activas(): void
    {
        Persona::factory()->create(['nombres' => 'Marcelo', 'ci' => '1111111', 'estado_persona' => 'ACTIVO']);
        Persona::factory()->create(['nombres' => 'Marcia', 'ci' => '2222222', 'estado_persona' => 'INACTIVO']);

        $response = $this->getJson(route('search.personas-para-encargado', ['q' => 'Marc']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['ci' => '1111111']);
    }
}
