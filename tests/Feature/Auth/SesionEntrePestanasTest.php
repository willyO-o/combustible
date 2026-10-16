<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerificarSesionDePestana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

/**
 * Varias pestañas comparten la misma cookie de sesión: una pestaña dibujada
 * con la sesión de un usuario no debe poder actuar con la sesión de otro que
 * se inició después en otra pestaña (VerificarSesionDePestana).
 */
class SesionEntrePestanasTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    private User $usuarioAnterior;

    private User $usuarioActual;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->usuarioAnterior = User::factory()->create();
        $this->usuarioAnterior->assignRole('administrador');

        $this->usuarioActual = User::factory()->create();
        $this->usuarioActual->assignRole('administrador');
    }

    public function test_una_accion_inertia_desde_la_pestana_de_otro_usuario_se_bloquea_y_recarga_la_pestana(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                VerificarSesionDePestana::CABECERA => (string) $this->usuarioAnterior->id,
            ])
            ->post(route('cargos.store'), ['cargo' => 'Cargo Fantasma', 'estado_cargo' => 'ACTIVO'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));

        $this->assertDatabaseMissing('cargo', ['cargo' => 'Cargo Fantasma']);
    }

    public function test_una_peticion_axios_desde_la_pestana_de_otro_usuario_responde_409_en_json(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([VerificarSesionDePestana::CABECERA => (string) $this->usuarioAnterior->id])
            ->postJson(route('cargos.store-rapido'), ['cargo' => 'Cargo Fantasma', 'estado_cargo' => 'ACTIVO'])
            ->assertStatus(409)
            ->assertHeader(VerificarSesionDePestana::CABECERA_RESPUESTA, '1')
            ->assertJson(['sesion_cambiada' => true]);

        $this->assertDatabaseMissing('cargo', ['cargo' => 'Cargo Fantasma']);
    }

    public function test_las_acciones_de_la_pestana_del_mismo_usuario_funcionan_normal(): void
    {
        $this->actingAs($this->usuarioActual)
            ->from(route('cargos.index'))
            ->withHeaders([VerificarSesionDePestana::CABECERA => (string) $this->usuarioActual->id])
            ->post(route('cargos.store'), ['cargo' => 'Cargo Valido', 'estado_cargo' => 'ACTIVO'])
            ->assertRedirect(route('cargos.index'));

        $this->assertDatabaseHas('cargo', ['cargo' => 'Cargo Valido']);
    }

    public function test_sin_la_cabecera_no_se_verifica_nada(): void
    {
        $this->actingAs($this->usuarioActual)
            ->from(route('cargos.index'))
            ->post(route('cargos.store'), ['cargo' => 'Cargo Sin Cabecera', 'estado_cargo' => 'ACTIVO'])
            ->assertRedirect(route('cargos.index'));

        $this->assertDatabaseHas('cargo', ['cargo' => 'Cargo Sin Cabecera']);
    }

    public function test_una_accion_desde_una_pestana_cuya_sesion_se_cerro_en_otra_va_al_login_sin_guardar(): void
    {
        // El middleware "auth" tiene prioridad y corre antes: manda al login.
        $this->withHeaders([
            'X-Inertia' => 'true',
            VerificarSesionDePestana::CABECERA => (string) $this->usuarioAnterior->id,
        ])
            ->post(route('cargos.store'), ['cargo' => 'Cargo Fantasma', 'estado_cargo' => 'ACTIVO'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('cargo', ['cargo' => 'Cargo Fantasma']);
    }

    public function test_el_poll_de_una_pestana_desactualizada_la_recarga_con_la_sesion_actual(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $this->versionDeAssets(),
                'X-Inertia-Partial-Component' => 'Dashboard',
                'X-Inertia-Partial-Data' => 'notificaciones',
                VerificarSesionDePestana::CABECERA => (string) $this->usuarioAnterior->id,
            ])
            ->get(route('dashboard'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));
    }

    public function test_el_poll_de_la_pestana_del_mismo_usuario_funciona_normal(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $this->versionDeAssets(),
                'X-Inertia-Partial-Component' => 'Dashboard',
                'X-Inertia-Partial-Data' => 'notificaciones',
                VerificarSesionDePestana::CABECERA => (string) $this->usuarioActual->id,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard');
    }

    public function test_una_visita_get_completa_no_se_bloquea_para_seguir_el_redirect_del_login(): void
    {
        // Tras iniciar sesión, el navegador sigue el redirect al dashboard
        // repitiendo la cabecera con la que se dibujó el login ("invitado").
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $this->versionDeAssets(),
                VerificarSesionDePestana::CABECERA => VerificarSesionDePestana::INVITADO,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard');
    }

    public function test_el_login_desde_una_pestana_sin_sesion_funciona_normal(): void
    {
        $this->withHeaders([VerificarSesionDePestana::CABECERA => VerificarSesionDePestana::INVITADO])
            ->post('/login', ['email' => $this->usuarioActual->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($this->usuarioActual);
    }

    public function test_el_login_desde_una_pestana_vieja_no_entra_en_silencio_con_la_sesion_abierta_en_otra(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                VerificarSesionDePestana::CABECERA => VerificarSesionDePestana::INVITADO,
            ])
            ->post('/login', ['email' => $this->usuarioAnterior->email, 'password' => 'password'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));

        $this->assertAuthenticatedAs($this->usuarioActual);
    }

    public function test_cerrar_sesion_desde_una_pestana_desactualizada_siempre_funciona(): void
    {
        $this->actingAs($this->usuarioActual)
            ->withHeaders([
                'X-Inertia' => 'true',
                VerificarSesionDePestana::CABECERA => (string) $this->usuarioAnterior->id,
            ])
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Misma versión que calcula Inertia\Middleware en cada petición (ver
     * .ai/rules/feature.md): sin ella una recarga parcial responde 409.
     */
    private function versionDeAssets(): ?string
    {
        return file_exists(public_path('build/manifest.json'))
            ? hash_file('xxh128', public_path('build/manifest.json'))
            : null;
    }
}
