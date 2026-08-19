<?php

namespace Tests\Feature;

use App\Models\ParametrosEmpresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParametrosEmpresaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 3],
            'estado' => 'ACTIVO',
        ], $overrides);
    }

    public function test_edit_muestra_null_cuando_no_existe_el_registro(): void
    {
        $response = $this->actingAs($this->admin)->get(route('parametros-empresa.edit'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ParametrosEmpresa/Edit')
            ->where('parametrosEmpresa', null)
        );
    }

    public function test_edit_muestra_los_parametros_existentes(): void
    {
        ParametrosEmpresa::create($this->datosValidos());

        $response = $this->actingAs($this->admin)->get(route('parametros-empresa.edit'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ParametrosEmpresa/Edit')
            ->where('parametrosEmpresa.nombre_empresa', 'Plus Metals Ltda.')
            ->where('parametrosEmpresa.parametros_vale.tiempo_expiracion', 3)
        );
    }

    public function test_update_crea_el_registro_cuando_no_existe(): void
    {
        $this->assertDatabaseCount('parametros_empresa', 0);

        $response = $this->actingAs($this->admin)->put(route('parametros-empresa.update'), $this->datosValidos());

        $response->assertRedirect(route('parametros-empresa.edit'));
        $this->assertDatabaseCount('parametros_empresa', 1);

        $parametrosEmpresa = ParametrosEmpresa::first();
        $this->assertSame('Plus Metals Ltda.', $parametrosEmpresa->nombre_empresa);
        $this->assertSame(3, $parametrosEmpresa->parametros_vale->tiempo_expiracion);
    }

    public function test_update_actualiza_el_registro_existente_sin_duplicarlo(): void
    {
        ParametrosEmpresa::create($this->datosValidos());

        $response = $this->actingAs($this->admin)->put(route('parametros-empresa.update'), $this->datosValidos([
            'nombre_empresa' => 'Plus Metals S.A.',
            'parametros_vale' => ['tiempo_expiracion' => 7],
        ]));

        $response->assertRedirect(route('parametros-empresa.edit'));
        $this->assertDatabaseCount('parametros_empresa', 1);

        $parametrosEmpresa = ParametrosEmpresa::first();
        $this->assertSame('Plus Metals S.A.', $parametrosEmpresa->nombre_empresa);
        $this->assertSame(7, $parametrosEmpresa->parametros_vale->tiempo_expiracion);
    }

    public function test_update_valida_los_campos_requeridos(): void
    {
        $response = $this->actingAs($this->admin)->put(route('parametros-empresa.update'), []);

        $response->assertSessionHasErrors([
            'nombre_empresa',
            'direccion_empresa',
            'telefono_empresa',
            'correo_empresa',
            'nit_empresa',
            'parametros_vale',
            'estado',
        ]);
        $this->assertDatabaseCount('parametros_empresa', 0);
    }

    public function test_update_guarda_el_logo_y_elimina_el_anterior(): void
    {
        Storage::fake('public');

        ParametrosEmpresa::create($this->datosValidos([
            'logo_empresa' => 'parametros-empresa/logo-anterior.png',
        ]));
        Storage::disk('public')->put('parametros-empresa/logo-anterior.png', 'contenido');

        $response = $this->actingAs($this->admin)->put(route('parametros-empresa.update'), $this->datosValidos([
            'logo_empresa' => UploadedFile::fake()->image('logo.png'),
        ]));

        $response->assertRedirect(route('parametros-empresa.edit'));

        $parametrosEmpresa = ParametrosEmpresa::first();
        Storage::disk('public')->assertExists($parametrosEmpresa->logo_empresa);
        Storage::disk('public')->assertMissing('parametros-empresa/logo-anterior.png');
    }

    public function test_update_rechaza_a_un_usuario_sin_rol_de_administrador(): void
    {
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');

        $response = $this->actingAs($conductor)->put(route('parametros-empresa.update'), $this->datosValidos());

        $response->assertForbidden();
        $this->assertDatabaseCount('parametros_empresa', 0);
    }
}
