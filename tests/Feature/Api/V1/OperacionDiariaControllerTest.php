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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        Storage::fake('public');
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

        // Evidencia obligatoria en ambos controles (uno con valor, el otro
        // marcado "Sí"): la petición pasa a ser multipart (->post(), no
        // ->postJson() — un cuerpo JSON no puede llevar archivos).
        $payload = $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 12.5, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]);
        $payload['actividades_realizadas'][0]['id_material'] = $material->id;

        $response = $this->actingAs($user, 'api')->post(route('api.v1.operacion-diaria.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.id_material', $material->id);
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.lugar', null);
        $response->assertJsonPath('data.actividades_realizadas.0.pivot.material.material', 'Concentrado');

        $controles = collect($response->json('data.mantenimientos_operacion'))->keyBy('tipo_mantenimiento');
        $this->assertSame('12.50', $controles['Combustible cargado']['pivot']['valor']);
        $this->assertSame('SI', $controles['Nivel de aceite']['pivot']['realizado']);
    }

    /**
     * A diferencia del resto de los tests de este archivo (postJson, cuerpo
     * JSON puro): la evidencia es un archivo, así que la petición debe
     * enviarse como multipart/form-data (mismo patrón que CargaCombustible
     * "respaldos" / CargaMaterial "viaje.foto") — ->post() en vez de
     * ->postJson(), con el arreglo `mantenimientos` anidado tal cual.
     */
    public function test_store_guarda_la_evidencia_del_mantenimiento_convertida_a_webp(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $response = $this->actingAs($user, 'api')->post(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg', 800, 600)],
            ],
        ]));

        $response->assertCreated();

        $operacion = OperacionDiaria::firstOrFail();
        $pivote = $operacion->mantenimientosOperacion()->first()->pivot;

        $this->assertNotNull($pivote->evidencia);
        $this->assertStringEndsWith('.webp', $pivote->evidencia);
        Storage::disk('public')->assertExists($pivote->evidencia);
        $response->assertJsonPath('data.mantenimientos_operacion.0.pivot.evidencia', $pivote->evidencia);
    }

    public function test_update_conserva_la_evidencia_del_mantenimiento_si_no_llega_un_archivo_nuevo(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $this->actingAs($user, 'api')->post(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
            ],
        ]));

        $operacion = OperacionDiaria::firstOrFail();
        $rutaOriginal = $operacion->mantenimientosOperacion()->first()->pivot->evidencia;

        $this->actingAs($user, 'api')->putJson(route('api.v1.operacion-diaria.update', $operacion), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'realizado' => 'SI'],
            ],
        ]));

        $this->assertSame($rutaOriginal, $operacion->mantenimientosOperacion()->first()->pivot->evidencia);
        Storage::disk('public')->assertExists($rutaOriginal);
    }

    public function test_store_exige_evidencia_para_un_control_con_valor_o_realizado_si(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $response = $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI'],
            ],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mantenimientos.0.evidencia');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }

    public function test_store_no_exige_evidencia_para_un_control_marcado_no(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $aceite = $this->crearTipoMantenimientoOperacion('Nivel de aceite', 'booleano');

        $response = $this->actingAs($user, 'api')->postJson(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'NO'],
            ],
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('mantenimiento_operacion_diaria', [
            'id_tipo_mantenimiento' => $aceite->id,
            'realizado' => 'NO',
            'evidencia' => null,
        ]);
    }

    public function test_show_devuelve_los_controles_de_mantenimiento_registrados(): void
    {
        [$user, $conductor, $vehiculo] = $this->usuarioConVehiculoAsignado();
        $combustible = $this->crearTipoMantenimientoOperacion('Combustible cargado', 'cantidad', 'L');

        $this->actingAs($user, 'api')->post(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 30, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
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

        $this->actingAs($user, 'api')->post(route('api.v1.operacion-diaria.store'), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $combustible->id, 'valor' => 10, 'realizado' => null, 'evidencia' => UploadedFile::fake()->image('combustible.jpg')],
            ],
        ]))->assertCreated();

        $operacion = OperacionDiaria::firstOrFail();

        // ->put() (no ->putJson()): el control nuevo también exige evidencia.
        // El cliente de test de Laravel sí sabe mandar archivos con PUT sin
        // necesidad de spoofear con _method (a diferencia del navegador).
        $response = $this->actingAs($user, 'api')->put(route('api.v1.operacion-diaria.update', $operacion), $this->payloadValido($conductor, $vehiculo, [
            'mantenimientos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'valor' => null, 'realizado' => 'SI', 'evidencia' => UploadedFile::fake()->image('aceite.jpg')],
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

    /**
     * Un conductor "puro" (sin rol de gestión) siempre opera como él mismo:
     * cualquier `id_conductor` que envíe se ignora y se usa el suyo propio.
     * El conductor autenticado es el PROVISIONAL (no el titular) a
     * propósito, para probar que se resuelve al usuario autenticado y no
     * "por casualidad" al titular del vehículo.
     */
    public function test_conductor_puro_ignora_el_id_conductor_enviado_y_usa_el_propio(): void
    {
        $titular = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $titular->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        $vehiculo->areas()->attach(Area::factory()->create()->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $personaSuplente = Persona::factory()->create();
        $suplente = Conductor::factory()->create(['id' => $personaSuplente->id]);
        $user = User::factory()->create(['id_persona' => $personaSuplente->id]);
        $user->assignRole('conductor');
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $suplente->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);

        // Envía el id_conductor del titular (otro conductor asignado al
        // mismo vehículo) — debe guardarse igual con el suyo (suplente).
        $response = $this->actingAs($user, 'api')->postJson(
            route('api.v1.operacion-diaria.store'),
            $this->payloadValido($titular, $vehiculo)
        );

        $response->assertCreated();
        $this->assertDatabaseHas('operacion_diaria', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $suplente->id,
        ]);
        $this->assertDatabaseMissing('operacion_diaria', ['id_conductor' => $titular->id]);
    }

    public function test_conductor_con_rol_de_jefe_area_puede_registrar_para_otro_conductor_asignado(): void
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $personaJefe = Persona::factory()->create();
        $conductorJefe = Conductor::factory()->create(['id' => $personaJefe->id]);
        $user = User::factory()->create(['id_persona' => $personaJefe->id]);
        $user->assignRole(['conductor', 'jefe-area']);

        $otroConductor = Conductor::factory()->create();

        // Ambos asignados al mismo vehículo (el jefe de área, como titular;
        // el otro conductor, como provisional que en verdad hizo la jornada).
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorJefe->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);
        $vehiculo->areas()->attach(Area::factory()->create()->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        // Registra la operación a nombre del OTRO conductor, no del suyo.
        $response = $this->actingAs($user, 'api')->postJson(
            route('api.v1.operacion-diaria.store'),
            $this->payloadValido($otroConductor, $vehiculo)
        );

        $response->assertCreated();
        $this->assertDatabaseHas('operacion_diaria', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
        ]);
    }

    public function test_conductor_con_rol_de_administrador_puede_registrar_para_otro_conductor_asignado(): void
    {
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $personaAdmin = Persona::factory()->create();
        $conductorAdmin = Conductor::factory()->create(['id' => $personaAdmin->id]);
        $user = User::factory()->create(['id_persona' => $personaAdmin->id]);
        $user->assignRole(['conductor', 'administrador']);

        $otroConductor = Conductor::factory()->create();

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorAdmin->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);
        $vehiculo->areas()->attach(Area::factory()->create()->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($user, 'api')->postJson(
            route('api.v1.operacion-diaria.store'),
            $this->payloadValido($otroConductor, $vehiculo)
        );

        $response->assertCreated();
        $this->assertDatabaseHas('operacion_diaria', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductor->id,
        ]);
    }

    /**
     * ConductorNoAsignadoException antes cala en el catch (\Exception $e)
     * genérico y devolvía 500; ahora tiene su propio catch, igual que
     * AreaNoAsignadaException.
     */
    public function test_store_devuelve_422_si_el_id_conductor_no_esta_asignado_al_vehiculo(): void
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $personaJefe = Persona::factory()->create();
        $conductorJefe = Conductor::factory()->create(['id' => $personaJefe->id]);
        $user = User::factory()->create(['id_persona' => $personaJefe->id]);
        $user->assignRole(['conductor', 'jefe-area']);

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorJefe->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        $vehiculo->areas()->attach(Area::factory()->create()->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        // Conductor que existe pero NO está asignado a este vehículo.
        $conductorAjeno = Conductor::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(
            route('api.v1.operacion-diaria.store'),
            $this->payloadValido($conductorAjeno, $vehiculo)
        );

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El conductor seleccionado no está asignado actualmente a este vehículo.');
        $this->assertDatabaseCount('operacion_diaria', 0);
    }
}
