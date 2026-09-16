<?php

namespace Tests\Feature\Api\V1;

use App\Models\CargaMaterial;
use App\Models\Material;
use App\Models\ParametrosEmpresa;
use App\Models\User;
use App\Models\VehiculoExterno;
use App\Notifications\CargaMaterialRegistradaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargaMaterialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $conductor;

    private User $jefeArea;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        // Marcar un flete como PAGADA exige este permiso (ver CargaMaterialController::pagar()).
        Permission::firstOrCreate(['name' => 'control-cargas.marcar-pagado', 'guard_name' => 'web']);

        $this->conductor = User::factory()->create();
        $this->conductor->assignRole('conductor');

        $this->jefeArea = User::factory()->create();
        $this->jefeArea->assignRole('jefe-area');

        // CargaMaterial::calcularGestion()/getNroAttribute() leen este parámetro.
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

    public function test_store_abre_una_carga_sin_registrar_ningun_viaje(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'nombre_conductor' => 'Juan Externo',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('carga_material', 1);
        $this->assertDatabaseCount('viaje', 0);

        $carga = CargaMaterial::first();
        $this->assertSame($this->conductor->id, $carga->id_usuario_apertura);
        $this->assertSame('ABIERTA', $carga->estado_carga);
    }

    public function test_store_notifica_a_los_supervisores_jefe_area_y_administrador(): void
    {
        Notification::fake();

        $vehiculoExterno = VehiculoExterno::factory()->create();

        $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'nombre_conductor' => 'Juan Externo',
        ])->assertCreated();

        Notification::assertSentTo($this->jefeArea, CargaMaterialRegistradaNotification::class);
        Notification::assertNothingSentTo($this->conductor);
    }

    public function test_store_permite_registrar_el_primer_viaje_junto_con_la_carga(): void
    {
        Storage::fake('public');

        $vehiculoExterno = VehiculoExterno::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'viaje' => [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('carga_material', 1);
        $this->assertDatabaseCount('viaje', 1);

        $carga = CargaMaterial::first();
        $viaje = $carga->viajes()->first();
        $this->assertSame($material->id, $viaje->id_material);
        $this->assertSame($this->conductor->id, $viaje->id_usuario_registro);
        $this->assertSame('Cantera Norte', $viaje->origen);
        Storage::disk('public')->assertExists($viaje->foto);
    }

    public function test_store_requiere_los_campos_del_viaje_cuando_se_envia_ese_bloque(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'viaje' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'viaje.id_material',
            'viaje.foto',
            'viaje.origen',
            'viaje.destino',
        ]);
        $this->assertDatabaseCount('carga_material', 0);
    }

    public function test_store_requiere_vehiculo(): void
    {
        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['id_vehiculo_externo']);
    }

    public function test_index_un_conductor_solo_ve_sus_propias_cargas(): void
    {
        $this->actingAs($this->conductor);
        $miCarga = CargaMaterial::factory()->create();

        $this->actingAs($this->jefeArea);
        CargaMaterial::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.cargas-material.index'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($miCarga->id, $response->json('data.0.id'));
    }

    public function test_index_un_jefe_de_area_ve_todas_las_cargas(): void
    {
        $this->actingAs($this->conductor);
        CargaMaterial::factory()->create();

        $this->actingAs($this->jefeArea);
        CargaMaterial::factory()->create();

        $response = $this->actingAs($this->jefeArea, 'api')->getJson(route('api.v1.cargas-material.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_incluye_los_viajes_con_su_material(): void
    {
        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $material = Material::factory()->create(['material' => 'Arena']);
        $carga->viajes()->create([
            'id_material' => $material->id,
            'foto' => 'control-cargas/viajes/foto.jpg',
            'origen' => 'Cantera',
            'destino' => 'Planta',
            'fecha_hora_carga' => now(),
        ]);

        $response = $this->actingAs($this->conductor, 'api')->getJson(route('api.v1.cargas-material.show', $carga->id));

        $response->assertOk();
        $this->assertCount(1, $response->json('data.viajes'));
        $this->assertSame('Arena', $response->json('data.viajes.0.material.material'));
    }

    public function test_registrar_viaje_agrega_un_viaje_separado_a_una_carga_abierta(): void
    {
        Storage::fake('public');

        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(
            route('api.v1.cargas-material.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
            ]
        );

        $response->assertCreated();
        $this->assertDatabaseCount('viaje', 1);
        $this->assertSame($carga->id, $response->json('data.id_carga_material'));
    }

    public function test_registrar_viaje_asigna_la_fecha_hora_actual_del_servidor_por_defecto(): void
    {
        Storage::fake('public');
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));

        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(
            route('api.v1.cargas-material.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
                // Un intento de forzar la fecha sin is_offline=true se ignora.
                'fecha_hora_carga' => '2020-01-01 00:00:00',
            ]
        );

        $response->assertCreated();
        $viaje = $carga->viajes()->first();
        $this->assertTrue(now()->equalTo($viaje->fecha_hora_carga));
    }

    public function test_registrar_viaje_offline_respeta_la_fecha_hora_carga_enviada(): void
    {
        Storage::fake('public');

        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(
            route('api.v1.cargas-material.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
                'is_offline' => true,
                'fecha_hora_carga' => '2026-08-18 09:15:00',
            ]
        );

        $response->assertCreated();
        $viaje = $carga->viajes()->first();
        $this->assertSame('2026-08-18 09:15:00', $viaje->fecha_hora_carga->format('Y-m-d H:i:s'));
    }

    public function test_registrar_viaje_offline_requiere_fecha_hora_carga(): void
    {
        Storage::fake('public');

        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(
            route('api.v1.cargas-material.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
                'is_offline' => true,
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['fecha_hora_carga']);
    }

    public function test_store_asigna_la_fecha_de_apertura_actual_del_servidor_por_defecto(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));

        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            // Un intento de forzar la fecha sin is_offline=true se ignora.
            'fecha_apertura' => '2020-01-01 00:00:00',
        ]);

        $response->assertCreated();
        $carga = CargaMaterial::first();
        $this->assertTrue(now()->equalTo($carga->fecha_apertura));
    }

    public function test_store_offline_respeta_la_fecha_de_apertura_y_la_fecha_hora_carga_del_primer_viaje(): void
    {
        Storage::fake('public');

        $vehiculoExterno = VehiculoExterno::factory()->create();
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'is_offline' => true,
            'fecha_apertura' => '2026-08-18 09:00:00',
            'viaje' => [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
                'fecha_hora_carga' => '2026-08-18 09:15:00',
            ],
        ]);

        $response->assertCreated();
        $carga = CargaMaterial::first();
        $this->assertSame('2026-08-18 09:00:00', $carga->fecha_apertura->format('Y-m-d H:i:s'));

        $viaje = $carga->viajes()->first();
        $this->assertSame('2026-08-18 09:15:00', $viaje->fecha_hora_carga->format('Y-m-d H:i:s'));
    }

    public function test_store_offline_requiere_fecha_apertura(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'is_offline' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['fecha_apertura']);
    }

    public function test_store_requiere_pais_cuando_es_al_exterior(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'es_al_exterior' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pais']);
    }

    public function test_store_guarda_es_al_exterior_pais_y_detalle(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'es_al_exterior' => true,
            'pais' => 'Perú',
            'detalle' => 'Carga urgente',
        ]);

        $response->assertCreated();
        $carga = CargaMaterial::first();
        $this->assertTrue($carga->es_al_exterior);
        $this->assertSame('Perú', $carga->pais);
        $this->assertSame('Carga urgente', $carga->detalle);
    }

    public function test_store_ignora_observaciones_enviadas_por_un_conductor(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'observaciones' => 'Intento de un conductor',
        ]);

        $response->assertCreated();
        $this->assertNull(CargaMaterial::first()->observaciones);
    }

    public function test_store_permite_observaciones_de_un_jefe_de_area(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->jefeArea, 'api')->postJson(route('api.v1.cargas-material.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'observaciones' => 'Nota del jefe de área',
        ]);

        $response->assertCreated();
        $this->assertSame('Nota del jefe de área', CargaMaterial::first()->observaciones);
    }

    public function test_cerrar_cierra_un_flete_abierto_del_propio_conductor(): void
    {
        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')
            ->postJson(route('api.v1.cargas-material.cerrar', $carga->id));

        $response->assertOk();
        $response->assertJsonPath('data.estado_carga', 'CERRADA');
        $carga->refresh();
        $this->assertSame('CERRADA', $carga->estado_carga);
        $this->assertSame($this->conductor->id, $carga->id_usuario_cierre);
        $this->assertNotNull($carga->fecha_cierre);
    }

    public function test_cerrar_rechaza_a_un_conductor_que_no_abrio_el_flete(): void
    {
        $this->actingAs($this->jefeArea);
        $carga = CargaMaterial::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')
            ->postJson(route('api.v1.cargas-material.cerrar', $carga->id));

        $response->assertStatus(403);
        $this->assertSame('ABIERTA', $carga->refresh()->estado_carga);
    }

    public function test_cerrar_falla_si_el_flete_no_esta_abierto(): void
    {
        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor, 'api')
            ->postJson(route('api.v1.cargas-material.cerrar', $carga->id));

        $response->assertStatus(422);
    }

    public function test_pagar_marca_como_pagado_un_flete_cerrado(): void
    {
        $this->jefeArea->givePermissionTo('control-cargas.marcar-pagado');

        $this->actingAs($this->jefeArea);
        $carga = CargaMaterial::factory()->create();
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->jefeArea, 'api')->postJson(
            route('api.v1.cargas-material.pagar', $carga->id),
            ['monto_pago' => 1500.50, 'observaciones' => 'Pago por transferencia']
        );

        $response->assertOk();
        $response->assertJsonPath('data.estado_carga', 'PAGADA');
        $carga->refresh();
        $this->assertSame('PAGADA', $carga->estado_carga);
        $this->assertEquals(1500.50, $carga->monto_pago);
        $this->assertSame('Pago por transferencia', $carga->observaciones);
        $this->assertNotNull($carga->fecha_pago);
    }

    public function test_pagar_requiere_el_permiso_marcar_pagado(): void
    {
        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor, 'api')
            ->postJson(route('api.v1.cargas-material.pagar', $carga->id));

        $response->assertStatus(403);
        $this->assertSame('CERRADA', $carga->refresh()->estado_carga);
    }

    public function test_pagar_falla_si_el_flete_no_esta_cerrado(): void
    {
        $this->jefeArea->givePermissionTo('control-cargas.marcar-pagado');

        $this->actingAs($this->jefeArea);
        $carga = CargaMaterial::factory()->create(); // ABIERTA

        $response = $this->actingAs($this->jefeArea, 'api')
            ->postJson(route('api.v1.cargas-material.pagar', $carga->id));

        $response->assertStatus(422);
    }

    public function test_registrar_viaje_falla_si_la_carga_esta_cerrada(): void
    {
        Storage::fake('public');

        $this->actingAs($this->conductor);
        $carga = CargaMaterial::factory()->create();
        $carga->update(['estado_carga' => 'CERRADA']);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor, 'api')->postJson(
            route('api.v1.cargas-material.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
            ]
        );

        $response->assertUnprocessable();
        $this->assertDatabaseCount('viaje', 0);
    }
}
