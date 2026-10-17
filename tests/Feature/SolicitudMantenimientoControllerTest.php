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
use Tests\ConPermisosReales;
use Tests\TestCase;

class SolicitudMantenimientoControllerTest extends TestCase
{
    use ConPermisosReales;
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

    /**
     * A pedido del usuario (2026-10-17) quien elige vehículo y conductor puede
     * elegir cualquier operario ACTIVO, aunque no esté asignado al vehículo.
     */
    public function test_administrador_puede_elegir_un_conductor_activo_no_asignado_al_vehiculo(): void
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

        $response->assertSessionHasNoErrors()->assertRedirect(route('mantenimiento.solicitudes.index'));
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
        ]);
    }

    public function test_store_rechaza_un_conductor_inactivo_o_sin_conductor(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductorUser = $this->crearUsuarioConductor();
        $conductor = Conductor::find($conductorUser->id_persona);
        $conductor->update(['estado_conductor' => 'INACTIVO']);

        $datos = [
            'id_vehiculo' => $vehiculo->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'kilometraje_actual' => 1000,
        ];

        $this->post(route('mantenimiento.solicitudes.store'), $datos + ['id_conductor' => $conductor->id])
            ->assertSessionHasErrors('id_conductor');
        $this->post(route('mantenimiento.solicitudes.store'), $datos)
            ->assertSessionHasErrors('id_conductor');

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
     * jefe-area ve TODOS los vehículos activos y operarios precargados: los
     * de sus áreas primero (meta.propio), y debe elegir el conductor
     * (mostrarSelectorConductor=true).
     */
    public function test_create_lista_todos_los_vehiculos_con_los_del_area_del_jefe_primero(): void
    {
        $area = Area::factory()->create();
        $jefe = $this->crearJefeDeArea($area);

        $vehiculoDeOtraArea = Vehiculo::factory()->create(['codigo' => 'AAA-001']);
        $vehiculoDelArea = Vehiculo::factory()->create(['codigo' => 'ZZZ-001']);
        $this->asignarVehiculoAArea($vehiculoDelArea, $area);
        $operario = Conductor::find($this->crearUsuarioConductor()->id_persona);

        $response = $this->actingAs($jefe)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculos', 2)
            ->where('vehiculos.0.id', $vehiculoDelArea->id)
            ->where('vehiculos.0.meta.propio', true)
            ->where('vehiculos.1.id', $vehiculoDeOtraArea->id)
            ->where('vehiculos.1.meta.propio', false)
            ->where('operarios', fn ($operarios) => collect($operarios)->pluck('id')->contains($operario->id))
        );
    }

    /**
     * Un usuario con ambos roles (conductor y jefe-area) es tratado como
     * jefe-area: ve todos los vehículos (no sólo el suyo propio), con los de
     * su área y el suyo primero, y debe elegir el conductor, en vez de operar
     * automáticamente sobre sí mismo.
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

        // Vehículo asignado a él como conductor, pero fuera de su área:
        // también es "propio".
        $vehiculoPropio = Vehiculo::factory()->create();
        $this->asignarConductorAVehiculo(Conductor::find($jefeYConductor->id_persona), $vehiculoPropio);

        // Vehículo ajeno: aparece igual (selección libre), al final.
        $vehiculoAjeno = Vehiculo::factory()->create();

        $response = $this->actingAs($jefeYConductor)->get(route('mantenimiento.solicitudes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SolicitudMantenimiento/Create')
            ->where('mostrarSelectorConductor', true)
            ->has('vehiculos', 3)
            ->where('vehiculos', fn ($vehiculos) => collect($vehiculos)->take(2)->pluck('id')->sort()->values()->all()
                    === collect([$vehiculoDelArea->id, $vehiculoPropio->id])->sort()->values()->all()
                && collect($vehiculos)->last()['id'] === $vehiculoAjeno->id
                && collect($vehiculos)->last()['meta']['propio'] === false)
            ->where('operarios', fn ($operarios) => count($operarios) > 0)
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

    /**
     * Registrar solicitudes depende del permiso, no del rol: un rol
     * personalizado con mantenimiento.solicitudes.crear (que no es conductor)
     * elige libremente vehículo y operario; sin el permiso, no puede.
     */
    public function test_un_rol_personalizado_con_el_permiso_registra_solicitudes_y_sin_el_permiso_no(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole(Role::create(['name' => 'supervisor-flota', 'guard_name' => 'web'])
            ->givePermissionTo(['mantenimiento.solicitudes.ver', 'mantenimiento.solicitudes.crear']));
        $sinPermiso = User::factory()->create();
        $sinPermiso->assignRole(Role::create(['name' => 'consulta', 'guard_name' => 'web'])
            ->givePermissionTo('mantenimiento.solicitudes.ver'));

        $vehiculo = Vehiculo::factory()->create();
        $operario = Conductor::find($this->crearUsuarioConductor()->id_persona);
        $datos = [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $operario->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'descripcion_problema' => 'Falla en frenos',
            'kilometraje_actual' => 1000,
        ];

        $this->actingAs($supervisor)->get(route('mantenimiento.solicitudes.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('mostrarSelectorConductor', true));
        $this->actingAs($supervisor)->post(route('mantenimiento.solicitudes.store'), $datos)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mantenimiento.solicitudes.index'));

        $this->actingAs($sinPermiso)->post(route('mantenimiento.solicitudes.store'), $datos)->assertForbidden();

        $this->assertDatabaseCount('solicitud_mantenimiento', 1);
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_conductor' => $operario->id,
            'id_usuario_registra' => $supervisor->id,
        ]);
    }
}
