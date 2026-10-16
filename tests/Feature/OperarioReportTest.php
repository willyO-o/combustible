<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Area;
use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

class OperarioReportTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    private User $admin;

    private Area $area;

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        $this->area = Area::factory()->create();
        $this->tipoCombustible = TipoCombustible::factory()->create();
        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Central', 'nit' => '123', 'direccion' => 'Av', 'ciudad' => 'LP',
            'telefono' => '700', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        // CargaCombustible::calcularGestion() lee el ciclo contable de aquí.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals', 'direccion_empresa' => 'x', 'telefono_empresa' => '1',
            'correo_empresa' => 'a@b.com', 'nit_empresa' => '1', 'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearConductor(string $estado = 'ACTIVO'): Conductor
    {
        return Conductor::create(['id' => Persona::factory()->create()->id, 'estado_conductor' => $estado]);
    }

    private function crearOperacion(Vehiculo $v, Conductor $c, Carbon $dia, array $overrides = []): OperacionDiaria
    {
        $inicio = $dia->copy()->setTime(8, 0);

        return OperacionDiaria::create(array_merge([
            'id_conductor' => $c->id,
            'id_vehiculo' => $v->id,
            'id_area' => $this->area->id,
            'turno' => 'DIA',
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addHours(10),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1080,
            'estado' => 'FINALIZADO',
        ], $overrides));
    }

    private function crearCarga(Vehiculo $v, Conductor $c, Carbon $dia, array $overrides = []): CargaCombustible
    {
        return CargaCombustible::create(array_merge([
            'fecha_carga' => $dia->copy()->setTime(7, 30),
            'litros' => 100,
            'precio' => 3.72,
            'kilometraje' => 990,
            'id_vehiculo' => $v->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $c->id,
            'tipo_carga' => 'PREPAGO',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDelReporte(Conductor $conductor): array
    {
        $response = $this->actingAs($this->admin)->get(route('operacion-diaria.reporte.operario.index', [
            'id_conductor' => $conductor->id,
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();

        return $response->viewData('page')['props']['datos'];
    }

    public function test_sin_operario_seleccionado_no_consulta_nada_y_lista_todos_los_operarios(): void
    {
        $activo = $this->crearConductor();
        $inactivo = $this->crearConductor('INACTIVO');

        $this->actingAs($this->admin)->get(route('operacion-diaria.reporte.operario.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reportes/OperarioReporte')
                ->where('operario', null)
                ->where('datos', null)
                ->where('filtros.fecha_inicio', now()->startOfMonth()->format('Y-m-d'))
                ->where('operarios', fn ($operarios) => collect($operarios)->pluck('id')->sort()->values()->all()
                    === collect([$activo->id, $inactivo->id])->sort()->values()->all()
                    && str_ends_with(collect($operarios)->firstWhere('id', $inactivo->id)['label'], '— inactivo'))
            );
    }

    public function test_muestra_las_operaciones_del_operario_con_sus_actividades_y_el_resumen(): void
    {
        $operario = $this->crearConductor();
        $otro = $this->crearConductor();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $material = Material::factory()->create(['material' => 'Concentrado']);
        $acarreo = Actividad::create(['nombre_actividad' => 'Acarreo', 'id_area' => $this->area->id]);
        $traslado = Actividad::create(['nombre_actividad' => 'Traslado de personal', 'id_area' => $this->area->id]);

        $op1 = $this->crearOperacion($vehiculo, $operario, now()->subDays(3));
        $op1->actividadesRealizadas()->attach($acarreo->id, ['origen' => 'Mina', 'destino' => 'Planta', 'id_material' => $material->id, 'cantidad' => 4, 'unidad_medida' => 'viajes']);
        $op1->actividadesRealizadas()->attach($traslado->id, ['origen' => 'Base', 'destino' => 'Mina']);

        $op2 = $this->crearOperacion($vehiculo, $operario, now()->subDays(2));
        $op2->actividadesRealizadas()->attach($acarreo->id, ['origen' => 'Mina', 'destino' => 'Planta', 'cantidad' => 6, 'unidad_medida' => 'viajes']);

        // Fuera del rango y de otro operario: no deben aparecer.
        $this->crearOperacion($vehiculo, $operario, now()->subMonths(3));
        $this->crearOperacion($vehiculo, $otro, now()->subDays(2));

        $datos = $this->datosDelReporte($operario);

        $this->assertSame([$op1->id, $op2->id], collect($datos['operaciones'])->pluck('id')->all());
        $primera = collect($datos['operaciones'])->firstWhere('id', $op1->id);
        $this->assertCount(2, $primera['actividades']);
        $this->assertSame('Concentrado', $primera['actividades'][0]['material']);
        $this->assertNull($primera['actividades'][1]['cantidad'], 'Cantidad opcional: llega null.');
        $this->assertEquals(1000, $primera['lectura_inicio']);

        $resumenAcarreo = collect($datos['actividades'])->firstWhere('actividad', 'Acarreo');
        $this->assertSame(2, $resumenAcarreo['veces']);
        $this->assertSame(2, $resumenAcarreo['jornadas']);
        $this->assertEquals(10, $resumenAcarreo['cantidad_total']);
        $this->assertSame('viajes', $resumenAcarreo['unidad_medida']);

        $this->assertSame(2, $datos['totales']['operaciones']);
        $this->assertEquals(20, $datos['totales']['horas_trabajadas']);
        $this->assertSame(2, $datos['totales']['dias_trabajados']);
        $this->assertSame(1, $datos['totales']['vehiculos']);
        $this->assertSame(3, $datos['totales']['actividades']);
    }

    public function test_lista_las_cargas_del_operario_y_excluye_las_anuladas_de_los_totales(): void
    {
        $operario = $this->crearConductor();
        $otro = $this->crearConductor();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $this->crearCarga($vehiculo, $operario, now()->subDays(3), ['litros' => 100, 'precio' => 4]);
        $this->crearCarga($vehiculo, $operario, now()->subDays(2), ['litros' => 50, 'precio' => 4]);
        $this->crearCarga($vehiculo, $operario, now()->subDays(1), ['litros' => 999, 'precio' => 4, 'estado_carga' => 'ANULADO']);
        $this->crearCarga($vehiculo, $otro, now()->subDays(1), ['litros' => 70, 'precio' => 4]);

        $datos = $this->datosDelReporte($operario);

        $this->assertCount(3, $datos['cargas'], 'Las anuladas se listan (marcadas).');
        $this->assertSame(2, $datos['totales']['cargas']);
        $this->assertEquals(150, $datos['totales']['litros']);
        $this->assertEquals(600, $datos['totales']['costo']);
        $this->assertEquals(400, collect($datos['cargas'])->first()['costo']);
        $this->assertEquals(990, collect($datos['cargas'])->first()['lectura']);
    }

    public function test_un_jefe_de_area_puede_ver_el_reporte_y_un_conductor_no(): void
    {
        $operario = $this->crearConductor();

        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');
        $this->actingAs($jefe)->get(route('operacion-diaria.reporte.operario.index', ['id_conductor' => $operario->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('operario.id', $operario->id));

        $usuarioConductor = User::factory()->create();
        $usuarioConductor->assignRole('conductor');
        $this->actingAs($usuarioConductor)->get(route('operacion-diaria.reporte.operario.index'))->assertForbidden();
    }

    public function test_un_operario_inexistente_muestra_el_estado_vacio(): void
    {
        $this->actingAs($this->admin)->get(route('operacion-diaria.reporte.operario.index', ['id_conductor' => 999999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('operario', null)->where('datos', null));
    }
}
