<?php

namespace Tests\Feature;

use App\Models\Grifo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

class GrifoControllerTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'razon_social' => 'Surtidor Central',
            'nit' => '1020304050',
            'estado_grifo' => 'ACTIVO',
        ], $extra);
    }

    public function test_el_modal_registra_el_grifo_y_recibe_json(): void
    {
        $this->postJson(route('grifos.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.nit', '1020304050');

        $this->assertDatabaseHas('grifo', ['nit' => '1020304050']);
    }

    /**
     * GrifoFormModal.vue envía por axios: ante un error de validación debe
     * recibir 422 con los errores en JSON. Antes recibía un redirect (las
     * rutas web no respondían JSON), axios lo seguía, y el modal se cerraba
     * como si hubiera guardado sin registrar nada.
     */
    public function test_el_modal_recibe_los_errores_de_validacion_en_json(): void
    {
        Grifo::create($this->payload());

        $this->postJson(route('grifos.store'), $this->payload(['razon_social' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit', 'razon_social']);

        $this->assertSame(1, Grifo::count());
    }

    public function test_un_formulario_normal_sigue_recibiendo_redirect_con_errores(): void
    {
        $this->from(route('grifos.index'))
            ->post(route('grifos.store'), $this->payload(['razon_social' => '']))
            ->assertRedirect(route('grifos.index'))
            ->assertSessionHasErrors('razon_social');
    }
}
