<?php

namespace Tests\Feature;

use App\Models\TipoMantenimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TipoMantenimientoControllerTest extends TestCase
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

    private function crear(array $overrides = []): TipoMantenimiento
    {
        return TipoMantenimiento::create(array_merge([
            'tipo_mantenimiento' => fake()->unique()->words(2, true),
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ], $overrides));
    }

    public function test_index_por_defecto_muestra_solo_el_tablero_taller(): void
    {
        $taller = $this->crear(['tipo_mantenimiento' => 'Cambio de aceite', 'ambito' => 'taller']);
        $this->crear(['tipo_mantenimiento' => 'Nivel de aceite', 'ambito' => 'operacion_diaria', 'tipo_valor' => 'booleano']);

        $response = $this->get(route('tipos-mantenimiento.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('TiposMantenimiento/Index')
            ->where('ambito', 'taller')
            ->where('tipos.data', fn ($data) => count($data) === 1 && $data[0]['id'] === $taller->id)
        );
    }

    public function test_index_con_ambito_operacion_diaria_muestra_el_otro_tablero(): void
    {
        $this->crear(['ambito' => 'taller']);
        $opDiaria = $this->crear(['ambito' => 'operacion_diaria', 'tipo_valor' => 'cantidad', 'unidad_medida' => 'Litros']);

        $response = $this->get(route('tipos-mantenimiento.index', ['ambito' => 'operacion_diaria']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('ambito', 'operacion_diaria')
            ->where('tipos.data', fn ($data) => count($data) === 1 && $data[0]['id'] === $opDiaria->id)
        );
    }

    public function test_index_con_ambito_invalido_cae_en_taller(): void
    {
        $response = $this->get(route('tipos-mantenimiento.index', ['ambito' => 'cualquier-cosa']));

        $response->assertInertia(fn (Assert $page) => $page->where('ambito', 'taller'));
    }

    public function test_store_taller_ignora_tipo_valor_y_unidad_medida(): void
    {
        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Revisión de frenos',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
            'tipo_valor' => 'cantidad',
            'unidad_medida' => 'mm',
        ]);

        $response->assertRedirect(route('tipos-mantenimiento.index', ['ambito' => 'taller']));

        $tipo = TipoMantenimiento::firstWhere('tipo_mantenimiento', 'Revisión de frenos');
        $this->assertSame('taller', $tipo->ambito);
        $this->assertNull($tipo->tipo_valor);
        $this->assertNull($tipo->unidad_medida);
    }

    public function test_store_operacion_diaria_exige_tipo_valor(): void
    {
        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Presión de llantas',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
        ]);

        $response->assertSessionHasErrors('tipo_valor');
    }

    public function test_store_operacion_diaria_con_cantidad_exige_unidad_de_medida(): void
    {
        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Nivel de refrigerante',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'cantidad',
        ]);

        $response->assertSessionHasErrors('unidad_medida');
    }

    public function test_store_operacion_diaria_booleano_no_exige_unidad_y_la_guarda_null(): void
    {
        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Luces funcionando',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
            'unidad_medida' => 'ignorame',
        ]);

        $response->assertRedirect(route('tipos-mantenimiento.index', ['ambito' => 'operacion_diaria']));

        $tipo = TipoMantenimiento::firstWhere('tipo_mantenimiento', 'Luces funcionando');
        $this->assertSame('booleano', $tipo->tipo_valor);
        $this->assertNull($tipo->unidad_medida);
    }

    public function test_store_operacion_diaria_cantidad_guarda_la_unidad(): void
    {
        $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Nivel de aceite motor',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'cantidad',
            'unidad_medida' => 'Litros',
        ])->assertSessionHasNoErrors();

        $tipo = TipoMantenimiento::firstWhere('tipo_mantenimiento', 'Nivel de aceite motor');
        $this->assertSame('cantidad', $tipo->tipo_valor);
        $this->assertSame('Litros', $tipo->unidad_medida);
    }

    public function test_update_no_falla_al_conservar_el_nombre(): void
    {
        $tipo = $this->crear(['tipo_mantenimiento' => 'Cambio de filtro', 'ambito' => 'taller']);

        $response = $this->put(route('tipos-mantenimiento.update', $tipo->id), [
            'tipo_mantenimiento' => 'Cambio de filtro',
            'estado_tipo_mantenimiento' => 'INACTIVO',
            'ambito' => 'taller',
        ]);

        $response->assertRedirect(route('tipos-mantenimiento.index', ['ambito' => 'taller']));
        $response->assertSessionHasNoErrors();
        $this->assertSame('INACTIVO', $tipo->fresh()->estado_tipo_mantenimiento);
    }

    public function test_el_mismo_nombre_puede_existir_en_los_dos_tableros(): void
    {
        $this->crear(['tipo_mantenimiento' => 'Nivel de aceite', 'ambito' => 'taller']);

        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Nivel de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, TipoMantenimiento::where('tipo_mantenimiento', 'Nivel de aceite')->count());
    }

    public function test_no_se_puede_duplicar_el_nombre_dentro_del_mismo_tablero(): void
    {
        $this->crear(['tipo_mantenimiento' => 'Cambio de aceite', 'ambito' => 'taller']);

        $response = $this->post(route('tipos-mantenimiento.store'), [
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);

        $response->assertSessionHasErrors('tipo_mantenimiento');
    }
}
