<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\CargaMaterialController;
use App\Http\Controllers\CargasCombustibleReportController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ControlCargasReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrifoController;
use App\Http\Controllers\GrupoVehiculoController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OperacionDiariaController;
use App\Http\Controllers\OperacionDiariaReportController;
use App\Http\Controllers\OperarioReportController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\ParametrosEmpresaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepuestoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SolicitudMantenimientoController;
use App\Http\Controllers\TipoCombustibleController;
use App\Http\Controllers\TipoMantenimientoController;
use App\Http\Controllers\TipoVehiculoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ValeController;
use App\Http\Controllers\ValePublicoController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoExternoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// ── Documentación de la API (Swagger UI) ────────────────────────────────────
// Sirve una página estática con Swagger UI apuntando al archivo público
// public/docs/openapi.yaml, que se mantiene desacoplado de Laravel: el
// contenido de la documentación se edita ahí, no en anotaciones PHP.
//
// Deshabilitada por defecto: sólo responde cuando API_DOCS_ENABLED=true
// (config('app.api_docs_enabled')); en caso contrario devuelve 404. La
// especificación también se sirve por aquí (y no como archivo estático) porque
// public/docs/ está bloqueada en public/.htaccess.
Route::get('/api/documentation', function () {
    abort_unless(config('app.api_docs_enabled'), 404);

    return view('docs.swagger');
})->name('api.documentation');

Route::get('/api/documentation/openapi.yaml', function () {
    abort_unless(config('app.api_docs_enabled'), 404);

    return response()->file(public_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]);
})->name('api.documentation.spec');

