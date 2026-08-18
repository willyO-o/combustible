<?php

namespace Tests\Feature;

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

    public function test_marcar_leida_marca_solo_la_notificacion_del_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $notificacion = $this->crearNotificacion($user);
        $notificacionAjena = $this->crearNotificacion($otro);

        $response = $this->actingAs($user)
            ->post(route('notificaciones.marcar-leida', $notificacion->id));

        $response->assertRedirect();
        $this->assertNotNull($notificacion->fresh()->read_at);
        $this->assertNull($notificacionAjena->fresh()->read_at);
    }

    public function test_marcar_leida_de_una_notificacion_ajena_devuelve_404(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $notificacionAjena = $this->crearNotificacion($otro);

        $this->actingAs($user)
            ->post(route('notificaciones.marcar-leida', $notificacionAjena->id))
            ->assertNotFound();
    }

    public function test_marcar_todas_leidas_marca_todas_las_no_leidas_del_usuario(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $primera = $this->crearNotificacion($user);
        $segunda = $this->crearNotificacion($user);
        $ajena = $this->crearNotificacion($otro);

        $response = $this->actingAs($user)
            ->post(route('notificaciones.marcar-todas-leidas'));

        $response->assertRedirect();
        $this->assertNotNull($primera->fresh()->read_at);
        $this->assertNotNull($segunda->fresh()->read_at);
        $this->assertNull($ajena->fresh()->read_at);
    }

    public function test_las_notificaciones_no_leidas_se_comparten_como_prop_de_inertia(): void
    {
        $user = User::factory()->create();
        $this->crearNotificacion($user);
        $this->crearNotificacion($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertInertia(fn ($page) => $page
            ->where('notificaciones.no_leidas', 1)
            ->has('notificaciones.items', 2)
            ->where('notificaciones.items.0.titulo', 'Observación en operación diaria')
        );
    }
}
