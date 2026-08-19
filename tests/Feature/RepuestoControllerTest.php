<?php

namespace Tests\Feature;

use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepuestoControllerTest extends TestCase
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

    private function crearRepuesto(array $overrides = []): Repuesto
    {
        return Repuesto::create(array_merge([
            'nombre_repuesto' => 'Filtro de aceite',
            'codigo_repuesto' => 'REP-0001',
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 10,
            'estado_repuesto' => 'ACTIVO',
        ], $overrides));
    }

    public function test_index_lista_los_repuestos(): void
    {
        $this->crearRepuesto();
        $this->crearRepuesto(['codigo_repuesto' => 'REP-0002', 'nombre_repuesto' => 'Correa']);

        $response = $this->get(route('repuestos.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Repuestos/Index')
            ->has('repuestos.data', 2)
        );
    }

    public function test_index_filtra_por_nombre_codigo_y_estado(): void
    {
        $this->crearRepuesto(['nombre_repuesto' => 'Filtro de aceite', 'codigo_repuesto' => 'REP-0001']);
        $this->crearRepuesto(['nombre_repuesto' => 'Correa de distribución', 'codigo_repuesto' => 'REP-0002', 'estado_repuesto' => 'INACTIVO']);

        $this->get(route('repuestos.index', ['nombre_repuesto' => 'correa']))
            ->assertInertia(fn (Assert $page) => $page->has('repuestos.data', 1));

        $this->get(route('repuestos.index', ['codigo_repuesto' => 'REP-0001']))
            ->assertInertia(fn (Assert $page) => $page->has('repuestos.data', 1));

        $this->get(route('repuestos.index', ['estado_repuesto' => 'INACTIVO']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('repuestos.data', 1)
                ->where('repuestos.data.0.codigo_repuesto', 'REP-0002')
            );
    }

    public function test_store_crea_un_repuesto(): void
    {
        $response = $this->post(route('repuestos.store'), [
            'nombre_repuesto' => 'Filtro de aire',
            'codigo_repuesto' => 'REP-0100',
            'descripcion_repuesto' => 'Filtro de aire para motor diésel',
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 25,
            'estado_repuesto' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('repuestos.index'));
        $this->assertDatabaseHas('repuesto', [
            'codigo_repuesto' => 'REP-0100',
            'nombre_repuesto' => 'Filtro de aire',
            'stock_actual' => 25,
        ]);
    }

    public function test_store_valida_los_campos_requeridos(): void
    {
        $response = $this->post(route('repuestos.store'), []);

        $response->assertSessionHasErrors([
            'nombre_repuesto',
            'codigo_repuesto',
            'unidad_medida',
            'stock_actual',
            'estado_repuesto',
        ]);
        $this->assertDatabaseCount('repuesto', 0);
    }

    public function test_store_rechaza_un_codigo_repuesto_duplicado(): void
    {
        $this->crearRepuesto(['codigo_repuesto' => 'REP-0001']);

        $response = $this->post(route('repuestos.store'), [
            'nombre_repuesto' => 'Otro repuesto',
            'codigo_repuesto' => 'REP-0001',
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 5,
            'estado_repuesto' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('codigo_repuesto');
        $this->assertSame(1, Repuesto::where('codigo_repuesto', 'REP-0001')->count());
    }

    public function test_update_actualiza_el_repuesto_y_permite_conservar_su_propio_codigo(): void
    {
        $repuesto = $this->crearRepuesto();

        $response = $this->put(route('repuestos.update', $repuesto->id), [
            'nombre_repuesto' => 'Filtro de aceite premium',
            'codigo_repuesto' => 'REP-0001', // sin cambios: no debe fallar el unique
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 15,
            'estado_repuesto' => 'AGOTADO',
        ]);

        $response->assertRedirect(route('repuestos.index'));
        $repuesto->refresh();
        $this->assertSame('Filtro de aceite premium', $repuesto->nombre_repuesto);
        $this->assertSame(15, $repuesto->stock_actual);
        $this->assertSame('AGOTADO', $repuesto->estado_repuesto);
    }

    public function test_update_rechaza_el_codigo_de_otro_repuesto(): void
    {
        $this->crearRepuesto(['codigo_repuesto' => 'REP-0001']);
        $otro = $this->crearRepuesto(['codigo_repuesto' => 'REP-0002', 'nombre_repuesto' => 'Correa']);

        $response = $this->put(route('repuestos.update', $otro->id), [
            'nombre_repuesto' => 'Correa',
            'codigo_repuesto' => 'REP-0001',
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 5,
            'estado_repuesto' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('codigo_repuesto');
    }

    public function test_destroy_elimina_un_repuesto_sin_uso(): void
    {
        $repuesto = $this->crearRepuesto();

        $response = $this->delete(route('repuestos.destroy', $repuesto->id));

        $response->assertRedirect(route('repuestos.index'));
        $this->assertDatabaseMissing('repuesto', ['id' => $repuesto->id]);
    }

    public function test_destroy_bloquea_un_repuesto_referenciado_en_un_detalle_de_orden(): void
    {
        $repuesto = $this->crearRepuesto();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $orden = OrdenTrabajo::create([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => User::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);
        DetalleMantenimiento::create([
            'id_orden_trabajo' => $orden->id,
            'id_repuesto' => $repuesto->id,
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'cantidad' => 2,
            'costo_unitario' => 50,
        ]);

        $response = $this->delete(route('repuestos.destroy', $repuesto->id));

        $response->assertRedirect(route('repuestos.index'));
        $this->assertDatabaseHas('repuesto', ['id' => $repuesto->id]);
    }
}
