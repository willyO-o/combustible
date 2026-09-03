<?php

namespace Tests\Feature\Api\V1;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargaCombustiblePdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearConductor(): Conductor
    {
        return Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function crearCarga(Conductor $conductor): CargaCombustible
    {
        $grifo = Grifo::create([
            'razon_social' => 'Grifo Central',
            'nit' => '123456',
            'direccion' => 'Av. Siempre Viva',
            'ciudad' => 'La Paz',
            'telefono' => '70000000',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);

        return CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => 50,
            'precio' => 10,
            'kilometraje' => 1000,
            'id_vehiculo' => Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje'])->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'id_conductor' => $conductor->id,
            'tipo_carga' => 'PREPAGO',
            'estado_carga' => 'REGISTRADO',
        ]);
    }

    public function test_un_conductor_descarga_el_comprobante_de_su_propia_carga(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $carga = $this->crearCarga($conductor);

        $response = $this->actingAs($user, 'api')->get(route('api.v1.cargas.pdf', $carga));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="comprobante_egreso_'.str_replace('/', '-', (string) $carga->nro).'.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_administrador_descarga_el_comprobante_de_cualquier_carga(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $carga = $this->crearCarga($this->crearConductor());

        $response = $this->actingAs($admin, 'api')->get(route('api.v1.cargas.pdf', $carga));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_conductor_no_descarga_el_comprobante_de_otro_conductor(): void
    {
        $carga = $this->crearCarga($this->crearConductor());

        $otro = $this->crearConductor();
        $otroUser = User::factory()->create(['id_persona' => $otro->id]);
        $otroUser->assignRole('conductor');

        $this->actingAs($otroUser, 'api')->get(route('api.v1.cargas.pdf', $carga))->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        $carga = $this->crearCarga($this->crearConductor());

        $this->get(route('api.v1.cargas.pdf', $carga))->assertStatus(401);
    }
}
