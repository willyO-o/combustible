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

    public function test_el_correo_no_se_puede_cambiar_desde_el_perfil(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'nuevo@example.com',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('original@example.com', $user->fresh()->email);
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
