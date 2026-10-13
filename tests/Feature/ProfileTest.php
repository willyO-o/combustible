<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $persona = Persona::factory()->create();
        $user = User::factory()->create(['id_persona' => $persona->id]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'celular' => '77712345',
                'direccion' => 'Av. Siempre Viva 123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('77712345', $persona->fresh()->celular);
        $this->assertSame('Av. Siempre Viva 123', $persona->fresh()->direccion);
    }

    public function test_el_usuario_puede_cambiar_su_correo_confirmando_su_contrasena(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => '  nuevo@example.com ',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile')
            ->assertSessionHas('success', 'Perfil actualizado. Desde ahora inicia sesión con nuevo@example.com.');

        $this->assertSame('nuevo@example.com', $user->fresh()->email);
    }

    public function test_despues_de_cambiar_el_correo_inicia_sesion_con_el_nuevo(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        auth()->logout();

        $this->post('/login', ['email' => 'original@example.com', 'password' => 'password']);
        $this->assertGuest();

        $this->post('/login', ['email' => 'nuevo@example.com', 'password' => 'password']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_cambiar_el_correo_exige_la_contrasena_actual(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'nuevo@example.com',
            ])
            ->assertSessionHasErrors(['current_password' => 'Ingresa tu contraseña actual para cambiar el correo electrónico.'])
            ->assertRedirect('/profile');

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_cambiar_el_correo_rechaza_una_contrasena_incorrecta(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com', 'name' => 'Nombre Original']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Nuevo',
                'email' => 'nuevo@example.com',
                'current_password' => 'incorrecta',
            ])
            ->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta.']);

        // Nada del formulario se guarda si la validación falla.
        $this->assertSame('original@example.com', $user->fresh()->email);
        $this->assertSame('Nombre Original', $user->fresh()->name);
    }

    public function test_no_se_puede_usar_el_correo_de_otro_usuario(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'ocupado@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors(['email' => 'Este correo electrónico ya está registrado por otro usuario.']);

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_rechaza_un_correo_con_formato_invalido_o_vacio(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        foreach (['no-es-un-correo', ''] as $email) {
            $this->actingAs($user)
                ->patch('/profile', [
                    'name' => $user->name,
                    'email' => $email,
                    'current_password' => 'password',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_guardar_el_perfil_con_el_mismo_correo_no_pide_contrasena(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        // Mismo correo, aunque cambien mayúsculas: no es un cambio de cuenta.
        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Otro Nombre',
                'email' => 'Original@Example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Perfil actualizado exitosamente.');

        $this->assertSame('Otro Nombre', $user->fresh()->name);
    }

    public function test_la_contrasena_nunca_se_guarda_ni_cambia_al_editar_el_perfil(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        $hashAnterior = $user->password;

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAnterior, $user->fresh()->password);
    }

    public function test_el_celular_y_la_direccion_no_se_actualizan_si_el_usuario_no_tiene_persona_vinculada(): void
    {
        $user = User::factory()->create(['id_persona' => null]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'celular' => '77712345',
                'direccion' => 'Av. Siempre Viva 123',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->persona);
    }

    public function test_profile_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['foto' => 'usuarios/anterior.jpg']);
        Storage::disk('public')->put('usuarios/anterior.jpg', 'contenido');

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'foto' => UploadedFile::fake()->image('foto.jpg'),
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        Storage::disk('public')->assertMissing('usuarios/anterior.jpg');
        Storage::disk('public')->assertExists($user->foto);
    }

    public function test_no_existe_ruta_de_eliminar_cuenta_desde_el_perfil(): void
    {
        $this->assertFalse(Route::has('profile.destroy'));

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', ['password' => 'password']);

        // /profile existe (GET, PATCH), pero ya no acepta DELETE: 405, no 404.
        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }
}
