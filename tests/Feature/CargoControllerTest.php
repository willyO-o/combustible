<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);
    }

    public function test_la_migracion_deja_precargados_los_cargos_iniciales(): void
    {
        foreach (['Técnico', 'Analista', 'Supervisor'] as $cargo) {
            $this->assertDatabaseHas('cargo', ['cargo' => $cargo, 'estado_cargo' => 'ACTIVO']);
        }
    }

    public function test_index_lista_los_cargos_con_su_cantidad_de_personas(): void
    {
        $cargo = Cargo::factory()->create(['cargo' => 'Almacenero']);
        Persona::factory()->count(2)->create(['id_cargo' => $cargo->id]);

        $this->get(route('cargos.index', ['cargo' => 'Almacen']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cargos/Index')
                ->has('cargos.data', 1)
                ->where('cargos.data.0.cargo', 'Almacenero')
                ->where('cargos.data.0.personas_count', 2)
                ->where('filters.cargo', 'Almacen')
            );
    }

    public function test_index_filtra_por_estado(): void
    {
        Cargo::factory()->inactivo()->create(['cargo' => 'Mensajero']);

        $this->get(route('cargos.index', ['estado_cargo' => 'INACTIVO']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cargos.data', 1)
                ->where('cargos.data.0.cargo', 'Mensajero')
            );
    }

    public function test_registra_un_cargo_normalizando_los_espacios(): void
    {
        $this->from(route('cargos.index'))
            ->post(route('cargos.store'), ['cargo' => '  Jefe   de   Turno ', 'estado_cargo' => 'ACTIVO'])
            ->assertRedirect(route('cargos.index'))
            ->assertSessionHas('success', 'Cargo "Jefe de Turno" registrado.');

        $this->assertDatabaseHas('cargo', ['cargo' => 'Jefe de Turno', 'estado_cargo' => 'ACTIVO']);
    }

    public function test_no_permite_un_cargo_repetido_ni_vacio(): void
    {
        $this->post(route('cargos.store'), ['cargo' => ' Supervisor ', 'estado_cargo' => 'ACTIVO'])
            ->assertSessionHasErrors(['cargo' => 'Ya existe un cargo con ese nombre.']);

        $this->post(route('cargos.store'), ['cargo' => '   ', 'estado_cargo' => 'ACTIVO'])
            ->assertSessionHasErrors('cargo');

        $this->assertSame(1, Cargo::where('cargo', 'Supervisor')->count());
    }

    public function test_alta_rapida_responde_json_con_el_cargo_creado(): void
    {
        $this->postJson(route('cargos.store-rapido'), ['cargo' => 'Topógrafo', 'estado_cargo' => 'ACTIVO'])
            ->assertCreated()
            ->assertJson(['cargo' => 'Topógrafo'])
            ->assertJsonStructure(['id', 'cargo']);

        $this->postJson(route('cargos.store-rapido'), ['cargo' => 'Topógrafo', 'estado_cargo' => 'ACTIVO'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cargo']);
    }

    public function test_edita_el_nombre_y_alterna_el_estado(): void
    {
        $cargo = Cargo::factory()->create(['cargo' => 'Chofer']);

        $this->from(route('cargos.index'))
            ->put(route('cargos.update', $cargo), ['cargo' => 'Chofer de planta', 'estado_cargo' => 'INACTIVO'])
            ->assertRedirect(route('cargos.index'));

        $this->assertDatabaseHas('cargo', ['id' => $cargo->id, 'cargo' => 'Chofer de planta', 'estado_cargo' => 'INACTIVO']);
    }

    public function test_renombrar_un_cargo_con_su_mismo_nombre_no_choca_con_el_unique(): void
    {
        $cargo = Cargo::factory()->create(['cargo' => 'Vigilante']);

        $this->put(route('cargos.update', $cargo), ['cargo' => 'Vigilante', 'estado_cargo' => 'INACTIVO'])
            ->assertSessionHasNoErrors();
    }

    public function test_no_elimina_un_cargo_en_uso(): void
    {
        $cargo = Cargo::factory()->create();
        Persona::factory()->create(['id_cargo' => $cargo->id]);

        $this->from(route('cargos.index'))
            ->delete(route('cargos.destroy', $cargo))
            ->assertRedirect(route('cargos.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cargo', ['id' => $cargo->id]);
    }

    public function test_elimina_un_cargo_sin_personas(): void
    {
        $cargo = Cargo::factory()->create(['cargo' => 'Temporal']);

        $this->from(route('cargos.index'))
            ->delete(route('cargos.destroy', $cargo))
            ->assertSessionHas('success', 'Cargo "Temporal" eliminado.');

        $this->assertDatabaseMissing('cargo', ['id' => $cargo->id]);
    }

    public function test_seleccionables_trae_los_activos_y_el_actual_aunque_este_inactivo(): void
    {
        $inactivo = Cargo::factory()->inactivo()->create(['cargo' => 'Obsoleto']);

        $nombres = Cargo::seleccionables()->pluck('cargo');
        $this->assertTrue($nombres->contains('Supervisor'));
        $this->assertFalse($nombres->contains('Obsoleto'));

        $this->assertTrue(Cargo::seleccionables($inactivo->id)->pluck('cargo')->contains('Obsoleto'));
    }
}