// ── Verificación pública de vales (QR) ──────────────────────────────────────
// Sin autenticación: la URL sólo expone md5(id), nunca el id real. Pensada
// para que el código QR impreso en el vale apunte aquí y cualquiera pueda
// verificar su estado (pendiente, usado o anulado) sin acceder al sistema.
Route::get('/vale/verificar/{hash}', [ValePublicoController::class, 'show'])->name('vales.publico');

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Permisos por ruta
|--------------------------------------------------------------------------
|
| Cada ruta exige el MISMO permiso con el que la interfaz muestra la acción
| (v-can en las páginas, `permission` del menú en Data/sidebar/nav.ts): la
| directiva sólo oculta botones, la protección real es ésta. "a|b" = basta
| con uno (rutas usadas desde más de un módulo, p.ej. un alta rápida dentro
| de otro formulario). super-admin pasa siempre (Gate::before).
|
| Sin permiso propio, sólo lo de cada usuario: dashboard (cada widget ya
| exige el suyo en DashboardController), perfil, contraseña propia,
| notificaciones y logout. tests/Feature/PermisosRutasWebTest.php falla si
| se agrega una ruta autenticada sin permiso: decláralo aquí al crearla.
|
| Los controladores conservan sus verificaciones finas (dueño del registro,
| estado, rol de jefe-area, etc.): esto es la primera barrera, no la única.
|
*/
Route::middleware('auth')->group(function () {
    // No existe ruta de eliminar cuenta: un usuario no puede borrarse a sí
    // mismo desde su perfil (solo un administrador puede hacerlo, y de
    // hecho ni siquiera el módulo de Usuarios expone esa acción).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Exportación de los listados (mismos filtros que su index). Antes de
    // cada resource para que "reporte" nunca se interprete como {modelo}.
    Route::get('conductores/reporte/pdf', [ConductorController::class, 'reportePdf'])->name('conductores.reporte.pdf')
        ->middleware('permission:conductores.reporte.pdf');
    Route::get('conductores/reporte/excel', [ConductorController::class, 'reporteExcel'])->name('conductores.reporte.excel')
        ->middleware('permission:conductores.reporte.excel');
    Route::get('personas/reporte/pdf', [PersonaController::class, 'reportePdf'])->name('personas.reporte.pdf')
        ->middleware('permission:personas.reporte.pdf');
    Route::get('personas/reporte/excel', [PersonaController::class, 'reporteExcel'])->name('personas.reporte.excel')
        ->middleware('permission:personas.reporte.excel');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor'])
        ->middlewareFor(['index', 'show'], 'permission:conductores.ver')
        ->middlewareFor(['create', 'store'], 'permission:conductores.crear')
        ->middlewareFor(['edit', 'update'], 'permission:conductores.editar')
        ->middlewareFor('destroy', 'permission:conductores.eliminar');
    Route::post('conductores/{conductor}/asignaciones', [ConductorController::class, 'asignarVehiculo'])->name('conductores.asignaciones.asignar')
        ->middleware('permission:conductores.asignar-vehiculo');
    Route::patch('conductores/{conductor}/asignaciones/{asignacion}/finalizar', [ConductorController::class, 'finalizarAsignacion'])->name('conductores.asignaciones.finalizar')
        ->middleware('permission:conductores.asignar-vehiculo');
    // Cambio rápido del cargo desde el perfil del operario (lápiz en Conductores/Show).
    Route::patch('conductores/{conductor}/cargo', [ConductorController::class, 'actualizarCargo'])->name('conductores.cargo.actualizar')
        ->middleware('permission:conductores.editar');

    Route::resource('personas', PersonaController::class)
        ->parameters(['personas' => 'persona'])
        ->except(['show'])
        ->middlewareFor('index', 'permission:personas.ver')
        ->middlewareFor(['create', 'store'], 'permission:personas.crear')
        ->middlewareFor(['edit', 'update'], 'permission:personas.editar')
        ->middlewareFor('destroy', 'permission:personas.eliminar');
    // ¿Ya existe una persona con este CI? (alta/edición de personas y conductores).
    Route::get('/search/persona-por-ci', [PersonaController::class, 'verificarCi'])->name('search.persona-por-ci')
        ->middleware('permission:personas.crear|personas.editar|conductores.crear|conductores.editar');

    Route::resource('areas', AreaController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'permission:areas.ver')
        ->middlewareFor('store', 'permission:areas.crear')
        ->middlewareFor('update', 'permission:areas.editar')
        ->middlewareFor('destroy', 'permission:areas.eliminar');
    Route::post('areas/{area}/encargados', [AreaController::class, 'asignarEncargado'])->name('areas.encargados.asignar')
        ->middleware('permission:areas.encargados.asignar');
    Route::patch('areas/{area}/encargados/{encargado}/finalizar', [AreaController::class, 'finalizarEncargado'])->name('areas.encargados.finalizar')
        ->middleware('permission:areas.encargados.asignar');
    Route::post('areas/{area}/vehiculos/{vehiculo}/asignacion', [AreaController::class, 'reasignarVehiculo'])->name('areas.vehiculos.reasignar')
        ->middleware('permission:vehiculos.asignar-area');
    Route::patch('areas/{area}/asignaciones-vehiculo/{asignacion}/finalizar', [AreaController::class, 'finalizarAsignacionVehiculo'])->name('areas.vehiculos.finalizar')
        ->middleware('permission:vehiculos.asignar-area');
    Route::get('/search/personas-para-encargado', [AreaController::class, 'searchPersonasParaEncargado'])->name('search.personas-para-encargado')
        ->middleware('permission:areas.encargados.asignar');

    Route::resource('tipos-combustible', TipoCombustibleController::class)
        ->parameters(['tipos-combustible' => 'tipoCombustible'])
        ->middlewareFor(['index', 'show'], 'permission:tipos-combustible.ver')
        ->middlewareFor(['create', 'store'], 'permission:tipos-combustible.crear')
        ->middlewareFor(['edit', 'update'], 'permission:tipos-combustible.editar')
        ->middlewareFor('destroy', 'permission:tipos-combustible.eliminar');

    Route::resource('tipos-mantenimiento', TipoMantenimientoController::class)
        ->parameters(['tipos-mantenimiento' => 'tipoMantenimiento'])
        ->middlewareFor(['index', 'show'], 'permission:tipos-mantenimiento.ver')
        ->middlewareFor(['create', 'store'], 'permission:tipos-mantenimiento.crear')
        ->middlewareFor(['edit', 'update'], 'permission:tipos-mantenimiento.editar')
        ->middlewareFor('destroy', 'permission:tipos-mantenimiento.eliminar');

    Route::resource('grupos-vehiculo', GrupoVehiculoController::class)
        ->parameters(['grupos-vehiculo' => 'grupoVehiculo'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:grupos-vehiculo.ver')
        ->middlewareFor('store', 'permission:grupos-vehiculo.crear')
        ->middlewareFor('update', 'permission:grupos-vehiculo.editar')
        ->middlewareFor('destroy', 'permission:grupos-vehiculo.eliminar');

    // Cargos del personal. El alta rápida (JSON, desde el selector de cargo
    // de Personas/Conductores) va antes del resource.
    Route::post('cargos/rapido', [CargoController::class, 'storeRapido'])->name('cargos.store-rapido')
        ->middleware('permission:cargos.crear');
    Route::resource('cargos', CargoController::class)
        ->parameters(['cargos' => 'cargo'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:cargos.ver')
        ->middlewareFor('store', 'permission:cargos.crear')
        ->middlewareFor('update', 'permission:cargos.editar')
        ->middlewareFor('destroy', 'permission:cargos.eliminar');

    Route::resource('tipos-vehiculo', TipoVehiculoController::class)
        ->parameters(['tipos-vehiculo' => 'tipoVehiculo'])
        ->middlewareFor(['index', 'show'], 'permission:tipos-vehiculo.ver')
        ->middlewareFor(['create', 'store'], 'permission:tipos-vehiculo.crear')
        ->middlewareFor(['edit', 'update'], 'permission:tipos-vehiculo.editar')
        ->middlewareFor('destroy', 'permission:tipos-vehiculo.eliminar');

    Route::resource('repuestos', RepuestoController::class)
        ->parameters(['repuestos' => 'repuesto'])
        ->middlewareFor(['index', 'show'], 'permission:repuestos.ver')
        ->middlewareFor(['create', 'store'], 'permission:repuestos.crear')
        ->middlewareFor(['edit', 'update'], 'permission:repuestos.editar')
        ->middlewareFor('destroy', 'permission:repuestos.eliminar');

    // El alta también la usa quien registra un viaje (alta rápida de
    // material dentro del modal de ControlCargas/Show.vue).
    Route::resource('materiales', MaterialController::class)
        ->parameters(['materiales' => 'material'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:materiales.ver')
        ->middlewareFor('store', 'permission:materiales.crear|control-cargas.viajes.registrar')
        ->middlewareFor('update', 'permission:materiales.editar')
        ->middlewareFor('destroy', 'permission:materiales.eliminar');

    // El alta también la usa quien registra/edita un flete (alta rápida de
    // vehículo externo en ControlCargas/Form.vue).
    Route::resource('vehiculos-externos', VehiculoExternoController::class)
        ->parameters(['vehiculos-externos' => 'vehiculoExterno'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:vehiculos-externos.ver')
        ->middlewareFor('store', 'permission:vehiculos-externos.crear|control-cargas.crear|control-cargas.editar')
        ->middlewareFor('update', 'permission:vehiculos-externos.editar')
        ->middlewareFor('destroy', 'permission:vehiculos-externos.eliminar');

    Route::resource('control-cargas', CargaMaterialController::class)
        ->parameters(['control-cargas' => 'cargaMaterial'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor(['index', 'show'], 'permission:control-cargas.ver')
        ->middlewareFor(['create', 'store'], 'permission:control-cargas.crear')
        ->middlewareFor(['edit', 'update'], 'permission:control-cargas.editar');
    Route::post('control-cargas/{cargaMaterial}/viajes', [CargaMaterialController::class, 'registrarViaje'])
        ->name('control-cargas.viajes.registrar')
        ->middleware('permission:control-cargas.viajes.registrar');
    // Cerrar no tiene permiso propio: lo puede quien edita la carga
    // (ver UserSeeder::permisosControlCargasPago()).
    Route::post('control-cargas/{cargaMaterial}/cerrar', [CargaMaterialController::class, 'cerrar'])
        ->name('control-cargas.cerrar')
        ->middleware('permission:control-cargas.editar');
    Route::post('control-cargas/{cargaMaterial}/pagar', [CargaMaterialController::class, 'pagar'])
        ->name('control-cargas.pagar')
        ->middleware('permission:control-cargas.marcar-pagado');
    Route::get('control-cargas/{cargaMaterial}/imprimir', [CargaMaterialController::class, 'generarPDF'])
        ->name('control-cargas.imprimir')
        ->middleware('permission:control-cargas.ver');

    // El módulo de Roles sólo tiene el permiso "roles.ver" (RolController
    // además exige rol administrador/super-admin para crear/editar).
    Route::resource('roles', RolController::class)
        ->except(['show', 'destroy'])
        ->middleware('permission:roles.ver');

    // ── Auditoría (bitácora de cambios del sistema) ──────────────────────────
    // El detalle se pide por AJAX desde el modal del listado (mismo patrón que
    // vales.detalle / cargas.detalle), no es una página aparte.
    Route::middleware('permission:auditoria.ver')->group(function () {
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('auditoria/{audit}/detalle', [AuditoriaController::class, 'detalle'])->name('auditoria.detalle');
    });

    // Parámetros de la Empresa (registro único: se crea o se actualiza)
    Route::get('parametros-empresa', [ParametrosEmpresaController::class, 'edit'])->name('parametros-empresa.edit')
        ->middleware('permission:parametros-empresa.ver');
    Route::put('parametros-empresa', [ParametrosEmpresaController::class, 'update'])->name('parametros-empresa.update')
        ->middleware('permission:parametros-empresa.editar');

    // Usuarios
    Route::get('usuarios/{usuario}/password', [UserController::class, 'editPassword'])->name('usuarios.edit-password')
        ->middleware('permission:usuarios.contrasena.cambiar');
    Route::put('usuarios/{usuario}/password', [UserController::class, 'updatePassword'])->name('usuarios.update-password')
        ->middleware('permission:usuarios.contrasena.cambiar');
    // Activar/Inactivar: botón con v-can "usuarios.eliminar" (no se borran usuarios).
    Route::patch('usuarios/{usuario}/estado', [UserController::class, 'cambiarEstado'])->name('usuarios.estado')
        ->middleware('permission:usuarios.eliminar');
    Route::get('/search/personas-sin-usuario', [UserController::class, 'searchPersonasSinUsuario'])->name('search.personas-sin-usuario')
        ->middleware('permission:usuarios.crear|usuarios.editar');
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['destroy', 'show'])
        ->middlewareFor('index', 'permission:usuarios.ver')
        ->middlewareFor(['create', 'store'], 'permission:usuarios.crear')
        ->middlewareFor(['edit', 'update'], 'permission:usuarios.editar');

    // El alta también la usa quien emite/edita un vale (alta rápida de
    // estación de servicio en Vales/Create.vue, GrifoFormModal).
    Route::resource('grifos', GrifoController::class)
        ->parameters(['grifos' => 'grifo'])
        ->middlewareFor(['index', 'show'], 'permission:grifos.ver')
        ->middlewareFor('create', 'permission:grifos.crear')
        ->middlewareFor('store', 'permission:grifos.crear|vales.crear|vales.editar')
        ->middlewareFor(['edit', 'update'], 'permission:grifos.editar')
        ->middlewareFor('destroy', 'permission:grifos.eliminar');

    // Exportación del listado (mismos filtros que vehiculos.index). Antes del
    // resource para que "reporte" nunca se interprete como {vehiculo}.
    Route::get('vehiculos/reporte/pdf', [VehiculoController::class, 'reportePdf'])->name('vehiculos.reporte.pdf')
        ->middleware('permission:vehiculos.reporte.pdf');
    Route::get('vehiculos/reporte/excel', [VehiculoController::class, 'reporteExcel'])->name('vehiculos.reporte.excel')
        ->middleware('permission:vehiculos.reporte.excel');
    // La ficha también la abre quien ve las alertas de mantenimiento del
    // dashboard (el conductor, sólo de SUS vehículos: VehiculoController::show).
    Route::resource('vehiculos', VehiculoController::class)
        ->parameters(['vehiculos' => 'vehiculo'])
        ->middlewareFor('index', 'permission:vehiculos.ver')
        ->middlewareFor('show', 'permission:vehiculos.ver|dashboard.mantenimiento-alertas.ver')
        ->middlewareFor(['create', 'store'], 'permission:vehiculos.crear')
        ->middlewareFor(['edit', 'update'], 'permission:vehiculos.editar')
        ->middlewareFor('destroy', 'permission:vehiculos.eliminar');
    Route::post('vehiculos/{vehiculo}/areas', [VehiculoController::class, 'asignarArea'])->name('vehiculos.areas.asignar')
        ->middleware('permission:vehiculos.asignar-area');
    Route::patch('vehiculos/{vehiculo}/areas/{asignacion}/finalizar', [VehiculoController::class, 'finalizarAsignacionArea'])->name('vehiculos.areas.finalizar')
        ->middleware('permission:vehiculos.asignar-area');
    Route::patch('vehiculos/{vehiculo}/asignaciones/{asignacion}/finalizar', [VehiculoController::class, 'finalizarAsignacionConductor'])->name('vehiculos.asignaciones.finalizar')
        ->middleware('permission:conductores.asignar-vehiculo');

    // Notificaciones (dropdown del header)
    Route::post('notificaciones/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('notificaciones.marcar-leida');
    Route::post('notificaciones/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.marcar-todas-leidas');

    // Vales. Los buscadores también los usan otros formularios (cargas,
    // conductores, personas, usuarios).
    Route::get('/search/vehiculos', [ValeController::class, 'searchVehiculos'])->name('search.vehiculos')
        ->middleware('permission:vales.crear|vales.editar|cargas-combustible.registrar|cargas-combustible.editar|conductores.asignar-vehiculo|conductores.crear|conductores.editar|personas.crear|personas.editar|usuarios.crear|usuarios.editar');
    Route::get('/search/conductores', [ValeController::class, 'searchConductores'])->name('search.conductores')
        ->middleware('permission:vales.crear|vales.editar|cargas-combustible.registrar|cargas-combustible.editar');
    Route::get('/search/grifos', [ValeController::class, 'searchGrifos'])->name('search.grifos')
        ->middleware('permission:vales.crear|vales.editar|cargas-combustible.registrar|cargas-combustible.editar');

    Route::resource('vales', ValeController::class)
        ->parameters(['vales' => 'vale'])
        ->middlewareFor(['index', 'show'], 'permission:vales.ver')
        ->middlewareFor(['create', 'store'], 'permission:vales.crear')
        ->middlewareFor(['edit', 'update'], 'permission:vales.editar')
        ->middlewareFor('destroy', 'permission:vales.eliminar');
    Route::get('vales/{vale}/detalle', [ValeController::class, 'detalle'])->name('vales.detalle')
        ->middleware('permission:vales.ver');
    Route::get('vale-imprimir/{vale}', [ValeController::class, 'imprimirVale'])->name('vales.imprimir')
        ->middleware('permission:vales.imprimir');

    // Cargas de Combustible
    Route::get('/cargas-combustible/vehiculo-info/{id}', [CargaCombustibleController::class, 'vehiculoInfo'])->name('cargas.vehiculo-info')
        ->middleware('permission:cargas-combustible.registrar|cargas-combustible.editar');
    Route::get('/search/vales-carga', [CargaCombustibleController::class, 'searchVales'])->name('search.vales-carga')
        ->middleware('permission:cargas-combustible.registrar|cargas-combustible.editar');
    Route::get('cargas/{carga}/detalle', [CargaCombustibleController::class, 'detalle'])->name('cargas.detalle')
        ->middleware('permission:cargas-combustible.ver');
    Route::get('cargas/{carga}/comprobante', [CargaCombustibleController::class, 'imprimirComprobante'])->name('cargas.comprobante')
        ->middleware('permission:cargas-combustible.ver');
    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga'])
        ->middlewareFor(['index', 'show'], 'permission:cargas-combustible.ver')
        ->middlewareFor(['create', 'store'], 'permission:cargas-combustible.registrar')
        ->middlewareFor(['edit', 'update'], 'permission:cargas-combustible.editar')
        ->middlewareFor('destroy', 'permission:cargas-combustible.eliminar');

    // ── Mantenimiento de vehículos (flujo 3 pasos) ────────────────────────────
    // Paso 1: Solicitudes (Chofer registra solicitud/alarma)
    Route::prefix('mantenimiento')->name('mantenimiento.')->group(function () {
        // Solicitudes. El detalle también lo abre el técnico desde su orden
        // de trabajo (sólo la de SUS órdenes: SolicitudMantenimientoController::show).
        Route::get('solicitudes', [SolicitudMantenimientoController::class, 'index'])->name('solicitudes.index')
            ->middleware('permission:mantenimiento.solicitudes.ver');
        Route::get('solicitudes/crear', [SolicitudMantenimientoController::class, 'create'])->name('solicitudes.create')
            ->middleware('permission:mantenimiento.solicitudes.crear');
        Route::post('solicitudes', [SolicitudMantenimientoController::class, 'store'])->name('solicitudes.store')
            ->middleware('permission:mantenimiento.solicitudes.crear');
        Route::get('solicitudes/{solicitud}', [SolicitudMantenimientoController::class, 'show'])->name('solicitudes.show')
            ->middleware('permission:mantenimiento.solicitudes.ver|mantenimiento.ordenes.ver');
        Route::get('solicitud-imprimir/{solicitud}', [SolicitudMantenimientoController::class, 'imprimir'])->name('solicitudes.imprimir')
            ->middleware('permission:mantenimiento.solicitudes.imprimir');

        // Órdenes de trabajo (Paso 2 – Jefe de Transportes)
        Route::get('ordenes', [OrdenTrabajoController::class, 'index'])->name('ordenes.index')
            ->middleware('permission:mantenimiento.ordenes.ver');
        Route::get('ordenes/crear', [OrdenTrabajoController::class, 'create'])->name('ordenes.create')
            ->middleware('permission:mantenimiento.ordenes.crear');
        Route::post('ordenes', [OrdenTrabajoController::class, 'store'])->name('ordenes.store')
            ->middleware('permission:mantenimiento.ordenes.crear');
        Route::get('ordenes/{orden}', [OrdenTrabajoController::class, 'show'])->name('ordenes.show')
            ->middleware('permission:mantenimiento.ordenes.ver');
        Route::get('ordenes/{orden}/editar', [OrdenTrabajoController::class, 'edit'])->name('ordenes.edit')
            ->middleware('permission:mantenimiento.ordenes.editar');
        Route::put('ordenes/{orden}', [OrdenTrabajoController::class, 'update'])->name('ordenes.update')
            ->middleware('permission:mantenimiento.ordenes.editar');
        Route::patch('ordenes/{orden}/estado', [OrdenTrabajoController::class, 'cambiarEstado'])->name('ordenes.estado')
            ->middleware('permission:mantenimiento.ordenes.estado.cambiar');

        // Ejecución / registro de trabajo realizado (Paso 3 – Jefe de Transportes)
        Route::middleware('permission:mantenimiento.ordenes.ejecucion.registrar')->group(function () {
            Route::get('ordenes/{orden}/ejecucion', [OrdenTrabajoController::class, 'createEjecucion'])->name('ordenes.ejecucion.create');
            Route::post('ordenes/{orden}/ejecucion/detalles', [OrdenTrabajoController::class, 'storeDetalle'])->name('ordenes.ejecucion.detalles.store');
            Route::put('ordenes/{orden}/ejecucion/detalles/{detalle}', [OrdenTrabajoController::class, 'updateDetalle'])->name('ordenes.ejecucion.detalles.update');
            Route::delete('ordenes/{orden}/ejecucion/detalles/{detalle}', [OrdenTrabajoController::class, 'destroyDetalle'])->name('ordenes.ejecucion.detalles.destroy');
            Route::post('ordenes/{orden}/ejecucion/culminar', [OrdenTrabajoController::class, 'culminarEjecucion'])->name('ordenes.ejecucion.culminar');
        });
    });

    // ── Reportes de Cargas de Combustible ────────────────────────────────────
    Route::prefix('reportes')->name('cargas-combustible.reporte.')->group(function () {
        Route::get('cargas-combustible', [CargasCombustibleReportController::class, 'index'])->name('index')
            ->middleware('permission:cargas-combustible.reporte');
        Route::get('cargas-combustible/pdf', [CargasCombustibleReportController::class, 'generarPDF'])->name('pdf')
            ->middleware('permission:cargas-combustible.reporte.pdf');
        Route::get('cargas-combustible/excel', [CargasCombustibleReportController::class, 'generarExcel'])->name('excel')
            ->middleware('permission:cargas-combustible.reporte.excel');
        Route::get('cargas-combustible/rendimiento', [CargasCombustibleReportController::class, 'generarReporteRendimiento'])->name('rendimiento')
            ->middleware('permission:cargas-combustible.reporte.rendimiento');
        Route::get('cargas-combustible/rendimiento/pdf', [CargasCombustibleReportController::class, 'generarPDFRendimiento'])->name('rendimiento.pdf')
            ->middleware('permission:cargas-combustible.reporte.rendimiento.pdf');
        Route::get('cargas-combustible/rendimiento/excel', [CargasCombustibleReportController::class, 'generarExcelRendimiento'])->name('rendimiento.excel')
            ->middleware('permission:cargas-combustible.reporte.rendimiento.excel');
        Route::get('cargas-combustible/rendimiento/detalle', [CargasCombustibleReportController::class, 'detalleRendimientoVehiculo'])->name('rendimiento.detalle')
            ->middleware('permission:cargas-combustible.reporte.rendimiento');
        Route::get('cargas-combustible/rendimiento/detalle/pdf', [CargasCombustibleReportController::class, 'generarPDFDetalleRendimiento'])->name('rendimiento.detalle.pdf')
            ->middleware('permission:cargas-combustible.reporte.rendimiento.pdf');
        Route::get('cargas-combustible/rendimiento/detalle/excel', [CargasCombustibleReportController::class, 'generarExcelDetalleRendimiento'])->name('rendimiento.detalle.excel')
            ->middleware('permission:cargas-combustible.reporte.rendimiento.excel');

    });

    // ── Reportes de Control de Carga de Material (viajes de vehículos externos) ─
    Route::prefix('reportes')->name('control-cargas.reporte.')->group(function () {
        Route::get('control-cargas', [ControlCargasReportController::class, 'index'])->name('index')
            ->middleware('permission:control-cargas.reporte');
        Route::get('control-cargas/pdf', [ControlCargasReportController::class, 'generarPDF'])->name('pdf')
            ->middleware('permission:control-cargas.reporte.pdf');
        Route::get('control-cargas/excel', [ControlCargasReportController::class, 'generarExcel'])->name('excel')
            ->middleware('permission:control-cargas.reporte.excel');
        Route::get('control-cargas/detalle', [ControlCargasReportController::class, 'detalle'])->name('detalle')
            ->middleware('permission:control-cargas.reporte');
        Route::get('control-cargas/detalle/pdf', [ControlCargasReportController::class, 'generarPDFDetalle'])->name('detalle.pdf')
            ->middleware('permission:control-cargas.reporte.pdf');
        Route::get('control-cargas/detalle/excel', [ControlCargasReportController::class, 'generarExcelDetalle'])->name('detalle.excel')
            ->middleware('permission:control-cargas.reporte.excel');
    });

    // Informe PDF de UNA operación: botón con v-can "operacion-diaria.informe"
    // en Operacion/Index y Show (también vale el permiso del propio reporte).
    Route::get('reportes/operacion-diaria/pdf/{operacionDiaria}', [OperacionDiariaController::class, 'generarPDF'])->name('operacion-diaria.reporte.pdf')
        ->middleware('permission:operacion-diaria.informe|operacion-diaria.reporte.pdf');

    // ── Reporte de uso de vehículos en operación diaria (horas + recorrido) ──
    Route::prefix('reportes')->name('operacion-diaria.reporte.uso.')->group(function () {
        Route::get('operacion-diaria', [OperacionDiariaReportController::class, 'index'])->name('index')
            ->middleware('permission:operacion-diaria.reporte.uso');
        Route::get('operacion-diaria/pdf', [OperacionDiariaReportController::class, 'generarPDF'])->name('pdf')
            ->middleware('permission:operacion-diaria.reporte.uso.pdf');
        Route::get('operacion-diaria/excel', [OperacionDiariaReportController::class, 'generarExcel'])->name('excel')
            ->middleware('permission:operacion-diaria.reporte.uso.excel');
    });

    // ── Bitácora detallada de UN vehículo (horas + combustible + mantenimiento + material) ──
    Route::prefix('reportes')->name('operacion-diaria.reporte.detalle.')->group(function () {
        Route::get('operacion-diaria/detalle', [OperacionDiariaReportController::class, 'detalle'])->name('index')
            ->middleware('permission:operacion-diaria.reporte.detalle');
        Route::get('operacion-diaria/detalle/pdf', [OperacionDiariaReportController::class, 'detallePDF'])->name('pdf')
            ->middleware('permission:operacion-diaria.reporte.detalle.pdf');
        Route::get('operacion-diaria/detalle/excel', [OperacionDiariaReportController::class, 'detalleExcel'])->name('excel')
            ->middleware('permission:operacion-diaria.reporte.detalle.excel');
    });

    // ── Reporte por operario (actividades de sus jornadas + cargas de combustible) ──
    Route::get('reportes/operacion-diaria/operario', [OperarioReportController::class, 'index'])->name('operacion-diaria.reporte.operario.index')
        ->middleware('permission:operacion-diaria.reporte.operario');

    //  Actividades de los operadores de transporte
    Route::post('operacion-diaria/verificar', [OperacionDiariaController::class, 'verificarOperacion'])->name('operacion-diaria.verificar')
        ->middleware('permission:operacion-diaria.verificar');
    Route::resource('operacion-diaria', OperacionDiariaController::class)
        ->parameters(['operacion-diaria' => 'operacionDiaria'])
        ->middlewareFor(['index', 'show'], 'permission:operacion-diaria.ver')
        ->middlewareFor(['create', 'store'], 'permission:operacion-diaria.crear')
        ->middlewareFor(['edit', 'update'], 'permission:operacion-diaria.editar')
        ->middlewareFor('destroy', 'permission:operacion-diaria.eliminar');

    // Valida una actividad antes de agregarla al formulario (alta y edición).
    Route::post('operacion-actividad', [OperacionDiariaController::class, 'validarActividad'])
        ->name('operacion-diaria.agregar-actividad')
        ->middleware('permission:operacion-diaria.crear|operacion-diaria.editar');

});

require __DIR__.'/auth.php';
