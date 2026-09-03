<?php

namespace Tests\Feature\Api\V1;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionDiariaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * @return array{0: User, 1: Conductor, 2: Vehiculo}
     */
    private function usuarioConVehiculoAsignado(string $tipoMedicion = 'kilometraje'): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('conductor');

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => $tipoMedicion]);

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $vehiculo->areas()->attach(Area::factory()->create()->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return [$user, $conductor, $vehiculo];
    }

    private function crearTipoMantenimientoOperacion(string $nombre, string $tipoValor = 'cantidad', ?string $unidad = 'L'): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => $nombre,
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => $tipoValor,
            'unidad_medida' => $tipoValor === 'cantidad' ? $unidad : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadValido(Conductor $conductor, Vehiculo $vehiculo, array $extra = []): array
    {
        return array_merge([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4)->format('Y-m-d\TH:i'),
            'fecha_fin' => now()->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            // Vehículo por kilometraje: el cliente envía origen/destino y omite
            // `lugar` por completo (mutuamente excluyentes) — ver
            // SincronizarActividadesRealizadasAction.
            'actividades_realizadas' => [[
                'actividad' => 'Transporte de material',
                'origen' => 'Cantera',
                'destino' => 'Planta',
                'cantidad' => 2,
                'unidad_medida' => 'viajes',
                'hora_inicio' => '08:00',
                'hora_fin' => '10:00',
            ]],
        ], $extra);
    }

    public function test_store_devuelve_el_material_trasladado_y_los_controles_de_mantenimiento(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $material = Material::factory()->create(['material' => 'Concentrado']);
        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $payload = $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null],
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI'],
            ],
        ]);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;

        $response = $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.id_material', $material->id);
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.lugar', null);
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.material.material', 'Concentrado');

        $controles = collect($response->json('data.mantenimientos_operacion'))->keyBy('tipo_mantenimiento');
        $this->assertSame('12.50', $controles['Combustible cargado']['pivot']['valor']);
        $this->assertSame('SI', $controles['Nivel de aceite']['pivot']['realizado']);
    }

    public function test_show_devuelve_los_controles_de_mantenimiento_registrados(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');

        $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 30, 'realizado' => null],
            ],
        ]))->assertCreated();

        $operacion = OperacionDiaria::firstOrFail();

        $response = $this->actingAs($user, 'api')->getJson(route('api.v1.operacion-diaria.show', $operacion));

        $response->assertOk();
        $response->assertJsonPath('data.mantenimientos_operacion.0.tipo_mantenimiento', 'Combustible cargado');
        $response->assertJsonPath('data.mantenimientos_operacion.0.pivot.valor', '30.00');
    }

    public function test_update_reemplaza_los_controles_y_los_devuelve(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 10, 'realizado' => null],
            ],
        ]))->assertCreated();

        $operacion = OperacionDiaria::firstOrFail();

        $response = $this->actingAs($user, 'api')->putJson(route('api.v1.operacion-diaria.update', $operacion), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI'],
            ],
        ]));

        $response->assertOk();
        $controles = collect($response->json('data.mantenimientos_operacion'));
        $this->assertCount(1, $controles);
        $this->assertSame('Nivel de aceite', $controles->first()['tipo_mantenimiento']);
        $this->assertSame('SI', $controles->first()['pivot']['realizado']);
    }

    public function test_store_rechaza_un_control_que_no_es_de_operacion_diaria(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $taller = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);

        $response = $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $taller->id, 'valor' => 5, 'realizado' => null],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mantenimientos.0.id_tipo_mantenimiento');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }
}
