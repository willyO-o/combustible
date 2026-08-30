<?php

namespace Tests\Feature;

use App\Models\CargaMaterial;
use App\Models\Material;
use App\Models\ParametrosEmpresa;
use App\Models\User;
use App\Models\VehiculoExterno;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlCargasReportControllerTest extends TestCase
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

    private function crearViajes(VehiculoExterno $vehiculo, int $cantidad, array $cargaAttrs = [], array $viajeAttrs = []): CargaMaterial
    {
        $carga = CargaMaterial::factory()->create(array_merge([
            'id_vehiculo_externo' => $vehiculo->id,
        ], $cargaAttrs));

        Viaje::factory()->count($cantidad)->create(array_merge([
            'id_carga_material' => $carga->id,
        ], $viajeAttrs));

        return $carga;
    }

    private function filaDe(array $props, int $idVehiculoExterno): ?array
    {
        return collect($props['datosResumen']['vehiculos'])
            ->firstWhere('id_vehiculo_externo', $idVehiculoExterno);
    }

    public function test_index_agrupa_los_viajes_por_vehiculo_externo(): void
    {
        $vehiculoA = VehiculoExterno::factory()->create();
        $vehiculoB = VehiculoExterno::factory()->create();

        // vehículo A: 2 fletes, 5 viajes en total
        $this->crearViajes($vehiculoA, 3);
        $this->crearViajes($vehiculoA, 2);
        // vehículo B: 1 flete, 1 viaje
        $this->crearViajes($vehiculoB, 1);

        $response = $this->get(route('control-cargas.reporte.index', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/ControlCargasReporte')
            ->has('vehiculosExternos')
            ->where('datosResumen.totales.total_viajes', 6)
            ->where('datosResumen.totales.total_fletes', 3)
            ->where('datosResumen.totales.total_vehiculos', 2)
        );

        $props = $response->viewData('page')['props'];
        $this->assertSame(5, $this->filaDe($props, $vehiculoA->id)['total_viajes']);
        $this->assertSame(2, $this->filaDe($props, $vehiculoA->id)['total_fletes']);
        $this->assertSame(1, $this->filaDe($props, $vehiculoB->id)['total_viajes']);
        // Sin monto_pago registrado, el monto total es 0 (no null).
        $this->assertEquals(0, $this->filaDe($props, $vehiculoA->id)['monto_total']);
    }

    public function test_index_suma_el_monto_pagado_una_sola_vez_por_flete(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();

        // Flete con 3 viajes y monto 900: el monto se cuenta una sola vez, no
        // multiplicado por los 3 viajes (fan-out del join a viaje).
        $this->crearViajes($vehiculo, 3, ['monto_pago' => 900]);
        $this->crearViajes($vehiculo, 1, ['monto_pago' => 100]);

        $response = $this->get(route('control-cargas.reporte.index', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertEquals(1000, $this->filaDe($props, $vehiculo->id)['monto_total']);
        $this->assertEquals(1000, $props['datosResumen']['totales']['monto_total']);
    }

    public function test_index_filtra_por_fecha_de_apertura_del_flete(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();

        $this->crearViajes($vehiculo, 2, ['fecha_apertura' => now()->subDays(3)]);
        $this->crearViajes($vehiculo, 4, ['fecha_apertura' => now()->subDays(40)]);

        $response = $this->get(route('control-cargas.reporte.index', [
            'fecha_desde' => now()->subDays(10)->format('Y-m-d'),
            'fecha_hasta' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('datosResumen.totales.total_viajes', 2)
            ->where('datosResumen.totales.total_fletes', 1)
        );
    }

    public function test_index_filtra_por_vehiculo_externo(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $otro = VehiculoExterno::factory()->create();

        $this->crearViajes($vehiculo, 3);
        $this->crearViajes($otro, 5);

        $response = $this->get(route('control-cargas.reporte.index', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            'id_vehiculo_externo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertCount(1, $props['datosResumen']['vehiculos']);
        $this->assertSame($vehiculo->id, $props['datosResumen']['vehiculos'][0]['id_vehiculo_externo']);
        $this->assertSame(3, $props['datosResumen']['totales']['total_viajes']);
    }

    public function test_index_filtra_por_ambito_al_exterior_o_nacional(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();

        $this->crearViajes($vehiculo, 2, ['es_al_exterior' => true]);
        $this->crearViajes($vehiculo, 5, ['es_al_exterior' => false]);

        $rango = [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
        ];

        $exterior = $this->get(route('control-cargas.reporte.index', $rango + ['ambito' => 'exterior']));
        $exterior->assertInertia(fn (Assert $page) => $page
            ->where('datosResumen.totales.total_viajes', 2)
            ->where('datosResumen.totales.viajes_nacional', 0)
        );

        $nacional = $this->get(route('control-cargas.reporte.index', $rango + ['ambito' => 'nacional']));
        $nacional->assertInertia(fn (Assert $page) => $page
            ->where('datosResumen.totales.total_viajes', 5)
            ->where('datosResumen.totales.viajes_exterior', 0)
        );
    }

    public function test_index_aplica_el_rango_del_mes_en_curso_por_defecto(): void
    {
        $response = $this->get(route('control-cargas.reporte.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filtros.fecha_desde', now()->startOfMonth()->format('Y-m-d'))
            ->where('filtros.fecha_hasta', now()->format('Y-m-d'))
            ->where('filtros.ambito', 'todos')
        );
    }

    public function test_genera_el_pdf_del_reporte(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $this->crearViajes($vehiculo, 3, ['es_al_exterior' => true]);

        $response = $this->get(route('control-cargas.reporte.pdf', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_el_pdf_exige_un_rango_de_fechas(): void
    {
        $response = $this->get(route('control-cargas.reporte.pdf'));

        $response->assertSessionHasErrors(['fecha_desde', 'fecha_hasta']);
    }

    /* ---------------------------------------------------------------------
     |  Detalle por vehículo externo
     | ------------------------------------------------------------------- */

    public function test_detalle_sin_vehiculo_seleccionado_no_consulta_nada(): void
    {
        $response = $this->get(route('control-cargas.reporte.detalle'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/ControlCargasReporteDetalle')
            ->has('vehiculosExternos')
            ->where('vehiculo', null)
            ->where('detalle', null)
        );
    }

    public function test_detalle_agrupa_los_viajes_por_material_del_vehiculo(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $cola = Material::factory()->create(['material' => 'Colá']);
        $broza = Material::factory()->create(['material' => 'Broza']);

        $this->crearViajes($vehiculo, 3, [], ['id_material' => $cola->id]);
        $this->crearViajes($vehiculo, 1, [], ['id_material' => $broza->id]);

        $response = $this->get(route('control-cargas.reporte.detalle', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            'id_vehiculo_externo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $materiales = collect($props['detalle']['materiales']);

        $this->assertSame(3, $materiales->firstWhere('material', 'Colá')['viajes']);
        $this->assertSame(1, $materiales->firstWhere('material', 'Broza')['viajes']);
        $this->assertSame(4, $props['detalle']['totales']['total_viajes']);
        $this->assertSame(2, $props['detalle']['totales']['total_materiales']);
        $this->assertSame(2, $props['detalle']['totales']['total_fletes']);
    }

    public function test_detalle_desglosa_los_materiales_de_cada_flete(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $cola = Material::factory()->create(['material' => 'Colá']);
        $concentrado = Material::factory()->create(['material' => 'Concentrado']);

        $carga = $this->crearViajes($vehiculo, 2, ['monto_pago' => 500], ['id_material' => $cola->id]);
        Viaje::factory()->create(['id_carga_material' => $carga->id, 'id_material' => $concentrado->id]);

        $response = $this->get(route('control-cargas.reporte.detalle', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            'id_vehiculo_externo' => $vehiculo->id,
        ]));

        $props = $response->viewData('page')['props'];
        $flete = collect($props['detalle']['fletes'])->firstWhere('id', $carga->id);

        $this->assertSame(3, $flete['viajes_count']);
        $this->assertEqualsCanonicalizing(
            [['material' => 'Colá', 'viajes' => 2], ['material' => 'Concentrado', 'viajes' => 1]],
            collect($flete['materiales'])->map(fn ($m) => (array) $m)->all(),
        );
        $this->assertEquals(500, $flete['monto_pago']);
        $this->assertEquals(500, $props['detalle']['totales']['monto_total']);
    }

    public function test_detalle_filtra_por_fecha_de_apertura_del_flete(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $material = Material::factory()->create();

        $this->crearViajes($vehiculo, 2, ['fecha_apertura' => now()->subDays(2)], ['id_material' => $material->id]);
        $this->crearViajes($vehiculo, 5, ['fecha_apertura' => now()->subDays(45)], ['id_material' => $material->id]);

        $response = $this->get(route('control-cargas.reporte.detalle', [
            'fecha_desde' => now()->subDays(10)->format('Y-m-d'),
            'fecha_hasta' => now()->format('Y-m-d'),
            'id_vehiculo_externo' => $vehiculo->id,
        ]));

        $props = $response->viewData('page')['props'];
        $this->assertSame(2, $props['detalle']['totales']['total_viajes']);
        $this->assertSame(1, $props['detalle']['totales']['total_fletes']);
    }

    public function test_genera_el_pdf_del_detalle(): void
    {
        $vehiculo = VehiculoExterno::factory()->create();
        $material = Material::factory()->create(['material' => 'Colá']);
        $this->crearViajes($vehiculo, 3, ['monto_pago' => 1200], ['id_material' => $material->id]);

        $response = $this->get(route('control-cargas.reporte.detalle.pdf', [
            'fecha_desde' => now()->subMonth()->format('Y-m-d'),
            'fecha_hasta' => now()->format('Y-m-d'),
            'id_vehiculo_externo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_el_pdf_del_detalle_exige_vehiculo_y_fechas(): void
    {
        $response = $this->get(route('control-cargas.reporte.detalle.pdf'));

        $response->assertSessionHasErrors(['fecha_desde', 'fecha_hasta', 'id_vehiculo_externo']);
    }
}
