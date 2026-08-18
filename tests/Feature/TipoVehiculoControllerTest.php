<?php

namespace Tests\Feature;

use App\Models\IntervaloMantenimientoTipo;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TipoVehiculoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);
    }

    private function crearTipoMantenimiento(string $nombre = 'Cambio de aceite'): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => $nombre,
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
    }

    public function test_store_crea_el_tipo_de_vehiculo_con_sus_intervalos(): void
    {
        $aceite = $this->crearTipoMantenimiento('Cambio de aceite');
        $frenos = $this->crearTipoMantenimiento('Revisión de frenos');

        $response = $this->post(route('tipos-vehiculo.store'), [
            'tipo_vehiculo' => 'Camioneta',
            'estado_tipo_vehiculo' => 'ACTIVO',
            'intervalos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'tipo_medicion' => 'kilometraje', 'frecuencia' => 10000],
                ['id_tipo_mantenimiento' => $frenos->id, 'tipo_medicion' => 'kilometraje', 'frecuencia' => 15000],
            ],
        ]);

        $response->assertRedirect(route('tipos-vehiculo.index'));

        $tipoVehiculo = TipoVehiculo::where('tipo_vehiculo', 'Camioneta')->firstOrFail();
        $this->assertSame(2, $tipoVehiculo->intervalos()->count());
        $this->assertDatabaseHas('intervalo_mantenimiento_tipo', [
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'frecuencia' => 10000,
        ]);
    }

    public function test_store_rechaza_dos_intervalos_para_el_mismo_tipo_de_mantenimiento(): void
    {
        $aceite = $this->crearTipoMantenimiento('Cambio de aceite');

        $response = $this->post(route('tipos-vehiculo.store'), [
            'tipo_vehiculo' => 'Camioneta',
            'estado_tipo_vehiculo' => 'ACTIVO',
            'intervalos' => [
                ['id_tipo_mantenimiento' => $aceite->id, 'tipo_medicion' => 'kilometraje', 'frecuencia' => 10000],
                ['id_tipo_mantenimiento' => $aceite->id, 'tipo_medicion' => 'kilometraje', 'frecuencia' => 20000],
            ],
        ]);

        $response->assertSessionHasErrors(['intervalos.0.id_tipo_mantenimiento', 'intervalos.1.id_tipo_mantenimiento']);
        $this->assertDatabaseMissing('tipo_vehiculo', ['tipo_vehiculo' => 'Camioneta']);
    }

    public function test_edit_reutiliza_la_pagina_create_con_los_intervalos_cargados(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create(['tipo_vehiculo' => 'Bus']);
        $aceite = $this->crearTipoMantenimiento('Cambio de aceite');
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => 10000,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->get(route('tipos-vehiculo.edit', $tipoVehiculo));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('TiposVehiculo/Create')
            ->where('tipo.id', $tipoVehiculo->id)
            ->has('tipo.intervalos', 1)
            ->where('tipo.intervalos.0.id_tipo_mantenimiento', $aceite->id)
        );
    }

    public function test_update_reemplaza_los_intervalos_existentes(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create();
        $aceite = $this->crearTipoMantenimiento('Cambio de aceite');
        $frenos = $this->crearTipoMantenimiento('Revisión de frenos');
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => 10000,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->put(route('tipos-vehiculo.update', $tipoVehiculo), [
            'tipo_vehiculo' => $tipoVehiculo->tipo_vehiculo,
            'estado_tipo_vehiculo' => 'ACTIVO',
            'intervalos' => [
                ['id_tipo_mantenimiento' => $frenos->id, 'tipo_medicion' => 'horometro', 'frecuencia' => 250],
            ],
        ]);

        $response->assertRedirect(route('tipos-vehiculo.index'));

        $intervalos = $tipoVehiculo->intervalos()->get();
        $this->assertCount(1, $intervalos);
        $this->assertSame($frenos->id, $intervalos->first()->id_tipo_mantenimiento);
        $this->assertSame('horometro', $intervalos->first()->tipo_medicion);
    }

    public function test_destroy_elimina_los_intervalos_junto_con_el_tipo(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create();
        $aceite = $this->crearTipoMantenimiento();
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $aceite->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => 10000,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->delete(route('tipos-vehiculo.destroy', $tipoVehiculo));

        $response->assertRedirect(route('tipos-vehiculo.index'));
        $this->assertDatabaseMissing('tipo_vehiculo', ['id' => $tipoVehiculo->id]);
        $this->assertDatabaseMissing('intervalo_mantenimiento_tipo', ['id_tipo_vehiculo' => $tipoVehiculo->id]);
    }

    public function test_destroy_no_elimina_si_hay_vehiculos_asignados(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create();
        Vehiculo::factory()->create(['id_tipo_vehiculo' => $tipoVehiculo->id]);

        $response = $this->delete(route('tipos-vehiculo.destroy', $tipoVehiculo));

        $response->assertRedirect(route('tipos-vehiculo.index'));
        $this->assertDatabaseHas('tipo_vehiculo', ['id' => $tipoVehiculo->id]);
    }
}
