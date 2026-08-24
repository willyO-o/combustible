<?php

namespace Tests\Feature\Api\V1;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_me_requiere_autenticacion(): void
    {
        $this->putJson(route('api.v1.auth.me.update'), ['name' => 'Nuevo'])
            ->assertUnauthorized();
    }

    public function test_update_me_actualiza_name_celular_y_direccion(): void
    {
        $persona = Persona::factory()->create(['celular' => '70000000']);
        $user = User::factory()->create(['id_persona' => $persona->id, 'name' => 'Original']);

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'name' => 'Apodo',
            'celular' => '77712345',
            'direccion' => 'Av. Siempre Viva 123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Apodo')
            ->assertJsonPath('data.celular', '77712345')
            ->assertJsonPath('data.direccion', 'Av. Siempre Viva 123');

        $this->assertSame('Apodo', $user->fresh()->name);
        $this->assertSame('77712345', $persona->fresh()->celular);
        $this->assertSame('Av. Siempre Viva 123', $persona->fresh()->direccion);
    }

    public function test_update_me_es_parcial_y_no_toca_campos_omitidos(): void
    {
        $persona = Persona::factory()->create(['celular' => '70000000', 'direccion' => 'Calle Original']);
        $user = User::factory()->create(['id_persona' => $persona->id, 'name' => 'Original']);

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'name' => 'Solo el nombre cambia',
        ]);

        $response->assertOk();

        $this->assertSame('Solo el nombre cambia', $user->fresh()->name);
        $this->assertSame('70000000', $persona->fresh()->celular);
        $this->assertSame('Calle Original', $persona->fresh()->direccion);
    }

    public function test_update_me_ignora_email_y_otros_campos_no_permitidos(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'email' => 'nuevo@example.com',
            'password' => 'nueva-clave',
        ]);

        $response->assertOk();
        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_update_me_no_actualiza_celular_ni_direccion_sin_persona_vinculada(): void
    {
        $user = User::factory()->create(['id_persona' => null]);

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'celular' => '77712345',
            'direccion' => 'Av. Siempre Viva 123',
        ]);

        $response->assertOk();
        $this->assertNull($user->fresh()->persona);
    }

    public function test_update_me_actualiza_la_foto_y_borra_la_anterior(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['foto' => 'usuarios/anterior.jpg']);
        Storage::disk('public')->put('usuarios/anterior.jpg', 'contenido');

        // multipart/form-data con `_method=PUT`: así debe hacerlo un cliente real,
        // ya que PHP no procesa el cuerpo de una petición PUT con archivos.
        $response = $this->actingAs($user, 'api')->post(route('api.v1.auth.me.update'), [
            '_method' => 'PUT',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertOk();

        $user->refresh();
        Storage::disk('public')->assertMissing('usuarios/anterior.jpg');
        Storage::disk('public')->assertExists($user->foto);
        $this->assertNotNull($response->json('data.foto_url'));
    }

    public function test_update_me_valida_la_longitud_maxima_de_los_campos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'name' => str_repeat('a', 256),
            'celular' => str_repeat('1', 21),
            'direccion' => str_repeat('a', 251),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'celular', 'direccion']);
    }
}
