<?php

namespace Tests\Feature;

use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        // Los modelos SolicitudMantenimiento/OrdenTrabajo asignan el usuario
        // autenticado (id_usuario_registra/id_usuario_emite) al crearse.
        $this->actingAs($this->admin);
    }

    private function crearSolicitud(array $overrides = []): SolicitudMantenimiento
    {
        return SolicitudMantenimiento::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'fecha_solicitud' => now(),
            'estado' => 'PENDIENTE',
        ], $overrides));
    }

    private function crearOrden(array $overrides = []): OrdenTrabajo
    {
        return OrdenTrabajo::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => User::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ], $overrides));
    }

    public function test_store_emite_una_orden_interna_y_aprueba_la_solicitud_origen(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $ejecutor = User::factory()->create();
        $solicitud = $this->crearSolicitud(['id_vehiculo' => $vehiculo->id]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $ejecutor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'nota_emisor' => 'Revisar frenos',
            'kilometraje_actual' => 85000,
        ]);

        $orden = OrdenTrabajo::first();

        $response->assertRedirect(route('mantenimiento.ordenes.show', $orden));
        $this->assertNotNull($orden);
        $this->assertSame('PENDIENTE', $orden->estado_orden);
        $this->assertSame($this->admin->id, $orden->id_usuario_emite);
        $this->assertSame(1, $orden->nro_orden);
        $this->assertNull($orden->id_taller);
        $this->assertSame('INTERNO', $orden->tipo_orden);
        $this->assertSame('APROBADA', $solicitud->fresh()->estado);
    }

    public function test_store_orden_externa_cuando_se_asigna_taller(): void
    {
        $taller = Taller::create([
            'razon_social' => 'Taller Central',
            'nit' => '123456',
            'estado_taller' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_taller' => $taller->id,
            'id_usuario_ejecuta' => User::factory()->create()->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 42000,
        ]);

        $response->assertRedirect();
        $orden = OrdenTrabajo::first();

        $this->assertSame($taller->id, $orden->id_taller);
        $this->assertSame('EXTERNO', $orden->tipo_orden);
    }

    public function test_la_numeracion_de_orden_es_secuencial_por_gestion(): void
    {
        $primera = $this->crearOrden();
        $segunda = $this->crearOrden();

        $this->assertSame(1, $primera->nro_orden);
        $this->assertSame(2, $segunda->nro_orden);
        $this->assertSame($primera->gestion, $segunda->gestion);
    }

    public function test_cambiar_estado_a_en_ejecucion_registra_la_fecha_de_ejecucion(): void
    {
        $orden = $this->crearOrden();

        $response = $this->actingAs($this->admin)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'EN_EJECUCION']);

        $response->assertRedirect();
        $orden->refresh();
        $this->assertSame('EN_EJECUCION', $orden->estado_orden);
        $this->assertNotNull($orden->fecha_ejecucion);
    }

    public function test_store_ejecucion_registra_el_detalle_y_culmina_la_orden(): void
    {
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.store', $orden), [
                'fecha_culminacion' => now()->toDateString(),
                'observacion' => 'Trabajo finalizado sin novedad',
                'detalles' => [
                    [
                        'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                        'detalle' => 'Mano de obra',
                        'cantidad' => 2,
                        'costo_unitario' => 50,
                    ],
                ],
            ]);

        $response->assertRedirect(route('mantenimiento.ordenes.show', $orden));
        $orden->refresh();

        $this->assertSame('CULMINADO', $orden->estado_orden);
        $this->assertNotNull($orden->fecha_culminacion);
        $this->assertNotNull($orden->fecha_ejecucion);
        $this->assertSame(1, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());

        $detalle = DetalleMantenimiento::first();
        $this->assertSame(2, $detalle->cantidad);
        $this->assertSame('50.00', (string) $detalle->costo_unitario);
        $this->assertSame(100.0, (float) $detalle->subtotal);
    }

    public function test_index_filtra_ordenes_externas(): void
    {
        $taller = Taller::create([
            'razon_social' => 'Taller Externo',
            'nit' => '999999',
            'estado_taller' => 'ACTIVO',
        ]);
        $this->crearOrden(); // interna
        $this->crearOrden(['id_taller' => $taller->id]); // externa

        $response = $this->actingAs($this->admin)
            ->get(route('mantenimiento.ordenes.index', ['tipo_orden' => 'EXTERNO']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenTrabajo/Index')
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.tipo_orden', 'EXTERNO')
        );
    }
}
