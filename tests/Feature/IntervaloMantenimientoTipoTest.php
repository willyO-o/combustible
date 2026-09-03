<?php

namespace Tests\Feature;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\DetalleMantenimiento;
use App\Models\Grifo;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervaloMantenimientoTipoTest extends TestCase
{
    use RefreshDatabase;

    private Grifo $grifo;

    private TipoCombustible $tipoCombustible;

    private Conductor $conductor;

    private TipoMantenimiento $tipoMantenimiento;

    protected function setUp(): void
    {
        parent::setUp();

        // OrdenTrabajo::boot() usa auth()->id() y ParametrosEmpresa.
        $this->actingAs(User::factory()->create());

        ParametrosEmpresa::create([
            'nombre_empresa' => 'Empresa de Prueba',
            'direccion_empresa' => 'Av. Test',
            'telefono_empresa' => '70000000',
            'correo_empresa' => 'empresa@example.com',
            'nit_empresa' => '123456',
            'parametros_vale' => ['mes_ciclo_contable' => 1, 'digitos_serie' => 6],
            'estado' => 'ACTIVO',
        ]);

        $this->grifo = Grifo::create([
            'razon_social' => 'Grifo Test',
            'nit' => '123456',
            'direccion' => 'Av. Test',
            'ciudad' => 'La Paz',
            'telefono' => '70000000',
            'estado_grifo' => 'ACTIVO',
            'es_principal' => true,
        ]);
        $this->tipoCombustible = TipoCombustible::factory()->create();

        $persona = Persona::factory()->create();
        $this->conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        $this->tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
    }

    /**
     * Crea un vehículo (con su propio tipo de vehículo), un intervalo de la
     * frecuencia dada, opcionalmente una carga de combustible con la lectura
     * actual, y opcionalmente un mantenimiento registrado a cierta lectura.
     * Devuelve la alerta calculada para ese vehículo.
     *
     * @return array<string, mixed>
     */
    private function alertaPara(int $frecuencia, ?float $lecturaActual, ?float $ultimoMantenimiento): array
    {
        $tipoVehiculo = TipoVehiculo::factory()->create();
        $vehiculo = Vehiculo::factory()->create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'tipo_medicion' => 'kilometraje',
        ]);

        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $this->tipoMantenimiento->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => $frecuencia,
            'estado' => 'ACTIVO',
        ]);

        if ($lecturaActual !== null) {
            CargaCombustible::create([
                'fecha_carga' => now(),
                'litros' => 50,
                'precio' => 7,
                'kilometraje' => $lecturaActual,
                'id_vehiculo' => $vehiculo->id,
                'id_grifo' => $this->grifo->id,
                'id_tipo_combustible' => $this->tipoCombustible->id,
                'id_conductor' => $this->conductor->id,
                'tipo_carga' => 'PREPAGO',
                'estado_carga' => 'REGISTRADO',
            ]);
        }

        if ($ultimoMantenimiento !== null) {
            $orden = OrdenTrabajo::create([
                'id_vehiculo' => $vehiculo->id,
                'id_usuario_ejecuta' => auth()->id(),
                'tipo_mantenimiento' => 'PREVENTIVO',
            ]);

            DetalleMantenimiento::create([
                'id_orden_trabajo' => $orden->id,
                'id_tipo_mantenimiento' => $this->tipoMantenimiento->id,
                'fecha' => now(),
                'kilometraje' => $ultimoMantenimiento,
                'cantidad' => 1,
            ]);
        }

        $alertas = IntervaloMantenimientoTipo::alertasMantenimiento([$vehiculo->id]);

        $this->assertCount(1, $alertas);

        return $alertas->first();
    }

    public function test_sin_cargas_de_combustible_el_estado_es_sin_datos(): void
    {
        $alerta = $this->alertaPara(10000, lecturaActual: null, ultimoMantenimiento: null);

        $this->assertSame('SIN_DATOS', $alerta['estado']);
        $this->assertNull($alerta['lectura_actual']);
        $this->assertNull($alerta['restante']);
    }

    public function test_lejos_del_objetivo_el_estado_es_al_dia(): void
    {
        $alerta = $this->alertaPara(10000, lecturaActual: 3000, ultimoMantenimiento: null);

        $this->assertSame('AL_DIA', $alerta['estado']);
        $this->assertSame(10000.0, $alerta['proximo_objetivo']);
        $this->assertSame(7000.0, $alerta['restante']);
    }

    public function test_dentro_de_la_holgura_del_5_por_ciento_el_estado_es_proximo(): void
    {
        $alerta = $this->alertaPara(10000, lecturaActual: 9800, ultimoMantenimiento: null);

        $this->assertSame('PROXIMO', $alerta['estado']);
        $this->assertSame(500.0, $alerta['holgura']);
        $this->assertSame(200.0, $alerta['restante']);
    }

    public function test_pasado_el_objetivo_mas_la_holgura_el_estado_es_vencido(): void
    {
        $alerta = $this->alertaPara(10000, lecturaActual: 12000, ultimoMantenimiento: null);

        $this->assertSame('VENCIDO', $alerta['estado']);
        $this->assertSame(-2000.0, $alerta['restante']);
    }

    public function test_el_proximo_objetivo_avanza_al_siguiente_multiplo_tras_un_mantenimiento(): void
    {
        // Mantenimiento hecho a 10200 km (cubre el ciclo de 10000 con holgura),
        // lectura actual 15000 -> el próximo sugerido es 20000.
        $alerta = $this->alertaPara(10000, lecturaActual: 15000, ultimoMantenimiento: 10200);

        $this->assertSame(10200.0, $alerta['ultimo_mantenimiento']);
        $this->assertSame(20000.0, $alerta['proximo_objetivo']);
        $this->assertSame(5000.0, $alerta['restante']);
        $this->assertSame('AL_DIA', $alerta['estado']);
    }

    public function test_resumen_para_dashboard_agrupa_por_vehiculo_solo_vencidos_y_proximos(): void
    {
        $this->alertaPara(10000, lecturaActual: 3000, ultimoMantenimiento: null);   // AL_DIA -> excluido
        $vencido = $this->alertaPara(10000, lecturaActual: 12000, ultimoMantenimiento: null); // VENCIDO

        $resumen = IntervaloMantenimientoTipo::resumenAlertasVehiculos();

        $this->assertCount(1, $resumen);
        $this->assertSame($vencido['id_vehiculo'], $resumen[0]['id_vehiculo']);
        $this->assertSame(1, $resumen[0]['vencidos']);
        $this->assertSame(0, $resumen[0]['proximos']);
        $this->assertSame('VENCIDO', $resumen[0]['estado']);
    }

    public function test_el_resumen_respeta_el_alcance_de_vehiculos(): void
    {
        $dentro = $this->alertaPara(10000, lecturaActual: 12000, ultimoMantenimiento: null);
        $this->alertaPara(10000, lecturaActual: 12000, ultimoMantenimiento: null); // fuera del alcance

        $resumen = IntervaloMantenimientoTipo::resumenAlertasVehiculos([$dentro['id_vehiculo']]);

        $this->assertCount(1, $resumen);
        $this->assertSame($dentro['id_vehiculo'], $resumen[0]['id_vehiculo']);
    }
}
