<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ValePdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        // Vale::boot() calcula fecha_vencimiento a partir de este parámetro.
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
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function crearVale(Conductor $conductor): Vale
    {
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba',
            'nit' => '123456',
            'direccion' => 'Av. Siempre Viva 123',
            'ciudad' => 'Oruro',
            'telefono' => '2525252',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);

        return Vale::create([
            'nro_vale' => 1,
            'gestion' => now()->year,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'litros' => 50,
            'precio' => 400,
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => $conductor->id,
            'id_grifo' => $grifo->id,
            'estado_vale' => 'PENDIENTE',
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
        ]);
    }

    public function test_un_conductor_puede_descargar_su_propio_vale_en_pdf(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vale = $this->crearVale($conductor);

        $response = $this->actingAs($user, 'api')
            ->get(route('api.v1.vales.pdf', $vale->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="vale_'.str_replace('/', '-', $vale->nro).'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_un_administrador_puede_descargar_cualquier_vale(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $conductor = $this->crearConductor();
        $vale = $this->crearVale($conductor);

        $response = $this->actingAs($admin, 'api')
            ->get(route('api.v1.vales.pdf', $vale->id));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_un_conductor_no_puede_descargar_el_vale_de_otro_conductor(): void
    {
        $conductor = $this->crearConductor();
        $vale = $this->crearVale($conductor);

        $otroConductor = $this->crearConductor();
        $otroUser = User::factory()->create(['id_persona' => $otroConductor->id]);
        $otroUser->assignRole('conductor');

        $response = $this->actingAs($otroUser, 'api')
            ->get(route('api.v1.vales.pdf', $vale->id));

        $response->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        $conductor = $this->crearConductor();
        $vale = $this->crearVale($conductor);

        $response = $this->get(route('api.v1.vales.pdf', $vale->id));

        $response->assertStatus(401);
    }
}
