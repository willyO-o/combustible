<?php

namespace Tests\Feature;

use App\Channels\FcmChannel;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use RefreshDatabase;

    /** Notification anónima con toFcm(), para probar el canal aislado del resto del dominio. */
    private function notificacionFcm(): Notification
    {
        return new class extends Notification
        {
            public function via($notifiable): array
            {
                return [FcmChannel::class];
            }

            public function toFcm($notifiable): array
            {
                return [
                    'title' => 'Título de prueba',
                    'body' => 'Cuerpo de prueba',
                    'data' => ['tipo' => 'prueba', 'id' => 7],
                ];
            }
        };
    }

    private function notificacionSinFcm(): Notification
    {
        return new class extends Notification
        {
            public function via($notifiable): array
            {
                return [FcmChannel::class];
            }
        };
    }

    public function test_envia_el_push_a_todos_los_tokens_registrados_del_usuario(): void
    {
        $usuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-1', 'plataforma' => 'android']);
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-2', 'plataforma' => 'ios']);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function (CloudMessage $mensaje, array $tokens) {
                $payload = $mensaje->jsonSerialize();
                sort($tokens);

                return $payload['notification']['title'] === 'Título de prueba'
                    && $payload['notification']['body'] === 'Cuerpo de prueba'
                    && $payload['data'] === ['tipo' => 'prueba', 'id' => '7']
                    && $tokens === ['token-1', 'token-2'];
            })
            ->andReturn(MulticastSendReport::withItems([]));
        $this->app->instance(Messaging::class, $messaging);

        $usuario->notify($this->notificacionFcm());

        $this->assertDatabaseCount('dispositivos', 2);
    }

    public function test_elimina_los_tokens_invalidos_o_desconocidos_tras_el_envio(): void
    {
        $usuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-valido', 'plataforma' => 'android']);
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-desconocido', 'plataforma' => 'android']);

        $reporte = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-valido'), []),
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'token-desconocido'),
                NotFound::becauseTokenNotFound('token-desconocido')
            ),
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn($reporte);
        $this->app->instance(Messaging::class, $messaging);

        $usuario->notify($this->notificacionFcm());

        $this->assertDatabaseHas('dispositivos', ['token' => 'token-valido']);
        $this->assertDatabaseMissing('dispositivos', ['token' => 'token-desconocido']);
    }

    public function test_no_envia_nada_si_el_usuario_no_tiene_dispositivos_registrados(): void
    {
        $usuario = User::factory()->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        $usuario->notify($this->notificacionFcm());
    }

    public function test_no_envia_nada_si_la_notification_no_implementa_tofcm(): void
    {
        $usuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-1', 'plataforma' => 'android']);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        $usuario->notify($this->notificacionSinFcm());
    }

    public function test_una_falla_de_firebase_no_interrumpe_el_envio(): void
    {
        $usuario = User::factory()->create();
        Dispositivo::create(['id_usuario' => $usuario->id, 'token' => 'token-1', 'plataforma' => 'android']);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andThrow(new \RuntimeException('Firebase no disponible.'));
        $this->app->instance(Messaging::class, $messaging);

        // No debe lanzar la excepción hacia afuera: se registra en el log y sigue.
        $usuario->notify($this->notificacionFcm());

        $this->assertDatabaseHas('dispositivos', ['token' => 'token-1']);
    }
}
