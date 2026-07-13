<?php

use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\GrifoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ValeController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
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

    // Cargas de Combustible
    Route::get('/cargas-combustible/vehiculo-info/{id}', [CargaCombustibleController::class, 'vehiculoInfo'])->name('cargas.vehiculo-info');
    Route::get('/search/vales-carga',                    [CargaCombustibleController::class, 'searchVales'])->name('search.vales-carga');
    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga']);
});

require __DIR__.'/auth.php';
