<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehiculoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    public function test_index_filtra_vehiculos_por_nro_placa(): void
    {
        Vehiculo::factory()->create(['nro_placa' => '1234ABC']);
        Vehiculo::factory()->create(['nro_placa' => '9999ZZZ']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['nro_placa' => '1234']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.nro_placa', '1234ABC')
        );
    }

    public function test_index_filtra_vehiculos_por_codigo(): void
    {
        Vehiculo::factory()->create(['codigo' => 'ACT-0001']);
        Vehiculo::factory()->create(['codigo' => 'ACT-0002']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['codigo' => '0001']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.codigo', 'ACT-0001')
        );
    }

    public function test_index_filtra_vehiculos_por_area(): void
    {
        $area = Area::factory()->create();
        $vehiculoEnArea = Vehiculo::factory()->create();
        VehiculoArea::create([
            'id_vehiculo' => $vehiculoEnArea->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        Vehiculo::factory()->create(); // sin área asignada

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['id_area' => $area->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.id', $vehiculoEnArea->id)
        );
    }

    public function test_crea_un_vehiculo_con_codigo_y_modelo(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '1111AAA',
            'codigo' => 'ACT-0099',
            'modelo' => 'Hilux',
            'marca' => 'Toyota',
            'anio' => '2022',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'horometro',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', [
            'nro_placa' => '1111AAA',
            'codigo' => 'ACT-0099',
            'modelo' => 'Hilux',
            'tipo_medicion' => 'horometro',
        ]);
    }

    public function test_edit_reutiliza_la_pagina_create_con_los_datos_del_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0005', 'modelo' => 'Corolla']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.edit', $vehiculo->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Create')
            ->where('vehiculo.id', $vehiculo->id)
            ->where('vehiculo.codigo', 'ACT-0005')
            ->where('vehiculo.modelo', 'Corolla')
        );
    }

    public function test_actualiza_el_codigo_y_modelo_de_un_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0010', 'modelo' => 'Corolla']);

        $response = $this->actingAs($this->admin)->put(route('vehiculos.update', $vehiculo->id), [
            'nro_placa' => $vehiculo->nro_placa,
            'codigo' => 'ACT-0011',
            'modelo' => 'Hilux',
            'marca' => $vehiculo->marca,
            'anio' => $vehiculo->anio,
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', [
            'id' => $vehiculo->id,
            'codigo' => 'ACT-0011',
            'modelo' => 'Hilux',
        ]);
    }
}
