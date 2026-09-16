<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Notifications\ValePorVencerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificarValesPorVencerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Vale::boot() calcula fecha_vencimiento a partir de este parámetro;
        // se sobrescribe manualmente después de crear cada vale para no
        // depender de tiempo_expiracion en los distintos escenarios.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 30],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearVale(array $overrides = []): Vale
    {
        $conductor = Conductor::factory()->create();
        $vale = Vale::create(array_merge([
            'litros' => 20,
            'precio' => 6.97,
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => Grifo::create([
                'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
                'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
            ])->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'estado_vale' => 'PENDIENTE',
        ], $overrides));

        if (isset($overrides['fecha_vencimiento'])) {
            $vale->update(['fecha_vencimiento' => $overrides['fecha_vencimiento']]);
        }

        return $vale;
    }

    public function test_notifica_al_conductor_de_un_vale_pendiente_por_vencer(): void
    {
        Notification::fake();

        $vale = $this->crearVale(['fecha_vencimiento' => now()->addDay()]);
        $usuarioConductor = User::factory()->create(['id_persona' => $vale->id_conductor]);

        $this->artisan('vales:notificar-vencimiento')->assertExitCode(0);

        Notification::assertSentTo($usuarioConductor, ValePorVencerNotification::class);
        $this->assertNotNull($vale->fresh()->notificado_vencimiento_at);
    }

    public function test_no_notifica_un_vale_que_vence_fuera_del_umbral(): void
    {
        Notification::fake();

        $vale = $this->crearVale(['fecha_vencimiento' => now()->addDays(10)]);
        User::factory()->create(['id_persona' => $vale->id_conductor]);

        $this->artisan('vales:notificar-vencimiento')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($vale->fresh()->notificado_vencimiento_at);
    }

    public function test_no_notifica_dos_veces_el_mismo_vale(): void
    {
        Notification::fake();

        $vale = $this->crearVale(['fecha_vencimiento' => now()->addDay()]);
        User::factory()->create(['id_persona' => $vale->id_conductor]);

        $this->artisan('vales:notificar-vencimiento');
        Notification::fake(); // limpia el historial antes de la segunda corrida

        $this->artisan('vales:notificar-vencimiento');

        Notification::assertNothingSent();
    }

    public function test_no_notifica_un_vale_ya_usado(): void
    {
        Notification::fake();

        $vale = $this->crearVale(['fecha_vencimiento' => now()->addDay(), 'estado_vale' => 'USADO']);
        User::factory()->create(['id_persona' => $vale->id_conductor]);

        $this->artisan('vales:notificar-vencimiento');

        Notification::assertNothingSent();
    }
}
