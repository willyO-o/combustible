<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
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
