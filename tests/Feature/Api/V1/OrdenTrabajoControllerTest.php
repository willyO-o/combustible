<?php

namespace Tests\Feature\Api\V1;

use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Repuesto;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdenTrabajoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);

        // OrdenTrabajo::calcularGestion()/getNroAttribute() leen este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1, 'digitos_serie' => 6],
            'estado' => 'ACTIVO',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    private function crearTecnico(): User
    {
        $tecnico = User::factory()->create(['estado_usuario' => 'ACTIVO']);
        $tecnico->assignRole('tecnico-mantenimiento');

        return $tecnico;
    }

    private function crearOrden(User $tecnico, array $overrides = []): OrdenTrabajo
    {
        // OrdenTrabajo::boot() asigna id_usuario_emite = auth()->id().
        $this->actingAs($this->admin);

        $orden = OrdenTrabajo::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => $tecnico->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ], $overrides));

        // OrdenTrabajo::boot() fuerza estado_orden = 'PENDIENTE' al crear, así
        // que un estado distinto se aplica después.
        if (isset($overrides['estado_orden']) && $overrides['estado_orden'] !== $orden->estado_orden) {
            $orden->update(['estado_orden' => $overrides['estado_orden']]);
        }

        return $orden;
    }

    private function crearTipoMantenimiento(): TipoMantenimiento
    {
        return TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
    }

    public function test_index_solo_lista_las_ordenes_asignadas_al_tecnico(): void
    {
        $tecnico = $this->crearTecnico();
        $otroTecnico = $this->crearTecnico();

        $ordenPropia = $this->crearOrden($tecnico);
        $this->crearOrden($otroTecnico);

        $response = $this->actingAs($tecnico, 'api')->getJson(route('api.v1.ordenes-trabajo.index'));

        $response->assertOk();
        $this->assertSame([$ordenPropia->id], $response->json('data.*.id'));
    }

    public function test_index_para_administrador_lista_todas_las_ordenes(): void
    {
        $ordenA = $this->crearOrden($this->crearTecnico());
        $ordenB = $this->crearOrden($this->crearTecnico());

        $response = $this->actingAs($this->admin, 'api')->getJson(route('api.v1.ordenes-trabajo.index'));

        $response->assertOk();
        $ids = $response->json('data.*.id');
        sort($ids);
        $this->assertSame([$ordenA->id, $ordenB->id], $ids);
    }

    public function test_show_devuelve_la_orden_con_su_detalle(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico);

        $response = $this->actingAs($tecnico, 'api')->getJson(route('api.v1.ordenes-trabajo.show', $orden));

        $response->assertOk();
        $response->assertJsonPath('data.id', $orden->id);
        $response->assertJsonStructure(['data' => ['id', 'nro', 'estado_orden', 'detalles']]);
    }

    public function test_show_esta_bloqueado_para_una_orden_de_otro_tecnico(): void
    {
        $orden = $this->crearOrden($this->crearTecnico());

        $response = $this->actingAs($this->crearTecnico(), 'api')->getJson(route('api.v1.ordenes-trabajo.show', $orden));

        $response->assertForbidden();
    }

    public function test_el_tecnico_registra_un_item_del_detalle_de_a_uno(): void
    {
        $tecnico = $this->crearTecnico();
        // VehiculoFactory usa tipo_medicion "kilometraje" por defecto.
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'EN_EJECUCION']);
        $tipo = $this->crearTipoMantenimiento();

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.detalles.store', $orden),
            [
                'id_tipo_mantenimiento' => $tipo->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 1250.5,
                'cantidad' => 2,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.cantidad', 2);
        $this->assertSame(1, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());
    }

    public function test_registrar_detalle_exige_la_lectura_del_tipo_de_medicion_del_vehiculo(): void
    {
        $tecnico = $this->crearTecnico();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $orden = $this->crearOrden($tecnico, ['id_vehiculo' => $vehiculo->id]);
        $tipo = $this->crearTipoMantenimiento();

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.detalles.store', $orden),
            [
                'id_tipo_mantenimiento' => $tipo->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 500,
                'cantidad' => 1,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('horometro');
    }

    public function test_un_tecnico_no_puede_registrar_detalle_en_una_orden_ajena(): void
    {
        $orden = $this->crearOrden($this->crearTecnico());
        $tipo = $this->crearTipoMantenimiento();

        $response = $this->actingAs($this->crearTecnico(), 'api')->postJson(
            route('api.v1.ordenes-trabajo.detalles.store', $orden),
            [
                'id_tipo_mantenimiento' => $tipo->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 100,
                'cantidad' => 1,
            ]
        );

        $response->assertForbidden();
        $this->assertSame(0, DetalleMantenimiento::count());
    }

    public function test_no_se_puede_registrar_detalle_en_una_orden_culminada(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'CULMINADO']);
        $tipo = $this->crearTipoMantenimiento();

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.detalles.store', $orden),
            [
                'id_tipo_mantenimiento' => $tipo->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 100,
                'cantidad' => 1,
            ]
        );

        $response->assertStatus(422);
        $this->assertSame(0, DetalleMantenimiento::count());
    }

    public function test_el_tecnico_elimina_un_item_del_detalle_registrado_por_error(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'EN_EJECUCION']);
        $tipo = $this->crearTipoMantenimiento();
        $detalle = $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipo->id,
            'fecha' => now()->toDateString(),
            'kilometraje' => 100,
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($tecnico, 'api')->deleteJson(
            route('api.v1.ordenes-trabajo.detalles.destroy', [$orden, $detalle])
        );

        $response->assertOk();
        $this->assertSame(0, DetalleMantenimiento::count());
    }

    public function test_el_tecnico_inicia_el_mantenimiento(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico);

        $response = $this->actingAs($tecnico, 'api')->patchJson(route('api.v1.ordenes-trabajo.iniciar', $orden));

        $response->assertOk();
        $response->assertJsonPath('data.estado_orden', 'EN_EJECUCION');
        $orden->refresh();
        $this->assertSame('EN_EJECUCION', $orden->estado_orden);
        $this->assertNotNull($orden->fecha_ejecucion);
    }

    public function test_no_se_puede_iniciar_una_orden_que_no_esta_pendiente(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'EN_EJECUCION']);

        $response = $this->actingAs($tecnico, 'api')->patchJson(route('api.v1.ordenes-trabajo.iniciar', $orden));

        $response->assertStatus(422);
    }

    public function test_un_tecnico_no_puede_iniciar_una_orden_ajena(): void
    {
        $orden = $this->crearOrden($this->crearTecnico());

        $response = $this->actingAs($this->crearTecnico(), 'api')->patchJson(route('api.v1.ordenes-trabajo.iniciar', $orden));

        $response->assertForbidden();
    }

    public function test_el_tecnico_culmina_el_mantenimiento_con_las_lecturas_finales(): void
    {
        $tecnico = $this->crearTecnico();
        // VehiculoFactory usa tipo_medicion "kilometraje" por defecto.
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'EN_EJECUCION']);
        $tipo = $this->crearTipoMantenimiento();
        $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipo->id,
            'fecha' => now()->toDateString(),
            'kilometraje' => 100,
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.culminar', $orden),
            ['kilometraje_actual' => 85300, 'observacion' => 'Todo en orden.']
        );

        $response->assertOk();
        $response->assertJsonPath('data.estado_orden', 'CULMINADO');
        $orden->refresh();
        $this->assertSame('CULMINADO', $orden->estado_orden);
        $this->assertSame(85300, $orden->kilometraje_actual);
        $this->assertNotNull($orden->fecha_culminacion);
    }

    public function test_no_se_puede_culminar_una_orden_no_iniciada(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico);

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.culminar', $orden),
            ['kilometraje_actual' => 85300]
        );

        $response->assertStatus(422);
        $this->assertSame('PENDIENTE', $orden->fresh()->estado_orden);
    }

    public function test_no_se_puede_culminar_una_orden_sin_items_de_detalle(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden($tecnico, ['estado_orden' => 'EN_EJECUCION']);

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.culminar', $orden),
            ['kilometraje_actual' => 85300]
        );

        $response->assertStatus(422);
        $this->assertSame('EN_EJECUCION', $orden->fresh()->estado_orden);
    }

    public function test_culminar_exige_la_lectura_final_del_tipo_de_medicion_del_vehiculo(): void
    {
        $tecnico = $this->crearTecnico();
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $orden = $this->crearOrden($tecnico, ['id_vehiculo' => $vehiculo->id, 'estado_orden' => 'EN_EJECUCION']);
        $tipo = $this->crearTipoMantenimiento();
        $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipo->id,
            'fecha' => now()->toDateString(),
            'horometro' => 10,
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($tecnico, 'api')->postJson(
            route('api.v1.ordenes-trabajo.culminar', $orden),
            ['kilometraje_actual' => 999]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('horometro_actual');
    }

    public function test_colecciones_incluye_los_catalogos_del_detalle_de_mantenimiento(): void
    {
        $this->crearTipoMantenimiento();
        TipoMantenimiento::create(['tipo_mantenimiento' => 'Inactivo', 'estado_tipo_mantenimiento' => 'INACTIVO']);

        Repuesto::create([
            'nombre_repuesto' => 'Filtro de aceite',
            'codigo_repuesto' => 'FIL-001',
            'unidad_medida' => 'UNIDAD',
            'stock_actual' => 10,
            'estado_repuesto' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->crearTecnico(), 'api')->getJson(route('api.v1.parametros.colecciones'));

        $response->assertOk();
        // Sólo los tipos de mantenimiento activos.
        $this->assertSame(['Cambio de aceite'], $response->json('data.ordenes_trabajo.tipos_mantenimiento.*.tipo_mantenimiento'));
        $this->assertSame('Filtro de aceite', $response->json('data.ordenes_trabajo.repuestos.0.nombre_repuesto'));
        $this->assertContains('EN_EJECUCION', $response->json('data.ordenes_trabajo.estados_orden'));
        // Colección de estados con detalle + acciones del técnico.
        $this->assertContains('EN_EJECUCION', $response->json('data.ordenes_trabajo.estados.*.value'));
        $this->assertSame(
            ['iniciar', 'agregar_detalle', 'eliminar_detalle', 'culminar'],
            $response->json('data.ordenes_trabajo.acciones_tecnico.*.accion')
        );
    }
}
