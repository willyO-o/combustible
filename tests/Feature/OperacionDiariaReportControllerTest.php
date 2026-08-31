<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionDiariaReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Conductor $conductor;

    private Area $area;

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
        $this->area = Area::factory()->create();
        $this->tipoCombustible = TipoCombustible::factory()->create();

        // OperacionDiaria::getNroAttribute() lee digitos_serie de este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Siempre Viva',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['tiempo_expiracion' => 5],
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearOperacion(Vehiculo $vehiculo, int $diasAtras, array $overrides = []): OperacionDiaria
    {
        $inicio = now()->subDays($diasAtras)->setTime(6, 0);

        return OperacionDiaria::create(array_merge([
            'id_conductor' => $this->conductor->id,
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $this->area->id,
            'turno' => 'DIA',
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addHours(8),
            'estado' => 'FINALIZADO',
        ], $overrides));
    }

    private function rangoAmplio(array $extra = []): array
    {
        return array_merge([
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->addDay()->format('Y-m-d'),
        ], $extra);
    }

    public function test_agrupa_por_vehiculo_con_horas_trabajadas_y_kilometraje_recorrido(): void
    {
        $vehiculo = Vehiculo::factory()->create([
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $this->tipoCombustible->id,
        ]);

        $this->crearOperacion($vehiculo, 3, ['kilometraje_inicio' => 1000, 'kilometraje_fin' => 1150]);
        $this->crearOperacion($vehiculo, 2, ['kilometraje_inicio' => 1150, 'kilometraje_fin' => 1275]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio()));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/OperacionDiariaReporte')
            ->has('vehiculos')
            ->has('tiposCombustible')
            ->has('areas')
            ->has('datosResumen.vehiculos', 1)
        );

        $fila = collect($response->viewData('page')['props']['datosResumen']['vehiculos'])->first();
        $this->assertSame($vehiculo->id, $fila['id_vehiculo']);
        $this->assertSame(2, $fila['total_operaciones']);
        $this->assertSame(2, $fila['dias_operados']);
        $this->assertEquals(16, $fila['total_horas']);
        $this->assertEquals(275, $fila['total_recorrido']);
        $this->assertSame('km', $fila['unidad_recorrido']);
        $this->assertSame($this->tipoCombustible->tipo_combustible, $fila['tipo_combustible']);
    }

    public function test_calcula_el_recorrido_de_horometro_para_vehiculos_por_horometro(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);

        $this->crearOperacion($vehiculo, 4, ['horometro_inicio' => 100, 'horometro_fin' => 106.5]);
        $this->crearOperacion($vehiculo, 3, ['horometro_inicio' => 106.5, 'horometro_fin' => 110]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio()));

        $fila = collect($response->viewData('page')['props']['datosResumen']['vehiculos'])->first();
        $this->assertEquals(10, $fila['total_recorrido']);
        $this->assertSame('h', $fila['unidad_recorrido']);
    }

    public function test_los_totales_se_calculan_en_la_consulta(): void
    {
        $km = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $hr = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);

        $this->crearOperacion($km, 3, ['kilometraje_inicio' => 1000, 'kilometraje_fin' => 1200]);
        $this->crearOperacion($hr, 2, ['horometro_inicio' => 50, 'horometro_fin' => 58, 'turno' => 'NOCHE']);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio()));

        $totales = $response->viewData('page')['props']['datosResumen']['totales'];
        $this->assertSame(2, $totales['total_vehiculos']);
        $this->assertSame(2, $totales['total_operaciones']);
        $this->assertEquals(200, $totales['total_km']);
        $this->assertEquals(8, $totales['total_horometro']);
        $this->assertSame(1, $totales['operaciones_dia']);
        $this->assertSame(1, $totales['operaciones_noche']);
        $this->assertEquals(16, $totales['total_horas']);
    }

    public function test_filtra_por_tipo_de_combustible(): void
    {
        $diesel = TipoCombustible::factory()->create();
        $vehiculoDiesel = Vehiculo::factory()->create(['id_tipo_combustible' => $diesel->id, 'tipo_medicion' => 'kilometraje']);
        $vehiculoOtro = Vehiculo::factory()->create(['id_tipo_combustible' => $this->tipoCombustible->id, 'tipo_medicion' => 'kilometraje']);

        $this->crearOperacion($vehiculoDiesel, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($vehiculoOtro, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio([
            'id_tipo_combustible' => $diesel->id,
        ])));

        $vehiculos = collect($response->viewData('page')['props']['datosResumen']['vehiculos']);
        $vehiculosProp = collect($response->viewData('page')['props']['vehiculos']);

        $this->assertCount(1, $vehiculos);
        $this->assertSame($vehiculoDiesel->id, $vehiculos->first()['id_vehiculo']);
        $this->assertTrue($vehiculosProp->pluck('id')->contains($vehiculoDiesel->id));
        $this->assertFalse($vehiculosProp->pluck('id')->contains($vehiculoOtro->id));
    }

    public function test_filtra_por_area_solo_con_asignacion_vigente(): void
    {
        $vehiculoEnArea = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $vehiculoSinArea = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $this->crearOperacion($vehiculoEnArea, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($vehiculoSinArea, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);

        $areaFiltro = Area::factory()->create();
        VehiculoArea::create([
            'id_vehiculo' => $vehiculoEnArea->id,
            'id_area' => $areaFiltro->id,
            'fecha_asignacion' => now()->subMonth()->format('Y-m-d'),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio([
            'id_area' => $areaFiltro->id,
        ])));

        $vehiculos = collect($response->viewData('page')['props']['datosResumen']['vehiculos']);
        $this->assertCount(1, $vehiculos);
        $this->assertSame($vehiculoEnArea->id, $vehiculos->first()['id_vehiculo']);
    }

    public function test_filtra_por_un_vehiculo_puntual(): void
    {
        $v1 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $v2 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $this->crearOperacion($v1, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($v2, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio([
            'id_vehiculo' => $v1->id,
        ])));

        $vehiculos = collect($response->viewData('page')['props']['datosResumen']['vehiculos']);
        $this->assertCount(1, $vehiculos);
        $this->assertSame($v1->id, $vehiculos->first()['id_vehiculo']);
        // El filtro vuelve como array de ids (selección múltiple).
        $this->assertSame([$v1->id], $response->viewData('page')['props']['filtros']['id_vehiculo']);
    }

    public function test_filtra_por_varios_vehiculos_para_comparar(): void
    {
        $v1 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $v2 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $v3 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $this->crearOperacion($v1, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($v2, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($v3, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio([
            'id_vehiculo' => [$v1->id, $v2->id],
        ])));

        $response->assertOk();
        $vehiculos = collect($response->viewData('page')['props']['datosResumen']['vehiculos']);
        $this->assertCount(2, $vehiculos);
        $this->assertEqualsCanonicalizing([$v1->id, $v2->id], $vehiculos->pluck('id_vehiculo')->all());
        $this->assertEqualsCanonicalizing([$v1->id, $v2->id], $response->viewData('page')['props']['filtros']['id_vehiculo']);
    }

    public function test_sin_filtro_de_vehiculo_devuelve_todos_y_el_filtro_es_un_array_vacio(): void
    {
        $v1 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $v2 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->crearOperacion($v1, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);
        $this->crearOperacion($v2, 3, ['kilometraje_inicio' => 1, 'kilometraje_fin' => 10]);

        $response = $this->get(route('operacion-diaria.reporte.uso.index', $this->rangoAmplio()));

        $response->assertOk();
        $this->assertCount(2, collect($response->viewData('page')['props']['datosResumen']['vehiculos']));
        $this->assertSame([], $response->viewData('page')['props']['filtros']['id_vehiculo']);
    }

    public function test_genera_el_pdf_del_reporte(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->crearOperacion($vehiculo, 3, ['kilometraje_inicio' => 1000, 'kilometraje_fin' => 1200]);

        $response = $this->get(route('operacion-diaria.reporte.uso.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_genera_el_pdf_comparando_varios_vehiculos(): void
    {
        $v1 = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $v2 = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $this->crearOperacion($v1, 3, ['kilometraje_inicio' => 1000, 'kilometraje_fin' => 1200]);
        $this->crearOperacion($v2, 3, ['horometro_inicio' => 10, 'horometro_fin' => 18]);

        $response = $this->get(route('operacion-diaria.reporte.uso.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => [$v1->id, $v2->id],
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_el_pdf_exige_un_rango_de_fechas(): void
    {
        $response = $this->get(route('operacion-diaria.reporte.uso.pdf'));

        $response->assertSessionHasErrors(['fecha_inicio', 'fecha_fin']);
    }
}
