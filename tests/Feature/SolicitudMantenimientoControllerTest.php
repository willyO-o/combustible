<?php

namespace Tests\Feature;

use App\Libraries\Reportes;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudMantenimientoControllerTest extends TestCase
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

        // SolicitudMantenimiento::calcularGestion()/getNroAttribute() leen este parámetro.
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

    private function crearUsuarioConductor(): User
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        return $user;
    }

    private function crearJefeDeArea(Area $area): User
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $persona = Persona::factory()->create();
        EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole('jefe-area');

        return $user;
    }

    private function asignarVehiculoAArea(Vehiculo $vehiculo, Area $area): void
    {
        VehiculoArea::create([
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    private function asignarConductorAVehiculo(Conductor $conductor, Vehiculo $vehiculo): Asignacion
    {
        return Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_index_lista_las_solicitudes(): void
    {
        $this->crearSolicitud();
        $this->crearSolicitud();

        $response = $this->get(route('mantenimiento.solicitudes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Index')
            ->has('solicitudes.data', 2)
        );
    }

    public function test_show_muestra_el_detalle_sin_orden_de_trabajo(): void
    {
        $solicitud = $this->crearSolicitud();

        $response = $this->get(route('mantenimiento.solicitudes.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Show')
            ->where('solicitud.id', $solicitud->id)
            ->where('solicitud.orden_trabajo', null)
        );
    }

    public function test_show_incluye_la_orden_de_trabajo_generada(): void
    {
        $solicitud = $this->crearSolicitud();
        $orden = OrdenTrabajo::create([
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_vehiculo' => $solicitud->id_vehiculo,
            'id_usuario_ejecuta' => User::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        $response = $this->get(route('mantenimiento.solicitudes.show', $solicitud));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Show')
            ->where('solicitud.orden_trabajo.id', $orden->id)
        );
    }

    /**
     * Un administrador (o super-admin) ahora también puede registrar una
     * solicitud: ve todos los vehículos activos, sin restricción, y debe
     * elegir el conductor desde el combo (mostrarSelectorConductor=true).
     */
    public function test_create_lista_todos_los_vehiculos_activos_para_administrador(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculo->id)
        );
    }

    public function test_administrador_puede_registrar_una_solicitud_eligiendo_vehiculo_y_conductor(): void
    {
        $conductorUser = $this->crearUsuarioConductor();
        $conductor = Conductor::find($conductorUser->id_persona);
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarConductorAVehiculo($conductor, $vehiculo);

        $response = $this->post(route('mantenimiento.solicitudes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertRedirect(route('mantenimiento.solicitudes.index'));
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_usuario_registra' => $this->admin->id,
        ]);
    }

    public function test_store_exige_un_conductor_asignado_al_vehiculo_para_administrador(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        // Conductor registrado pero SIN asignación a este vehículo.
        $conductorUser = $this->crearUsuarioConductor();
        $conductor = Conductor::find($conductorUser->id_persona);

        $response = $this->post(route('mantenimiento.solicitudes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertSessionHasErrors('id_conductor');
        $this->assertDatabaseCount('solicitud_mantenimiento', 0);
    }

    public function test_create_es_accesible_para_un_usuario_conductor(): void
    {
        $conductor = $this->crearUsuarioConductor();

        $response = $this->actingAs($conductor)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
        );
    }

    /**
     * Un conductor "puro" no elige conductor (siempre es él mismo, ver
     * esConductorFinal()): el combo va oculto y sólo ve sus propios
     * vehículos asignados.
     */
    public function test_create_solo_lista_los_vehiculos_asignados_al_conductor_y_oculta_el_selector(): void
    {
        $conductorUser = $this->crearUsuarioConductor();
        $conductor = Conductor::find($conductorUser->id_persona);
        $vehiculoAsignado = Vehiculo::factory()->create();
        $this->asignarConductorAVehiculo($conductor, $vehiculoAsignado);
        Vehiculo::factory()->create(); // otro vehículo, no asignado a este conductor

        $response = $this->actingAs($conductorUser)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', false)
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculoAsignado->id)
        );
    }

    /**
     * Un conductor no puede suplantar a otro: aunque envíe un id_conductor
     * ajeno, la solicitud se registra siempre a su propio nombre (ver
     * CreateSolicitudMantenimientoAction::execute()).
     */
    public function test_conductor_no_puede_suplantar_a_otro_conductor_al_registrar(): void
    {
        $conductorUser = $this->crearUsuarioConductor();
        $conductor = Conductor::find($conductorUser->id_persona);
        $vehiculo = Vehiculo::factory()->create();
        $this->asignarConductorAVehiculo($conductor, $vehiculo);

        $otroConductorUser = $this->crearUsuarioConductor();

        $response = $this->actingAs($conductorUser)->post(route('mantenimiento.solicitudes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $otroConductorUser->id_persona,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertRedirect(route('mantenimiento.solicitudes.index'));
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
        ]);
    }

    /**
     * jefe-area sólo ve los vehículos de las áreas que tiene a cargo, y debe
     * elegir el conductor (mostrarSelectorConductor=true).
     */
    public function test_create_lista_solo_los_vehiculos_del_area_del_jefe(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);
        Vehiculo::factory()->create(); // de otra área / sin área

        $response = $this->actingAs($jefe)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculoDelArea->id)
        );
    }

    /**
     * Un usuario con ambos roles (conductor y jefe-area) es tratado como
     * jefe-area: ve los vehículos de su área (no sólo el suyo propio) y debe
     * elegir el conductor, en vez de operar automáticamente sobre sí mismo.
     */
    public function test_jefe_area_prevalece_sobre_conductor_cuando_el_usuario_tiene_ambos_roles(): void
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $area = Area::factory()->create();
        $jefeYConductor = $this->crearUsuarioConductor();
        $jefeYConductor->assignRole('jefe-area');
        EncargadoArea::create([
            'id_persona' => $jefeYConductor->id_persona,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => now(),
            'estado_encargo' => 'ACTIVO',
        ]);

        // Vehículo del área a su cargo (NO asignado a él como conductor).
        $vehiculoDelArea = Vehiculo::factory()->create();
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);

        // Vehículo asignado a él como conductor, pero fuera de su área: no
        // debería aparecer (jefe-area prevalece, no se usa el criterio de
        // conductor).
        $vehiculoPropio = Vehiculo::factory()->create();
        $this->asignarConductorAVehiculo(Conductor::find($jefeYConductor->id_persona), $vehiculoPropio);

        $response = $this->actingAs($jefeYConductor)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculos', 1)
            ->where('vehiculos.0.id', $vehiculoDelArea->id)
        );
    }

    /**
     * SolicitudMantenimientoController::imprimir() llama a
     * Reportes::generarSolicitudMantenimiento() con el modo por defecto ('I',
     * salida directa + exit;), lo que no se puede probar vía HTTP dentro de
     * PHPUnit (el exit; terminaría el proceso de test). Se prueba llamando a
     * la librería directamente con modo 'S' (devuelve el PDF como string),
     * igual que hace la API para el resto de reportes.
     */
    public function test_generar_solicitud_mantenimiento_produce_un_pdf_valido(): void
    {
        $solicitud = $this->crearSolicitud();

        $pdf = (new Reportes)->generarSolicitudMantenimiento($solicitud, 'S');

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
