<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehiculoExterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

class VehiculoExternoControllerTest extends TestCase
{
    use ConPermisosReales;
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

    public function test_index_lista_los_vehiculos_externos(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '1234-ABC']);

        $response = $this->get(route('vehiculos-externos.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('VehiculosExternos/Index')
            ->where('vehiculosExternos.data.0.nro_placa', '1234-ABC')
        );
    }

    public function test_store_crea_un_vehiculo_externo(): void
    {
        $response = $this->post(route('vehiculos-externos.store'), [
            'nro_placa' => '5678-XYZ',
            'propietario' => 'Juan Pérez',
        ]);

        $response->assertRedirect(route('vehiculos-externos.index'));
        $this->assertDatabaseHas('vehiculo_externo', [
            'nro_placa' => '5678-XYZ',
            'propietario' => 'Juan Pérez',
        ]);
    }

    public function test_store_por_ajax_responde_en_json_con_el_vehiculo_creado(): void
    {
        // El componente reutilizable VehiculoExternoFormModal usa axios
        // (AJAX), no Inertia: al invocarse desde otro formulario no debe
        // redirigir.
        $response = $this->postJson(route('vehiculos-externos.store'), [
            'nro_placa' => '9999-ZZZ',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nro_placa', '9999-ZZZ');

        $this->assertDatabaseHas('vehiculo_externo', ['nro_placa' => '9999-ZZZ']);
    }

    public function test_store_rechaza_una_placa_duplicada(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '1234-ABC']);

        $response = $this->post(route('vehiculos-externos.store'), [
            'nro_placa' => '1234-ABC',
        ]);

        $response->assertSessionHasErrors('nro_placa');
    }

    public function test_store_requiere_la_placa(): void
    {
        $response = $this->post(route('vehiculos-externos.store'), []);

        $response->assertSessionHasErrors('nro_placa');
    }

    public function test_store_no_requiere_propietario(): void
    {
        $response = $this->post(route('vehiculos-externos.store'), [
            'nro_placa' => '1111-AAA',
        ]);

        $response->assertRedirect(route('vehiculos-externos.index'));
        $this->assertDatabaseHas('vehiculo_externo', ['nro_placa' => '1111-AAA']);
    }

    public function test_update_actualiza_el_vehiculo_externo(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create(['nro_placa' => 'Original']);

        $response = $this->put(route('vehiculos-externos.update', $vehiculoExterno->id), [
            'nro_placa' => 'Renombrado',
            'propietario' => 'Nuevo Propietario',
        ]);

        $response->assertRedirect(route('vehiculos-externos.index'));
        $this->assertSame('Renombrado', $vehiculoExterno->fresh()->nro_placa);
        $this->assertSame('Nuevo Propietario', $vehiculoExterno->fresh()->propietario);
    }

    public function test_destroy_elimina_un_vehiculo_externo_sin_cargas_asociadas(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->delete(route('vehiculos-externos.destroy', $vehiculoExterno->id));

        $response->assertRedirect(route('vehiculos-externos.index'));
        $this->assertDatabaseMissing('vehiculo_externo', ['id' => $vehiculoExterno->id]);
    }

    public function test_destroy_no_elimina_si_tiene_cargas_registradas(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        DB::table('carga_material')->insert([
            'uuid' => (string) Str::uuid(),
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'id_usuario_apertura' => $this->admin->id,
            'fecha_apertura' => now(),
            'estado_carga' => 'ABIERTA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete(route('vehiculos-externos.destroy', $vehiculoExterno->id));

        $response->assertRedirect(route('vehiculos-externos.index'));
        $this->assertDatabaseHas('vehiculo_externo', ['id' => $vehiculoExterno->id]);
    }

    /**
     * VehiculoExternoFormModal.vue envía por axios: ante una placa repetida
     * debe recibir 422 con el error en JSON (antes, un redirect que el modal
     * tomaba como éxito).
     */
    public function test_el_modal_recibe_el_error_de_placa_repetida_en_json(): void
    {
        VehiculoExterno::factory()->create(['nro_placa' => '1234ABC']);

        $this->postJson(route('vehiculos-externos.store'), ['nro_placa' => '1234ABC'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nro_placa']);

        $this->assertSame(1, VehiculoExterno::where('nro_placa', '1234ABC')->count());
    }

    /**
     * @return array<int, int>
     */
    private function idsBuscados(array $parametros): array
    {
        $ids = [];

        $this->get(route('vehiculos-externos.index', $parametros))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['vehiculosExternos']['data'])->pluck('id')->sort()->values()->all();

                return $page;
            });

        return $ids;
    }

    public function test_el_buscador_encuentra_por_placa_o_por_propietario(): void
    {
        $juan = VehiculoExterno::factory()->create(['nro_placa' => '1234-ABC', 'propietario' => 'Transportes Juan Pérez']);
        $mamani = VehiculoExterno::factory()->create(['nro_placa' => '5678-XYZ', 'propietario' => 'Rosa Mamani']);
        $sinPropietario = VehiculoExterno::factory()->create(['nro_placa' => '9999-SIN', 'propietario' => null]);

        $this->assertSame([$juan->id], $this->idsBuscados(['buscar' => '1234']), 'por placa');
        $this->assertSame([$mamani->id], $this->idsBuscados(['buscar' => 'mamani']), 'por propietario, sin distinguir mayúsculas');

        // Varias palabras: cada una en la placa o en el propietario.
        $this->assertSame([$juan->id], $this->idsBuscados(['buscar' => 'juan 1234']));
        $this->assertSame([], $this->idsBuscados(['buscar' => 'juan 5678']));

        // Sin propietario (NULL) se sigue encontrando por la placa.
        $this->assertSame([$sinPropietario->id], $this->idsBuscados(['buscar' => '9999']));

        // Búsqueda vacía o sólo espacios: todos.
        $todos = collect([$juan->id, $mamani->id, $sinPropietario->id])->sort()->values()->all();
        $this->assertSame($todos, $this->idsBuscados(['buscar' => '   ']));
    }

    public function test_el_buscador_devuelve_el_filtro_aplicado_y_el_parametro_viejo_sigue_funcionando(): void
    {
        $vehiculo = VehiculoExterno::factory()->create(['nro_placa' => '1234-ABC']);
        VehiculoExterno::factory()->create(['nro_placa' => '5678-XYZ']);

        $this->get(route('vehiculos-externos.index', ['buscar' => '1234']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.buscar', '1234'));

        $this->assertSame([$vehiculo->id], $this->idsBuscados(['nro_placa' => '1234']));
    }
}
