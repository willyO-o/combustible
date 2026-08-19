<?php

/**
 * Parametrización del módulo de Roles y Permisos (App\Http\Controllers\RolController).
 *
 * Este archivo NO crea permisos en base de datos — eso lo sigue haciendo
 * database/seeders/UserSeeder.php, que continúa siendo la única fuente de
 * verdad sobre qué permisos existen y qué rol los recibe por defecto. Aquí
 * solo se define cómo se presentan (agrupados, con etiqueta legible) y qué
 * roles reciben un trato especial en el módulo de administración de roles.
 *
 * Si un permiso existe en base de datos pero no está catalogado aquí abajo,
 * el módulo lo sigue mostrando (agrupado en "Otros permisos"): este archivo
 * nunca puede ocultar un permiso real, solo mejora su presentación.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Roles ocultos
    |--------------------------------------------------------------------------
    | Nunca se listan ni pueden asignarse a un usuario desde ningún módulo.
    | super-admin tiene acceso total vía el bypass de Gate::before en
    | AppServiceProvider, no a través de permisos asignados explícitamente.
    */
    'roles_ocultos' => [
        'super-admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles protegidos
    |--------------------------------------------------------------------------
    | Sus permisos base (los que UserSeeder les asigna) no se pueden quitar
    | desde el módulo de Roles y Permisos: sólo se les pueden agregar
    | permisos adicionales a los que ya tienen.
    */
    'roles_protegidos' => [
        'jefe-area',
        'conductor',
        'tecnico-mantenimiento',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de permisos
    |--------------------------------------------------------------------------
    | Agrupados por módulo, con etiqueta legible, en el mismo orden en que
    | aparecen los módulos de la aplicación. Debe mantenerse en paridad con
    | los permisos que crea UserSeeder::todosLosPermisos().
    */
    'modulos' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'permisos' => [
                'dashboard.ver' => 'Ver',
            ],
        ],

        'vales' => [
            'label' => 'Vales',
            'permisos' => [
                'vales.ver' => 'Ver',
                'vales.crear' => 'Crear',
                'vales.editar' => 'Editar',
                'vales.eliminar' => 'Eliminar',
            ],
        ],

        'cargas-combustible' => [
            'label' => 'Cargas de Combustible',
            'permisos' => [
                'cargas-combustible.ver' => 'Ver',
                'cargas-combustible.registrar' => 'Registrar',
                'cargas-combustible.editar' => 'Editar',
                'cargas-combustible.eliminar' => 'Eliminar',
                'cargas-combustible.reporte' => 'Ver reporte',
            ],
        ],

        'operacion-diaria' => [
            'label' => 'Operación Diaria',
            'permisos' => [
                'operacion-diaria.ver' => 'Ver',
                'operacion-diaria.crear' => 'Registrar',
                'operacion-diaria.editar' => 'Editar',
                'operacion-diaria.eliminar' => 'Eliminar',
                'operacion-diaria.informe' => 'Ver informe',
                'operacion-diaria.reporte.pdf' => 'Generar reporte PDF',
                'operacion-diaria.verificar' => 'Verificar',
            ],
        ],

        'mantenimiento' => [
            'label' => 'Mantenimiento',
            'permisos' => [
                'mantenimiento.solicitudes.ver' => 'Ver solicitudes',
                'mantenimiento.solicitudes.crear' => 'Crear solicitudes',
                'mantenimiento.ordenes.ver' => 'Ver órdenes de trabajo',
                'mantenimiento.ordenes.crear' => 'Emitir órdenes de trabajo',
                'mantenimiento.ordenes.editar' => 'Editar órdenes de trabajo',
                'mantenimiento.ordenes.estado.cambiar' => 'Cambiar estado de la orden',
                'mantenimiento.ordenes.ejecucion.registrar' => 'Registrar ejecución',
            ],
        ],

        'conductores' => [
            'label' => 'Conductores',
            'permisos' => [
                'conductores.ver' => 'Ver',
                'conductores.crear' => 'Crear',
                'conductores.editar' => 'Editar',
                'conductores.eliminar' => 'Eliminar',
            ],
        ],

        'vehiculos' => [
            'label' => 'Vehículos',
            'permisos' => [
                'vehiculos.ver' => 'Ver',
                'vehiculos.crear' => 'Crear',
                'vehiculos.editar' => 'Editar',
                'vehiculos.eliminar' => 'Eliminar',
            ],
        ],

        'grifos' => [
            'label' => 'Surtidores',
            'permisos' => [
                'grifos.ver' => 'Ver',
                'grifos.crear' => 'Crear',
                'grifos.editar' => 'Editar',
                'grifos.eliminar' => 'Eliminar',
            ],
        ],

        'tipos-combustible' => [
            'label' => 'Tipos de Combustible',
            'permisos' => [
                'tipos-combustible.ver' => 'Ver',
                'tipos-combustible.crear' => 'Crear',
                'tipos-combustible.editar' => 'Editar',
                'tipos-combustible.eliminar' => 'Eliminar',
            ],
        ],

        'tipos-mantenimiento' => [
            'label' => 'Tipos de Mantenimiento',
            'permisos' => [
                'tipos-mantenimiento.ver' => 'Ver',
                'tipos-mantenimiento.crear' => 'Crear',
                'tipos-mantenimiento.editar' => 'Editar',
                'tipos-mantenimiento.eliminar' => 'Eliminar',
            ],
        ],

        'tipos-vehiculo' => [
            'label' => 'Tipos de Vehículo',
            'permisos' => [
                'tipos-vehiculo.ver' => 'Ver',
                'tipos-vehiculo.crear' => 'Crear',
                'tipos-vehiculo.editar' => 'Editar',
                'tipos-vehiculo.eliminar' => 'Eliminar',
            ],
        ],

        'grupos-vehiculo' => [
            'label' => 'Grupos de Vehículo',
            'permisos' => [
                'grupos-vehiculo.ver' => 'Ver',
                'grupos-vehiculo.crear' => 'Crear',
                'grupos-vehiculo.editar' => 'Editar',
                'grupos-vehiculo.eliminar' => 'Eliminar',
            ],
        ],

        'repuestos' => [
            'label' => 'Repuestos',
            'permisos' => [
                'repuestos.ver' => 'Ver',
                'repuestos.crear' => 'Crear',
                'repuestos.editar' => 'Editar',
                'repuestos.eliminar' => 'Eliminar',
            ],
        ],

        'usuarios' => [
            'label' => 'Usuarios',
            'permisos' => [
                'usuarios.ver' => 'Ver',
                'usuarios.crear' => 'Crear',
                'usuarios.editar' => 'Editar',
                'usuarios.eliminar' => 'Eliminar',
                'usuarios.contrasena.cambiar' => 'Cambiar contraseña',
            ],
        ],

        'areas' => [
            'label' => 'Áreas',
            'permisos' => [
                'areas.ver' => 'Ver',
                'areas.crear' => 'Crear',
                'areas.editar' => 'Editar',
                'areas.eliminar' => 'Eliminar',
                'areas.encargados.asignar' => 'Asignar encargados',
            ],
        ],

        'personas' => [
            'label' => 'Personas',
            'permisos' => [
                'personas.ver' => 'Ver',
                'personas.crear' => 'Crear',
                'personas.editar' => 'Editar',
                'personas.eliminar' => 'Eliminar',
            ],
        ],

        'roles' => [
            'label' => 'Roles y Permisos',
            'permisos' => [
                'roles.ver' => 'Ver / administrar',
            ],
        ],

    ],

];
