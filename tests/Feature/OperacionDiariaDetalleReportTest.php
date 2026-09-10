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
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionDiariaDetalleReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Area $area;

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);

        $this->area = Area::factory()->create();
        $this->tipoCombustible = TipoCombustible::factory()->create();
        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Central', 'nit' => '123', 'direccion' => 'Av', 'ciudad' => 'LP',
            'telefono' => '700', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals', 'direccion_empresa' => 'x', 'telefono_empresa' => '1',
            'correo_empresa' => 'a@b.com', 'nit_empresa' => '1', 'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearConductor(): Conductor
    {
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
    }

    private function crearOperacion(Vehiculo $v, Conductor $c, string $dia, array $overrides = []): OperacionDiaria
    {
        $inicio = Carbon::parse($dia)->setTime(6, 0);

        return OperacionDiaria::create(array_merge([
            'id_conductor' => $c->id,
            'id_vehiculo' => $v->id,
            'id_area' => $this->area->id,
            'turno' => 'DIA',
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addHours(8),
            'estado' => 'FINALIZADO',
        ], $overrides));
    }

    private function crearCarga(Vehiculo $v, Conductor $c, string $dia, array $overrides = []): CargaCombustible
    {
        return CargaCombustible::create(array_merge([
            'fecha_carga' => Carbon::parse($dia)->setTime(7, 30),
            'litros' => 100,
            'precio' => 3.72,
            'id_vehiculo' => $v->id,
            'id_grifo' => $this->grifo->id,
            'id_tipo_combustible' => $this->tipoCombustible->id,
            'id_conductor' => $c->id,
        ], $overrides));
    }

    private function tipoMantenimiento(string $nombre, string $tipoValor = 'cantidad', ?string $unidad = 'L'): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => $nombre,
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => $tipoValor,
            'unidad_medida' => $tipoValor === 'cantidad' ? $unidad : null,
        ]);
    }

    private function rango(array $extra = []): array
    {
        return array_merge([
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->addDay()->format('Y-m-d'),
        ], $extra);
    }

    public function test_sin_vehiculo_seleccionado_no_consulta_nada(): void
    {
        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango()));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reportes/OperacionDiariaDetalle')
            ->has('vehiculos')
            ->where('vehiculo', null)
            ->where('datos', null)
        );
    }

    public function test_lista_una_fila_por_operacion_con_su_conductor(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c1 = $this->crearConductor();
        $c2 = $this->crearConductor();

        $this->crearOperacion($vehiculo, $c1, now()->subDays(5)->format('Y-m-d'), ['horometro_inicio' => 100, 'horometro_fin' => 110]);
        $this->crearOperacion($vehiculo, $c2, now()->subDays(4)->format('Y-m-d'), ['horometro_inicio' => 110, 'horometro_fin' => 118]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $vehiculo->id])));

        $response->assertOk();
        $filas = collect($response->viewData('page')['props']['datos']['filas']);

        $this->assertCount(2, $filas);
        $this->assertSame($c1->persona->nombre_completo, $filas[0]['operador']);
        $this->assertSame($c2->persona->nombre_completo, $filas[1]['operador']);
        $this->assertEquals(100, $filas[0]['lectura_inicio']);
        $this->assertEquals(110, $filas[0]['lectura_fin']);
    }

    public function test_enlaza_la_carga_de_combustible_del_mismo_dia(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c = $this->crearConductor();

        $conCarga = now()->subDays(5)->format('Y-m-d');
        $sinCarga = now()->subDays(4)->format('Y-m-d');

        $this->crearOperacion($vehiculo, $c, $conCarga);
        $this->crearOperacion($vehiculo, $c, $sinCarga);
        $this->crearCarga($vehiculo, $c, $conCarga, ['litros' => 50, 'precio' => 4, 'horometro' => 1234.5]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $vehiculo->id])));

        $filas = collect($response->viewData('page')['props']['datos']['filas']);

        $this->assertNotNull($filas[0]['carga']);
        $this->assertEquals(50, $filas[0]['carga']['litros']);
        $this->assertEquals(4, $filas[0]['carga']['precio_unitario']);
        $this->assertEquals(200, $filas[0]['carga']['costo']); // 50 * 4
        $this->assertEquals(1234.5, $filas[0]['carga']['lectura_carga']);
        $this->assertNull($filas[1]['carga']);
    }

    public function test_columnas_de_mantenimiento_dinamicas_con_sus_valores_y_totales(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c = $this->crearConductor();
        $aceite = $this->tipoMantenimiento('Aceite de motor', 'cantidad', 'L');
        $revision = $this->tipoMantenimiento('Revisión de frenos', 'booleano');
        $this->tipoMantenimiento('Nunca usado', 'cantidad', 'L'); // no se le registra nada -> no debe salir como columna

        $op1 = $this->crearOperacion($vehiculo, $c, now()->subDays(5)->format('Y-m-d'));
        $op2 = $this->crearOperacion($vehiculo, $c, now()->subDays(4)->format('Y-m-d'));

        $op1->mantenimientosOperacion()->attach($aceite->id, ['valor' => 2.5]);
        $op1->mantenimientosOperacion()->attach($revision->id, ['realizado' => 'SI']);
        $op2->mantenimientosOperacion()->attach($aceite->id, ['valor' => 1.5]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $vehiculo->id])));

        $datos = $response->viewData('page')['props']['datos'];
        $cols = collect($datos['columnas']['mantenimiento'])->keyBy('nombre');

        // Sólo los tipos con registros aparecen como columnas.
        $this->assertCount(2, $cols);
        $this->assertFalse($cols->has('Nunca usado'));
        $this->assertEquals(4, $cols['Aceite de motor']['total']); // 2.5 + 1.5
        $this->assertSame(1, $cols['Revisión de frenos']['total']); // 1 "SI"

        $filas = collect($datos['filas']);
        $this->assertEquals(2.5, $filas[0]['mantenimientos'][$aceite->id]['valor']);
        $this->assertSame('SI', $filas[0]['mantenimientos'][$revision->id]['realizado']);
    }

    public function test_columnas_de_material_solo_para_vehiculos_por_kilometraje(): void
    {
        $material = Material::create(['material' => 'Tierra']);
        $actividad = Actividad::create(['nombre_actividad' => 'Traslado', 'id_area' => $this->area->id, 'unidad_medida' => 'viajes']);

        // Vehículo por kilometraje: sí lleva columnas de material.
        $km = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $c = $this->crearConductor();
        $op = $this->crearOperacion($km, $c, now()->subDays(5)->format('Y-m-d'), ['kilometraje_inicio' => 10, 'kilometraje_fin' => 40]);
        $op->actividadesRealizadas()->attach($actividad->id, ['id_material' => $material->id, 'cantidad' => 6, 'unidad_medida' => 'viajes']);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $km->id])));
        $datos = $response->viewData('page')['props']['datos'];

        $this->assertCount(1, $datos['columnas']['material']);
        $this->assertSame('Tierra', $datos['columnas']['material'][0]['nombre']);
        $this->assertEquals(6, $datos['columnas']['material'][0]['total']);
        $this->assertEquals(6, collect($datos['filas'])[0]['materiales'][$material->id]);

        // Vehículo por horómetro: sin columnas de material aunque hubiera actividades.
        $hr = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $this->crearOperacion($hr, $c, now()->subDays(5)->format('Y-m-d'));

        $responseHr = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $hr->id])));
        $this->assertCount(0, $responseHr->viewData('page')['props']['datos']['columnas']['material']);
    }

    public function test_columnas_de_material_traen_movimientos_y_unidad_para_el_grafico(): void
    {
        $tierra = Material::create(['material' => 'Tierra']);
        $ripio = Material::create(['material' => 'Ripio']);
        $traslado = Actividad::create(['nombre_actividad' => 'Traslado', 'id_area' => $this->area->id]);

        $km = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $c = $this->crearConductor();
        $op1 = $this->crearOperacion($km, $c, now()->subDays(5)->format('Y-m-d'), ['kilometraje_inicio' => 10, 'kilometraje_fin' => 40]);
        $op2 = $this->crearOperacion($km, $c, now()->subDays(4)->format('Y-m-d'), ['kilometraje_inicio' => 40, 'kilometraje_fin' => 70]);

        // Tierra: 2 registros de traslado (uno por operación) => 6 + 4 = 10 viajes.
        $op1->actividadesRealizadas()->attach($traslado->id, ['id_material' => $tierra->id, 'cantidad' => 6, 'unidad_medida' => 'viajes']);
        $op2->actividadesRealizadas()->attach($traslado->id, ['id_material' => $tierra->id, 'cantidad' => 4, 'unidad_medida' => 'viajes']);
        // Ripio: 1 registro => 3 viajes.
        $op1->actividadesRealizadas()->attach($traslado->id, ['id_material' => $ripio->id, 'cantidad' => 3, 'unidad_medida' => 'viajes']);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $km->id])));

        $material = collect($response->viewData('page')['props']['datos']['columnas']['material'])->keyBy('nombre');

        $this->assertEqualsCanonicalizing(['Tierra', 'Ripio'], $material->keys()->all());
        $this->assertEquals(10, $material['Tierra']['total']);
        $this->assertSame(2, $material['Tierra']['movimientos']);
        $this->assertSame('viajes', $material['Tierra']['unidad_medida']);
        $this->assertEquals(3, $material['Ripio']['total']);
        $this->assertSame(1, $material['Ripio']['movimientos']);
    }

    public function test_totales_en_sql_no_duplican_el_combustible_por_doble_turno(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c = $this->crearConductor();
        $dia = now()->subDays(5)->format('Y-m-d');

        // Dos operaciones el mismo día (turno día y noche) + una sola carga.
        $this->crearOperacion($vehiculo, $c, $dia, ['turno' => 'DIA']);
        $this->crearOperacion($vehiculo, $c, $dia, ['turno' => 'NOCHE']);
        $this->crearCarga($vehiculo, $c, $dia, ['litros' => 80, 'precio' => 5]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.index', $this->rango(['id_vehiculo' => $vehiculo->id])));
        $datos = $response->viewData('page')['props']['datos'];

        $this->assertEquals(80, $datos['totales']['litros']);      // no 160
        $this->assertEquals(400, $datos['totales']['costo']);      // 80 * 5, no 800
        $this->assertEquals(16, $datos['totales']['horas_trabajadas']); // 8 + 8
        $this->assertEquals(5, $datos['totales']['litros_por_hora']); // 80 / 16

        // La carga se muestra sólo en la primera fila del día.
        $filas = collect($datos['filas']);
        $this->assertNotNull($filas[0]['carga']);
        $this->assertNull($filas[1]['carga']);
    }

    public function test_genera_el_pdf_de_la_bitacora(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $c = $this->crearConductor();
        $aceite = $this->tipoMantenimiento('Aceite de motor', 'cantidad', 'L');
        $material = Material::create(['material' => 'Ripio']);
        $actividad = Actividad::create(['nombre_actividad' => 'Traslado', 'id_area' => $this->area->id]);

        $dia = now()->subDays(5)->format('Y-m-d');
        $op = $this->crearOperacion($vehiculo, $c, $dia, ['kilometraje_inicio' => 100, 'kilometraje_fin' => 250]);
        $op->mantenimientosOperacion()->attach($aceite->id, ['valor' => 3]);
        $op->actividadesRealizadas()->attach($actividad->id, ['id_material' => $material->id, 'cantidad' => 4, 'unidad_medida' => 'viajes']);
        $this->crearCarga($vehiculo, $c, $dia);

        $response = $this->get(route('operacion-diaria.reporte.detalle.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_genera_el_pdf_sin_columnas_dinamicas(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c = $this->crearConductor();
        $this->crearOperacion($vehiculo, $c, now()->subDays(3)->format('Y-m-d'), ['horometro_inicio' => 10, 'horometro_fin' => 18]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.pdf', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_el_pdf_exige_vehiculo_y_fechas(): void
    {
        $this->get(route('operacion-diaria.reporte.detalle.pdf'))
            ->assertSessionHasErrors(['fecha_inicio', 'fecha_fin', 'id_vehiculo']);
    }

    /* ---------------------------------------------------------------------
     |  Excel (.xlsx) — mismos datos y columnas dinámicas que el PDF
     | ------------------------------------------------------------------- */

    public function test_genera_el_excel_de_la_bitacora(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $c = $this->crearConductor();
        $aceite = $this->tipoMantenimiento('Aceite de motor', 'cantidad', 'L');
        $material = Material::create(['material' => 'Ripio']);
        $actividad = Actividad::create(['nombre_actividad' => 'Traslado', 'id_area' => $this->area->id]);

        $dia = now()->subDays(5)->format('Y-m-d');
        $op = $this->crearOperacion($vehiculo, $c, $dia, ['kilometraje_inicio' => 100, 'kilometraje_fin' => 250]);
        $op->mantenimientosOperacion()->attach($aceite->id, ['valor' => 3]);
        $op->actividadesRealizadas()->attach($actividad->id, ['id_material' => $material->id, 'cantidad' => 4, 'unidad_medida' => 'viajes']);
        $this->crearCarga($vehiculo, $c, $dia);

        $response = $this->get(route('operacion-diaria.reporte.detalle.excel', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_genera_el_excel_sin_columnas_dinamicas(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $c = $this->crearConductor();
        $this->crearOperacion($vehiculo, $c, now()->subDays(3)->format('Y-m-d'), ['horometro_inicio' => 10, 'horometro_fin' => 18]);

        $response = $this->get(route('operacion-diaria.reporte.detalle.excel', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    /**
     * Sin operaciones en el rango la tabla va vacía: el Excel debe generarse
     * igual (fila de "sin datos" y sin fila de totales).
     */
    public function test_genera_el_excel_de_la_bitacora_sin_operaciones(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->get(route('operacion-diaria.reporte.detalle.excel', [
            'fecha_inicio' => now()->subMonth()->format('Y-m-d'),
            'fecha_fin' => now()->format('Y-m-d'),
            'id_vehiculo' => $vehiculo->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_el_excel_exige_vehiculo_y_fechas(): void
    {
        $this->get(route('operacion-diaria.reporte.detalle.excel'))
            ->assertSessionHasErrors(['fecha_inicio', 'fecha_fin', 'id_vehiculo']);
    }
}
