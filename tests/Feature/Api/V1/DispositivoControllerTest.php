<?php

namespace Tests\Feature\Api\V1;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositivoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_registra_un_token_nuevo_para_el_usuario_autenticado(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario, 'api')->postJson(route('api.v1.dispositivos.store'), [
            'token' => 'token-fcm-123',
            'plataforma' => 'android',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dispositivos', [
            'id_usuario' => $usuario->id,
            'token' => 'token-fcm-123',
            'plataforma' => 'android',
        ]);
    }

    public function test_store_reasigna_el_token_si_ya_pertenecia_a_otro_usuario(): void
    {
        $usuarioAnterior = User::factory()->create();
        $usuarioNuevo = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuarioAnterior->id, 'token' => 'token-compartido', 'plataforma' => 'android']);

        $this->actingAs($usuarioNuevo, 'api')->postJson(route('api.v1.dispositivos.store'), [
            'token' => 'token-compartido',
            'plataforma' => 'android',
        ])->assertCreated();

        $this->assertDatabaseCount('dispositivos', 1);
        $this->assertDatabaseHas('dispositivos', [
            'token' => 'token-compartido',
            'id_usuario' => $usuarioNuevo->id,
        ]);
    }

    public function test_store_requiere_token_y_plataforma_valida(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario, 'api')->postJson(route('api.v1.dispositivos.store'), [
            'plataforma' => 'windows-phone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'plataforma']);
    }

    public function test_destroy_elimina_el_token_del_usuario_autenticado(): void
    {
        $usuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-a-borrar', 'plataforma' => 'ios']);

        $this->actingAs($usuario, 'api')->deleteJson(route('api.v1.dispositivos.destroy'), [
            'token' => 'token-a-borrar',
        ])->assertOk();

        $this->assertDatabaseMissing('dispositivos', ['token' => 'token-a-borrar']);
    }

    public function test_destroy_no_elimina_el_token_de_otro_usuario(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $otroUsuario->id, 'token' => 'token-ajeno', 'plataforma' => 'ios']);

        $this->actingAs($usuario, 'api')->deleteJson(route('api.v1.dispositivos.destroy'), [
            'token' => 'token-ajeno',
        ])->assertOk();

        $this->assertDatabaseHas('dispositivos', ['token' => 'token-ajeno']);
    }
}
