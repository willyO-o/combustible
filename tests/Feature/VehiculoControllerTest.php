<?php

namespace Tests\Feature;

use App\Libraries\Reportes;
use App\Libraries\ReportesExcel;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

class VehiculoControllerTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        Storage::fake('public');
    }

    public function test_index_filtra_vehiculos_por_nro_placa(): void
    {
        Vehiculo::factory()->create(['nro_placa' => '1234ABC']);
        Vehiculo::factory()->create(['nro_placa' => '9999ZZZ']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['nro_placa' => '1234']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.nro_placa', '1234ABC')
        );
    }

    public function test_index_filtra_vehiculos_por_codigo(): void
    {
        Vehiculo::factory()->create(['codigo' => 'ACT-0001']);
        Vehiculo::factory()->create(['codigo' => 'ACT-0002']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['codigo' => '0001']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.codigo', 'ACT-0001')
        );
    }

    public function test_index_filtra_vehiculos_por_area(): void
    {
        $area = Area::factory()->create();
        $vehiculoEnArea = Vehiculo::factory()->create();
        VehiculoArea::create([
            'id_vehiculo' => $vehiculoEnArea->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        Vehiculo::factory()->create(); // sin área asignada

        $response = $this->actingAs($this->admin)->get(route('vehiculos.index', ['id_area' => $area->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Index')
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.id', $vehiculoEnArea->id)
        );
    }

    public function test_crea_un_vehiculo_con_codigo_y_modelo(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '1111AAA',
            'codigo' => 'ACT-0099',
            'modelo' => 'Hilux',
            'marca' => 'Toyota',
            'anio' => '2022',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'horometro',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', [
            'nro_placa' => '1111AAA',
            'codigo' => 'ACT-0099',
            'modelo' => 'Hilux',
            'tipo_medicion' => 'horometro',
        ]);
    }

    public function test_crea_un_vehiculo_con_capacidad_y_unidad(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '2222BBB',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'capacidad' => '3.5',
            'capacidad_unidad' => 'm³',
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', [
            'nro_placa' => '2222BBB',
            'capacidad' => 3.5,
            'capacidad_unidad' => 'm³',
        ]);
    }

    public function test_capacidad_unidad_es_requerida_si_se_envia_capacidad(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '3333CCC',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'capacidad' => '10',
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ]);

        $response->assertSessionHasErrors('capacidad_unidad');
        $this->assertDatabaseMissing('vehiculo', ['nro_placa' => '3333CCC']);
    }

    public function test_puede_crear_un_vehiculo_sin_capacidad(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '4444DDD',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ]);

        $response->assertRedirect(route('vehiculos.index'));
        $this->assertDatabaseHas('vehiculo', ['nro_placa' => '4444DDD', 'capacidad' => null]);
    }

    public function test_puede_crear_un_vehiculo_sin_nro_placa(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'codigo' => 'ACT-0100',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ]);

        $response->assertRedirect(route('vehiculos.index'));
        $this->assertDatabaseHas('vehiculo', ['codigo' => 'ACT-0100', 'nro_placa' => null]);
    }

    /**
     * Payload como lo envía el formulario real (Inertia, multipart): el año
     * vacío llega como texto vacío.
     */
    public function test_el_anio_es_opcional_al_registrar_un_vehiculo(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'codigo' => 'SIN-ANIO',
            'anio' => '',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', ['codigo' => 'SIN-ANIO', 'anio' => null]);
    }

    public function test_el_anio_se_puede_vaciar_al_editar_un_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'CON-ANIO', 'anio' => '2019']);

        $this->actingAs($this->admin)->post(route('vehiculos.update', $vehiculo->id), [
            '_method' => 'PUT',
            'codigo' => 'CON-ANIO',
            'anio' => '',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'fotografia' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($vehiculo->fresh()->anio);
    }

    public function test_el_anio_si_se_informa_debe_tener_cuatro_digitos(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        foreach (['20', '20a1', '19999'] as $anioInvalido) {
            $this->actingAs($this->admin)->post(route('vehiculos.store'), [
                'codigo' => 'ANIO-MAL',
                'anio' => $anioInvalido,
                'estado_vehiculo' => 'ACTIVO',
                'tipo_medicion' => 'kilometraje',
                'id_tipo_combustible' => $tipoCombustible->id,
                'id_tipo_vehiculo' => $tipoVehiculo->id,
                'fotografia' => UploadedFile::fake()->image('vehiculo.jpg'),
            ])->assertSessionHasErrors(['anio' => 'El año debe tener 4 dígitos (ej. 2020) o dejarse vacío.']);
        }

        $this->assertDatabaseMissing('vehiculo', ['codigo' => 'ANIO-MAL']);
    }

    public function test_fotografia_es_obligatoria_al_crear_un_vehiculo(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'nro_placa' => '5555EEE',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
        ]);

        $response->assertSessionHasErrors('fotografia');
        $this->assertDatabaseMissing('vehiculo', ['nro_placa' => '5555EEE']);
    }

    public function test_fotografia_es_opcional_al_actualizar_un_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0020']);

        $response = $this->actingAs($this->admin)->put(route('vehiculos.update', $vehiculo->id), [
            'nro_placa' => $vehiculo->nro_placa,
            'codigo' => 'ACT-0021',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
        ]);

        $response->assertRedirect(route('vehiculos.index'));
        $response->assertSessionDoesntHaveErrors('fotografia');
        $this->assertDatabaseHas('vehiculo', ['id' => $vehiculo->id, 'codigo' => 'ACT-0021']);
    }

    /**
     * Lo que envía de verdad el formulario de edición (Inertia, multipart con
     * _method=PUT): si no se cambia la foto, manda `fotografia` como texto
     * vacío. Antes eso fallaba con "La fotografía debe ser una imagen".
     */
    public function test_editar_sin_cambiar_la_foto_como_lo_envia_el_formulario_conserva_la_actual(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0030', 'fotografia' => 'vehiculos/actual.jpg']);
        Storage::disk('public')->put('vehiculos/actual.jpg', 'contenido');

        $response = $this->actingAs($this->admin)->post(route('vehiculos.update', $vehiculo->id), [
            '_method' => 'PUT',
            'nro_placa' => $vehiculo->nro_placa,
            'codigo' => 'ACT-0031',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'fotografia' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('vehiculos.index'));
        $this->assertSame('ACT-0031', $vehiculo->fresh()->codigo);
        $this->assertSame('vehiculos/actual.jpg', $vehiculo->fresh()->fotografia);
        Storage::disk('public')->assertExists('vehiculos/actual.jpg');
    }

    /**
     * Las URLs de imágenes salen del host real de la petición (ej. el
     * servidor en la red local con puerto), no de APP_URL.
     */
    public function test_las_urls_de_imagenes_usan_el_host_real_del_servidor(): void
    {
        config(['app.url' => 'http://localhost']);
        $vehiculo = Vehiculo::factory()->create(['fotografia' => 'vehiculos/foto.jpg']);

        $this->actingAs($this->admin)
            ->get('http://192.168.0.200:8443/vehiculos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('storage_url', 'http://192.168.0.200:8443/storage')
                ->where('vehiculos.data.0.url_fotografia', 'http://192.168.0.200:8443/storage/vehiculos/foto.jpg')
            );

        $this->actingAs($this->admin)
            ->get('http://192.168.0.200:8443/vehiculos/'.$vehiculo->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehiculo.url_fotografia', 'http://192.168.0.200:8443/storage/vehiculos/foto.jpg')
            );
    }

    public function test_vehiculo_sin_foto_no_tiene_url_de_fotografia(): void
    {
        Vehiculo::factory()->create(['fotografia' => null]);

        $this->actingAs($this->admin)->get(route('vehiculos.index'))
            ->assertInertia(fn (Assert $page) => $page->where('vehiculos.data.0.url_fotografia', null));
    }

    public function test_editar_con_una_foto_nueva_reemplaza_la_anterior(): void
    {
        $vehiculo = Vehiculo::factory()->create(['fotografia' => 'vehiculos/anterior.jpg']);
        Storage::disk('public')->put('vehiculos/anterior.jpg', 'contenido');

        $this->actingAs($this->admin)->post(route('vehiculos.update', $vehiculo->id), [
            '_method' => 'PUT',
            'nro_placa' => $vehiculo->nro_placa,
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'fotografia' => UploadedFile::fake()->image('nueva.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $nueva = $vehiculo->fresh()->fotografia;
        $this->assertNotSame('vehiculos/anterior.jpg', $nueva);
        Storage::disk('public')->assertMissing('vehiculos/anterior.jpg');
        Storage::disk('public')->assertExists($nueva);
    }

    public function test_editar_rechaza_un_archivo_que_no_es_imagen(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('vehiculos.update', $vehiculo->id), [
            '_method' => 'PUT',
            'nro_placa' => $vehiculo->nro_placa,
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
            'fotografia' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('fotografia');
    }

    public function test_crear_con_fotografia_vacia_como_la_envia_el_formulario_la_exige(): void
    {
        $tipoCombustible = TipoCombustible::factory()->create();
        $tipoVehiculo = TipoVehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('vehiculos.store'), [
            'codigo' => 'SIN-FOTO',
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => 'kilometraje',
            'id_tipo_combustible' => $tipoCombustible->id,
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'fotografia' => '',
        ])->assertSessionHasErrors('fotografia');

        $this->assertDatabaseMissing('vehiculo', ['codigo' => 'SIN-FOTO']);
    }

    public function test_show_incluye_el_historial_de_asignaciones_y_el_conductor_actual(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create(['nombre_area' => 'Transporte']);
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $conductorAnterior = Conductor::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorAnterior->id,
            'fecha_asignacion' => now()->subMonth(),
            'fecha_culminacion' => now()->subDay(),
            'estado_asignacion' => 'INACTIVO',
            'detalle' => 'Asignación anterior',
        ]);

        $conductorActual = Conductor::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorActual->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'detalle' => 'Asignación vigente',
        ]);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.show', $vehiculo->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Show')
            ->where('vehiculo.id', $vehiculo->id)
            ->where('vehiculo.conductor_asignado.id', $conductorActual->id)
            ->where('vehiculo.areas_asignadas.0.nombre_area', 'Transporte')
            ->has('historialAsignaciones', 2)
            ->where('historialAsignaciones.0.conductor.id', $conductorActual->id)
            ->where('historialAsignaciones.0.estado_asignacion', 'ACTIVO')
            ->where('historialAsignaciones.0.puede_finalizar', true)
            ->where('historialAsignaciones.1.conductor.id', $conductorAnterior->id)
            ->where('historialAsignaciones.1.estado_asignacion', 'INACTIVO')
            ->where('historialAsignaciones.1.puede_finalizar', false)
        );
    }

    /**
     * puede_finalizar habilita el botón "Finalizar" del historial: activa, o
     * provisional todavía dentro de (o sin) su fecha de culminación. Una
     * PROVISIONAL cuya fecha ya pasó no debe habilitarlo (ya está vencida).
     */
    public function test_show_marca_puede_finalizar_segun_estado_y_fecha_de_culminacion(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $activa = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $provisionalVigente = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now(),
            'fecha_culminacion' => now()->addWeek(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);

        $provisionalVencida = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now()->subMonth(),
            'fecha_culminacion' => now()->subDay(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.show', $vehiculo->id));

        $response->assertOk();
        $historial = collect($response->viewData('page')['props']['historialAsignaciones'])->keyBy('id');

        $this->assertTrue($historial[$activa->id]['puede_finalizar']);
        $this->assertTrue($historial[$provisionalVigente->id]['puede_finalizar']);
        $this->assertFalse($historial[$provisionalVencida->id]['puede_finalizar']);
    }

    public function test_show_incluye_las_alertas_de_mantenimiento_del_tipo_de_vehiculo(): void
    {
        $tipoVehiculo = TipoVehiculo::factory()->create();
        $vehiculo = Vehiculo::factory()->create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'tipo_medicion' => 'kilometraje',
        ]);
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
        IntervaloMantenimientoTipo::create([
            'id_tipo_vehiculo' => $tipoVehiculo->id,
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'tipo_medicion' => 'kilometraje',
            'frecuencia' => 10000,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.show', $vehiculo->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Show')
            ->has('alertasMantenimiento', 1)
            ->where('alertasMantenimiento.0.tipo_mantenimiento', 'Cambio de aceite')
            ->where('alertasMantenimiento.0.estado', 'SIN_DATOS')
        );
    }

    public function test_edit_reutiliza_la_pagina_create_con_los_datos_del_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0005', 'modelo' => 'Corolla']);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.edit', $vehiculo->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Vehiculos/Create')
            ->where('vehiculo.id', $vehiculo->id)
            ->where('vehiculo.codigo', 'ACT-0005')
            ->where('vehiculo.modelo', 'Corolla')
        );
    }

    public function test_actualiza_el_codigo_y_modelo_de_un_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'ACT-0010', 'modelo' => 'Corolla']);

        $response = $this->actingAs($this->admin)->put(route('vehiculos.update', $vehiculo->id), [
            'nro_placa' => $vehiculo->nro_placa,
            'codigo' => 'ACT-0011',
            'modelo' => 'Hilux',
            'marca' => $vehiculo->marca,
            'anio' => $vehiculo->anio,
            'estado_vehiculo' => 'ACTIVO',
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
            'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
        ]);

        $response->assertRedirect(route('vehiculos.index'));

        $this->assertDatabaseHas('vehiculo', [
            'id' => $vehiculo->id,
            'codigo' => 'ACT-0011',
            'modelo' => 'Hilux',
        ]);
    }

    /**
     * Botón "Finalizar" del historial de asignaciones (Vehiculos/Show.vue):
     * misma acción que ConductorController::finalizarAsignacion(), pero
     * redirige de vuelta a la ficha del vehículo en vez de a conductores.index.
     */
    public function test_finalizar_asignacion_de_conductor_la_marca_inactiva_sin_reemplazarla(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $asignacion = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('vehiculos.asignaciones.finalizar', [$vehiculo->id, $asignacion->id]));

        $response->assertRedirect(route('vehiculos.show', $vehiculo->id));
        $asignacion->refresh();
        $this->assertSame('INACTIVO', $asignacion->estado_asignacion);
        $this->assertNotNull($asignacion->fecha_culminacion);
    }

    public function test_finalizar_asignacion_de_conductor_esta_bloqueado_para_quien_no_es_administrador_ni_jefe_de_area(): void
    {
        $conductorUser = User::factory()->create();
        $conductorUser->assignRole('conductor');

        $vehiculo = Vehiculo::factory()->create();
        $asignacion = Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($conductorUser)
            ->patch(route('vehiculos.asignaciones.finalizar', [$vehiculo->id, $asignacion->id]));

        $response->assertForbidden();
        $this->assertSame('ACTIVO', $asignacion->fresh()->estado_asignacion);
    }

    public function test_finalizar_asignacion_de_conductor_de_otro_vehiculo_devuelve_404(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $otroVehiculo = Vehiculo::factory()->create();
        $asignacion = Asignacion::create([
            'id_vehiculo' => $otroVehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('vehiculos.asignaciones.finalizar', [$vehiculo->id, $asignacion->id]));

        $response->assertNotFound();
        $this->assertSame('ACTIVO', $asignacion->fresh()->estado_asignacion);
    }

    /**
     * Abre el .xlsx de la respuesta y devuelve sus celdas (valores crudos,
     * sin formatear) como colección de filas.
     *
     * @return Collection<int, array<int, mixed>>
     */
    private function celdasDelExcel(string $contenido): Collection
    {
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($archivo, $contenido);

        try {
            return collect(IOFactory::load($archivo)->getActiveSheet()->toArray(null, true, false));
        } finally {
            @unlink($archivo);
        }
    }

    public function test_exporta_el_listado_en_pdf(): void
    {
        Vehiculo::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('listado_vehiculos_', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * Una flota grande no entra en una hoja: la tabla debe paginar (con el
     * encabezado y el pie repetidos) y el alias {nb} del total de páginas
     * debe quedar reemplazado en todas ellas.
     */
    public function test_el_pdf_de_una_flota_grande_ocupa_varias_paginas(): void
    {
        Vehiculo::factory()->count(60)->create();

        $contenido = $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf'))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $contenido);
        $this->assertGreaterThan(1, preg_match_all('#/Type /Page[^s]#', $contenido));
        $this->assertStringNotContainsString('{nb}', $contenido);
    }

    /**
     * Vehículos con datos opcionales vacíos (sin placa, capacidad, área ni
     * operario) y un listado sin resultados no deben romper ningún formato.
     */
    public function test_los_reportes_toleran_datos_opcionales_vacios_y_listados_sin_resultados(): void
    {
        Vehiculo::factory()->create([
            'nro_placa' => null,
            'marca' => null,
            'modelo' => null,
            'anio' => null,
            'capacidad' => null,
            'capacidad_unidad' => null,
        ]);

        $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf'))->assertOk();
        $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel'))->assertOk();

        $sinResultados = ['nro_placa' => 'NO-EXISTE'];

        $pdf = $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf', $sinResultados));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $excel = $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel', $sinResultados));
        $excel->assertOk();
        $this->assertTrue(
            $this->celdasDelExcel($excel->getContent())->flatten()->contains('No hay vehículos que coincidan con los filtros seleccionados.'),
        );
    }

    public function test_exporta_el_listado_en_excel_con_los_datos_de_cada_vehiculo(): void
    {
        $area = Area::factory()->create(['nombre_area' => 'Transporte Pesado']);
        $vehiculo = Vehiculo::factory()->create([
            'nro_placa' => '4321XYZ',
            'codigo' => 'VOL-01',
            'marca' => 'Volvo',
            'modelo' => 'FMX',
            'anio' => '2019',
            'capacidad' => 15.5,
            'capacidad_unidad' => 'm3',
        ]);
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'PROVISIONAL',
        ]);
        $conductor = Conductor::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename="listado_vehiculos_', $response->headers->get('Content-Disposition'));

        $fila = $this->celdasDelExcel($response->getContent())->first(fn ($f) => in_array('4321XYZ', $f, true));

        $this->assertNotNull($fila, 'El Excel no contiene la fila del vehículo.');
        $this->assertSame(
            ['VOL-01', '4321XYZ', 'Volvo', 'FMX'],
            array_slice($fila, 1, 4),
        );
        $this->assertSame(2019.0, (float) $fila[5]);
        $this->assertIsNumeric($fila[5], 'El año debe escribirse como número.');
        $this->assertSame(15.5, (float) $fila[8]);
        $this->assertIsFloat($fila[8], 'La capacidad debe escribirse como número.');
        $this->assertSame('m3', $fila[9]);
        $this->assertSame('Transporte Pesado (Provisional)', $fila[10]);
        $this->assertSame($conductor->persona->nombre_completo, $fila[11]);
        $this->assertSame('ACTIVO', $fila[12]);
    }

    /**
     * Lo exportado debe coincidir con lo que muestra el listado filtrado
     * (todas sus páginas), y el encabezado deja constancia de los filtros
     * con nombres legibles (el área por su nombre, no por su id).
     */
    public function test_los_reportes_respetan_los_filtros_del_listado(): void
    {
        $area = Area::factory()->create(['nombre_area' => 'Mina Norte']);
        $retirado = Vehiculo::factory()->create(['nro_placa' => 'RET111', 'estado_vehiculo' => 'RETIRADO']);
        VehiculoArea::create([
            'id_vehiculo' => $retirado->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        Vehiculo::factory()->create(['nro_placa' => 'ACT222', 'estado_vehiculo' => 'ACTIVO']);
        Vehiculo::factory()->create(['nro_placa' => 'RET333', 'estado_vehiculo' => 'RETIRADO']);

        $filtros = ['estado_vehiculo' => 'RETIRADO', 'id_area' => $area->id];

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel', $filtros))->assertOk()->getContent()
        )->flatten();

        $this->assertTrue($celdas->contains('RET111'));
        $this->assertFalse($celdas->contains('ACT222'), 'Un vehículo de otro estado no debe exportarse.');
        $this->assertFalse($celdas->contains('RET333'), 'Un vehículo de otra área no debe exportarse.');
        $this->assertTrue($celdas->contains('Filtro aplicado: Estado: RETIRADO'));
        $this->assertTrue($celdas->contains('Filtro aplicado: Área: Mina Norte'));

        $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf', $filtros))->assertOk();
    }

    public function test_el_excel_sin_filtros_lo_indica_y_cuenta_los_vehiculos_por_estado(): void
    {
        Vehiculo::factory()->count(2)->create(['estado_vehiculo' => 'ACTIVO']);
        Vehiculo::factory()->create(['estado_vehiculo' => 'VENDIDO']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel'))->assertOk()->getContent()
        );

        $this->assertTrue($celdas->flatten()->contains('Sin filtros: todos los vehículos registrados'));

        // Tarjetas: fila de etiquetas seguida de la fila de valores.
        $indiceEtiquetas = $celdas->search(fn ($f) => in_array('TOTAL VEHÍCULOS', $f, true));
        $this->assertNotFalse($indiceEtiquetas);
        $etiquetas = $celdas[$indiceEtiquetas];
        $valores = $celdas[$indiceEtiquetas + 1];

        $valorDe = fn (string $etiqueta) => (int) $valores[array_search($etiqueta, $etiquetas, true)];
        $this->assertSame(3, $valorDe('TOTAL VEHÍCULOS'));
        $this->assertSame(2, $valorDe('ACTIVOS'));
        $this->assertSame(0, $valorDe('RETIRADOS'));
        $this->assertSame(1, $valorDe('VENDIDOS'));
    }

    /**
     * Texto impreso en el PDF: FPDF comprime cada stream con zlib y escribe
     * el texto como "(...) Tj" en los streams de contenido de página. Se
     * descartan las fuentes incrustadas (/Length1) y las imágenes: son
     * binarias y, según los caracteres usados (nombres aleatorios de las
     * factories), a veces contienen bytes que parecen "(...) Tj" y daban
     * falsos positivos intermitentes (ej. el byte \x97 de la raya).
     */
    private function textoDelPdf(string $pdf): string
    {
        preg_match_all('/<<([^<>]*(?:<<[^<>]*>>[^<>]*)*)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams, PREG_SET_ORDER);

        return collect($streams)
            ->reject(fn (array $stream) => preg_match('#/Length1|/Subtype\s*/Image|/Type\s*/XObject#', $stream[1]) === 1)
            ->map(fn (array $stream) => @gzuncompress($stream[2]) ?: $stream[2])
            ->flatMap(fn (string $contenido) => preg_match_all('/\((.*?)(?<!\\\\)\)\s*Tj/s', $contenido, $textos) ? $textos[1] : [])
            ->implode("\n");
    }

    private function vehiculoSinDatosNiRelaciones(): Vehiculo
    {
        return Vehiculo::factory()->create([
            'codigo' => 'VACIO-01',
            'nro_placa' => null,
            'marca' => null,
            'modelo' => null,
            'anio' => null,
            'capacidad' => null,
            'capacidad_unidad' => null,
        ]);
    }

    public function test_el_excel_deja_en_blanco_los_datos_vacios_y_las_relaciones_inexistentes(): void
    {
        $this->vehiculoSinDatosNiRelaciones();

        $fila = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel'))->assertOk()->getContent()
        )->first(fn ($f) => in_array('VACIO-01', $f, true));

        $this->assertNotNull($fila);
        // placa, marca, modelo, año, capacidad, unidad, área y operario.
        foreach ([2, 3, 4, 5, 8, 9, 10, 11] as $columna) {
            $this->assertNull($fila[$columna], "La columna {$columna} debería quedar en blanco.");
        }
    }

    public function test_el_pdf_no_imprime_textos_de_relleno_para_los_datos_vacios(): void
    {
        $this->vehiculoSinDatosNiRelaciones();

        $texto = $this->textoDelPdf(
            $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('VACIO-01', $texto, 'No se pudo leer el texto del PDF.');
        foreach (['Sin placa', 'Sin asignar', 'Sin operario', "\x97"] as $relleno) {
            $this->assertStringNotContainsString($relleno, $texto);
        }
    }

    public function test_el_operario_sale_sin_espacios_sobrantes_si_le_falta_un_apellido(): void
    {
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'OPE-01']);
        $conductor = Conductor::factory()->create();
        $conductor->persona->update(['nombres' => 'Juan', 'paterno' => 'Quispe', 'materno' => null]);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $fila = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel'))->assertOk()->getContent()
        )->first(fn ($f) => in_array('OPE-01', $f, true));

        $this->assertSame('Juan Quispe', $fila[11]);
    }

    /**
     * Un dato mal codificado (bytes UTF-8 inválidos) o caracteres fuera de
     * Windows-1252 (emoji) no deben tumbar ninguno de los dos formatos.
     */
    public function test_los_reportes_toleran_texto_mal_codificado_o_con_emojis(): void
    {
        $listado = [
            'vehiculos' => collect([[
                'codigo' => "COD-\xC3\x28-01",
                'nro_placa' => 'ABC 🚛 123',
                'marca' => "Volvo\xFF",
                'modelo' => 'FH — 中文',
                'operario' => '=SUMA(A1:A2)',
                'estado_vehiculo' => 'ACTIVO',
            ]]),
            'totales' => ['total' => 1, 'activos' => 1],
        ];

        $pdf = (new Reportes)->generarReporteVehiculos($listado, [], 'S');
        $this->assertStringStartsWith('%PDF-', $pdf);

        $celdas = $this->celdasDelExcel((new ReportesExcel)->generarReporteVehiculos($listado, ['Placa' => "=1+1\xFF"]));
        $fila = $celdas->first(fn ($f) => in_array('ABC 🚛 123', $f, true));

        $this->assertNotNull($fila, 'El Excel debe abrirse y conservar el texto válido.');
        // Se guarda como texto, nunca como fórmula.
        $this->assertSame('=SUMA(A1:A2)', $fila[11]);
    }

    public function test_si_falla_un_reporte_vuelve_al_listado_con_los_filtros_y_un_aviso(): void
    {
        Exceptions::fake();
        Vehiculo::factory()->create(['marca' => 'Volvo']);
        // Falla real de base de datos al armar el listado (la consulta carga
        // el tipo de vehículo de cada fila).
        DB::statement('ALTER TABLE tipo_vehiculo RENAME TO tipo_vehiculo_no_disponible');

        $filtros = ['marca' => 'Volvo'];

        $this->actingAs($this->admin)->get(route('vehiculos.reporte.pdf', $filtros))
            ->assertRedirect(route('vehiculos.index', $filtros))
            ->assertSessionHas('error', 'No se pudo generar el reporte en PDF. Intente nuevamente o contacte al administrador.');

        $this->actingAs($this->admin)->get(route('vehiculos.reporte.excel', $filtros))
            ->assertRedirect(route('vehiculos.index', $filtros))
            ->assertSessionHas('error', 'No se pudo generar el reporte en Excel. Intente nuevamente o contacte al administrador.');

        Exceptions::assertReported(QueryException::class);
    }

    public function test_los_reportes_requieren_iniciar_sesion(): void
    {
        $this->get(route('vehiculos.reporte.pdf'))->assertRedirect(route('login'));
        $this->get(route('vehiculos.reporte.excel'))->assertRedirect(route('login'));
    }
}
