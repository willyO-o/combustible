<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RutaRegistrada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\ConPermisosReales;
use Tests\TestCase;

/**
 * Cada ruta web autenticada exige el mismo permiso con el que la interfaz
 * muestra la acción (routes/web.php). Los roles son los reales del seeder.
 */
class PermisosRutasWebTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    /**
     * Rutas autenticadas sin permiso propio: sólo lo de cada usuario.
     *
     * @var list<string>
     */
    private const RUTAS_SIN_PERMISO = [
        'dashboard',
        'profile.edit',
        'profile.update',
        'password.update',
        'logout',
        'notificaciones.marcar-leida',
        'notificaciones.marcar-todas-leidas',
    ];

    public function test_toda_ruta_web_autenticada_declara_un_permiso(): void
    {
        $sinPermiso = $this->rutasWebAutenticadas()
            ->reject(fn (RutaRegistrada $ruta) => in_array($ruta->getName(), self::RUTAS_SIN_PERMISO, true))
            ->reject(fn (RutaRegistrada $ruta) => $this->permisosDe($ruta) !== [])
            ->map(fn (RutaRegistrada $ruta) => implode('|', $ruta->methods()).' '.($ruta->getName() ?? $ruta->uri()))
            ->values()
            ->all();

        $this->assertSame([], $sinPermiso, 'Rutas autenticadas sin permiso: declararlo en routes/web.php.');
    }

    public function test_los_permisos_de_las_rutas_existen_en_el_sistema(): void
    {
        $existentes = Permission::pluck('name')->all();

        $inexistentes = $this->rutasWebAutenticadas()
            ->flatMap(fn (RutaRegistrada $ruta) => $this->permisosDe($ruta))
            ->unique()
            ->reject(fn (string $permiso) => in_array($permiso, $existentes, true))
            ->values()
            ->all();

        $this->assertSame([], $inexistentes, 'Permisos usados en routes/web.php que no crea UserSeeder.');
    }

    public function test_un_usuario_sin_rol_solo_accede_a_lo_propio(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk();
        $this->actingAs($usuario)->get(route('profile.edit'))->assertOk();
        $this->actingAs($usuario)->get(route('vales.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_super_admin_accede_aunque_su_rol_no_tenga_permisos_asignados(): void
    {
        Role::findByName('super-admin', 'web')->syncPermissions([]);
        $superAdmin = $this->usuarioConRol('super-admin');

        $this->actingAs($superAdmin)->get(route('usuarios.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('tipos-combustible.index'))->assertOk();
    }

    public function test_conductor_usa_sus_modulos_y_no_los_de_gestion(): void
    {
        $conductor = $this->usuarioConRol('conductor');

        $this->actingAs($conductor)->get(route('vales.index'))->assertOk();
        $this->actingAs($conductor)->get(route('cargas.index'))->assertOk();
        $this->actingAs($conductor)->get(route('control-cargas.index'))->assertOk();

        $this->actingAs($conductor)->get(route('vales.create'))->assertForbidden();
        $this->actingAs($conductor)->get(route('tipos-combustible.index'))->assertForbidden();
        $this->actingAs($conductor)->get(route('vehiculos.index'))->assertForbidden();
        $this->actingAs($conductor)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($conductor)->get(route('cargas-combustible.reporte.index'))->assertForbidden();
    }

    public function test_conductor_no_puede_verificar_una_operacion_diaria(): void
    {
        $operacion = OperacionDiaria::create([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'id_area' => Area::factory()->create()->id,
            'turno' => 'DIA',
            'fecha_inicio' => now()->subHours(4),
            'fecha_fin' => now(),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'estado' => 'PENDIENTE',
        ]);

        $this->actingAs($this->usuarioConRol('conductor'))
            ->post(route('operacion-diaria.verificar'), ['id_operacion' => $operacion->id])
            ->assertForbidden();

        $this->assertNotSame('VERIFICADO', $operacion->fresh()->estado);
    }

    public function test_conductor_puede_dar_de_alta_material_y_vehiculo_externo_desde_control_de_cargas(): void
    {
        $conductor = $this->usuarioConRol('conductor');

        // Alta rápida dentro del modal "Registrar Viaje" (ControlCargas/Show.vue).
        $this->actingAs($conductor)
            ->postJson(route('materiales.store'), ['material' => 'Ripio fino'])
            ->assertSuccessful();

        // Alta rápida dentro del formulario del flete (ControlCargas/Form.vue).
        $this->actingAs($conductor)
            ->postJson(route('vehiculos-externos.store'), ['nro_placa' => 'EXT-123'])
            ->assertSuccessful();

        $this->assertDatabaseHas('material', ['material' => 'Ripio fino']);
        $this->assertDatabaseHas('vehiculo_externo', ['nro_placa' => 'EXT-123']);

        // Pero no administra esos catálogos.
        $this->actingAs($conductor)->get(route('materiales.index'))->assertForbidden();
        $this->actingAs($conductor)->get(route('vehiculos-externos.index'))->assertForbidden();
    }

    public function test_conductor_ve_la_ficha_solo_de_sus_vehiculos(): void
    {
        $persona = Persona::factory()->create();
        Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $conductor = $this->usuarioConRol('conductor', ['id_persona' => $persona->id]);

        $suVehiculo = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $suVehiculo->id,
            'id_conductor' => $persona->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        $otroVehiculo = Vehiculo::factory()->create();

        // Enlace de las alertas de mantenimiento del dashboard.
        $this->actingAs($conductor)->get(route('vehiculos.show', $suVehiculo))->assertOk();
        $this->actingAs($conductor)->get(route('vehiculos.show', $otroVehiculo))->assertForbidden();
    }

    public function test_tecnico_ve_solo_la_solicitud_de_sus_ordenes(): void
    {
        $tecnico = $this->usuarioConRol('tecnico-mantenimiento');
        $otroTecnico = $this->usuarioConRol('tecnico-mantenimiento');

        $suSolicitud = $this->crearSolicitudConOrdenPara($tecnico);
        $solicitudAjena = $this->crearSolicitudConOrdenPara($otroTecnico);

        // Enlace "solicitud de origen" en OrdenTrabajo/Show.vue.
        $this->actingAs($tecnico)->get(route('mantenimiento.solicitudes.show', $suSolicitud))->assertOk();
        $this->actingAs($tecnico)->get(route('mantenimiento.solicitudes.show', $solicitudAjena))->assertForbidden();

        $this->actingAs($tecnico)->get(route('mantenimiento.solicitudes.index'))->assertForbidden();
        $this->actingAs($tecnico)->get(route('operacion-diaria.create'))->assertForbidden();
        $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.index'))->assertOk();
    }

    public function test_jefe_de_area_gestiona_catalogos_pero_no_la_administracion(): void
    {
        $jefeArea = $this->usuarioConRol('jefe-area');

        $this->actingAs($jefeArea)->get(route('tipos-combustible.index'))->assertOk();
        $this->actingAs($jefeArea)->get(route('vehiculos.index'))->assertOk();
        $this->actingAs($jefeArea)->get(route('cargas-combustible.reporte.index'))->assertOk();

        $this->actingAs($jefeArea)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($jefeArea)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($jefeArea)->get(route('personas.index'))->assertForbidden();
        $this->actingAs($jefeArea)->get(route('parametros-empresa.edit'))->assertForbidden();
        $this->actingAs($jefeArea)->get(route('auditoria.index'))->assertForbidden();
    }

    public function test_quien_emite_vales_puede_dar_de_alta_una_estacion_desde_el_formulario(): void
    {
        // Rol creado desde el módulo de Roles con sólo emisión de vales.
        $emisor = User::factory()->create();
        $emisor->assignRole(Role::create(['name' => 'emisor-vales', 'guard_name' => 'web'])->givePermissionTo('vales.crear'));

        $this->actingAs($emisor)
            ->postJson(route('grifos.store'), [
                'razon_social' => 'Estación Norte',
                'nit' => '9988776',
                'estado_grifo' => 'ACTIVO',
            ])
            ->assertSuccessful();

        $this->actingAs($emisor)->get(route('grifos.index'))->assertForbidden();
    }

    /**
     * @return Collection<int, RutaRegistrada>
     */
    private function rutasWebAutenticadas()
    {
        return collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RutaRegistrada $ruta) => str_starts_with($ruta->uri(), 'api/'))
            ->filter(fn (RutaRegistrada $ruta) => in_array('auth', $ruta->gatherMiddleware(), true));
    }

    /**
     * @return list<string>
     */
    private function permisosDe(RutaRegistrada $ruta): array
    {
        return collect($ruta->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
            ->flatMap(fn (string $middleware) => explode('|', explode(',', substr($middleware, strlen('permission:')))[0]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function usuarioConRol(string $rol, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function crearSolicitudConOrdenPara(User $tecnico): SolicitudMantenimiento
    {
        // La numeración de la orden lee los parámetros de la empresa.
        ParametrosEmpresa::firstOrCreate(['nit_empresa' => '123456789'], [
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);

        $vehiculo = Vehiculo::factory()->create();

        $solicitud = SolicitudMantenimiento::create([
            'id_vehiculo' => $vehiculo->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'fecha_solicitud' => now(),
            'estado' => 'APROBADA',
        ]);

        // OrdenTrabajo::boot() toma como emisor al usuario autenticado.
        $this->actingAs($this->usuarioConRol('administrador'));
        OrdenTrabajo::create([
            'id_vehiculo' => $vehiculo->id,
            'id_usuario_ejecuta' => $tecnico->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        return $solicitud;
    }
}
