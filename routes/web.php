<?php

use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\GrifoController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\ProfileController;
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

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor']);

    Route::resource('tipos-combustible', TipoCombustibleController::class)
        ->parameters(['tipos-combustible' => 'tipoCombustible']);

    Route::resource('tipos-mantenimiento', TipoMantenimientoController::class)
        ->parameters(['tipos-mantenimiento' => 'tipoMantenimiento']);

    Route::resource('tipos-vehiculo', TipoVehiculoController::class)
        ->parameters(['tipos-vehiculo' => 'tipoVehiculo']);

    // Usuarios
    Route::get('usuarios/{usuario}/password',  [UserController::class, 'editPassword'])->name('usuarios.edit-password');
    Route::put('usuarios/{usuario}/password',  [UserController::class, 'updatePassword'])->name('usuarios.update-password');
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'usuario']);

    Route::resource('grifos', GrifoController::class)
        ->parameters(['grifos' => 'grifo']);

    Route::resource('vehiculos', VehiculoController::class)
        ->parameters(['vehiculos' => 'vehiculo']);

    // Vales
    Route::get('/search/vehiculos',   [ValeController::class, 'searchVehiculos'])->name('search.vehiculos');
    Route::get('/search/conductores', [ValeController::class, 'searchConductores'])->name('search.conductores');
    Route::get('/search/grifos',      [ValeController::class, 'searchGrifos'])->name('search.grifos');

    Route::resource('vales', ValeController::class)
        ->parameters(['vales' => 'vale']);
    Route::get('vales/{vale}/detalle', [ValeController::class, 'detalle'])->name('vales.detalle');
    Route::get('vale-imprimir/{vale}', [ValeController::class, 'imprimirVale'])->name('vales.imprimir');

    // Cargas de Combustible
    Route::get('/cargas-combustible/vehiculo-info/{id}', [CargaCombustibleController::class, 'vehiculoInfo'])->name('cargas.vehiculo-info');
    Route::get('/search/vales-carga',                    [CargaCombustibleController::class, 'searchVales'])->name('search.vales-carga');
    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga']);

    // ── Mantenimiento de vehículos (flujo 3 pasos) ────────────────────────────
    // Paso 1: Solicitudes (Chofer registra solicitud/alarma)
    Route::prefix('mantenimiento')->name('mantenimiento.')->group(function () {
        // Solicitudes
        Route::get('solicitudes',               [MantenimientoController::class, 'indexSolicitudes'])->name('solicitudes.index');
        Route::get('solicitudes/crear',         [MantenimientoController::class, 'createSolicitud'])->name('solicitudes.create');
        Route::post('solicitudes',              [MantenimientoController::class, 'storeSolicitud'])->name('solicitudes.store');
        Route::get('solicitudes/{solicitud}',   [MantenimientoController::class, 'showSolicitud'])->name('solicitudes.show');
        Route::get('solicitud-imprimir/{solicitud}',   [MantenimientoController::class, 'imprimirSolicitud'])->name('solicitudes.imprimir');

        // Órdenes de trabajo (Paso 2 – Jefe de Transportes)
        Route::get('ordenes',                   [MantenimientoController::class, 'indexOrdenes'])->name('ordenes.index');
        Route::get('ordenes/crear',             [MantenimientoController::class, 'createOrden'])->name('ordenes.create');
        Route::post('ordenes',                  [MantenimientoController::class, 'storeOrden'])->name('ordenes.store');
        Route::get('ordenes/{orden}',           [MantenimientoController::class, 'showOrden'])->name('ordenes.show');
        Route::get('ordenes/{orden}/editar',    [MantenimientoController::class, 'editOrden'])->name('ordenes.edit');
        Route::put('ordenes/{orden}',           [MantenimientoController::class, 'updateOrden'])->name('ordenes.update');
        Route::patch('ordenes/{orden}/estado',  [MantenimientoController::class, 'cambiarEstadoOrden'])->name('ordenes.estado');

        // Ejecución / registro de trabajo realizado (Paso 3 – Jefe de Transportes)
        Route::get('ordenes/{orden}/ejecucion', [MantenimientoController::class, 'createEjecucion'])->name('ordenes.ejecucion.create');
        Route::post('ordenes/{orden}/ejecucion', [MantenimientoController::class, 'storeEjecucion'])->name('ordenes.ejecucion.store');
    });
});

require __DIR__ . '/auth.php';
