<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\CargasCombustibleReportController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrifoController;
use App\Http\Controllers\GrupoVehiculoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OperacionDiariaController;
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
use App\Http\Controllers\VehiculoController;
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
Route::get('/api/documentation', function () {
    return view('docs.swagger');
})->name('api.documentation');

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor']);
    Route::post('conductores/{conductor}/asignaciones', [ConductorController::class, 'asignarVehiculo'])->name('conductores.asignaciones.asignar');
    Route::patch('conductores/{conductor}/asignaciones/{asignacion}/finalizar', [ConductorController::class, 'finalizarAsignacion'])->name('conductores.asignaciones.finalizar');

    Route::resource('personas', PersonaController::class)
        ->parameters(['personas' => 'persona'])
        ->except(['show']);

    Route::resource('areas', AreaController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('areas/{area}/encargados', [AreaController::class, 'asignarEncargado'])->name('areas.encargados.asignar');
    Route::patch('areas/{area}/encargados/{encargado}/finalizar', [AreaController::class, 'finalizarEncargado'])->name('areas.encargados.finalizar');
    Route::get('/search/personas-para-encargado', [AreaController::class, 'searchPersonasParaEncargado'])->name('search.personas-para-encargado');

    Route::resource('tipos-combustible', TipoCombustibleController::class)
        ->parameters(['tipos-combustible' => 'tipoCombustible']);

    Route::resource('tipos-mantenimiento', TipoMantenimientoController::class)
        ->parameters(['tipos-mantenimiento' => 'tipoMantenimiento']);

    Route::resource('grupos-vehiculo', GrupoVehiculoController::class)
        ->parameters(['grupos-vehiculo' => 'grupoVehiculo'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('tipos-vehiculo', TipoVehiculoController::class)
        ->parameters(['tipos-vehiculo' => 'tipoVehiculo']);

    Route::resource('repuestos', RepuestoController::class)
        ->parameters(['repuestos' => 'repuesto']);

    Route::resource('roles', RolController::class)
        ->except(['show', 'destroy']);

    // Parámetros de la Empresa (registro único: se crea o se actualiza)
    Route::get('parametros-empresa', [ParametrosEmpresaController::class, 'edit'])->name('parametros-empresa.edit');
    Route::put('parametros-empresa', [ParametrosEmpresaController::class, 'update'])->name('parametros-empresa.update');

    // Usuarios
    Route::get('usuarios/{usuario}/password', [UserController::class, 'editPassword'])->name('usuarios.edit-password');
    Route::put('usuarios/{usuario}/password', [UserController::class, 'updatePassword'])->name('usuarios.update-password');
    Route::patch('usuarios/{usuario}/estado', [UserController::class, 'cambiarEstado'])->name('usuarios.estado');
    Route::get('/search/personas-sin-usuario', [UserController::class, 'searchPersonasSinUsuario'])->name('search.personas-sin-usuario');
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['destroy', 'show']);

    Route::resource('grifos', GrifoController::class)
        ->parameters(['grifos' => 'grifo']);

    Route::resource('vehiculos', VehiculoController::class)
        ->parameters(['vehiculos' => 'vehiculo']);
    Route::post('vehiculos/{vehiculo}/areas', [VehiculoController::class, 'asignarArea'])->name('vehiculos.areas.asignar');
    Route::patch('vehiculos/{vehiculo}/areas/{asignacion}/finalizar', [VehiculoController::class, 'finalizarAsignacionArea'])->name('vehiculos.areas.finalizar');

    // Notificaciones (dropdown del header)
    Route::post('notificaciones/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('notificaciones.marcar-leida');
    Route::post('notificaciones/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.marcar-todas-leidas');

    // Vales
    Route::get('/search/vehiculos', [ValeController::class, 'searchVehiculos'])->name('search.vehiculos');
    Route::get('/search/conductores', [ValeController::class, 'searchConductores'])->name('search.conductores');
    Route::get('/search/grifos', [ValeController::class, 'searchGrifos'])->name('search.grifos');

    Route::resource('vales', ValeController::class)
        ->parameters(['vales' => 'vale']);
    Route::get('vales/{vale}/detalle', [ValeController::class, 'detalle'])->name('vales.detalle');
    Route::get('vale-imprimir/{vale}', [ValeController::class, 'imprimirVale'])->name('vales.imprimir');

    // Cargas de Combustible
    Route::get('/cargas-combustible/vehiculo-info/{id}', [CargaCombustibleController::class, 'vehiculoInfo'])->name('cargas.vehiculo-info');
    Route::get('/search/vales-carga', [CargaCombustibleController::class, 'searchVales'])->name('search.vales-carga');
    Route::get('cargas/{carga}/detalle', [CargaCombustibleController::class, 'detalle'])->name('cargas.detalle');
    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga']);

    // ── Mantenimiento de vehículos (flujo 3 pasos) ────────────────────────────
    // Paso 1: Solicitudes (Chofer registra solicitud/alarma)
    Route::prefix('mantenimiento')->name('mantenimiento.')->group(function () {
        // Solicitudes
        Route::get('solicitudes', [SolicitudMantenimientoController::class, 'index'])->name('solicitudes.index');
        Route::get('solicitudes/crear', [SolicitudMantenimientoController::class, 'create'])->name('solicitudes.create');
        Route::post('solicitudes', [SolicitudMantenimientoController::class, 'store'])->name('solicitudes.store');
        Route::get('solicitudes/{solicitud}', [SolicitudMantenimientoController::class, 'show'])->name('solicitudes.show');
        Route::get('solicitud-imprimir/{solicitud}', [SolicitudMantenimientoController::class, 'imprimir'])->name('solicitudes.imprimir');

        // Órdenes de trabajo (Paso 2 – Jefe de Transportes)
        Route::get('ordenes', [OrdenTrabajoController::class, 'index'])->name('ordenes.index');
        Route::get('ordenes/crear', [OrdenTrabajoController::class, 'create'])->name('ordenes.create');
        Route::post('ordenes', [OrdenTrabajoController::class, 'store'])->name('ordenes.store');
        Route::get('ordenes/{orden}', [OrdenTrabajoController::class, 'show'])->name('ordenes.show');
        Route::get('ordenes/{orden}/editar', [OrdenTrabajoController::class, 'edit'])->name('ordenes.edit');
        Route::put('ordenes/{orden}', [OrdenTrabajoController::class, 'update'])->name('ordenes.update');
        Route::patch('ordenes/{orden}/estado', [OrdenTrabajoController::class, 'cambiarEstado'])->name('ordenes.estado');

        // Ejecución / registro de trabajo realizado (Paso 3 – Jefe de Transportes)
        Route::get('ordenes/{orden}/ejecucion', [OrdenTrabajoController::class, 'createEjecucion'])->name('ordenes.ejecucion.create');
        Route::post('ordenes/{orden}/ejecucion', [OrdenTrabajoController::class, 'storeEjecucion'])->name('ordenes.ejecucion.store');
    });

    // ── Reportes de Cargas de Combustible ────────────────────────────────────
    Route::prefix('reportes')->name('cargas-combustible.reporte.')->group(function () {
        Route::get('cargas-combustible', [CargasCombustibleReportController::class, 'index'])->name('index');
        Route::get('cargas-combustible/pdf', [CargasCombustibleReportController::class, 'generarPDF'])->name('pdf');

    });

    Route::get('reportes/operacion-diaria/pdf/{operacionDiaria}', [OperacionDiariaController::class, 'generarPDF'])->name('operacion-diaria.reporte.pdf');

    //  Actividades de los operadores de transporte
    Route::post('operacion-diaria/verificar', [OperacionDiariaController::class, 'verificarOperacion'])->name('operacion-diaria.verificar');
    Route::resource('operacion-diaria', OperacionDiariaController::class)
        ->parameters(['operacion-diaria' => 'operacionDiaria']);

    Route::post('operacion-actividad', [OperacionDiariaController::class, 'validarActividad'])
        ->name('operacion-diaria.agregar-actividad');

});

require __DIR__.'/auth.php';
