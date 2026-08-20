<?php

namespace Tests\Feature;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\TipoCombustible;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ValePublicoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Vale::boot() calcula fecha_vencimiento a partir de este parámetro.
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

    private function crearVale(array $overrides = []): Vale
    {
        $grifo = Grifo::create([
            'razon_social' => 'Grifo de Prueba', 'nit' => '123', 'direccion' => 'Calle 1',
            'ciudad' => 'Oruro', 'telefono' => '123', 'estado_grifo' => 'ACTIVO', 'es_principal' => true,
        ]);

        return Vale::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_grifo' => $grifo->id,
            'id_tipo_combustible' => TipoCombustible::factory()->create()->id,
            'litros' => 20,
            'precio' => 6.97,
            'estado_vale' => 'PENDIENTE',
        ], $overrides));
    }

    public function test_es_accesible_sin_autenticacion(): void
    {
        $vale = $this->crearVale();

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vales/Publico')
            ->where('vale.nro', $vale->nro)
        );
    }

    public function test_un_vale_pendiente_y_vigente_no_aparece_como_vencido(): void
    {
        $vale = $this->crearVale();

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vale.estado_vale', 'PENDIENTE')
            ->where('vale.vencido', false)
        );
    }

    public function test_un_vale_pendiente_con_fecha_vencida_se_marca_como_vencido(): void
    {
        $vale = $this->crearVale();
        $vale->update(['fecha_vencimiento' => now()->subDay()]);

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vale.estado_vale', 'PENDIENTE')
            ->where('vale.vencido', true)
        );
    }

    public function test_un_vale_anulado_muestra_su_estado(): void
    {
        $vale = $this->crearVale(['estado_vale' => 'ANULADO']);

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vale.estado_vale', 'ANULADO')
        );
    }

    public function test_un_vale_usado_incluye_el_detalle_de_la_carga(): void
    {
        $vale = $this->crearVale(['estado_vale' => 'USADO']);

        CargaCombustible::create([
            'fecha_carga' => now(),
            'litros' => $vale->litros,
            'precio' => $vale->precio,
            'id_vehiculo' => $vale->id_vehiculo,
            'id_grifo' => $vale->id_grifo,
            'id_tipo_combustible' => $vale->id_tipo_combustible,
            'id_conductor' => $vale->id_conductor,
            'id_vale' => $vale->id,
            'tipo_carga' => 'VALE',
        ]);

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vale.estado_vale', 'USADO')
            ->where('vale.usado_en.litros', 20)
        );
    }

    public function test_un_hash_que_no_corresponde_a_ningun_vale_devuelve_404(): void
    {
        $response = $this->get(route('vales.publico', md5('no-existe')));

        $response->assertNotFound();
    }

    public function test_un_vale_eliminado_no_es_accesible(): void
    {
        $vale = $this->crearVale();
        $vale->delete();

        $response = $this->get(route('vales.publico', md5($vale->id)));

        $response->assertNotFound();
    }
}
