<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearNotificacion(User $user, array $overrides = []): DatabaseNotification
    {
        return DatabaseNotification::create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ObservacionOperacionNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['tipo' => 'observacion_operacion', 'observaciones' => 'Revisar frenos'],
            'read_at' => null,
        ], $overrides));
    }

    public function test_index_solo_devuelve_las_notificaciones_del_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $propia = $this->crearNotificacion($user);
        $ajena = $this->crearNotificacion($otro);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.notificaciones.index'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($propia->id));
        $this->assertFalse($ids->contains($ajena->id));
    }

    public function test_index_solo_devuelve_no_leidas_o_con_menos_de_2_dias(): void
    {
        $user = User::factory()->create();

        $noLeida = $this->crearNotificacion($user, ['created_at' => now()->subDays(10)]);
        $leidaReciente = $this->crearNotificacion($user, ['read_at' => now(), 'created_at' => now()->subHours(2)]);
        $leidaAntigua = $this->crearNotificacion($user, ['read_at' => now(), 'created_at' => now()->subDays(5)]);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.notificaciones.index'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($noLeida->id));
        $this->assertTrue($ids->contains($leidaReciente->id));
        $this->assertFalse($ids->contains($leidaAntigua->id));
    }

    public function test_index_devuelve_las_notificaciones_formateadas(): void
    {
        $user = User::factory()->create();
        $this->crearNotificacion($user);

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.notificaciones.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.tipo', 'observacion_operacion')
            ->assertJsonPath('data.0.titulo', 'Observación en operación diaria')
            ->assertJsonPath('data.0.descripcion', 'Revisar frenos')
            ->assertJsonPath('data.0.leida', false);
    }

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson(route('api.v1.notificaciones.index'))->assertUnauthorized();
    }

    public function test_marcar_leida_marca_solo_la_notificacion_del_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $notificacion = $this->crearNotificacion($user);
        $notificacionAjena = $this->crearNotificacion($otro);

        $response = $this->actingAs($user, 'api')
            ->postJson(route('api.v1.notificaciones.marcar-leida', $notificacion->id));

        $response->assertOk()->assertJsonPath('data.leida', true);
        $this->assertNotNull($notificacion->fresh()->read_at);
        $this->assertNull($notificacionAjena->fresh()->read_at);
    }

    public function test_marcar_leida_de_una_notificacion_ajena_devuelve_404(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $notificacionAjena = $this->crearNotificacion($otro);

        $this->actingAs($user, 'api')
            ->postJson(route('api.v1.notificaciones.marcar-leida', $notificacionAjena->id))
            ->assertNotFound();
    }
}
