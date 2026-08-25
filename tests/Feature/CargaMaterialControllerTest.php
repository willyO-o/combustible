<?php

namespace Tests\Feature;

use App\Models\CargaMaterial;
use App\Models\Material;
use App\Models\ParametrosEmpresa;
use App\Models\User;
use App\Models\VehiculoExterno;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);

        $this->conductor = User::factory()->create();
        $this->conductor->assignRole('conductor');

        $this->jefeArea = User::factory()->create();
        $this->jefeArea->assignRole('jefe-area');

        // control-cargas.marcar-pagado: sólo jefe-area/administrador/super-admin
        // lo tienen (ver UserSeeder::permisosControlCargasPago()); un conductor
        // nunca lo recibe.
        Permission::firstOrCreate(['name' => 'control-cargas.marcar-pagado', 'guard_name' => 'web']);
        $this->jefeArea->givePermissionTo('control-cargas.marcar-pagado');

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

    /** Crea una carga abierta por el usuario indicado, sin afectar la sesión actual. */
    private function crearCargaAbiertaPor(User $usuario): CargaMaterial
    {
        $this->actingAs($usuario);

        return CargaMaterial::factory()->create();
    }

    public function test_index_un_conductor_solo_ve_sus_propias_cargas(): void
    {
        $otroConductor = User::factory()->create();
        $otroConductor->assignRole('conductor');

        $miCarga = $this->crearCargaAbiertaPor($this->conductor);
        $this->crearCargaAbiertaPor($otroConductor);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ControlCargas/Index')
            ->where('cargas.data', fn ($cargas) => count($cargas) === 1 && $cargas[0]['id'] === $miCarga->id)
        );
    }

    public function test_index_un_jefe_de_area_ve_todas_las_cargas(): void
    {
        $otroConductor = User::factory()->create();
        $otroConductor->assignRole('conductor');

        $this->crearCargaAbiertaPor($this->conductor);
        $this->crearCargaAbiertaPor($otroConductor);

        $response = $this->actingAs($this->jefeArea)->get(route('control-cargas.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ControlCargas/Index')
            ->where('cargas.data', fn ($cargas) => count($cargas) === 2)
        );
    }

    public function test_index_filtra_por_estado_carga(): void
    {
        $abierta = $this->crearCargaAbiertaPor($this->conductor);
        $cerrada = $this->crearCargaAbiertaPor($this->conductor);
        $cerrada->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.index', ['estado_carga' => 'CERRADA']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('cargas.data', fn ($cargas) => count($cargas) === 1 && $cargas[0]['id'] === $cerrada->id)
        );
    }

    public function test_index_busca_por_placa(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create(['nro_placa' => '9988-ZZZ']);
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['id_vehiculo_externo' => $vehiculoExterno->id]);
        $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.index', ['q' => '9988']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('cargas.data', fn ($cargas) => count($cargas) === 1 && $cargas[0]['id'] === $carga->id)
        );
    }

    public function test_store_abre_una_nueva_carga_y_redirige_al_detalle(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'nombre_conductor' => 'Juan Externo',
            'telefono' => '77712345',
        ]);

        $carga = CargaMaterial::first();

        $response->assertRedirect(route('control-cargas.show', $carga->id));
        $this->assertSame(1, $carga->nro_carga);
        $this->assertSame('ABIERTA', $carga->estado_carga);
        $this->assertSame($this->conductor->id, $carga->id_usuario_apertura);
        $this->assertSame('Juan Externo', $carga->nombre_conductor);
    }

    public function test_store_numera_las_cargas_de_forma_secuencial(): void
    {
        $datos = ['id_vehiculo_externo' => VehiculoExterno::factory()->create()->id];

        $this->actingAs($this->conductor)->post(route('control-cargas.store'), $datos);
        $this->actingAs($this->conductor)->post(route('control-cargas.store'), $datos);

        $this->assertSame([1, 2], CargaMaterial::orderBy('id')->pluck('nro_carga')->toArray());
    }

    public function test_store_asigna_la_gestion_configurada_y_formatea_el_nro(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $this->actingAs($this->conductor)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
        ]);

        $carga = CargaMaterial::first();

        $this->assertSame((string) now()->year, $carga->gestion);
        $this->assertSame('000001/'.now()->year, $carga->nro);
    }

    public function test_store_requiere_pais_cuando_es_al_exterior(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'es_al_exterior' => true,
        ]);

        $response->assertSessionHasErrors(['pais']);
    }

    public function test_store_guarda_es_al_exterior_pais_y_detalle(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $this->actingAs($this->conductor)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'es_al_exterior' => true,
            'pais' => 'Perú',
            'detalle' => 'Carga urgente',
        ]);

        $carga = CargaMaterial::first();
        $this->assertTrue($carga->es_al_exterior);
        $this->assertSame('Perú', $carga->pais);
        $this->assertSame('Carga urgente', $carga->detalle);
    }

    public function test_store_ignora_observaciones_enviadas_por_un_conductor(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $this->actingAs($this->conductor)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'observaciones' => 'Intento de un conductor',
        ]);

        $this->assertNull(CargaMaterial::first()->observaciones);
    }

    public function test_store_permite_observaciones_de_un_jefe_de_area(): void
    {
        $vehiculoExterno = VehiculoExterno::factory()->create();

        $this->actingAs($this->jefeArea)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
            'observaciones' => 'Nota del jefe de área',
        ]);

        $this->assertSame('Nota del jefe de área', CargaMaterial::first()->observaciones);
    }

    public function test_store_requiere_vehiculo(): void
    {
        $response = $this->actingAs($this->conductor)->post(route('control-cargas.store'), []);

        $response->assertSessionHasErrors(['id_vehiculo_externo']);
    }

    public function test_store_rechaza_a_un_usuario_sin_rol_permitido(): void
    {
        $tecnico = User::factory()->create();
        $tecnico->assignRole('tecnico-mantenimiento');

        $vehiculoExterno = VehiculoExterno::factory()->create();

        $response = $this->actingAs($tecnico)->post(route('control-cargas.store'), [
            'id_vehiculo_externo' => $vehiculoExterno->id,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, CargaMaterial::count());
    }

    public function test_create_muestra_el_formulario_con_el_catalogo_de_vehiculos(): void
    {
        VehiculoExterno::factory()->create();

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ControlCargas/Form')
            ->has('vehiculosExternos', 1)
            ->where('puedeEditarObservaciones', false)
        );
    }

    public function test_edit_muestra_los_datos_de_la_carga_a_su_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.edit', $carga->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ControlCargas/Form')
            ->where('carga.id', $carga->id)
            ->where('puedeEditarObservaciones', false)
        );
    }

    public function test_edit_permite_editar_observaciones_a_un_jefe_de_area(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->jefeArea)->get(route('control-cargas.edit', $carga->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('puedeEditarObservaciones', true)
        );
    }

    public function test_edit_bloqueado_para_un_conductor_que_no_es_el_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $otroConductor = User::factory()->create();
        $otroConductor->assignRole('conductor');

        $response = $this->actingAs($otroConductor)->get(route('control-cargas.edit', $carga->id));

        $response->assertForbidden();
    }

    public function test_edit_permitido_para_jefe_de_area_aunque_no_sea_el_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->jefeArea)->get(route('control-cargas.edit', $carga->id));

        $response->assertOk();
    }

    public function test_edit_bloqueado_si_la_carga_ya_no_esta_abierta(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.edit', $carga->id));

        $response->assertForbidden();
    }

    public function test_update_actualiza_los_datos_editables(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->put(route('control-cargas.update', $carga->id), [
            'nombre_conductor' => 'Nuevo Nombre',
            'telefono' => '77700000',
            'es_al_exterior' => true,
            'pais' => 'Perú',
            'detalle' => 'Carga urgente',
        ]);

        $response->assertRedirect(route('control-cargas.index'));
        $carga->refresh();
        $this->assertSame('Nuevo Nombre', $carga->nombre_conductor);
        $this->assertSame('77700000', $carga->telefono);
        $this->assertTrue($carga->es_al_exterior);
        $this->assertSame('Perú', $carga->pais);
        $this->assertSame('Carga urgente', $carga->detalle);
    }

    public function test_update_ignora_observaciones_enviadas_por_un_conductor(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $this->actingAs($this->conductor)->put(route('control-cargas.update', $carga->id), [
            'nombre_conductor' => 'Nuevo Nombre',
            'observaciones' => 'Intento de un conductor',
        ]);

        $this->assertNull($carga->fresh()->observaciones);
    }

    public function test_update_permite_observaciones_de_un_jefe_de_area(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->jefeArea)->put(route('control-cargas.update', $carga->id), [
            'observaciones' => 'Nota del jefe de área',
        ]);

        $response->assertRedirect(route('control-cargas.index'));
        $this->assertSame('Nota del jefe de área', $carga->fresh()->observaciones);
    }

    public function test_update_no_falla_cuando_el_formulario_reenvia_el_vehiculo_vacio(): void
    {
        // El formulario de edición no muestra el select de vehículo, pero
        // Inertia igual reenvía id_vehiculo_externo como '' en el payload;
        // no debe disparar un error de validación sobre un campo que ni
        // siquiera se está editando.
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->put(route('control-cargas.update', $carga->id), [
            'id_vehiculo_externo' => '',
            'nombre_conductor' => 'Nuevo Nombre',
        ]);

        $response->assertRedirect(route('control-cargas.index'));
        $response->assertSessionDoesntHaveErrors('id_vehiculo_externo');
        $this->assertSame('Nuevo Nombre', $carga->fresh()->nombre_conductor);
    }

    public function test_update_ignora_cambios_de_vehiculo_externo(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $vehiculoOriginal = $carga->id_vehiculo_externo;
        $otroVehiculo = VehiculoExterno::factory()->create();

        $this->actingAs($this->conductor)->put(route('control-cargas.update', $carga->id), [
            'id_vehiculo_externo' => $otroVehiculo->id,
        ]);

        $carga->refresh();
        $this->assertSame($vehiculoOriginal, $carga->id_vehiculo_externo);
        $this->assertNotEquals($otroVehiculo->id, $carga->id_vehiculo_externo);
    }

    public function test_update_bloqueado_para_un_conductor_que_no_es_el_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $otroConductor = User::factory()->create();
        $otroConductor->assignRole('conductor');

        $response = $this->actingAs($otroConductor)->put(route('control-cargas.update', $carga->id), [
            'nombre_conductor' => 'Intento Ajeno',
        ]);

        $response->assertForbidden();
        $this->assertNotSame('Intento Ajeno', $carga->fresh()->nombre_conductor);
    }

    public function test_show_muestra_los_viajes_de_la_carga_con_su_material(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $material = Material::factory()->create(['material' => 'Arena']);
        Viaje::factory()->create(['id_carga_material' => $carga->id, 'id_material' => $material->id]);
        Viaje::factory()->create(['id_carga_material' => $carga->id]);

        $response = $this->actingAs($this->conductor)->get(route('control-cargas.show', $carga->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ControlCargas/Show')
            ->where('carga.id', $carga->id)
            ->where('viajes', fn ($viajes) => count($viajes) === 2)
            ->has('materiales')
            ->where('viajes.0.material', fn ($material) => $material !== null)
        );
    }

    public function test_registrar_viaje_guarda_la_foto_y_el_material_seleccionado(): void
    {
        Storage::fake('public');

        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor)->post(
            route('control-cargas.viajes.registrar', $carga->id),
            [
                'id_material' => $material->id,
                'foto' => UploadedFile::fake()->image('viaje.jpg'),
                'origen' => 'Cantera Norte',
                'destino' => 'Planta',
            ]
        );

        $response->assertRedirect(route('control-cargas.show', $carga->id));

        $viaje = Viaje::first();
        $this->assertNotNull($viaje);
        $this->assertSame($carga->id, $viaje->id_carga_material);
        $this->assertSame($material->id, $viaje->id_material);
        $this->assertSame($this->conductor->id, $viaje->id_usuario_registro);
        $this->assertSame('Cantera Norte', $viaje->origen);
        Storage::disk('public')->assertExists($viaje->foto);
    }

    public function test_registrar_viaje_requiere_material(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->post(
            route('control-cargas.viajes.registrar', $carga->id),
            ['foto' => UploadedFile::fake()->image('viaje.jpg')]
        );

        $response->assertSessionHasErrors('id_material');
        $this->assertSame(0, Viaje::count());
    }

    public function test_registrar_viaje_requiere_foto(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor)->post(
            route('control-cargas.viajes.registrar', $carga->id),
            ['id_material' => $material->id, 'origen' => 'Cantera Norte']
        );

        $response->assertSessionHasErrors('foto');
        $this->assertSame(0, Viaje::count());
    }

    public function test_registrar_viaje_requiere_origen_y_destino(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor)->post(
            route('control-cargas.viajes.registrar', $carga->id),
            ['id_material' => $material->id, 'foto' => UploadedFile::fake()->image('viaje.jpg')]
        );

        $response->assertSessionHasErrors(['origen', 'destino']);
        $this->assertSame(0, Viaje::count());
    }

    public function test_registrar_viaje_falla_si_la_carga_esta_cerrada(): void
    {
        Storage::fake('public');

        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);
        $material = Material::factory()->create();

        $response = $this->actingAs($this->conductor)
            ->from(route('control-cargas.show', $carga->id))
            ->post(
                route('control-cargas.viajes.registrar', $carga->id),
                [
                    'id_material' => $material->id,
                    'foto' => UploadedFile::fake()->image('viaje.jpg'),
                    'origen' => 'Cantera Norte',
                    'destino' => 'Planta',
                ]
            );

        $response->assertRedirect(route('control-cargas.show', $carga->id));
        $response->assertSessionHas('error');
        $this->assertSame(0, Viaje::count());
    }

    /* -----------------------------------------------------------------
     |  Cerrar carga
     | ----------------------------------------------------------------- */

    public function test_cerrar_marca_la_carga_como_cerrada_y_registra_usuario_y_fecha(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));

        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->conductor)->post(route('control-cargas.cerrar', $carga->id));

        $response->assertRedirect();
        $carga->refresh();
        $this->assertSame('CERRADA', $carga->estado_carga);
        $this->assertSame($this->conductor->id, $carga->id_usuario_cierre);
        $this->assertTrue(now()->equalTo($carga->fecha_cierre));
    }

    public function test_cerrar_bloqueado_para_un_conductor_que_no_es_el_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $otroConductor = User::factory()->create();
        $otroConductor->assignRole('conductor');

        $response = $this->actingAs($otroConductor)->post(route('control-cargas.cerrar', $carga->id));

        $response->assertForbidden();
        $this->assertSame('ABIERTA', $carga->fresh()->estado_carga);
    }

    public function test_cerrar_permitido_para_jefe_de_area_aunque_no_sea_el_dueno(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->jefeArea)->post(route('control-cargas.cerrar', $carga->id));

        $response->assertRedirect();
        $this->assertSame('CERRADA', $carga->fresh()->estado_carga);
        $this->assertSame($this->jefeArea->id, $carga->fresh()->id_usuario_cierre);
    }

    public function test_cerrar_falla_si_la_carga_ya_no_esta_abierta(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor)->post(route('control-cargas.cerrar', $carga->id));

        $response->assertForbidden();
    }

    /* -----------------------------------------------------------------
     |  Marcar como pagado
     | ----------------------------------------------------------------- */

    public function test_pagar_requiere_el_permiso_marcar_pagado(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->conductor)->post(route('control-cargas.pagar', $carga->id));

        $response->assertForbidden();
        $this->assertSame('CERRADA', $carga->fresh()->estado_carga);
    }

    public function test_pagar_marca_la_carga_como_pagada(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(10, 0));

        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->jefeArea)->post(route('control-cargas.pagar', $carga->id));

        $response->assertRedirect();
        $carga->refresh();
        $this->assertSame('PAGADA', $carga->estado_carga);
        $this->assertTrue(now()->equalTo($carga->fecha_pago));
    }

    public function test_pagar_guarda_monto_y_observaciones_cuando_se_envian(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $this->actingAs($this->jefeArea)->post(route('control-cargas.pagar', $carga->id), [
            'monto_pago' => 1500.50,
            'observaciones' => 'Pagado en efectivo',
        ]);

        $carga->refresh();
        $this->assertEquals(1500.50, $carga->monto_pago);
        $this->assertSame('Pagado en efectivo', $carga->observaciones);
    }

    public function test_pagar_permite_omitir_monto_y_observaciones(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);
        $carga->update(['estado_carga' => 'CERRADA']);

        $response = $this->actingAs($this->jefeArea)->post(route('control-cargas.pagar', $carga->id));

        $response->assertRedirect();
        $carga->refresh();
        $this->assertSame('PAGADA', $carga->estado_carga);
        $this->assertNull($carga->monto_pago);
    }

    public function test_pagar_falla_si_la_carga_no_esta_cerrada(): void
    {
        $carga = $this->crearCargaAbiertaPor($this->conductor);

        $response = $this->actingAs($this->jefeArea)->post(route('control-cargas.pagar', $carga->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('ABIERTA', $carga->fresh()->estado_carga);
    }
}
