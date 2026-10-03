<?php

namespace Tests\Feature\Api\V1;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionDiariaPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        // OperacionDiaria::getNroAttribute() lee digitos_serie de este parámetro.
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

    private function crearConductorConUsuario(): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('conductor');

        return [$user, $conductor];
    }

    private function crearOperacion(Conductor $conductor): OperacionDiaria
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $area = Area::factory()->create();

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return OperacionDiaria::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_area' => $area->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4),
            'fecha_fin' => now(),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'estado' => 'FINALIZADO',
        ]);
    }

    public function test_un_conductor_descarga_el_reporte_de_su_propia_operacion(): void
    {
        [$user, $conductor] = $this->crearConductorConUsuario();
        $operacion = $this->crearOperacion($conductor);

        $response = $this->actingAs($user, 'api')->get(route('api.v1.operacion-diaria.pdf', $operacion));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="reporte_operacion_'.str_replace('/', '-', (string) $operacion->nro).'.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_administrador_descarga_el_reporte_de_cualquier_operacion(): void
    {
        [, $conductor] = $this->crearConductorConUsuario();
        $operacion = $this->crearOperacion($conductor);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin, 'api')->get(route('api.v1.operacion-diaria.pdf', $operacion));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * El rol administrador prevalece sobre conductor: no debe recibir 403
     * por no ser el conductor del registro.
     */
    public function test_un_administrador_que_tambien_es_conductor_descarga_el_reporte_de_cualquier_operacion(): void
    {
        [, $conductor] = $this->crearConductorConUsuario();
        $operacion = $this->crearOperacion($conductor);

        $admin = User::factory()->create(['id_persona' => Persona::factory()->create()->id]);
        $admin->assignRole(['administrador', 'conductor']);

        $response = $this->actingAs($admin, 'api')->get(route('api.v1.operacion-diaria.pdf', $operacion));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_un_conductor_no_descarga_el_reporte_de_otro_conductor(): void
    {
        [, $conductor] = $this->crearConductorConUsuario();
        $operacion = $this->crearOperacion($conductor);

        [$otroUser] = $this->crearConductorConUsuario();

        $response = $this->actingAs($otroUser, 'api')->get(route('api.v1.operacion-diaria.pdf', $operacion));

        $response->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        [, $conductor] = $this->crearConductorConUsuario();
        $operacion = $this->crearOperacion($conductor);

        $this->get(route('api.v1.operacion-diaria.pdf', $operacion))->assertStatus(401);
    }
}
