<?php

namespace Tests\Feature;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargasCombustibleReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Conductor $conductor;

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);

        $persona = Persona::factory()->create();
        $this->conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Central',
            'nit' => '123456',
            'direccion' => 'Av. Siempre Viva',
            'ciudad' => 'La Paz',
            'telefono' => '70000000',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);

        $this->tipoCombustible = TipoCombustible::factory()->create();
    }

    private function crearCarga(Vehiculo $vehiculo, array $overrides = []): CargaCombustible
    {
        return CargaCombustible::create(array_merge([
            'fecha_carga' => now(),
            'litros' => 20,
            'precio' => 5,
            'kilometraje' => 1000,
            'id_vehiculo' => $vehiculo->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $this->conductor->id,
        ], $overrides));
    }

    /**
     * Dos cargas consecutivas con lectura de kilometraje generan un recorrido
     * y rendimiento calculables (la primera carga sólo fija el punto de partida).
     */
    private function crearVehiculoConDosCargas(array $vehiculoOverrides = []): Vehiculo
    {
        $vehiculo = Vehiculo::factory()->create(array_merge(['tipo_medicion' => 'kilometraje'], $vehiculoOverrides));

        $this->crearCarga($vehiculo, ['fecha_carga' => now()->subDays(2), 'kilometraje' => 1000, 'litros' => 20]);
        $this->crearCarga($vehiculo, ['fecha_carga' => now()->subDay(), 'kilometraje' => 1200, 'litros' => 20]);

        return $vehiculo;
    }

    public function test_muestra_el_rendimiento_de_todos_los_vehiculos_por_defecto(): void
    {
        $vehiculo1 = $this->crearVehiculoConDosCargas();
        $vehiculo2 = $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/CargasCombustibleRendimientoReporte')
            ->has('vehiculos')
            ->has('resultado', 2)
            ->where('filtros.id_vehiculo', [])
        );

        $ids = collect($response->viewData('page')['props']['resultado'])->pluck('id_vehiculo');
        $this->assertTrue($ids->contains($vehiculo1->id));
        $this->assertTrue($ids->contains($vehiculo2->id));
    }

    public function test_filtra_por_uno_o_mas_vehiculos_seleccionados(): void
    {
        $vehiculo1 = $this->crearVehiculoConDosCargas();
        $vehiculo2 = $this->crearVehiculoConDosCargas();
        $vehiculo3 = $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento', [
            'id_vehiculo' => [$vehiculo1->id, $vehiculo2->id],
        ]));

        $response->assertOk();
        $resultado = collect($response->viewData('page')['props']['resultado']);

        $this->assertCount(2, $resultado);
        $this->assertTrue($resultado->pluck('id_vehiculo')->contains($vehiculo1->id));
        $this->assertTrue($resultado->pluck('id_vehiculo')->contains($vehiculo2->id));
        $this->assertFalse($resultado->pluck('id_vehiculo')->contains($vehiculo3->id));
    }

    public function test_calcula_el_rendimiento_en_km_por_litro_para_vehiculos_por_kilometraje(): void
    {
        // 1200 - 1000 = 200 km recorridos; se cargaron 20 L en la segunda carga (única con "anterior").
        $vehiculo = $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento', ['id_vehiculo' => [$vehiculo->id]]));

        $resultado = collect($response->viewData('page')['props']['resultado'])->first();

        $this->assertSame('kilometraje', $resultado->tipo_medicion);
        $this->assertSame('km/L', $resultado->unidad_medida);
        $this->assertSame(1, $resultado->total_cargas); // sólo la 2ª carga tiene "medición anterior"
        $this->assertEquals(200, $resultado->total_recorrido);
        $this->assertEquals(10, $resultado->rendimiento_promedio); // 200 km / 20 L
    }

    public function test_un_vehiculo_con_una_sola_carga_no_tiene_rendimiento_calculable(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->crearCarga($vehiculo, ['kilometraje' => 1000]);

        $response = $this->get(route('cargas-combustible.reporte.rendimiento', ['id_vehiculo' => [$vehiculo->id]]));

        $response->assertOk();
        // Sin una segunda lectura no hay "medición anterior": el vehículo no aparece en el resumen.
        $this->assertCount(0, $response->viewData('page')['props']['resultado']);
    }

    public function test_detalle_sin_vehiculo_seleccionado_no_consulta_cargas(): void
    {
        $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento.detalle'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/CargasCombustibleRendimientoDetalle')
            ->has('vehiculos')
            ->has('detalle', 0)
            ->where('filtros.id_vehiculo', null)
        );
    }

    public function test_detalle_muestra_las_cargas_de_un_solo_vehiculo_con_su_rendimiento(): void
    {
        $vehiculo = $this->crearVehiculoConDosCargas();
        // Un segundo vehículo con datos: no debe filtrarse en el detalle del primero.
        $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento.detalle', ['id_vehiculo' => $vehiculo->id]));

        $response->assertOk();
        $detalle = collect($response->viewData('page')['props']['detalle']);

        $this->assertCount(1, $detalle); // sólo la carga con "medición anterior"
        $this->assertTrue($detalle->every(fn ($d) => $d->id_vehiculo === $vehiculo->id));

        $fila = $detalle->first();
        $this->assertEquals(1000, $fila->medicion_anterior);
        $this->assertEquals(1200, $fila->medicion_actual);
        $this->assertEquals(200, $fila->recorrido);
        $this->assertEquals(10, $fila->rendimiento); // 200 km / 20 L
    }

    public function test_genera_el_pdf_del_reporte_general_de_rendimiento(): void
    {
        $this->crearVehiculoConDosCargas();
        $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_del_reporte_general_exige_fechas(): void
    {
        $response = $this->get(route('cargas-combustible.reporte.rendimiento.pdf'));

        $response->assertSessionHasErrors(['fecha_inicio', 'fecha_fin']);
    }

    public function test_genera_el_pdf_del_detalle_de_un_vehiculo(): void
    {
        $vehiculo = $this->crearVehiculoConDosCargas();

        $response = $this->get(route('cargas-combustible.reporte.rendimiento.detalle.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_del_detalle_exige_un_vehiculo(): void
    {
        $response = $this->get(route('cargas-combustible.reporte.rendimiento.detalle.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
        ]));

        $response->assertSessionHasErrors('id_vehiculo');
    }
}
