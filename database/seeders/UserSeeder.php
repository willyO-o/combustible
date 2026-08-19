<?php

namespace Database\Seeders;

use App\Models\EncargadoArea;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Convención de nombres: "modulo.accion" y "modulo.submodulo.accion".
     * Los permisos y roles se crean de forma idempotente (firstOrCreate/syncPermissions),
     * por lo que el seeder puede ejecutarse varias veces sin duplicar datos.
     */

    /* -----------------------------------------------------------------
     |  Grupos de permisos por módulo
     |  Mantener cada módulo en su propio array facilita agregar,
     |  quitar o reutilizar permisos al armar los roles más abajo.
     | -----------------------------------------------------------------
     */

    private function permisosDashboard(): array
    {
        return [
            'dashboard.ver',
        ];
    }

    private function permisosVales(): array
    {
        return [
            'vales.ver',
            'vales.crear',
            'vales.editar',
            'vales.eliminar',
        ];
    }

    private function permisosOperacionDiaria(): array
    {
        return [
            'operacion-diaria.ver',
            'operacion-diaria.crear',
            'operacion-diaria.editar',
            'operacion-diaria.eliminar',
            'operacion-diaria.informe',
            'operacion-diaria.reporte.pdf',
            'operacion-diaria.verificar',
        ];
    }

    private function permisosOperacionDiariaJefe(): array
    {
        return [
            'operacion-diaria.ver',
            'operacion-diaria.informe',
            'operacion-diaria.reporte.pdf',
            'operacion-diaria.verificar',
        ];
    }

    private function permisosCargasCombustible(): array
    {
        return [
            'cargas-combustible.ver',
            'cargas-combustible.registrar',
            'cargas-combustible.editar',
            'cargas-combustible.eliminar',
            'cargas-combustible.reporte',
        ];
    }

    private function permisosMantenimiento(): array
    {
        return [
            'mantenimiento.solicitudes.ver',
            'mantenimiento.solicitudes.crear',
            'mantenimiento.ordenes.ver',
            'mantenimiento.ordenes.crear',
            'mantenimiento.ordenes.editar',
            'mantenimiento.ordenes.estado.cambiar',
            'mantenimiento.ordenes.ejecucion.registrar',
        ];
    }

    private function permisosConductores(): array
    {
        return [
            'conductores.ver',
            'conductores.crear',
            'conductores.editar',
            'conductores.eliminar',
        ];
    }

    private function permisosVehiculos(): array
    {
        return [
            'vehiculos.ver',
            'vehiculos.crear',
            'vehiculos.editar',
            'vehiculos.eliminar',
        ];
    }

    private function permisosGrifos(): array
    {
        return [
            'grifos.ver',
            'grifos.crear',
            'grifos.editar',
            'grifos.eliminar',
        ];
    }

    private function permisosTiposCombustible(): array
    {
        return [
            'tipos-combustible.ver',
            'tipos-combustible.crear',
            'tipos-combustible.editar',
            'tipos-combustible.eliminar',
        ];
    }

    private function permisosTiposMantenimiento(): array
    {
        return [
            'tipos-mantenimiento.ver',
            'tipos-mantenimiento.crear',
            'tipos-mantenimiento.editar',
            'tipos-mantenimiento.eliminar',
        ];
    }

    private function permisosTiposVehiculo(): array
    {
        return [
            'tipos-vehiculo.ver',
            'tipos-vehiculo.crear',
            'tipos-vehiculo.editar',
            'tipos-vehiculo.eliminar',
        ];
    }

    private function permisosGruposVehiculo(): array
    {
        return [
            'grupos-vehiculo.ver',
            'grupos-vehiculo.crear',
            'grupos-vehiculo.editar',
            'grupos-vehiculo.eliminar',
        ];
    }

    private function permisosRepuestos(): array
    {
        return [
            'repuestos.ver',
            'repuestos.crear',
            'repuestos.editar',
            'repuestos.eliminar',
        ];
    }

    private function permisosUsuarios(): array
    {
        return [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.contrasena.cambiar',
        ];
    }

    private function permisosAreas(): array
    {
        return [
            'areas.ver',
            'areas.crear',
            'areas.editar',
            'areas.eliminar',
            'areas.encargados.asignar',
        ];
    }

    private function permisosPersonas(): array
    {
        return [
            'personas.ver',
            'personas.crear',
            'personas.editar',
            'personas.eliminar',
        ];
    }

    /**
     * Módulo de Roles y Permisos (App\Http\Controllers\RolController): sólo
     * administrador/super-admin lo usan, así que no se agrupa en ningún otro
     * bundle de permisos (no forma parte de permisosCatalogos()).
     */
    private function permisosRoles(): array
    {
        return [
            'roles.ver',
        ];
    }

    /**
     * Todos los catálogos (conductores, vehículos, grifos, tipos-*) agrupados,
     * útil porque varios roles (admin, jefe de transporte) los comparten.
     */
    private function permisosCatalogos(): array
    {
        return [
            ...$this->permisosConductores(),
            ...$this->permisosVehiculos(),
            ...$this->permisosGrifos(),
            ...$this->permisosTiposCombustible(),
            ...$this->permisosTiposMantenimiento(),
            ...$this->permisosTiposVehiculo(),
            ...$this->permisosGruposVehiculo(),
            ...$this->permisosRepuestos(),
        ];
    }

    /**
     * Operación diaria de flota: vales, combustible y mantenimiento.
     * Compartido por administrador y jefe de transporte.
     */
    private function permisosOperacionVehiculo(): array
    {
        return [
            ...$this->permisosVales(),
            ...$this->permisosCargasCombustible(),
            ...$this->permisosMantenimiento(),
            ...$this->permisosOperacionDiaria(),
        ];
    }

    /**
     * Listado completo de todos los permisos del sistema.
     * Es la única fuente de verdad usada para crear los permisos en BD.
     */
    private function todosLosPermisos(): array
    {
        return [
            ...$this->permisosDashboard(),
            ...$this->permisosOperacionVehiculo(),
            ...$this->permisosCatalogos(),
            ...$this->permisosUsuarios(),
            ...$this->permisosAreas(),
            ...$this->permisosPersonas(),
            ...$this->permisosRoles(),
        ];
    }

    /* -----------------------------------------------------------------
     |  Permisos por rol
     |  Cada método describe explícitamente qué puede hacer cada rol.
     | -----------------------------------------------------------------
     */

    private function permisosParaConductor(): array
    {
        return [
            'vales.ver',
            'cargas-combustible.ver',
            'cargas-combustible.registrar',
            'mantenimiento.solicitudes.crear',
            'mantenimiento.solicitudes.ver',
            'mantenimiento.ordenes.ver',
            'operacion-diaria.ver',
            'operacion-diaria.crear',
            'operacion-diaria.editar',
            'operacion-diaria.eliminar',
            'operacion-diaria.informe',
        ];
    }

    private function permisosParaJefeArea(): array
    {
        return [
            ...$this->permisosVales(),
            ...$this->permisosCargasCombustible(),
            ...$this->permisosMantenimiento(),
            ...$this->permisosOperacionDiariaJefe(),
            ...$this->permisosCatalogos(),
            'operacion-diaria.verificar',
        ];
    }

    /**
     * Ejecuta el trabajo de mantenimiento asignado (Paso 3): sólo ve sus
     * propias órdenes, registra la ejecución/detalle y puede marcarlas
     * EN_EJECUCION/CULMINADO. No crea ni edita órdenes, ni las verifica
     * (la verificación queda reservada al usuario que emitió cada orden).
     */
    private function permisosParaTecnicoMantenimiento(): array
    {
        return [
            'mantenimiento.ordenes.ver',
            'mantenimiento.ordenes.estado.cambiar',
            'mantenimiento.ordenes.ejecucion.registrar',
        ];
    }

    /* -----------------------------------------------------------------
     |  Helpers de creación (roles, permisos, usuarios)
     | -----------------------------------------------------------------
     */

    /**
     * Crea (o recupera) todos los permisos indicados, de forma idempotente.
     */
    private function crearPermisos(array $permisos): void
    {
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * Crea (o recupera) un rol y sincroniza su lista de permisos.
     */
    private function crearRolConPermisos(string $nombre, array $permisos): Role
    {
        $rol = Role::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]);

        $rol->syncPermissions($permisos);

        return $rol;
    }

    /**
     * Crea (o recupera) un usuario y le asigna un rol.
     *
     * @param  array  $datosBusqueda  Campos usados para localizar el registro (p.ej. email).
     * @param  array  $datosCreacion  Campos usados solo al crear el registro.
     */
    private function crearUsuarioConRol(array $datosBusqueda, array $datosCreacion, Role $rol): User
    {
        $usuario = User::firstOrCreate($datosBusqueda, $datosCreacion);

        $usuario->assignRole($rol);

        return $usuario;
    }

    /* -----------------------------------------------------------------
     |  Run
     | -----------------------------------------------------------------
     */

    public function run(): void
    {
        // Mantiene sincronizada la caché de Spatie al ejecutar el seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->crearPermisos($this->todosLosPermisos());

        $this->crearRolAdministrador();
        $this->crearRolSuperAdmin();
        $this->crearRolConductor();
        $this->crearRolJefeArea();
        $this->crearRolTecnicoMantenimiento();
    }

    /* -----------------------------------------------------------------
     |  Roles + usuarios de ejemplo
     |  Un método por rol: crea el rol con sus permisos y sus usuarios
     |  asociados. Mantenerlos separados facilita añadir o quitar roles
     |  sin tocar el resto del seeder.
     | -----------------------------------------------------------------
     */

    private function crearRolAdministrador(): void
    {
        $rol = $this->crearRolConPermisos('administrador', $this->todosLosPermisos());

        $this->crearUsuarioConRol(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('admin123'),
            ],
            $rol
        );
    }

    private function crearRolSuperAdmin(): void
    {
        // El super-admin recibe automáticamente todos los permisos existentes.
        $rol = $this->crearRolConPermisos('super-admin', $this->todosLosPermisos());

        $this->crearUsuarioConRol(
            ['email' => 'super-admin@gmail.com'],
            [
                'name' => 'Super Admin User',
                'password' => bcrypt('superadmin123'),
            ],
            $rol
        );
    }

    private function crearRolConductor(): void
    {
        $rol = $this->crearRolConPermisos('conductor', $this->permisosParaConductor());

        // Usuarios de ejemplo, cada uno vinculado a su id_persona correspondiente.
        $personas = [
            ['email' => 'conductor@gmail.com', 'name' => 'Conductor User', 'password' => 'conductor123', 'id_persona' => 1],
            ['email' => 'conductor2@gmail.com', 'name' => 'Conductor User 2', 'password' => 'conductor123', 'id_persona' => 2],
            ['email' => 'conductor3@gmail.com', 'name' => 'Conductor User 3', 'password' => 'conductor123', 'id_persona' => 3],
        ];

        foreach ($personas as $persona) {
            $this->crearUsuarioConRol(
                ['email' => $persona['email']],
                [
                    'name' => $persona['name'],
                    'password' => bcrypt($persona['password']),
                    'id_persona' => $persona['id_persona'],
                ],
                $rol
            );
        }
    }

    private function crearRolJefeArea(): void
    {

        $personas = [
            ['email' => 'jefearea1@gmail.com', 'name' => 'Jefe area 1', 'password' => 'jefearea123', 'id_persona' => 5],
            ['email' => 'jefearea2@gmail.com', 'name' => 'Jefe area 2', 'password' => 'jefearea123', 'id_persona' => 6],
            ['email' => 'jefearea3@gmail.com', 'name' => 'Jefe area 3', 'password' => 'jefearea123', 'id_persona' => 7],
        ];

        $rol = $this->crearRolConPermisos('jefe-area', $this->permisosParaJefeArea());

        $idArea = 1; // Asignar áreas 1, 2 y 3 a los jefes de área 1
        foreach ($personas as $persona) {
            $us = $this->crearUsuarioConRol(
                ['email' => $persona['email']],
                [
                    'name' => $persona['name'],
                    'password' => bcrypt($persona['password']),
                    'id_persona' => $persona['id_persona'],
                ],
                $rol
            );

            // Crear un registro en la tabla encargado_area para cada jefe de área
            EncargadoArea::updateOrCreate(
                ['id_persona' => $persona['id_persona']],
                [
                    'id_area' => $idArea, // Asignar áreas 1, 2 y 3 a los jefes de área 1
                    'fecha_inicio' => now(),
                    'tipo_encargo' => 'TITULAR',
                    'estado_encargo' => 'ACTIVO',
                ]
            );

            $idArea++; // Incrementar el área para el siguiente jefe de área
        }
    }

    private function crearRolTecnicoMantenimiento(): void
    {
        $rol = $this->crearRolConPermisos('tecnico-mantenimiento', $this->permisosParaTecnicoMantenimiento());

        $tecnicos = [
            ['email' => 'tecnico1@gmail.com', 'name' => 'Técnico Mantenimiento 1', 'password' => 'tecnico123'],
            ['email' => 'tecnico2@gmail.com', 'name' => 'Técnico Mantenimiento 2', 'password' => 'tecnico123'],
        ];

        foreach ($tecnicos as $tecnico) {
            $this->crearUsuarioConRol(
                ['email' => $tecnico['email']],
                [
                    'name' => $tecnico['name'],
                    'password' => bcrypt($tecnico['password']),
                ],
                $rol
            );
        }
    }
}
