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

    public function test_update_me_ignora_la_contrasena_y_otros_campos_no_permitidos(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com', 'estado_usuario' => 'ACTIVO']);
        $hashAnterior = $user->password;

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'password' => 'nueva-clave',
            'estado_usuario' => 'INACTIVO',
        ]);

        $response->assertOk();
        $this->assertSame($hashAnterior, $user->fresh()->password);
        $this->assertSame('ACTIVO', $user->fresh()->estado_usuario);
    }

    public function test_update_me_cambia_el_correo_confirmando_la_contrasena_actual(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        $hashAnterior = $user->password;

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'email' => ' nuevo@example.com ',
            'current_password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado. Desde ahora inicia sesión con nuevo@example.com.')
            ->assertJsonPath('data.email', 'nuevo@example.com');

        $this->assertSame('nuevo@example.com', $user->fresh()->email);
        // current_password sólo valida: la contraseña no cambia.
        $this->assertSame($hashAnterior, $user->fresh()->password);
    }

    public function test_despues_de_cambiar_el_correo_la_app_inicia_sesion_con_el_nuevo(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->postJson(route('api.v1.auth.login'), ['email' => 'original@example.com', 'password' => 'password'])
            ->assertUnauthorized();

        $this->postJson(route('api.v1.auth.login'), ['email' => 'nuevo@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'nuevo@example.com');
    }

    public function test_update_me_exige_la_contrasena_actual_para_cambiar_el_correo(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'email' => 'nuevo@example.com',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.current_password.0', 'Ingresa tu contraseña actual para cambiar el correo electrónico.');

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_update_me_rechaza_una_contrasena_incorrecta_sin_guardar_nada(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com', 'name' => 'Original']);

        $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'name' => 'Otro',
            'email' => 'nuevo@example.com',
            'current_password' => 'incorrecta',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'La contraseña actual no es correcta.');

        $this->assertSame('original@example.com', $user->fresh()->email);
        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_update_me_no_permite_el_correo_de_otro_usuario(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'email' => 'ocupado@example.com',
            'current_password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este correo electrónico ya está registrado por otro usuario.');

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_update_me_rechaza_un_correo_invalido_o_vacio(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        foreach (['no-es-un-correo', '', null] as $email) {
            $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
                'email' => $email,
                'current_password' => 'password',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    /**
     * Compatibilidad con la versión instalada de la app: si reenvía el mismo
     * correo junto con el resto del perfil, no se le pide contraseña.
     */
    public function test_update_me_con_el_mismo_correo_no_pide_contrasena(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user, 'api')->putJson(route('api.v1.auth.me.update'), [
            'name' => 'Apodo',
            'email' => 'ORIGINAL@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado exitosamente.');

        $this->assertSame('Apodo', $user->fresh()->name);
    }

    public function test_el_token_sigue_siendo_valido_despues_de_cambiar_el_correo(): void
    {
        User::factory()->create(['email' => 'original@example.com']);
        $token = $this->postJson(route('api.v1.auth.login'), ['email' => 'original@example.com', 'password' => 'password'])
            ->json('access_token');

        $this->withToken($token)->putJson(route('api.v1.auth.me.update'), [
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->withToken($token)->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'nuevo@example.com');
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
